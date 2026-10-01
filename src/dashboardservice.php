<?php

namespace AuraTech\SmartDashboard;

use AuraTech\SmartDashboard\Layouts\Activities;
use AuraTech\SmartDashboard\Layouts\Layout;
use AuraTech\SmartDashboard\Layouts\LayoutResolver;
use AuraTech\SmartDashboard\Layouts\LayoutStore;
use AuraTech\SmartDashboard\Layouts\Presets;
use AuraTech\SmartDashboard\Support\Connection;
use AuraTech\SmartDashboard\Support\Period;
use AuraTech\SmartDashboard\Support\Schema;
use AuraTech\SmartDashboard\Widgets\Registry;
use AuraTech\SmartDashboard\Widgets\WidgetContext;
use DateTimeImmutable;
use InvalidArgumentException;
use Throwable;

/**
 * The whole dashboard backend behind one framework-free API.
 * Laravel controllers and the standalone demo server are thin wrappers around it.
 *
 * $user = ['id' => int, 'role_id' => ?int, 'name' => string, 'can' => ?callable(string $permission): bool]
 */
class DashboardService
{
    public const SETTING_DEFAULTS = [
        'business_type' => null,     // null = config default_business_type
        'monthly_target' => 0,       // 0 = automatic (last month + growth)
        'daily_target' => 0,
        'refresh_minutes' => 5,
        'activities' => [],          // activity => bool, last used in the smart builder
        'company_name' => '',
    ];

    private Schema $schema;
    private Registry $registry;
    private LayoutStore $store;
    private ?array $settingsCache = null;

    /**
     * @param callable|null $cache fn(string $key, int $ttl, callable $compute): mixed
     */
    public function __construct(
        private Connection $db,
        private array $config,
        private $cache = null,
        private ?DateTimeImmutable $clock = null,   // fixed "now" (tests / demo)
    ) {
        $this->schema = new Schema($config['schema'] ?? [], $db);
        $this->registry = new Registry($config['widgets'] ?? []);
        $this->store = new LayoutStore($db);
    }

    public function registry(): Registry { return $this->registry; }
    public function store(): LayoutStore { return $this->store; }
    public function config(): array { return $this->config; }

    /* ------------------------------------------------------------------ *
     | Permissions
     * ------------------------------------------------------------------ */

    public function canEditTemplates(array $user): bool
    {
        return in_array((int) ($user['role_id'] ?? 0), array_map('intval', $this->config['editor_role_ids'] ?? [1]), true);
    }

    public function canEditPersonal(array $user): bool
    {
        return $this->canEditTemplates($user) || ($this->config['allow_personal_layouts'] ?? true);
    }

    /** Users whose role is restricted always see only their own data. */
    public function forcedUserId(array $user): ?int
    {
        $roles = array_map('intval', $this->config['own_data_role_ids'] ?? []);
        return in_array((int) ($user['role_id'] ?? 0), $roles, true) ? (int) $user['id'] : null;
    }

    private function permissionFilter(array $user): ?callable
    {
        if (!($this->config['enforce_widget_permissions'] ?? false) || !isset($user['can'])) return null;
        return $user['can'];
    }

    /* ------------------------------------------------------------------ *
     | Settings
     * ------------------------------------------------------------------ */

    public function settings(): array
    {
        if ($this->settingsCache === null) {
            try { $stored = $this->store->settings(); } catch (Throwable) { $stored = []; }
            $this->settingsCache = array_merge(self::SETTING_DEFAULTS, $stored);
            $this->settingsCache['business_type'] = $this->validType($this->settingsCache['business_type']);
        }
        return $this->settingsCache;
    }

    public function saveSettings(array $in, array $user): array
    {
        $this->assertTemplateEditor($user);
        foreach ($in as $k => $v) {
            if (!array_key_exists($k, self::SETTING_DEFAULTS)) continue;
            $v = match ($k) {
                'business_type' => $this->validType($v),
                'monthly_target', 'daily_target' => max(0, round((float) $v, 2)),
                'refresh_minutes' => max(0, min(60, (int) $v)),
                'activities' => array_map('boolval', array_intersect_key((array) $v, Activities::all())),
                'company_name' => mb_substr(strip_tags((string) $v), 0, 80),
            };
            $this->store->setSetting($k, $v);
        }
        $this->settingsCache = null;
        return $this->settings();
    }

    public function businessType(): string
    {
        return $this->settings()['business_type'];
    }

    private function validType(mixed $t): string
    {
        $types = array_keys($this->config['business_types'] ?? []);
        return is_string($t) && in_array($t, $types, true) ? $t : ($this->config['default_business_type'] ?? 'grocery');
    }

    /* ------------------------------------------------------------------ *
     | Context
     * ------------------------------------------------------------------ */

