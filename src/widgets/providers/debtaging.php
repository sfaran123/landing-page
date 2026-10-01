<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

/** Preserves "גיול חובות" from the executive dashboard – for customers or suppliers. */
class DebtAging extends Widget
{
    public static function key(): string { return 'debt_aging'; }

    public function meta(): array
    {
        return [
            'title' => 'גיול חובות', 'description' => 'חובות פתוחים לפי ותק (0-30, 31-60, 61-90, 90+) והחייבים הגדולים.',
            'category' => 'finance', 'type' => 'aging', 'icon' => 'clock', 'size' => ['w' => 6, 'h' => 6], 'min' => ['w' => 4, 'h' => 5],
            'uses_period' => false, 'multiple' => true,
            'options' => [
                'side' => ['type' => 'select', 'label' => 'צד', 'default' => 'customers', 'choices' => ['customers' => 'לקוחות', 'suppliers' => 'ספקים']],
                'limit' => self::limitOption(5),
            ],
        ];
    }

    public function requires(): array { return ['sales' => ['date', 'total', 'paid']]; }

    public function data(WidgetContext $ctx): array
    {
        $s = $ctx->schema;
        $o = $this->opts($ctx);
        $sup = $o['side'] === 'suppliers';
        [$entity, $partyEntity, $fk] = $sup ? ['purchases', 'suppliers', 'supplier_id'] : ['sales', 'customers', 'customer_id'];
        if (!$s->available($entity, ['date', 'total', 'paid', $fk]) || !$s->available($partyEntity, ['name'])) {
            return ['buckets' => [], 'rows' => [], 'total' => 0, 'side' => $o['side']];
        }
        [$w, $b] = $this->where($ctx, $entity, 'd', null);
        $open = "({$s->c($entity,'total','d')} - COALESCE({$s->c($entity,'paid','d')},0))";
        $age = $ctx->sql->daysUntilParam($s->c($entity, 'date', 'd'));
        $now = $ctx->period->now->format('Y-m-d H:i:s');
        $fkCol = $s->c($entity, $fk);
        $rows = $ctx->db->select(
            "SELECT pt.id, pt.{$s->c($partyEntity,'name')} AS name, SUM(q.open_amt) AS due, MAX(q.age) AS oldest,
                    SUM(CASE WHEN q.age <= 30 THEN q.open_amt ELSE 0 END) AS b1,
                    SUM(CASE WHEN q.age > 30 AND q.age <= 60 THEN q.open_amt ELSE 0 END) AS b2,
                    SUM(CASE WHEN q.age > 60 AND q.age <= 90 THEN q.open_amt ELSE 0 END) AS b3,
                    SUM(CASE WHEN q.age > 90 THEN q.open_amt ELSE 0 END) AS b4
             FROM (SELECT d.$fkCol AS party_id, $open AS open_amt, $age AS age
                   FROM {$s->t($entity)} d WHERE $w AND $open > 0.009) q
             JOIN {$s->t($partyEntity)} pt ON pt.id = q.party_id
             GROUP BY pt.id, pt.{$s->c($partyEntity,'name')} ORDER BY due DESC",
            array_merge([$now], $b)
        );
        $buckets = ['0-30' => 0, '31-60' => 0, '61-90' => 0, '90+' => 0];
        foreach ($rows as $r) {
            $buckets['0-30'] += $r['b1']; $buckets['31-60'] += $r['b2']; $buckets['61-90'] += $r['b3']; $buckets['90+'] += $r['b4'];
        }
        return [
            'side' => $o['side'],
            'title_suffix' => $sup ? 'ספקים' : 'לקוחות',
            'link' => $sup ? '/report/accounts-receivable-supplier' : '/report/accounts-receivable',
            'total' => round(array_sum($buckets), 2),
            'count' => count($rows),
            'buckets' => array_map(fn ($k, $v) => ['label' => $k . ' ימים', 'value' => round($v, 2)], array_keys($buckets), $buckets),
            'rows' => array_map(fn ($r) => ['name' => $r['name'], 'due' => round((float) $r['due'], 2), 'oldest' => (int) $r['oldest']], array_slice($rows, 0, (int) $o['limit'])),
        ];
    }
}
