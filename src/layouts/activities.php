<?php

namespace AuraTech\SmartDashboard\Layouts;

use AuraTech\SmartDashboard\Widgets\WidgetContext;
use Throwable;

/**
 * Business activities ("what does this business actually do?").
 * Each activity switches a set of widgets on/off in the smart builder, and can be
 * detected automatically from the installation's own data.
 */
class Activities
{
    public static function all(): array
    {
        return [
            'inventory' => ['label' => 'ניהול מלאי', 'icon' => 'box', 'hint' => 'התראות מלאי נמוך וצפי גמר מלאי',
                'widgets' => [['low_stock', 6, 6]]],
            'expiry' => ['label' => 'מוצרים עם תוקף', 'icon' => 'hourglass', 'hint' => 'אצוות שפג תוקפן בקרוב',
                'widgets' => [['expiring_products', 4, 5]]],
            'credit_sales' => ['label' => 'מכירה בהקפה / לקוחות עסקיים', 'icon' => 'users', 'hint' => 'חובות לקוחות, גיול חובות, לקוחות מובילים',
                'widgets' => [['kpi_customer_debt', 3, 2], ['debt_aging', 6, 6, ['side' => 'customers']], ['top_customers', 6, 6]]],
            'supplier_credit' => ['label' => 'רכש באשראי מספקים', 'icon' => 'factory', 'hint' => 'יתרות ספקים וגיול',
                'widgets' => [['kpi_supplier_debt', 3, 2], ['debt_aging', 6, 6, ['side' => 'suppliers']]]],
            'cheques' => ['label' => 'עבודה עם צ׳קים', 'icon' => 'cheque', 'hint' => 'צ׳קים לפירעון בימים הקרובים',
                'widgets' => [['cheques_due', 4, 5]]],
            'deliveries' => ['label' => 'משלוחים', 'icon' => 'truck', 'hint' => 'סטטוס משלוחים ומשלוחים מתעכבים',
                'widgets' => [['deliveries', 6, 5]]],
            'orders' => ['label' => 'הזמנות מראש / טיוטות', 'icon' => 'inbox', 'hint' => 'הזמנות שממתינות להשלמה',
                'widgets' => [['open_orders', 6, 5]]],
            'tables' => ['label' => 'הושבה ושולחנות', 'icon' => 'table', 'hint' => 'שולחנות פתוחים וזמני ישיבה',
                'widgets' => [['open_tables', 8, 5]]],
            'quotations' => ['label' => 'הצעות מחיר', 'icon' => 'doc', 'hint' => 'צנרת הצעות מחיר',
                'widgets' => [['quotations_pipeline', 6, 5]]],
            'team' => ['label' => 'כמה קופאים / עובדים', 'icon' => 'trophy', 'hint' => 'דירוג מכירות לפי עובד',
                'widgets' => [['employee_sales', 6, 5]]],
            'returns' => ['label' => 'החזרות', 'icon' => 'undo', 'hint' => 'מעקב החזרות',
                'widgets' => [['kpi_returns', 3, 2]]],
        ];
    }

