<?php

namespace AuraTech\SmartDashboard\Insights;

use AuraTech\SmartDashboard\Support\Period;
use AuraTech\SmartDashboard\Widgets\Providers\KpiProfit;
use AuraTech\SmartDashboard\Widgets\Providers\LowStock;
use AuraTech\SmartDashboard\Widgets\Queries;
use AuraTech\SmartDashboard\Widgets\WidgetContext;
use Throwable;

/**
 * Rule-based "smart" layer. Each rule inspects the data and may return an
 * insight: level (critical|warning|success|info), title, text, action.
 * A failing rule is skipped – it never breaks the dashboard.
 */
class InsightEngine
{
    use Queries;

    private const ORDER = ['critical' => 0, 'warning' => 1, 'success' => 2, 'info' => 3];

    public function rules(): array
    {
        return ['todayPace', 'noSalesYet', 'stockouts', 'monthForecast', 'overdueCustomers', 'chequesSoon',
            'expiringSoon', 'staleOrders', 'productMovers', 'returnsRate', 'lowMargin'];
    }

    public function run(WidgetContext $ctx): array
    {
        $out = [];
        foreach ($this->rules() as $rule) {
            try {
                $r = $this->$rule($ctx);
                if ($r) $out[] = $r + ['rule' => $rule];
            } catch (Throwable $e) {
                // missing table/column for this installation – skip silently
            }
        }
        usort($out, fn ($a, $b) => (self::ORDER[$a['level']] <=> self::ORDER[$b['level']]) ?: (($b['weight'] ?? 0) <=> ($a['weight'] ?? 0)));
        return $out;
    }

    private function tz(WidgetContext $ctx): string
    {
        return $ctx->period->from->getTimezone()->getName();
    }

    private function money(float $v): string
    {
        return '₪' . number_format($v, 0);
    }

    /** Sales so far today vs the average of the same weekday, same time, over the last 4 weeks. */
    private function todayPace(WidgetContext $ctx): ?array
    {
        $now = $ctx->period->now;
        if ((int) $now->format('G') < 10) return null;
        $today = new Period($now->setTime(0, 0), $now, 'custom', $now);
        $cur = $this->sum($ctx, 'sales', 'total', $today);
        $vals = [];
        for ($w = 1; $w <= 4; $w++) {
            $f = $now->setTime(0, 0)->modify("-$w weeks");
            $vals[] = $this->sum($ctx, 'sales', 'total', new Period($f, $now->modify("-$w weeks"), 'custom', $now));
        }
        $avg = array_sum($vals) / 4;
        if ($avg < 200) return null;
        $d = ($cur - $avg) / $avg * 100;
        if ($d <= -20) {
            return ['level' => 'warning', 'weight' => abs($d), 'icon' => 'trend-down', 'title' => 'היום חלש מהרגיל',
                'text' => sprintf('נמכר %s עד עכשיו – %d%% מתחת לממוצע של אותו יום בשבוע (%s).', $this->money($cur), abs(round($d)), $this->money($avg)),
                'action' => ['label' => 'צור מבצע', 'url' => '/offer-plans/create']];
        }
        if ($d >= 20) {
            return ['level' => 'success', 'weight' => $d, 'icon' => 'trend-up', 'title' => 'יום חזק במיוחד',
                'text' => sprintf('נמכר %s עד עכשיו – %d%% מעל הממוצע לאותו יום ושעה.', $this->money($cur), round($d))];
        }
        return null;
    }

    private function noSalesYet(WidgetContext $ctx): ?array
    {
        $now = $ctx->period->now;
        if ((int) $now->format('G') < 11) return null;
        $today = new Period($now->setTime(0, 0), $now, 'custom', $now);
        if ($this->countRows($ctx, 'sales', $today) > 0) return null;
        $last = new Period($now->setTime(0, 0)->modify('-7 days'), $now->setTime(0, 0), 'custom', $now);
        if ($this->countRows($ctx, 'sales', $last) < 7) return null;
        return ['level' => 'critical', 'weight' => 100, 'icon' => 'alert', 'title' => 'אין מכירות היום',
            'text' => 'עד השעה ' . $now->format('H:i') . ' לא נרשמה אף מכירה. כדאי לבדוק שהקופה מחוברת ופועלת.',
            'action' => ['label' => 'פתח קופה', 'url' => '/pos']];
    }

