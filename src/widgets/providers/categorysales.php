<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class CategorySales extends Widget
{
    public static function key(): string { return 'category_sales'; }

    public function meta(): array
    {
        return [
            'title' => 'מכירות לפי קטגוריה', 'description' => 'אילו קטגוריות מוצרים מכניסות הכי הרבה.',
            'category' => 'products', 'type' => 'chart', 'icon' => 'tag', 'size' => ['w' => 6, 'h' => 5], 'min' => ['w' => 4, 'h' => 4],
            'business_types' => ['retail', 'grocery', 'restaurant', 'ecommerce', 'wholesale'],
            'options' => ['period' => self::periodOption(), 'limit' => self::limitOption(8)],
        ];
    }

    public function requires(): array
    {
        return ['sales' => ['date'], 'product_sales' => ['sale_id', 'product_id', 'total'], 'products' => ['category_id'], 'categories' => ['name']];
    }

    public function data(WidgetContext $ctx): array
    {
        $s = $ctx->schema;
        $o = $this->opts($ctx);
        $p = $this->periodFor($ctx);
        [$w, $b] = $this->where($ctx, 'sales', 's', $p);
        $rows = $ctx->db->select(
            "SELECT COALESCE(c.{$s->c('categories','name')}, 'ללא קטגוריה') AS name, COALESCE(SUM({$s->c('product_sales','total','ps')}),0) AS v
             FROM {$s->t('product_sales')} ps
             JOIN {$s->t('sales')} s ON s.id = {$s->c('product_sales','sale_id','ps')}
             LEFT JOIN {$s->t('products')} pr ON pr.id = {$s->c('product_sales','product_id','ps')}
             LEFT JOIN {$s->t('categories')} c ON c.id = {$s->c('products','category_id','pr')}
             WHERE $w GROUP BY c.{$s->c('categories','name')} ORDER BY v DESC LIMIT " . (int) $o['limit'],
            $b
        );
        return ['kind' => 'hbar', 'format' => 'currency', 'categories' => array_column($rows, 'name'),
            'series' => [['name' => 'מכירות', 'data' => array_map(fn ($r) => round((float) $r['v'], 2), $rows)]]];
    }
}