    /** @param array $params period, from, to, user_id, warehouse_id */
    public function context(array $params, array $user, array $options = []): WidgetContext
    {
        $tz = $this->config['timezone'] ?? 'Asia/Jerusalem';
        $period = Period::make((string) ($params['period'] ?? 'month'), $params['from'] ?? null, $params['to'] ?? null, $tz, $this->clock);
        $userId = $this->forcedUserId($user) ?? (isset($params['user_id']) && $params['user_id'] !== '' ? (int) $params['user_id'] : null);
        $wh = isset($params['warehouse_id']) && $params['warehouse_id'] !== '' ? (int) $params['warehouse_id'] : null;
        return new WidgetContext($this->db, $this->schema, $period, $userId ?: null, $wh ?: null, $options, $this->settings(), $this->config);
    }

    /* ------------------------------------------------------------------ *
     | Data
     * ------------------------------------------------------------------ */

    /**
     * Compute several widgets in one request.
     * @param array $items [{id, key, options}]
     * @return array{period: array, results: array<string, array>}
     */
    public function runBatch(array $items, array $params, array $user): array
    {
        $base = $this->context($params, $user);
        $can = $this->permissionFilter($user);
        $debug = (bool) ($this->config['debug'] ?? false);
        $ttl = (int) ($this->config['cache_ttl'] ?? 120);
        $fresh = !empty($params['fresh']);
        $results = [];
        foreach (array_slice($items, 0, 60) as $item) {
            $id = preg_replace('/[^A-Za-z0-9_-]/', '', (string) ($item['id'] ?? $item['key'] ?? ''));
            $key = (string) ($item['key'] ?? '');
            $opts = is_array($item['options'] ?? null) ? $item['options'] : [];
            $w = $this->registry->get($key);
            if (!$w) { $results[$id] = ['ok' => false, 'error' => 'unknown_widget']; continue; }
            $perm = $w->describe()['permission'];
            if ($can && $perm && !$can($perm)) { $results[$id] = ['ok' => false, 'error' => 'forbidden', 'message' => 'אין הרשאה לצפייה בנתון זה']; continue; }
            $ctx = $base->withOptions($opts);
            $compute = fn () => $this->registry->run($key, $ctx, $debug);
            if ($this->cache && $ttl > 0 && !$fresh) {
                $ck = 'sd:' . md5(json_encode([$key, $opts, $params['period'] ?? 'month', $params['from'] ?? null, $params['to'] ?? null,
                    $ctx->userId, $ctx->warehouseId, intdiv(time(), $ttl)]));
                $r = ($this->cache)($ck, $ttl, $compute);
            } else {
                $r = $compute();
            }
            $results[$id] = $r;
        }
        return ['period' => $base->period->toArray(), 'generated_at' => $base->period->now->format('c'), 'results' => $results];
    }

    /* ------------------------------------------------------------------ *
     | Layouts
     * ------------------------------------------------------------------ */

    public function layoutFor(array $user): array
    {
        $l = (new LayoutResolver($this->store))->resolve($user['id'] ?? null, $user['role_id'] ?? null, $this->businessType());
        $l['items'] = $this->visibleItems($l['items'] ?? [], $user);
        return $l;
    }

    public function getLayout(string $scope, string $scopeId): array
    {
        $this->assertScope($scope);
        $saved = $this->store->get($scope, $scopeId);
        if ($saved) return $saved + ['source' => $scope, 'source_id' => $scopeId, 'saved' => true];
        // nothing saved yet: start from what this scope would inherit
        $type = $scope === 'business_type' ? $this->validType($scopeId) : $this->businessType();
        if ($scope === 'user') {
            return (new LayoutResolver($this->store))->resolve(null, null, $type) + ['saved' => false];
        }
        return Presets::for($type) + ['source' => 'preset', 'source_id' => $type, 'saved' => false];
    }

    public function saveLayout(string $scope, string $scopeId, array $layout, array $user): array
    {
        $this->assertCanEditScope($scope, $scopeId, $user);
        $clean = Layout::sanitize($layout, array_keys($this->registry->all()));
        $this->store->save($scope, $scopeId, $clean, $user['id'] ?? null);
        return $this->getLayout($scope, $scopeId);
    }

    public function resetLayout(string $scope, string $scopeId, array $user): void
    {
        $this->assertCanEditScope($scope, $scopeId, $user);
        $this->store->delete($scope, $scopeId);
    }

    public function resetAllPersonal(array $user): void
    {
        $this->assertTemplateEditor($user);
        $this->store->deleteScope('user');
    }

    /** Smart builder: preset for a type, tuned to activities. */
    public function buildLayout(string $type, array $activities): array
    {
        return Presets::build($this->validType($type), array_map('boolval', $activities));
    }

    public function detectActivities(array $user): array
    {
        $ctx = $this->context(['period' => 'month'], $user);
        $d = Activities::detect($ctx);
        return ['activities' => $d, 'suggested_type' => Activities::suggestType($d)];
    }

    /* ------------------------------------------------------------------ *
     | Bootstrap payloads for the two pages
     * ------------------------------------------------------------------ */

    public function catalog(array $user): array
    {
        return $this->registry->catalog($this->context([], $user), $this->permissionFilter($user), $this->businessType());
    }

