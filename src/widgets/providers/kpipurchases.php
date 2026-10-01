<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class KpiPurchases extends Widget
{
    public static function key(): string { return 'kpi_purchases'; }

    public function meta(): array
    {
        return [
            'title' => 'רכש', 'description' => 'סך הרכישות מספקים בתקופה.',
            'category' => 'kpi', 'type' => 'kpi', 'icon' => 'cart', 'size' => ['w' => 3, 'h' => 2], 'min' => ['w' => 2, 'h' => 2],
            'link' => '/sales?transaction_type=purchase', 'options' => ['period' => self::periodOption()],
        ];
    }

    public function requires(): array { return ['purchases' => ['date', 'total']]; }

    public function data(WidgetContext $ctx): array
    {
        $p = $this->periodFor($ctx);
        $cur = $this->sum($ctx, 'purchases', 'total', $p);
        $prev = $this->sum($ctx, 'purchases', 'total', $p->previous());
        $extra = ['good' => 'neutral', 'spark' => array_values($this->series($ctx, 'purchases', 'total', $p))];
        if ('purchases' === 'returns' && $ctx->schema->available('sales', ['date', 'total'])) {
            $sales = $this->sum($ctx, 'sales', 'total', $p);
            $extra['sub'] = ($sales > 0 ? round($cur / $sales * 100, 1) : 0) . '% מהמכירות';
        }
        return $this->kpi($cur, $prev, $extra);
    }
}
