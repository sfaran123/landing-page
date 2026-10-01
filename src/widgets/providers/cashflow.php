<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Support\Period;
use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

/** Payments received from customers vs. payments sent to suppliers, per month. */
class CashFlow extends Widget
{
    public static function key(): string { return 'cash_flow'; }

    public function meta(): array
    {
        return [
            'title' => 'תזרים מזומנים', 'description' => 'תשלומים שהתקבלו מול תשלומים ששולמו לספקים, לפי חודש, כולל נטו.',
            'category' => 'finance', 'type' => 'chart', 'icon' => 'swap', 'size' => ['w' => 6, 'h' => 5], 'min' => ['w' => 4, 'h' => 4],
            'uses_period' => false, 'permission' => 'cash_flow', 'link' => '/accounts/balancesheet',
            'options' => ['months' => ['type' => 'select', 'label' => 'חודשים', 'default' => 7, 'choices' => [4 => '4', 7 => '7', 12 => '12']]],
        ];
    }

    public function requires(): array { return ['payments' => ['date', 'amount', 'sale_id']]; }

    public function data(WidgetContext $ctx): array
    {
        $s = $ctx->schema;
        $months = (int) $this->opts($ctx)['months'];
        $now = $ctx->period->now;
        $from = $now->modify('first day of this month')->setTime(0, 0)->modify('-' . ($months - 1) . ' months');
        $p = new Period($from, $now, 'custom', $now);
        $bucket = $ctx->sql->yearMonth($s->c('payments', 'date', 'pay'));
        $cats = $p->buckets('month');
        $out = ['in' => array_fill_keys($cats, 0.0), 'out' => array_fill_keys($cats, 0.0)];

        $samePurchaseTable = $s->t('purchases') === $s->t('sales');
        $legs = [
            'in' => ['sales', 'sale_id'],
            'out' => ['purchases', $samePurchaseTable ? 'sale_id' : 'purchase_id'],
        ];
        foreach ($legs as $dir => [$entity, $fk]) {
            if (!$s->hasColumn('payments', $fk) || !$s->available($entity)) continue;
            [$sc, $sb] = $s->scope($entity, 'd', false);
            [$pc, $pb] = $s->scope('payments', 'pay', false);
            $sql = "SELECT $bucket AS k, COALESCE(SUM({$s->c('payments','amount','pay')}),0) AS v
                    FROM {$s->t('payments')} pay JOIN {$s->t($entity)} d ON d.id = {$s->c('payments',$fk,'pay')}
                    WHERE {$s->c('payments','date','pay')} >= ? AND {$s->c('payments','date','pay')} < ? $sc $pc
                    GROUP BY $bucket";
            foreach ($ctx->db->select($sql, array_merge([$p->fromSql(), $p->toSql()], $sb, $pb)) as $r) {
                if (isset($out[$dir][$r['k']])) $out[$dir][$r['k']] = round((float) $r['v'], 2);
            }
        }
        $net = array_map(fn ($a, $b) => round($a - $b, 2), $out['in'], $out['out']);
        return [
            'kind' => 'mixed', 'granularity' => 'month', 'categories' => $cats, 'format' => 'currency',
            'series' => [
                ['name' => 'התקבל', 'type' => 'column', 'data' => array_values($out['in']), 'color' => 'green'],
                ['name' => 'שולם לספקים', 'type' => 'column', 'data' => array_values($out['out']), 'color' => 'red'],
                ['name' => 'נטו', 'type' => 'line', 'data' => $net, 'color' => 'primary'],
            ],
            'totals' => ['in' => array_sum($out['in']), 'out' => array_sum($out['out']), 'net' => round(array_sum($net), 2)],
        ];
    }
}
