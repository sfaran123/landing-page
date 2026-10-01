<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class QuickActions extends Widget
{
    public static function key(): string { return 'quick_actions'; }

    public function meta(): array
    {
        return [
            'title' => 'פעולות מהירות', 'description' => 'כפתורי הפעולה של המערכת: קופה, חשבונית, הזמנה, משלוח, לקוח, ספק…',
            'category' => 'general', 'type' => 'actions', 'icon' => 'bolt', 'size' => ['w' => 12, 'h' => 2], 'min' => ['w' => 4, 'h' => 2],
            'uses_period' => false,
            'options' => ['hide' => ['type' => 'text', 'label' => 'כפתורים להסתרה (שמות, פסיק)', 'default' => '']],
        ];
    }

    public function data(WidgetContext $ctx): array
    {
        $hide = array_filter(array_map('trim', explode(',', (string) $this->opts($ctx)['hide'])));
        return ['actions' => array_values(array_filter($ctx->config['quick_actions'] ?? [], fn ($a) => !in_array($a['label'], $hide, true)))];
    }
}