    /**
     * Look at real data from the last months and say which activities are in use.
     * @return array<string, array{on:bool, evidence:string}>
     */
    public static function detect(WidgetContext $ctx): array
    {
        $s = $ctx->schema;
        $now = $ctx->period->now;
        $ago = fn (string $m) => $now->modify($m)->format('Y-m-d H:i:s');
        $one = function (string $sql, array $b = []) use ($ctx) {
            $r = $ctx->db->select($sql, $b);
            return $r ? (float) array_values($r[0])[0] : 0.0;
        };
        $safe = function (callable $f) { try { return $f(); } catch (Throwable) { return null; } };
        $out = [];
        $put = function (string $k, ?array $v) use (&$out) { $out[$k] = $v ?? ['on' => false, 'evidence' => 'לא זמין במערכת']; };

        $put('inventory', $safe(function () use ($s, $one) {
            if (!$s->available('products', ['qty', 'alert_quantity'])) return null;
            $n = $one("SELECT COUNT(*) FROM {$s->t('products')} WHERE {$s->c('products','alert_quantity')} > 0");
            return ['on' => $n > 0, 'evidence' => (int) $n . ' מוצרים עם כמות התראה'];
        }));
        $put('expiry', $safe(function () use ($s, $one, $now) {
            if (!$s->available('product_batches', ['qty', 'expired_date'])) return null;
            $n = $one("SELECT COUNT(*) FROM {$s->t('product_batches')} WHERE {$s->c('product_batches','expired_date')} IS NOT NULL AND {$s->c('product_batches','qty')} > 0");
            return ['on' => $n > 0, 'evidence' => (int) $n . ' אצוות עם תאריך תוקף'];
        }));
        $put('credit_sales', $safe(function () use ($s, $one, $ago, $ctx) {
            if (!$s->available('sales', ['date', 'total', 'paid'])) return null;
            [$sc, $sb] = $s->scope('sales', 'x');
            $open = "({$s->c('sales','total','x')} - COALESCE({$s->c('sales','paid','x')},0))";
            $n = $one("SELECT COUNT(*) FROM {$s->t('sales')} x WHERE {$s->c('sales','date','x')} >= ? AND {$s->c('sales','date','x')} < ? AND $open > 0.009 $sc",
                array_merge([$ago('-180 days'), $ago('-3 days')], $sb));
            return ['on' => $n >= 3, 'evidence' => (int) $n . ' מכירות לא שולמו במלואן (חצי שנה)'];
        }));
        $put('supplier_credit', $safe(function () use ($s, $one) {
            if (!$s->available('purchases', ['total', 'paid'])) return null;
            [$sc, $sb] = $s->scope('purchases', 'x');
            $n = $one("SELECT COUNT(*) FROM {$s->t('purchases')} x WHERE {$s->c('purchases','total','x')} - COALESCE({$s->c('purchases','paid','x')},0) > 0.009 $sc", $sb);
            return ['on' => $n > 0, 'evidence' => (int) $n . ' רכישות עם יתרה פתוחה'];
        }));
        $put('cheques', $safe(function () use ($s, $one, $ago) {
            if ($s->available('cheques', ['due_date'])) {
                $n = $one("SELECT COUNT(*) FROM {$s->t('cheques')} WHERE {$s->c('cheques','due_date')} >= ?", [substr($ago('-180 days'), 0, 10)]);
                return ['on' => $n > 0, 'evidence' => (int) $n . ' צ׳קים בחצי השנה האחרונה'];
            }
            return null;
        }));
        $put('deliveries', $safe(function () use ($s, $one, $ago) {
            if (!$s->available('deliveries', ['date'])) return null;
            $n = $one("SELECT COUNT(*) FROM {$s->t('deliveries')} WHERE {$s->c('deliveries','date')} >= ?", [$ago('-90 days')]);
            return ['on' => $n > 0, 'evidence' => (int) $n . ' משלוחים ב-90 יום'];
        }));
        $put('orders', $safe(function () use ($s, $one, $ago) {
            $st = array_merge($s->get('sales', 'draft_status', []), $s->get('sales', 'pending_status', []));
            if (!$st || !$s->available('sales', ['status', 'date'])) return null;
            $n = $one("SELECT COUNT(*) FROM {$s->t('sales')} WHERE {$s->c('sales','status')} IN (" . implode(',', array_fill(0, count($st), '?')) . ") AND {$s->c('sales','date')} >= ?",
                array_merge($st, [$ago('-60 days')]));
            return ['on' => $n > 0, 'evidence' => (int) $n . ' הזמנות/טיוטות ב-60 יום'];
        }));
        $put('tables', $safe(function () use ($s, $one, $ago) {
            if (!$s->available('sales', ['table_id', 'date']) || !$s->available('tables', ['name'])) return null;
            $n = $one("SELECT COUNT(*) FROM {$s->t('sales')} WHERE {$s->c('sales','table_id')} IS NOT NULL AND {$s->c('sales','table_id')} <> 0 AND {$s->c('sales','date')} >= ?", [$ago('-30 days')]);
            $all = max(1, $one("SELECT COUNT(*) FROM {$s->t('sales')} WHERE {$s->c('sales','date')} >= ?", [$ago('-30 days')]));
            return ['on' => $n > 0, 'share' => round($n / $all, 3), 'evidence' => (int) $n . ' מכירות לשולחן ב-30 יום'];
        }));
        $put('quotations', $safe(function () use ($s, $one, $ago) {
            if (!$s->available('quotations', ['date'])) return null;
            $n = $one("SELECT COUNT(*) FROM {$s->t('quotations')} WHERE {$s->c('quotations','date')} >= ?", [$ago('-90 days')]);
            return ['on' => $n > 0, 'evidence' => (int) $n . ' הצעות מחיר ב-90 יום'];
        }));
        $put('team', $safe(function () use ($s, $one, $ago) {
            if (!$s->available('sales', ['user_id', 'date'])) return null;
            $n = $one("SELECT COUNT(DISTINCT {$s->c('sales','user_id')}) FROM {$s->t('sales')} WHERE {$s->c('sales','date')} >= ?", [$ago('-30 days')]);
            return ['on' => $n >= 2, 'evidence' => (int) $n . ' משתמשים מכרו החודש'];
        }));
        $put('returns', $safe(function () use ($s, $one, $ago) {
            if (!$s->available('returns', ['date'])) return null;
            $n = $one("SELECT COUNT(*) FROM {$s->t('returns')} WHERE {$s->c('returns','date')} >= ?", [$ago('-90 days')]);
            return ['on' => $n >= 3, 'evidence' => (int) $n . ' החזרות ב-90 יום'];
        }));
        return $out;
    }

    /** Best-guess business type from detected activities (shown as a suggestion only). */
    public static function suggestType(array $detected): string
    {
        $on = fn ($k) => ($detected[$k]['on'] ?? false) ? 1 : 0;
        $tables = ($detected['tables']['share'] ?? 0) >= 0.25 ? 1 : 0;
        $score = [
            'grocery'    => 4 * $on('expiry') + $on('inventory') + $on('team') + .5 * $on('credit_sales') + .5 * $on('deliveries'),
            'retail'     => 2 * $on('inventory') + 2 * $on('returns') + $on('team') - 2 * $on('expiry'),
            'restaurant' => 8 * $tables + $on('expiry'),
            'wholesale'  => 2 * $on('credit_sales') + $on('supplier_credit') + 1.5 * $on('deliveries') + $on('quotations') + 1.5 * $on('cheques') - $on('team'),
            'ecommerce'  => 2 * $on('deliveries') + 1.5 * $on('returns') - 2 * $on('team'),
            'services'   => 2 * $on('quotations') + $on('credit_sales') + 3 * (1 - $on('inventory')),
        ];
        arsort($score);   // stable: ties keep the order above
        return array_key_first($score);
    }
}
