<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class KpiOrders extends Widget
{
    public static function key(): string { return 'kpi_orders'; }

    public function meta(): array
    {
        return [
            'title' => 'מספר עסקאות', 'description' => 'כמות המכירות בתקופה והשוואה לתקופה המקבילה.',
            'category' => 'kpi', 'type' => 'kpi', 'icon' => 'receipt', 'size' => ['w' => 3, 'h' => 2], 'min' => ['w' => 2, 'h' => 2],
            'link' => '/sales', 'options' => ['period' => self::periodOption()],
        ];
    }

    public function requires(): array { return ['sales' => ['date']]; }

    public function data(WidgetContext $ctx): array
    {
        $p = $this->periodFor($ctx);
        $cur = $this->countRows($ctx, 'sales', $p);
        $prev = $this->countRows($ctx, 'sales', $p->previous());
        $spark = array_values($this->series($ctx, 'sales', 'total', $p, null, 'COUNT'));
        return $this->kpi($cur, $prev, ['format' => 'number', 'spark' => $spark, 'sub' => 'עסקאות בתקופה']);
    }
}
