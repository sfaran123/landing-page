<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

/** Stock alerts with "days of cover" based on the last 30 days of sales velocity. */
class LowStock extends Widget
{
    public static function key(): string { return 'low_stock'; }

    public function meta(): array
    {
        return [
            'title' => 'מלאי נמוך – להזמין', 'description' => 'מוצרים מתחת לכמות ההתראה, כולל קצב מכירה ולכמה ימים המלאי יספיק.',
            'category' => 'products', 'type' => 'table', 'icon' => 'alert', 'size' => ['w' => 6, 'h' => 6], 'min' => ['w' => 4, 'h' => 4],
            'uses_period' => false, 'business_types' => ['retail', 'grocery', 'wholesale', 'ecommerce', 'restaurant'],
            'link' => '/report/product_quantity_alert',
            'options' => ['limit' => self::limitOption(10)],
        ];
    }

    public function requires(): array { return ['products' => ['name', 'qty', 'alert_quantity']]; }

    public function rows(WidgetContext $ctx, int $limit): array
    {
        $s = $ctx->schema;
        $active = $s->hasColumn('products', 'is_active') ? " AND (pr.{$s->c('products','is_active')} = 1 OR pr.{$s->c('products','is_active')} IS NULL)" : '';
        $qty = "pr.{$s->c('products','qty')}";
        $alert = "pr.{$s->c('products','alert_quantity')}";
        $code = $s->hasColumn('products', 'code') ? "pr.{$s->c('products','code')}" : "''";
        $velocity = '0';
        $b = [];
        if ($s->available('product_sales', ['sale_id', 'product_id', 'qty']) && $s->available('sales', ['date'])) {
            [$sc, $sb] = $s->scope('sales', 's', true);
            $velocity = "(SELECT COALESCE(SUM(ps.{$s->c('product_sales','qty')}),0) / 30.0 FROM {$s->t('product_sales')} ps
                          JOIN {$s->t('sales')} s ON s.id = ps.{$s->c('product_sales','sale_id')}
                          WHERE ps.{$s->c('product_sales','product_id')} = pr.id AND {$s->c('sales','date','s')} >= ? $sc)";
            $b = array_merge([$ctx->period->now->modify('-30 days')->format('Y-m-d H:i:s')], $sb);
        }
        $rows = $ctx->db->select(
            "SELECT pr.id, pr.{$s->c('products','name')} AS name, $code AS code, $qty AS qty, $alert AS alert, $velocity AS per_day
             FROM {$s->t('products')} pr
             WHERE $alert IS NOT NULL AND $alert > 0 AND $qty <= $alert $active
             ORDER BY ($qty / $alert) ASC LIMIT $limit", $b);
        return array_map(function ($r) {
            $perDay = (float) $r['per_day'];
            $qty = (float) $r['qty'];
            $cover = $perDay > 0 ? max(0, floor($qty / $perDay)) : null;
            return [
                'name' => $r['name'], 'code' => $r['code'], 'qty' => round($qty, 2), 'alert' => round((float) $r['alert'], 2),
                'per_day' => round($perDay, 2), 'cover' => $cover,
                'tone' => $qty <= 0 ? 'danger' : (($cover !== null && $cover <= 3) ? 'danger' : 'warning'),
                'suggest' => $perDay > 0 ? max(0, (int) ceil($perDay * 14 - $qty)) : null,   // two weeks of stock
            ];
        }, $rows);
    }

    public function data(WidgetContext $ctx): array
    {
        $rows = $this->rows($ctx, (int) $this->opts($ctx)['limit']);
        return [
            'columns' => [
                ['key' => 'name', 'label' => 'מוצר', 'sub' => 'code'],
                ['key' => 'qty', 'label' => 'במלאי', 'format' => 'qty', 'align' => 'end', 'toneKey' => 'tone'],
                ['key' => 'per_day', 'label' => 'נמכר ליום', 'format' => 'qty', 'align' => 'end'],
                ['key' => 'cover', 'label' => 'יספיק ל', 'format' => 'days', 'align' => 'end'],
                ['key' => 'suggest', 'label' => 'להזמין', 'format' => 'qty', 'align' => 'end', 'hint' => 'מלאי לשבועיים'],
            ],
            'rows' => $rows,
            'empty' => 'כל המוצרים מעל כמות ההתראה 👌',
        ];
    }
}
