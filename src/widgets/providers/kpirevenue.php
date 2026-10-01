<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class KpiRevenue extends Widget
{
    public static function key(): string { return 'kpi_revenue'; }

    public function meta(): array
    {
        return [
            'title' => 'סך הכנסות', 'description' => 'סך המכירות בתקופה, השוואה לתקופה המקבילה וגרף מגמה.',
            'category' => 'kpi', 'type' => 'kpi', 'icon' => 'cash', 'size' => ['w' => 3, 'h' => 2], 'min' => ['w' => 2, 'h' => 2],
            'link' => '/sales', 'options' => ['period' => self::periodOption()],
        ];
    }

    public function requires(): array { return ['sales' => ['date', 'total']]; }

    public function data(WidgetContext $ctx): array
    {
        $p = $this->periodFor($ctx);
        $cur = $this->sum($ctx, 'sales', 'total', $p);
        $prev = $this->sum($ctx, 'sales', 'total', $p->previous());
        $spark = array_values($this->series($ctx, 'sales', 'total', $p));
        return $this->kpi($cur, $prev, ['spark' => $spark, 'sub' => 'לעומת ' . $this->prevLabel($p->preset)]);
    }

    public function prevLabel(string $preset): string
    {
        return match ($preset) {
            'today' => 'אתמול באותה שעה', 'yesterday' => 'שלשום', '7d' => '7 הימים שלפני', '30d' => '30 הימים שלפני',
            'month' => 'החודש הקודם עד אותו יום', 'last_month' => 'החודש שלפניו', 'quarter' => 'הרבעון הקודם',
            'year' => 'אשתקד עד אותו יום', default => 'התקופה הקודמת',
        };
    }
}
