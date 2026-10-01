<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

/** Replaces the three "מוכר ביותר" tables – one widget, configurable metric & period. */
class TopProducts extends Widget
{
    public static function key(): string { return 'top_products'; }

    public function meta(): array
    {
        return [
            'title' => 'המוצרים המובילים', 'description' => 'המוצרים הנמכרים ביותר לפי כמות או הכנסה, עם נתח מסך המכירות.',
            'category' => 'products', 'type' => 'table', 'icon' => 'star', 'size' => ['w' => 4, 'h' => 6], 'min' => ['w' => 3, 'h' => 4],
            'multiple' => true, 'permission' => 'best-seller', 'link' => '/report/product_report',
            'options' => [
                'metric' => ['type' => 'select', 'label' => 'מדד', 'default' => 'qty', 'choices' => ['qty' => 'כמות', 'revenue' => 'הכנסה']],
                'period' => self::periodOption(),
                'limit' => self::limitOption(5),
                'exclude_codes' => ['type' => 'text', 'label' => 'קודים להחרגה (פסיק)', 'default' => ''],
            ],
        ];
    }

    public function requires(): array
    {
        return ['sales' => ['date'], 'product_sales' => ['sale_id', 'product_id', 'qty', 'total'], 'products' => ['name']];
    }

    public function data(WidgetContext $ctx): array
    {
        $s = $ctx->schema;
        $o = $this->opts($ctx);
        $p = $this->periodFor($ctx);
        [$w, $b] = $this->where($ctx, 'sales', 's', $p);
        $excl = array_values(array_filter(array_map('trim', explode(',', (string) $o['exclude_codes']))));
        $code = $s->hasColumn('products', 'code') ? $s->c('products', 'code', 'pr') : "''";
        if ($excl && $s->hasColumn('products', 'code')) {
            $w .= " AND ($code IS NULL OR $code NOT IN (" . implode(',', array_fill(0, count($excl), '?')) . '))';
            array_push($b, ...$excl);
        }
        $order = $o['metric'] === 'revenue' ? 'revenue' : 'qty';
        $rows = $ctx->db->select(
            "SELECT pr.id, pr.{$s->c('products','name')} AS name, $code AS code,
                    COALESCE(SUM({$s->c('product_sales','qty','ps')}),0) AS qty, COALESCE(SUM({$s->c('product_sales','total','ps')}),0) AS revenue
             FROM {$s->t('product_sales')} ps
             JOIN {$s->t('sales')} s ON s.id = {$s->c('product_sales','sale_id','ps')}
             JOIN {$s->t('products')} pr ON pr.id = {$s->c('product_sales','product_id','ps')}
             WHERE $w GROUP BY pr.id, pr.{$s->c('products','name')}, $code ORDER BY $order DESC LIMIT " . (int) $o['limit'], $b);
        $total = array_sum(array_map(fn ($r) => (float) $r[$order], $rows)) ?: 1;
        return [
            'title_suffix' => ($o['metric'] === 'revenue' ? 'לפי הכנסה' : 'לפי כמות') . ' · ' . ($p->toArray()['label']),
            'columns' => [
                ['key' => 'rank', 'label' => '#'],
                ['key' => 'name', 'label' => 'מוצר', 'sub' => 'code'],
                ['key' => 'qty', 'label' => 'כמות', 'format' => 'qty', 'align' => 'end'],
                ['key' => 'revenue', 'label' => 'הכנסה', 'format' => 'currency', 'align' => 'end'],
                ['key' => 'share', 'label' => 'נתח', 'format' => 'bar'],
            ],
            'rows' => array_map(fn ($r, $i) => [
                'rank' => $i + 1, 'name' => $r['name'], 'code' => $r['code'],
                'qty' => round((float) $r['qty'], 2), 'revenue' => round((float) $r['revenue'], 2),
                'share' => round((float) $r[$order] / $total * 100, 1),
            ], $rows, array_keys($rows)),
            'empty' => 'אין מכירות בתקופה',
        ];
    }
}
