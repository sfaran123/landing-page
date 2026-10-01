<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class KpiSupplierDebt extends Widget
{
    public static function key(): string { return 'kpi_supplier_debt'; }

    public function meta(): array
    {
        return [
            'title' => 'יתרות ספקים פתוחות', 'description' => 'סך יתרת הספקים לתשלום נכון להיום.',
            'category' => 'kpi', 'type' => 'kpi', 'icon' => 'factory', 'size' => ['w' => 3, 'h' => 2], 'min' => ['w' => 2, 'h' => 2],
            'uses_period' => false, 'link' => '/report/accounts-receivable-supplier',
        ];
    }

    public function requires(): array { return ['purchases' => ['total', 'paid', 'supplier_id']]; }

    public function data(WidgetContext $ctx): array
    {
        $s = $ctx->schema;
        [$w, $b] = $this->where($ctx, 'purchases', 'x', null);
        $t = $s->c('purchases', 'total', 'x');
        $p = $s->c('purchases', 'paid', 'x');
        $n = $ctx->db->select("SELECT COUNT(DISTINCT {$s->c('purchases','supplier_id','x')}) AS n FROM {$s->t('purchases')} x WHERE $w AND $t - COALESCE($p,0) > 0.009", $b);
        return $this->kpi($this->openBalance($ctx, 'purchases'), null, [
            'good' => 'down', 'sub' => ((int) ($n[0]['n'] ?? 0)) . ' ספקים לתשלום',
        ]);
    }
}
