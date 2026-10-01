<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

/** Drafts & pending orders that are waiting for someone – oldest first. */
class OpenOrders extends Widget
{
    public static function key(): string { return 'open_orders'; }

    public function meta(): array
    {
        return [
            'title' => 'הזמנות וטיוטות פתוחות', 'description' => 'הזמנות ממתינות וטיוטות שלא הושלמו – הוותיקות ראשונות.',
            'category' => 'operations', 'type' => 'table', 'icon' => 'inbox', 'size' => ['w' => 6, 'h' => 5], 'min' => ['w' => 4, 'h' => 4],
            'uses_period' => false, 'business_types' => ['wholesale', 'ecommerce', 'services'], 'link' => '/sales?sale_status=5',
            'options' => ['limit' => self::limitOption(5)],
        ];
    }

    public function requires(): array { return ['sales' => ['date', 'total', 'status']]; }

    public function data(WidgetContext $ctx): array
    {
        $s = $ctx->schema;
        $statuses = array_merge($s->get('sales', 'draft_status', []), $s->get('sales', 'pending_status', []));
        if (!$statuses) return ['columns' => [], 'rows' => [], 'summary' => []];
        [$sc, $sb] = $s->scope('sales', 'd', false);
        $st = $s->c('sales', 'status', 'd');
        $in = implode(',', array_fill(0, count($statuses), '?'));
        $labels = $s->get('sales', 'statuses', []);
        $summary = $ctx->db->select("SELECT $st AS st, COUNT(*) AS n, COALESCE(SUM({$s->c('sales','total','d')}),0) AS v FROM {$s->t('sales')} d WHERE $st IN ($in) $sc GROUP BY $st", array_merge($statuses, $sb));
        $party = $s->available('customers', ['name']) && $s->hasColumn('sales', 'customer_id');
        $rows = $ctx->db->select(
            "SELECT d.id, {$s->c('sales','reference','d')} AS reference, {$s->c('sales','date','d')} AS date, $st AS st, {$s->c('sales','total','d')} AS amount"
            . ($party ? ", c.{$s->c('customers','name')} AS party FROM {$s->t('sales')} d LEFT JOIN {$s->t('customers')} c ON c.id = {$s->c('sales','customer_id','d')}" : ", NULL AS party FROM {$s->t('sales')} d")
            . " WHERE $st IN ($in) $sc ORDER BY {$s->c('sales','date','d')} ASC LIMIT " . (int) $this->opts($ctx)['limit'],
            array_merge($statuses, $sb));
        $now = $ctx->period->now->getTimestamp();
        return [
            'summary' => array_map(fn ($r) => ['label' => $labels[$r['st']] ?? (string) $r['st'], 'count' => (int) $r['n'], 'value' => round((float) $r['v'], 2)], $summary),
            'columns' => [
                ['key' => 'reference', 'label' => 'אסמכתא'], ['key' => 'party', 'label' => 'לקוח'],
                ['key' => 'status', 'label' => 'סטטוס', 'format' => 'badge'], ['key' => 'age', 'label' => 'ממתין', 'format' => 'days', 'align' => 'end', 'toneKey' => 'tone'],
                ['key' => 'amount', 'label' => 'סכום', 'format' => 'currency', 'align' => 'end'],
            ],
            'rows' => array_map(function ($r) use ($labels, $now, $ctx) {
                $age = max(0, (int) floor(($now - $this->ts($ctx, (string) $r['date'])) / 86400));
                return ['reference' => $r['reference'], 'party' => $r['party'] ?? '—', 'status' => $labels[$r['st']] ?? $r['st'], 'status_tone' => 'warning',
                    'age' => $age, 'tone' => $age >= 3 ? 'danger' : 'neutral', 'amount' => round((float) $r['amount'], 2)];
            }, $rows),
            'empty' => 'אין הזמנות פתוחות 🎉',
        ];
    }
}
