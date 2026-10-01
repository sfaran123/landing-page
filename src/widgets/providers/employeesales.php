<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class EmployeeSales extends Widget
{
    public static function key(): string { return 'employee_sales'; }

    public function meta(): array
    {
        return [
            'title' => 'מכירות לפי עובד / קופה', 'description' => 'טבלת מובילים: מכירות, עסקאות וסל ממוצע לכל משתמש.',
            'category' => 'team', 'type' => 'table', 'icon' => 'trophy', 'size' => ['w' => 4, 'h' => 5], 'min' => ['w' => 3, 'h' => 4],
            'link' => '/report/employee-sales', 'options' => ['period' => self::periodOption(), 'limit' => self::limitOption(5)],
        ];
    }

    public function requires(): array { return ['sales' => ['date', 'total', 'user_id'], 'users' => ['name']]; }

    public function data(WidgetContext $ctx): array
    {
        $s = $ctx->schema;
        $o = $this->opts($ctx);
        $p = $this->periodFor($ctx);
        [$sc, $sb] = $s->scope('sales', 's', true);
        $d = $s->c('sales', 'date', 's');
        $rows = $ctx->db->select(
            "SELECT u.{$s->c('users','name')} AS name, COUNT(*) AS orders, COALESCE(SUM({$s->c('sales','total','s')}),0) AS revenue
             FROM {$s->t('sales')} s JOIN {$s->t('users')} u ON u.id = {$s->c('sales','user_id','s')}
             WHERE $d >= ? AND $d < ? $sc GROUP BY u.id, u.{$s->c('users','name')} ORDER BY revenue DESC LIMIT " . (int) $o['limit'],
            array_merge([$p->fromSql(), $p->toSql()], $sb));
        $max = max(array_map(fn ($r) => (float) $r['revenue'], $rows) ?: [1]) ?: 1;
        return [
            'columns' => [
                ['key' => 'name', 'label' => 'משתמש'], ['key' => 'orders', 'label' => 'עסקאות', 'format' => 'number', 'align' => 'end'],
                ['key' => 'avg', 'label' => 'סל ממוצע', 'format' => 'currency', 'align' => 'end'],
                ['key' => 'revenue', 'label' => 'מכירות', 'format' => 'currency', 'align' => 'end'], ['key' => 'share', 'label' => '', 'format' => 'bar'],
            ],
            'rows' => array_map(fn ($r) => [
                'name' => $r['name'], 'orders' => (int) $r['orders'], 'revenue' => round((float) $r['revenue'], 2),
                'avg' => $r['orders'] ? round($r['revenue'] / $r['orders'], 2) : 0, 'share' => round($r['revenue'] / $max * 100, 1),
            ], $rows),
            'empty' => 'אין מכירות בתקופה',
        ];
    }
}
