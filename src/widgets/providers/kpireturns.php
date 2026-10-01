<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class KpiReturns extends Widget
{
    public static function key(): string { return 'kpi_returns'; }

    public function meta(): array
    {
        return [
            'title' => 'החזרות', 'description' => 'סך החזרות המכירה בתקופה ושיעורן מהמכירות.',
            'category' => 'kpi', 'type' => 'kpi', 'icon' => 'undo', 'size' => ['w' => 3, 'h' => 2], 'min' => ['w' => 2, 'h' => 2],
            'link' => '/return-sale', 'options' => ['period' => self::periodOption()],
        ];
    }

    public function requires(): array { return ['returns' => ['date', 'total']]; }

    public function data(WidgetContext $ctx): array
    {
        $p = $this->periodFor($ctx);
        $cur = $this->sum($ctx, 'returns', 'total', $p);
        $prev = $this->sum($ctx, 'returns', 'total', $p->previous());
        $extra = ['good' => 'down', 'spark' => array_values($this->series($ctx, 'returns', 'total', $p))];
        if ('returns' === 'returns' && $ctx->schema->available('sales', ['date', 'total'])) {
            $sales = $this->sum($ctx, 'sales', 'total', $p);
            $extra['sub'] = ($sales > 0 ? round($cur / $sales * 100, 1) : 0) . '% מהמכירות';
        }
        return $this->kpi($cur, $prev, $extra);
    }
}
