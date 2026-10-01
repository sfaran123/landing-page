<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Support\Period;
use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class KpiAvgTicket extends Widget
{
    public static function key(): string { return 'kpi_avg_ticket'; }

    public function meta(): array
    {
        return [
            'title' => 'סל ממוצע', 'description' => 'ממוצע סכום לעסקה – מדד חשוב למסעדות וקמעונאות.',
            'category' => 'kpi', 'type' => 'kpi', 'icon' => 'basket', 'size' => ['w' => 3, 'h' => 2], 'min' => ['w' => 2, 'h' => 2],
            'business_types' => ['retail', 'grocery', 'restaurant', 'ecommerce'],
            'options' => ['period' => self::periodOption()],
        ];
    }

    public function requires(): array { return ['sales' => ['date', 'total']]; }

    private function avg(WidgetContext $ctx, Period $p): float
    {
        $n = $this->countRows($ctx, 'sales', $p);
        return $n ? $this->sum($ctx, 'sales', 'total', $p) / $n : 0.0;
    }

    public function data(WidgetContext $ctx): array
    {
        $p = $this->periodFor($ctx);
        return $this->kpi($this->avg($ctx, $p), $this->avg($ctx, $p->previous()), ['sub' => 'לעסקה']);
    }
}