    private function stockouts(WidgetContext $ctx): ?array
    {
        $w = new LowStock();
        if (!$w->isAvailable($ctx)) return null;
        $rows = $w->rows($ctx, 200);
        if (!$rows) return null;
        $out = array_filter($rows, fn ($r) => $r['qty'] <= 0 && $r['per_day'] > 0);
        $urgent = array_filter($rows, fn ($r) => $r['cover'] !== null && $r['cover'] <= 3);
        if ($out) {
            $names = implode(', ', array_slice(array_column($out, 'name'), 0, 3));
            return ['level' => 'critical', 'weight' => 90 + count($out), 'icon' => 'box', 'title' => count($out) . ' מוצרים נמכרים אזלו מהמלאי',
                'text' => "מוצרים שנמכרים באופן קבוע ואינם במלאי: $names" . (count($out) > 3 ? ' ועוד' : '') . '.',
                'action' => ['label' => 'צור הזמנת רכש', 'url' => '/sales/create?transaction_type=purchase']];
        }
        return ['level' => 'warning', 'weight' => 50 + count($rows), 'icon' => 'box', 'title' => count($rows) . ' מוצרים מתחת לכמות ההתראה',
            'text' => $urgent ? count($urgent) . ' מהם יגמרו תוך 3 ימים בקצב המכירה הנוכחי.' : 'מומלץ לתכנן הזמנה מהספקים.',
            'action' => ['label' => 'לרשימה', 'url' => '/report/product_quantity_alert']];
    }

    private function monthForecast(WidgetContext $ctx): ?array
    {
        $now = $ctx->period->now;
        if ((int) $now->format('j') < 5) return null;
        $m = Period::make('month', null, null, $this->tz($ctx), $now);
        $cur = $this->sum($ctx, 'sales', 'total', $m);
        $f = $now->modify('first day of this month')->setTime(0, 0);
        $lastFull = $this->sum($ctx, 'sales', 'total', new Period($f->modify('-1 month'), $f, 'custom', $now));
        if ($lastFull <= 0 || $m->elapsedRatio() <= 0) return null;
        $forecast = $cur / $m->elapsedRatio();
        $d = ($forecast - $lastFull) / $lastFull * 100;
        return ['level' => $d < -10 ? 'warning' : ($d > 5 ? 'success' : 'info'), 'weight' => abs($d), 'icon' => 'target',
            'title' => 'צפי לסוף החודש: ' . $this->money($forecast),
            'text' => sprintf('לפי הקצב הנוכחי – %s%d%% לעומת החודש הקודם (%s).', $d >= 0 ? '+' : '−', abs(round($d)), $this->money($lastFull))];
    }

