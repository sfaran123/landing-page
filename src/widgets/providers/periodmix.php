<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class PeriodMix extends Widget
{
    public static function key(): string { return 'period_mix'; }

    public function meta(): array
    {
        return [
            'title' => 'הכנסה / רכש / הוצאה', 'description' => 'חלוקה בין הכנסות, רכש והוצאות בתקופה (הגרף החודשי הקיים).',
            'category' => 'finance', 'type' => 'chart', 'icon' => 'pie', 'size' => ['w' => 4, 'h' => 5], 'min' => ['w' => 3, 'h' => 4],
            'permission' => 'monthly_summary', 'options' => ['period' => self::periodOption()],
        ];
    }

    public function requires(): array { return ['sales' => ['date', 'total']]; }

    public function data(WidgetContext $ctx): array
    {
        $p = $this->periodFor($ctx);
        $labels = ['הכנסה'];
        $vals = [$this->sum($ctx, 'sales', 'total', $p)];
        $colors = ['primary'];
        if ($ctx->schema->available('purchases', ['date', 'total'])) {
            $labels[] = 'רכש'; $vals[] = $this->sum($ctx, 'purchases', 'total', $p); $colors[] = 'purple';
        }
        if ($ctx->schema->available('expenses', ['date', 'amount'])) {
            $labels[] = 'הוצאה'; $vals[] = $this->sum($ctx, 'expenses', 'amount', $p); $colors[] = 'orange';
        }
        return ['kind' => 'donut', 'labels' => $labels, 'values' => $vals, 'colors' => $colors, 'format' => 'currency', 'center' => 'הכנסה'];
    }
}
