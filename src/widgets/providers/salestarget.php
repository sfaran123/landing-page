<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Support\Period;
use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

/**
 * Targets with forecasts. If no target is set in the editor, an automatic
 * target (average of the last 3 full months) is used and flagged as such.
 */
class SalesTarget extends Widget
{
    public static function key(): string { return 'sales_target'; }

    public function meta(): array
    {
        return [
            'title' => 'יעדי מכירות וצפי', 'description' => 'התקדמות מול יעד יומי וחודשי, צפי לסוף היום/החודש וכמה נדרש ליום כדי לעמוד ביעד.',
            'category' => 'kpi', 'type' => 'progress', 'icon' => 'target', 'size' => ['w' => 4, 'h' => 4], 'min' => ['w' => 3, 'h' => 3],
            'uses_period' => false, 'link' => '/report/daily-sale-objective',
        ];
    }

    public function requires(): array { return ['sales' => ['date', 'total']]; }

    public function data(WidgetContext $ctx): array
    {
        $tz = $ctx->period->from->getTimezone()->getName();
        $now = $ctx->period->now;
        $today = Period::make('today', null, null, $tz, $now);
        $month = Period::make('month', null, null, $tz, $now);

        $monthTarget = (float) $ctx->setting('monthly_target', 0);
        $auto = false;
        if ($monthTarget <= 0) {
            $auto = true;
            $f = $now->modify('first day of this month')->setTime(0, 0);
            $monthTarget = $this->sum($ctx, 'sales', 'total', new Period($f->modify('-3 months'), $f, 'custom', $now)) / 3;
        }
        $daysInMonth = (int) $now->format('t');
        $dayTarget = (float) $ctx->setting('daily_target', 0) ?: $monthTarget / $daysInMonth;

        $todayActual = $this->sum($ctx, 'sales', 'total', $today);
        $monthActual = $this->sum($ctx, 'sales', 'total', $month);

        $share = $this->shareOfDayDone($ctx, $now);
        $todayForecast = $share > 0.05 ? $todayActual / $share : null;
        $monthForecast = $month->elapsedRatio() > 0.02 ? $monthActual / $month->elapsedRatio() : null;
        $daysLeft = max(1, $daysInMonth - (int) $now->format('j') + 1);

        return [
            'auto' => $auto,
            'items' => [
                ['label' => 'היום', 'actual' => round($todayActual, 2), 'target' => round($dayTarget, 2),
                 'pct' => $dayTarget > 0 ? round($todayActual / $dayTarget * 100, 1) : 0,
                 'forecast' => $todayForecast === null ? null : round($todayForecast, 2)],
                ['label' => 'החודש', 'actual' => round($monthActual, 2), 'target' => round($monthTarget, 2),
                 'pct' => $monthTarget > 0 ? round($monthActual / $monthTarget * 100, 1) : 0,
                 'forecast' => $monthForecast === null ? null : round($monthForecast, 2),
                 'needed_per_day' => round(max(0, $monthTarget - $monthActual) / $daysLeft, 2)],
            ],
        ];
    }

    /** Typical share of a day's sales already done by this time (last 4 weeks, same weekday). */
    private function shareOfDayDone(WidgetContext $ctx, \DateTimeImmutable $now): float
    {
        $s = $ctx->schema;
        $d = $s->c('sales', 'date', 'x');
        [$sc, $sb] = $s->scope('sales', 'x', true);
        $from = $now->setTime(0, 0)->modify('-28 days');
        $to = $now->setTime(0, 0);
        $wd = (int) $now->format('w');
        $h = (int) $now->format('G');
        $t = $s->c('sales', 'total', 'x');
        $r = $ctx->db->select(
            "SELECT COALESCE(SUM($t),0) AS total, COALESCE(SUM(CASE WHEN {$ctx->sql->hour($d)} < ? THEN $t ELSE 0 END),0) AS done
             FROM {$s->t('sales')} x WHERE $d >= ? AND $d < ? AND {$ctx->sql->weekday($d)} = ? $sc",
            array_merge([$h, $from->format('Y-m-d H:i:s'), $to->format('Y-m-d H:i:s'), $wd], $sb))[0] ?? ['total' => 0, 'done' => 0];
        $partial = (int) $now->format('i') / 60;
        if ((float) $r['total'] <= 0) return ($h + $partial) / 24;
        return min(1.0, (float) $r['done'] / (float) $r['total'] + 0.0);
    }
}
