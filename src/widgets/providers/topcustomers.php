<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class TopCustomers extends Widget
{
    public static function key(): string { return 'top_customers'; }

    public function meta(): array
    {
        return [
            'title' => 'לקוחות מובילים', 'description' => 'הלקוחות שקנו הכי הרבה בתקופה, כולל יתרה פתוחה.',
            'category' => 'customers', 'type' => 'table', 'icon' => 'users', 'size' => ['w' => 4, 'h' => 6], 'min' => ['w' => 3, 'h' => 4],
            'business_types' => ['wholesale', 'services', 'ecommerce'], 'link' => '/customer',
            'options' => ['period' => self::periodOption(), 'limit' => self::limitOption(5), 'exclude_walkin' => ['type' => 'toggle', 'label' => 'החרג "לקוח כללי"', 'default' => true]],
        ];
    }

    public function requires(): array { return ['sales' => ['date', 'total', 'paid', 'customer_id'], 'customers' => ['name']]; }

    public function data(WidgetContext $ctx): array
    {
        $s = $ctx->schema;
        $o = $this->opts($ctx);
        $p = $this->periodFor($ctx);
        [$w, $b] = $this->where($ctx, 'sales', 's', $p);
        $name = "c.{$s->c('customers','name')}";
        if ($o['exclude_walkin']) {
            $w .= " AND $name NOT IN ('לקוח כללי', 'walk-in-customer', 'Walk in Customer')";
        }
        $t = $s->c('sales', 'total', 's');
        $rows = $ctx->db->select(
            "SELECT c.id, $name AS name, COUNT(*) AS orders, COALESCE(SUM($t),0) AS revenue,
                    COALESCE(SUM(CASE WHEN $t - COALESCE({$s->c('sales','paid','s')},0) > 0 THEN $t - COALESCE({$s->c('sales','paid','s')},0) ELSE 0 END),0) AS due
             FROM {$s->t('sales')} s JOIN {$s->t('customers')} c ON c.id = {$s->c('sales','customer_id','s')}
             WHERE $w GROUP BY c.id, $name ORDER BY revenue DESC LIMIT " . (int) $o['limit'], $b);
        return [
            'columns' => [
                ['key' => 'name', 'label' => 'לקוח'], ['key' => 'orders', 'label' => 'עסקאות', 'format' => 'number', 'align' => 'end'],
                ['key' => 'revenue', 'label' => 'סך קניות', 'format' => 'currency', 'align' => 'end'],
                ['key' => 'due', 'label' => 'יתרה', 'format' => 'currency', 'align' => 'end', 'tone' => 'danger-if-positive'],
            ],
            'rows' => array_map(fn ($r) => ['name' => $r['name'], 'orders' => (int) $r['orders'], 'revenue' => round((float) $r['revenue'], 2), 'due' => round((float) $r['due'], 2)], $rows),
            'empty' => 'אין נתונים לתקופה',
        ];
    }
}
