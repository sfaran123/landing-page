<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class RevenueTrend extends Widget
{
    public static function key(): string { return 'revenue_trend'; }

    public function meta(): array
    {
        return [
            'title' => 'מגמת הכנסות, רכש והוצאות', 'description' => 'גרף מגמה אוטומטי: שעתי להיום, יומי לחודש, חודשי לשנה.',
            'category' => 'charts', 'type' => 'chart', 'icon' => 'chart', 'size' => ['w' => 8, 'h' => 5], 'min' => ['w' => 4, 'h' => 4],
            'link' => '/report/income-statement',
            'options' => [
                'period' => self::periodOption(),
                'chart' => ['type' => 'select', 'label' => 'סוג גרף', 'default' => 'area', 'choices' => ['area' => 'שטח', 'bar' => 'עמודות', 'line' => 'קו']],
                'show_purchases' => ['type' => 'toggle', 'label' => 'הצג רכש', 'default' => true],
                'show_expenses' => ['type' => 'toggle', 'label' => 'הצג הוצאות', 'default' => true],
            ],
        ];
    }

    public function requires(): array { return ['sales' => ['date', 'total']]; }

    public function data(WidgetContext $ctx): array
    {
        $o = $this->opts($ctx);
        $p = $this->periodFor($ctx);
        $g = $p->granularity();
        $sales = $this->series($ctx, 'sales', 'total', $p, $g);
        $series = [['name' => 'מכירות', 'data' => array_values($sales), 'color' => 'primary']];
        if ($o['show_purchases'] && $ctx->schema->available('purchases', ['date', 'total'])) {
            $series[] = ['name' => 'רכש', 'data' => array_values($this->series($ctx, 'purchases', 'total', $p, $g)), 'color' => 'purple'];
        }
        if ($o['show_expenses'] && $ctx->schema->available('expenses', ['date', 'amount'])) {
            $series[] = ['name' => 'הוצאות', 'data' => array_values($this->series($ctx, 'expenses', 'amount', $p, $g)), 'color' => 'orange'];
        }
        return ['kind' => $o['chart'], 'granularity' => $g, 'categories' => array_keys($sales), 'series' => $series, 'format' => 'currency'];
    }
}
