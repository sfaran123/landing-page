<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

/** Preserves "עסקה אחרונה": tabs for sale / return / purchase / quotation / payment. */
class RecentTransactions extends Widget
{
    public static function key(): string { return 'recent_transactions'; }

    public function meta(): array
    {
        return [
            'title' => 'עסקאות אחרונות', 'description' => 'מכירות, החזרות, רכש, הצעות מחיר ותשלומים אחרונים – בלשוניות.',
            'category' => 'lists', 'type' => 'tabs-table', 'icon' => 'list', 'size' => ['w' => 6, 'h' => 6], 'min' => ['w' => 4, 'h' => 4],
            'uses_period' => false, 'permission' => 'recent_transaction',
            'options' => ['limit' => self::limitOption(5)],
        ];
    }

    public function requires(): array { return ['sales' => ['date', 'total', 'reference']]; }

    public function data(WidgetContext $ctx): array
    {
        $limit = (int) $this->opts($ctx)['limit'];
        $s = $ctx->schema;
        $tabs = [];
        $tabs[] = ['key' => 'sale', 'label' => 'מכירה', 'link' => '/sales', 'rows' => $this->docs($ctx, 'sales', 'customers', 'customer_id', $limit)];
        if ($s->available('returns', ['date', 'total'])) {
            $tabs[] = ['key' => 'return', 'label' => 'החזרה', 'link' => '/return-sale', 'rows' => $this->docs($ctx, 'returns', 'customers', 'customer_id', $limit)];
        }
        if ($s->available('purchases', ['date', 'total'])) {
            $tabs[] = ['key' => 'purchase', 'label' => 'רכש', 'link' => '/sales?transaction_type=purchase', 'rows' => $this->docs($ctx, 'purchases', 'suppliers', 'supplier_id', $limit)];
        }
        if ($s->available('quotations', ['date', 'total'])) {
            $tabs[] = ['key' => 'quotation', 'label' => 'הצעת מחיר', 'link' => '/quotations', 'rows' => $this->docs($ctx, 'quotations', 'customers', 'customer_id', $limit)];
        }
        if ($s->available('payments', ['date', 'amount'])) {
            $tabs[] = ['key' => 'payment', 'label' => 'תשלום', 'link' => '/money-deposits', 'rows' => $this->payments($ctx, $limit)];
        }
        return [
            'columns' => [
                ['key' => 'date', 'label' => 'תאריך', 'format' => 'date'],
                ['key' => 'reference', 'label' => 'אסמכתא'],
                ['key' => 'party', 'label' => 'לקוח / ספק'],
                ['key' => 'status', 'label' => 'סטטוס', 'format' => 'badge'],
                ['key' => 'amount', 'label' => 'סכום', 'format' => 'currency', 'align' => 'end'],
            ],
            'tabs' => $tabs,
        ];
    }

    private function docs(WidgetContext $ctx, string $entity, string $partyEntity, string $partyKey, int $limit): array
    {
        $s = $ctx->schema;
        [$w, $b] = $this->where($ctx, $entity, 'd', null, false);
        $ref = $s->hasColumn($entity, 'reference') ? $s->c($entity, 'reference', 'd') : 'd.id';
        $status = $s->hasColumn($entity, 'status') ? $s->c($entity, 'status', 'd') : 'NULL';
        $joinParty = $s->hasColumn($entity, $partyKey) && $s->available($partyEntity, ['name']);
        $party = $joinParty ? "pt.{$s->c($partyEntity,'name')}" : 'NULL';
        $join = $joinParty ? "LEFT JOIN {$s->t($partyEntity)} pt ON pt.id = {$s->c($entity,$partyKey,'d')}" : '';
        $rows = $ctx->db->select(
            "SELECT d.id, {$s->c($entity,'date','d')} AS date, $ref AS reference, $party AS party, $status AS status, {$s->c($entity,'total','d')} AS amount
             FROM {$s->t($entity)} d $join WHERE $w ORDER BY {$s->c($entity,'date','d')} DESC, d.id DESC LIMIT $limit", $b);
        $labels = $s->get($entity, 'statuses', []);
        return array_map(fn ($r) => [
            'date' => $r['date'], 'reference' => $r['reference'], 'party' => $r['party'] ?? 'לקוח כללי',
            'status' => $r['status'] === null ? '' : ($labels[$r['status']] ?? (string) $r['status']),
            'status_tone' => match ((int) $r['status']) { 1 => 'success', 2 => 'warning', 3, 4 => 'danger', default => 'neutral' },
            'amount' => round((float) $r['amount'], 2),
        ], $rows);
    }

    private function payments(WidgetContext $ctx, int $limit): array
    {
        $s = $ctx->schema;
        [$pc, $pb] = $s->scope('payments', 'p', false);
        $ref = $s->hasColumn('payments', 'reference') ? $s->c('payments', 'reference', 'p') : 'p.id';
        $method = $s->hasColumn('payments', 'method') ? $s->c('payments', 'method', 'p') : 'NULL';
        $rows = $ctx->db->select("SELECT {$s->c('payments','date','p')} AS date, $ref AS reference, $method AS m, {$s->c('payments','amount','p')} AS amount
            FROM {$s->t('payments')} p WHERE 1=1 $pc ORDER BY {$s->c('payments','date','p')} DESC LIMIT $limit", $pb);
        return array_map(fn ($r) => ['date' => $r['date'], 'reference' => $r['reference'], 'party' => '—',
            'status' => $this->methodLabel($ctx, $r['m']), 'status_tone' => 'info', 'amount' => round((float) $r['amount'], 2)], $rows);
    }
}
