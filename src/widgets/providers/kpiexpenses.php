<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class KpiExpenses extends Widget
{
    public static function key(): string { return 'kpi_expenses'; }

    public function meta(): array
    {
        return [
            'title' => 'הוצאות', 'description' => 'סך ההוצאות בתקופה.',
            'category' => 'kpi', 'type' => 'kpi', 'icon' => 'wallet', 'size' => ['w' => 3, 'h' => 2], 'min' => ['w' => 2, 'h' => 2],
            'link' => '/expenses', 'options' => ['period' => self::periodOption()],
        ];
    }

    public function requires(): array { return ['expenses' => ['date', 'amount']]; }

    public function data(WidgetContext $ctx): array
    {
        $p = $this->periodFor($ctx);
        $cur = $this->sum($ctx, 'expenses', 'amount', $p);
        $prev = $this->sum($ctx, 'expenses', 'amount', $p->previous());
        $extra = ['good' => 'down', 'spark' => array_values($this->series($ctx, 'expenses', 'amount', $p))];
        if ('expenses' === 'returns' && $ctx->schema->available('sales', ['date', 'total'])) {
            $sales = $this->sum($ctx, 'sales', 'total', $p);
            $extra['sub'] = ($sales > 0 ? round($cur / $sales * 100, 1) : 0) . '% מהמכירות';
        }
        return $this->kpi($cur, $prev, $extra);
    }
}
