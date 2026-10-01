<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class KpiCustomerDebt extends Widget
{
    public static function key(): string { return 'kpi_customer_debt'; }

    public function meta(): array
    {
        return [
            'title' => 'יתרות לקוחות פתוחות', 'description' => 'סך יתרת הלקוחות לתשלום נכון להיום.',
            'category' => 'kpi', 'type' => 'kpi', 'icon' => 'users', 'size' => ['w' => 3, 'h' => 2], 'min' => ['w' => 2, 'h' => 2],
            'uses_period' => false, 'link' => '/report/accounts-receivable',
        ];
    }

    public function requires(): array { return ['sales' => ['total', 'paid', 'customer_id']]; }

    public function data(WidgetContext $ctx): array
    {
        $s = $ctx->schema;
        [$w, $b] = $this->where($ctx, 'sales', 'x', null);
        $t = $s->c('sales', 'total', 'x');
        $p = $s->c('sales', 'paid', 'x');
        $n = $ctx->db->select("SELECT COUNT(DISTINCT {$s->c('sales','customer_id','x')}) AS n FROM {$s->t('sales')} x WHERE $w AND $t - COALESCE($p,0) > 0.009", $b);
        return $this->kpi($this->openBalance($ctx, 'sales'), null, [
            'good' => 'down', 'sub' => ((int) ($n[0]['n'] ?? 0)) . ' לקוחות עם יתרה פתוחה',
        ]);
    }
}
