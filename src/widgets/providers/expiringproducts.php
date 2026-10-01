<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class ExpiringProducts extends Widget
{
    public static function key(): string { return 'expiring_products'; }

    public function meta(): array
    {
        return [
            'title' => 'פג תוקף בקרוב', 'description' => 'אצוות מוצרים שתוקפן פג בימים הקרובים – למבצע או להחזרה לספק.',
            'category' => 'products', 'type' => 'table', 'icon' => 'hourglass', 'size' => ['w' => 4, 'h' => 5], 'min' => ['w' => 3, 'h' => 4],
            'uses_period' => false, 'business_types' => ['grocery', 'restaurant'], 'link' => '/report/product-expiry',
            'options' => ['days' => ['type' => 'select', 'label' => 'טווח ימים', 'default' => 30, 'choices' => [7 => '7', 14 => '14', 30 => '30', 60 => '60']], 'limit' => self::limitOption(10)],
        ];
    }

    public function requires(): array { return ['product_batches' => ['product_id', 'qty', 'expired_date'], 'products' => ['name']]; }

    public function data(WidgetContext $ctx): array
    {
        $s = $ctx->schema;
        $o = $this->opts($ctx);
        $now = $ctx->period->now;
        $exp = "b.{$s->c('product_batches','expired_date')}";
        $rows = $ctx->db->select(
            "SELECT pr.{$s->c('products','name')} AS name, b.{$s->c('product_batches','qty')} AS qty, $exp AS expires
             FROM {$s->t('product_batches')} b JOIN {$s->t('products')} pr ON pr.id = b.{$s->c('product_batches','product_id')}
             WHERE b.{$s->c('product_batches','qty')} > 0 AND $exp IS NOT NULL AND $exp <= ?
             ORDER BY $exp ASC LIMIT " . (int) $o['limit'],
            [$now->modify('+' . (int) $o['days'] . ' days')->format('Y-m-d')]
        );
        $today = $now->setTime(0, 0);
        return [
            'columns' => [
                ['key' => 'name', 'label' => 'מוצר'], ['key' => 'qty', 'label' => 'כמות', 'format' => 'qty', 'align' => 'end'],
                ['key' => 'expires', 'label' => 'תוקף', 'format' => 'date'], ['key' => 'days', 'label' => 'נותרו', 'format' => 'days', 'align' => 'end', 'toneKey' => 'tone'],
            ],
            'rows' => array_map(function ($r) use ($today, $ctx) {
                $d = (int) floor(($this->ts($ctx, substr($r['expires'], 0, 10)) - $today->getTimestamp()) / 86400);
                return ['name' => $r['name'], 'qty' => round((float) $r['qty'], 2), 'expires' => substr($r['expires'], 0, 10), 'days' => $d,
                    'tone' => $d < 0 ? 'danger' : ($d <= 7 ? 'warning' : 'neutral')];
            }, $rows),
            'empty' => 'אין מוצרים שפג תוקפם בטווח',
        ];
    }
}
