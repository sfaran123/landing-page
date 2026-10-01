<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class PaymentMethods extends Widget
{
    public static function key(): string { return 'payment_methods'; }

    public function meta(): array
    {
        return [
            'title' => 'אמצעי תשלום', 'description' => 'איך הלקוחות משלמים: מזומן, אשראי, צ׳ק, ביט, סיבוס…',
            'category' => 'finance', 'type' => 'chart', 'icon' => 'card', 'size' => ['w' => 4, 'h' => 5], 'min' => ['w' => 3, 'h' => 4],
            'link' => '/money-deposits', 'options' => ['period' => self::periodOption()],
        ];
    }

    public function requires(): array { return ['payments' => ['date', 'amount', 'method', 'sale_id']]; }

    public function data(WidgetContext $ctx): array
    {
        $s = $ctx->schema;
        $p = $this->periodFor($ctx);
        [$sc, $sb] = $s->scope('sales', 'd', false);
        [$pc, $pb] = $s->scope('payments', 'pay', false);
        $m = $s->c('payments', 'method', 'pay');
        $rows = $ctx->db->select(
            "SELECT $m AS m, COALESCE(SUM({$s->c('payments','amount','pay')}),0) AS v, COUNT(*) AS n
             FROM {$s->t('payments')} pay JOIN {$s->t('sales')} d ON d.id = {$s->c('payments','sale_id','pay')}
             WHERE {$s->c('payments','date','pay')} >= ? AND {$s->c('payments','date','pay')} < ? $sc $pc
             GROUP BY $m ORDER BY v DESC",
            array_merge([$p->fromSql(), $p->toSql()], $sb, $pb)
        );
        return [
            'kind' => 'donut', 'format' => 'currency', 'center' => 'סה״כ',
            'labels' => array_map(fn ($r) => $this->methodLabel($ctx, $r['m']), $rows),
            'values' => array_map(fn ($r) => round((float) $r['v'], 2), $rows),
            'counts' => array_map(fn ($r) => (int) $r['n'], $rows),
        ];
    }
}
