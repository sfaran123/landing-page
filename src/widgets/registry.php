<?php

namespace AuraTech\SmartDashboard\Widgets;

use Throwable;

class Registry
{
    /** @var array<string, Widget> */
    private array $widgets = [];

    public function __construct(array $classes)
    {
        foreach ($classes as $class) {
            $w = is_string($class) ? new $class() : $class;
            $this->widgets[$w::key()] = $w;
        }
    }

    /** @return array<string, Widget> */
    public function all(): array
    {
        return $this->widgets;
    }

    public function get(string $key): ?Widget
    {
        return $this->widgets[$key] ?? null;
    }

    /**
     * Library for the editor: metadata + whether this installation's database supports it.
     * @param callable|null $can fn(?string $permission): bool
     */
    public function catalog(WidgetContext $ctx, ?callable $can = null, ?string $businessType = null): array
    {
        $out = [];
        foreach ($this->widgets as $w) {
            $d = $w->describe();
            if ($can && $d['permission'] && !$can($d['permission'])) continue;
            try { $d['available'] = $w->isAvailable($ctx); } catch (Throwable) { $d['available'] = false; }
            $d['recommended'] = !$d['business_types'] || ($businessType && in_array($businessType, $d['business_types'], true));
            $out[] = $d;
        }
        return $out;
    }

    /** Execute one widget safely. */
    public function run(string $key, WidgetContext $ctx, bool $debug = false): array
    {
        $w = $this->get($key);
        if (!$w) return ['ok' => false, 'error' => 'unknown_widget'];
        $t = microtime(true);
        try {
            if (!$w->isAvailable($ctx)) {
                return ['ok' => false, 'error' => 'unavailable', 'message' => 'הווידג׳ט אינו זמין במערכת זו (טבלה/עמודה חסרה).'];
            }
            $data = $w->data($ctx);
            return ['ok' => true, 'data' => $data, 'ms' => round((microtime(true) - $t) * 1000, 1)];
        } catch (Throwable $e) {
            return ['ok' => false, 'error' => 'exception', 'message' => $debug ? $e->getMessage() : 'שגיאה בטעינת הנתונים'];
        }
    }
}