    public function dashboardBootstrap(array $user): array
    {
        $catalog = $this->catalog($user);
        return [
            'user' => ['id' => $user['id'] ?? null, 'name' => $user['name'] ?? ''],
            'layout' => $this->layoutFor($user),
            'widgets' => $this->metaMap($catalog),
            'filters' => $this->filterLists($user),
            'periods' => Period::PRESETS,
            'palette' => $this->config['palette'] ?? [],
            'settings' => array_intersect_key($this->settings(), array_flip(['business_type', 'refresh_minutes', 'company_name'])),
            'business_types' => $this->config['business_types'] ?? [],
            'can_edit' => $this->canEditPersonal($user),
            'locale' => ['currency' => $this->config['currency'] ?? 'ILS', 'lang' => $this->config['locale'] ?? 'he', 'dir' => $this->config['direction'] ?? 'rtl',
                'week_starts_on' => $this->config['week_starts_on'] ?? 0],
            'now' => $this->context([], $user)->period->now->format('c'),
        ];
    }

    public function editorBootstrap(array $user): array
    {
        $templates = $this->canEditTemplates($user);
        return [
            'user' => ['id' => $user['id'] ?? null, 'name' => $user['name'] ?? '', 'role_id' => $user['role_id'] ?? null],
            'can_edit_templates' => $templates,
            'catalog' => $this->catalog($user),
            'categories' => [
                'kpi' => 'מדדים', 'charts' => 'גרפים', 'finance' => 'כספים', 'products' => 'מוצרים ומלאי',
                'customers' => 'לקוחות', 'operations' => 'תפעול', 'lists' => 'רשימות', 'team' => 'צוות', 'general' => 'כללי',
            ],
            'business_types' => $this->config['business_types'] ?? [],
            'activities' => Activities::all(),
            'roles' => $templates ? $this->roles() : [],
            'layouts_index' => $templates ? $this->store->index() : [],
            'settings' => $this->settings(),
            'effective' => $this->layoutFor($user),
            'periods' => Period::PRESETS,
            'filters' => $this->filterLists($user),
            'locale' => ['currency' => $this->config['currency'] ?? 'ILS', 'lang' => $this->config['locale'] ?? 'he', 'dir' => $this->config['direction'] ?? 'rtl'],
        ];
    }

    /* ------------------------------------------------------------------ */

    private function metaMap(array $catalog): array
    {
        $out = [];
        foreach ($catalog as $c) {
            $out[$c['key']] = array_intersect_key($c, array_flip(['key', 'title', 'type', 'icon', 'category', 'link', 'uses_period', 'available', 'description', 'options', 'size', 'min']));
        }
        return $out;
    }

    private function visibleItems(array $items, array $user): array
    {
        $can = $this->permissionFilter($user);
        return array_values(array_filter($items, function ($i) use ($can) {
            $w = $this->registry->get($i['key'] ?? '');
            if (!$w) return false;
            $perm = $w->describe()['permission'];
            return !($can && $perm && !$can($perm));
        }));
    }

    private function filterLists(array $user): array
    {
        $s = $this->schema;
        $users = $warehouses = [];
        try {
            if ($this->forcedUserId($user) === null && $s->available('users', ['name'])) {
                $active = $s->hasColumn('users', 'is_active') ? " WHERE {$s->c('users','is_active')} = 1" : '';
                $users = $this->db->select("SELECT id, {$s->c('users','name')} AS name FROM {$s->t('users')}$active ORDER BY {$s->c('users','name')}");
            }
        } catch (Throwable) {}
        try {
            if ($s->available('warehouses', ['name'])) {
                $warehouses = $this->db->select("SELECT id, {$s->c('warehouses','name')} AS name FROM {$s->t('warehouses')} ORDER BY id");
            }
        } catch (Throwable) {}
        return ['users' => $users, 'warehouses' => count($warehouses) > 1 ? $warehouses : []];
    }

    private function roles(): array
    {
        $s = $this->schema;
        try {
            if ($s->available('roles', ['name'])) {
                return $this->db->select("SELECT id, {$s->c('roles','name')} AS name FROM {$s->t('roles')} ORDER BY id");
            }
            if ($s->available('users', ['role_id'])) {
                return array_map(fn ($r) => ['id' => (int) $r['id'], 'name' => 'תפקיד ' . $r['id']],
                    $this->db->select("SELECT DISTINCT {$s->c('users','role_id')} AS id FROM {$s->t('users')} WHERE {$s->c('users','role_id')} IS NOT NULL ORDER BY 1"));
            }
        } catch (Throwable) {}
        return [];
    }

    private function assertScope(string $scope): void
    {
        if (!in_array($scope, LayoutStore::SCOPES, true)) throw new InvalidArgumentException('invalid scope');
    }

    private function assertTemplateEditor(array $user): void
    {
        if (!$this->canEditTemplates($user)) throw new AccessDenied('אין הרשאה לעריכת תבניות');
    }

    private function assertCanEditScope(string $scope, string $scopeId, array $user): void
    {
        $this->assertScope($scope);
        if ($scope === 'user' && (string) ($user['id'] ?? '') === $scopeId && $this->canEditPersonal($user)) return;
        $this->assertTemplateEditor($user);
    }
}
