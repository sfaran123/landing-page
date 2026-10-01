<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class ChequesDue extends Widget
{
    public static function key(): string { return 'cheques_due'; }

    public function meta(): array
    {
        return [
            'title' => 'צ׳קים לפירעון', 'description' => 'צ׳קים פתוחים שמועד פירעונם מתקרב – לתכנון תזרים.',
            'category' => 'finance', 'type' => 'table', 'icon' => 'cheque', 'size' => ['w' => 4, 'h' => 5], 'min' => ['w' => 3, 'h' => 4],
            'uses_period' => false, 'business_types' => ['wholesale', 'services'], 'link' => '/cheques-dashboard?tab=list',
            'options' => ['days' => ['type' => 'select', 'label' => 'ימים קדימה', 'default' => 14, 'choices' => [7 => '7', 14 => '14', 30 => '30']], 'limit' => self::limitOption(5)],
        ];
    }

    public function requires(): array { return ['cheques' => ['amount', 'due_date']]; }

    public function data(WidgetContext $ctx): array
    {
        $s = $ctx->schema;
        $o = $this->opts($ctx);
        $due = "c.{$s->c('cheques','due_date')}";
        $where = "$due <= ?";
        $b = [$ctx->period->now->modify('+' . (int) $o['days'] . ' days')->format('Y-m-d')];
        $open = $s->get('cheques', 'open_status', []);
        if ($open && $s->hasColumn('cheques', 'status')) {
            $where .= " AND c.{$s->c('cheques','status')} IN (" . implode(',', array_fill(0, count($open), '?')) . ')';
            array_push($b, ...$open);
        }
        $num = $s->hasColumn('cheques', 'number') ? "c.{$s->c('cheques','number')}" : 'c.id';
        $party = $s->hasColumn('cheques', 'party') ? "c.{$s->c('cheques','party')}" : 'NULL';
        $rows = $ctx->db->select("SELECT $num AS number, $party AS party, $due AS due, c.{$s->c('cheques','amount')} AS amount FROM {$s->t('cheques')} c WHERE $where ORDER BY $due ASC LIMIT " . (int) $o['limit'], $b);
        $today = $ctx->period->now->setTime(0, 0)->getTimestamp();
        return [
            'columns' => [
                ['key' => 'due', 'label' => 'פירעון', 'format' => 'date'], ['key' => 'party', 'label' => 'מוטב'],
                ['key' => 'number', 'label' => 'מס׳ צ׳ק'], ['key' => 'amount', 'label' => 'סכום', 'format' => 'currency', 'align' => 'end', 'toneKey' => 'tone'],
            ],
            'rows' => array_map(function ($r) use ($today, $ctx) {
                $d = (int) floor(($this->ts($ctx, substr($r['due'], 0, 10)) - $today) / 86400);
                return ['due' => substr($r['due'], 0, 10), 'party' => $r['party'] ?? '—', 'number' => $r['number'], 'amount' => round((float) $r['amount'], 2),
                    'tone' => $d <= 2 ? 'danger' : 'neutral'];
            }, $rows),
            'total' => round(array_sum(array_map(fn ($r) => (float) $r['amount'], $rows)), 2),
            'empty' => 'אין צ׳קים לפירעון בטווח',
        ];
    }
}
