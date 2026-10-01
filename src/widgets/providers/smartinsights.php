<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Insights\InsightEngine;
use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class SmartInsights extends Widget
{
    public static function key(): string { return 'smart_insights'; }

    public function meta(): array
    {
        return [
            'title' => 'מה דורש את תשומת לבך', 'description' => 'התראות חכמות: קצב מכירות חריג, חוסרים, חובות ישנים, צ׳קים, תוקף, הזמנות תקועות ומגמות מוצרים.',
            'category' => 'general', 'type' => 'insights', 'icon' => 'sparkle', 'size' => ['w' => 4, 'h' => 6], 'min' => ['w' => 3, 'h' => 4],
            'uses_period' => false,
            'options' => ['limit' => self::limitOption(5)],
        ];
    }

    public function requires(): array { return ['sales' => ['date', 'total']]; }

    public function data(WidgetContext $ctx): array
    {
        $all = (new InsightEngine())->run($ctx);
        return ['items' => array_slice($all, 0, (int) $this->opts($ctx)['limit']), 'total' => count($all)];
    }
}