    private function overdueCustomers(WidgetContext $ctx): ?array
    {
        $s = $ctx->schema;
        if (!$s->available('sales', ['date', 'total', 'paid', 'customer_id'])) return null;
        [$w, $b] = $this->where($ctx, 'sales', 'd', null);
        $open = "({$s->c('sales','total','d')} - COALESCE({$s->c('sales','paid','d')},0))";
        $r = $ctx->db->select("SELECT COUNT(DISTINCT {$s->c('sales','customer_id','d')}) AS n, COALESCE(SUM($open),0) AS v FROM {$s->t('sales')} d
            WHERE $w AND $open > 0.009 AND {$s->c('sales','date','d')} < ?", array_merge($b, [$ctx->period->now->modify('-60 days')->format('Y-m-d H:i:s')]))[0];
        if ((float) $r['v'] < 1) return null;
        return ['level' => 'warning', 'weight' => 40, 'icon' => 'clock', 'title' => 'חובות לקוחות מעל 60 יום',
            'text' => sprintf('%d לקוחות חייבים %s בחשבוניות בנות יותר מחודשיים.', $r['n'], $this->money((float) $r['v'])),
            'action' => ['label' => 'שלח תזכורת SMS', 'url' => '/setting/createsms']];
    }

    private function chequesSoon(WidgetContext $ctx): ?array
    {
        $s = $ctx->schema;
        if (!$s->available('cheques', ['amount', 'due_date'])) return null;
        $b = [$ctx->period->now->format('Y-m-d'), $ctx->period->now->modify('+7 days')->format('Y-m-d')];
        $where = "c.{$s->c('cheques','due_date')} >= ? AND c.{$s->c('cheques','due_date')} <= ?";
        $open = $s->get('cheques', 'open_status', []);
        if ($open && $s->hasColumn('cheques', 'status')) {
            $where .= " AND c.{$s->c('cheques','status')} IN (" . implode(',', array_fill(0, count($open), '?')) . ')';
            array_push($b, ...$open);
        }
        $r = $ctx->db->select("SELECT COUNT(*) AS n, COALESCE(SUM(c.{$s->c('cheques','amount')}),0) AS v FROM {$s->t('cheques')} c WHERE $where", $b)[0];
        if ((int) $r['n'] === 0) return null;
        return ['level' => 'info', 'weight' => 30, 'icon' => 'cheque', 'title' => 'צ׳קים לפירעון השבוע',
            'text' => sprintf('%d צ׳קים בסך %s יפרעו ב-7 הימים הקרובים. ודא יתרה מספקת בבנק.', $r['n'], $this->money((float) $r['v'])),
            'action' => ['label' => 'לצ׳קים', 'url' => '/cheques-dashboard?tab=list']];
    }

    private function expiringSoon(WidgetContext $ctx): ?array
    {
        $s = $ctx->schema;
        if (!$s->available('product_batches', ['qty', 'expired_date'])) return null;
        $r = $ctx->db->select("SELECT COUNT(*) AS n FROM {$s->t('product_batches')} b WHERE b.{$s->c('product_batches','qty')} > 0
            AND b.{$s->c('product_batches','expired_date')} IS NOT NULL AND b.{$s->c('product_batches','expired_date')} <= ?",
            [$ctx->period->now->modify('+7 days')->format('Y-m-d')])[0];
        if ((int) $r['n'] === 0) return null;
        return ['level' => 'warning', 'weight' => 35, 'icon' => 'hourglass', 'title' => $r['n'] . ' אצוות פגות תוקף תוך שבוע',
            'text' => 'כדאי להוציא למבצע, להעביר לקדמת המדף או לתאם החזרה לספק.', 'action' => ['label' => 'לדוח תוקף', 'url' => '/report/product-expiry']];
    }

    private function staleOrders(WidgetContext $ctx): ?array
    {
        $s = $ctx->schema;
        $st = array_merge($s->get('sales', 'draft_status', []), $s->get('sales', 'pending_status', []));
        if (!$st || !$s->available('sales', ['status', 'date'])) return null;
        [$sc, $sb] = $s->scope('sales', 'd', false);
        $r = $ctx->db->select("SELECT COUNT(*) AS n FROM {$s->t('sales')} d WHERE {$s->c('sales','status','d')} IN (" . implode(',', array_fill(0, count($st), '?')) . ")
            AND {$s->c('sales','date','d')} < ? $sc", array_merge($st, [$ctx->period->now->modify('-2 days')->format('Y-m-d H:i:s')], $sb))[0];
        if ((int) $r['n'] === 0) return null;
        return ['level' => 'warning', 'weight' => 20 + (int) $r['n'], 'icon' => 'inbox', 'title' => $r['n'] . ' הזמנות/טיוטות תקועות',
            'text' => 'ממתינות יותר מיומיים ללא השלמה – כסף שעוד לא נגבה.', 'action' => ['label' => 'לטיוטות', 'url' => '/sales?sale_status=5']];
    }

    /** Biggest riser this week vs last week (by revenue). */
    private function productMovers(WidgetContext $ctx): ?array
    {
        $s = $ctx->schema;
        if (!$s->available('product_sales', ['sale_id', 'product_id', 'total']) || !$s->available('products', ['name'])) return null;
        $now = $ctx->period->now;
        $cut = $now->modify('-7 days')->format('Y-m-d H:i:s');
        $start = $now->modify('-14 days')->format('Y-m-d H:i:s');
        [$sc, $sb] = $s->scope('sales', 's', true);
        $d = $s->c('sales', 'date', 's');
        $t = $s->c('product_sales', 'total', 'ps');
        $rows = $ctx->db->select(
            "SELECT pr.{$s->c('products','name')} AS name,
                    COALESCE(SUM(CASE WHEN $d >= ? THEN $t ELSE 0 END),0) AS cur,
                    COALESCE(SUM(CASE WHEN $d < ? THEN $t ELSE 0 END),0) AS prev
             FROM {$s->t('product_sales')} ps JOIN {$s->t('sales')} s ON s.id = {$s->c('product_sales','sale_id','ps')}
             JOIN {$s->t('products')} pr ON pr.id = {$s->c('product_sales','product_id','ps')}
             WHERE $d >= ? AND $d < ? $sc GROUP BY pr.id, pr.{$s->c('products','name')}",
            array_merge([$cut, $cut, $start, $now->format('Y-m-d H:i:s')], $sb));
        $best = null;
        foreach ($rows as $r) {
            $prev = (float) $r['prev']; $cur = (float) $r['cur'];
            if ($prev < 300 || $cur < 300) continue;
            $g = ($cur - $prev) / $prev * 100;
            if ($g >= 30 && (!$best || $g > $best['g'])) $best = ['name' => $r['name'], 'g' => $g, 'cur' => $cur];
        }
        if (!$best) return null;
        return ['level' => 'success', 'weight' => $best['g'] / 10, 'icon' => 'rocket', 'title' => 'במגמת עלייה: ' . $best['name'],
            'text' => sprintf('+%d%% במכירות השבוע לעומת השבוע שעבר (%s). ודא מלאי מספיק.', round($best['g']), $this->money($best['cur']))];
    }

    private function returnsRate(WidgetContext $ctx): ?array
    {
        if (!$ctx->schema->available('returns', ['date', 'total'])) return null;
        $m = Period::make('30d', null, null, $this->tz($ctx), $ctx->period->now);
        $sales = $this->sum($ctx, 'sales', 'total', $m);
        $ret = $this->sum($ctx, 'returns', 'total', $m);
        if ($sales <= 0 || $ret / $sales < 0.05) return null;
        return ['level' => 'warning', 'weight' => 25, 'icon' => 'undo', 'title' => 'שיעור החזרות גבוה',
            'text' => sprintf('החזרות של %s ב-30 יום = %.1f%% מהמכירות.', $this->money($ret), $ret / $sales * 100),
            'action' => ['label' => 'להחזרות', 'url' => '/return-sale']];
    }

    private function lowMargin(WidgetContext $ctx): ?array
    {
        $w = new KpiProfit();
        if (!$w->isAvailable($ctx)) return null;
        $m = Period::make('30d', null, null, $this->tz($ctx), $ctx->period->now);
        [$profit, $rev, $cogs] = $w->compute($ctx, $m);
        if ($rev < 1000 || $cogs <= 0) return null;
        $margin = $profit / $rev * 100;
        if ($margin >= 10) return null;
        return ['level' => 'warning', 'weight' => 45, 'icon' => 'percent', 'title' => 'שולי רווח נמוכים',
            'text' => sprintf('ב-30 הימים האחרונים שולי הרווח עומדים על %.1f%%. בדוק מחירי מכירה, עלויות והוצאות.', $margin),
            'action' => ['label' => 'רווח והפסד', 'url' => '/report/profit-and-loss']];
    }
}
