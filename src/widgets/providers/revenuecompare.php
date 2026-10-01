<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Support\Period;
use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;
use DateTimeImmutable;

/** Preserves the existing "גרף הכנסות – השוואה מרובה" (hourly / daily / monthly). */
class RevenueCompare extends Widget
{
    public static function key(): string { return 'revenue_compare'; }

    public function meta(): array
    {
        return [
            'title' => 'השוואת הכנסות מרובה', 'description' => 'השוואת ימים (שעתי), חודשים (יומי) או שנים (חודשי) על אותו גרף.',
            'category' => 'charts', 'type' => 'compare', 'icon' => 'compare', 'size' => ['w' => 12, 'h' => 6], 'min' => ['w' => 6, 'h' => 5],
            'uses_period' => false, 'link' => '/report/revenue-chart',
            'options' => [
                'mode' => ['type' => 'select', 'label' => 'מצב', 'default' => 'hour', 'choices' => ['hour' => 'יומי (שעתי)', 'day' => 'חודשי (יומי)', 'month' => 'שנתי (חודשי)']],
                'dates' => ['type' => 'hidden', 'default' => []],
            ],
        ];
    }

    public function requires(): array { return ['sales' => ['date', 'total']]; }

    public function data(WidgetContext $ctx): array
    {
        $o = $this->opts($ctx);
        $mode = in_array($o['mode'], ['hour', 'day', 'month'], true) ? $o['mode'] : 'hour';
        $now = $ctx->period->now;
        $dates = array_values(array_filter((array) $o['dates'], fn ($d) => is_string($d) && preg_match('/^\d{4}-\d{2}(-\d{2})?$/', $d)));
        if (!$dates) {
            $dates = match ($mode) {
                'hour' => [$now->format('Y-m-d'), $now->modify('-1 day')->format('Y-m-d')],
                'day' => [$now->format('Y-m'), $now->modify('first day of last month')->format('Y-m')],
                'month' => [$now->format('Y'), $now->modify('-1 year')->format('Y')],
            };
        }
        $tz = $now->getTimezone();
        $series = [];
        $cats = [];
        foreach (array_slice($dates, 0, 6) as $d) {
            [$from, $to, $label] = match ($mode) {
                'hour' => (function () use ($d, $tz) { $f = new DateTimeImmutable(substr($d, 0, 10), $tz); return [$f, $f->modify('+1 day'), $f->format('Y-m-d')]; })(),
                'day' => (function () use ($d, $tz) { $f = new DateTimeImmutable(substr($d, 0, 7) . '-01', $tz); return [$f, $f->modify('+1 month'), $f->format('Y-m')]; })(),
                'month' => (function () use ($d, $tz) { $f = new DateTimeImmutable(substr($d, 0, 4) . '-01-01', $tz); return [$f, $f->modify('+1 year'), $f->format('Y')]; })(),
            };
            $p = new Period($from, min($to, $now) > $from ? min($to, $now) : $to, 'custom', $now);
            $g = $mode;
            $raw = $this->series($ctx, 'sales', 'total', $p, $g);
            // align on relative position (hour 0-23 / day 1-31 / month 1-12)
            $aligned = [];
            foreach ($raw as $k => $v) {
                $pos = match ($mode) { 'hour' => (int) $k, 'day' => (int) substr($k, 8, 2), 'month' => (int) substr($k, 5, 2) };
                $aligned[$pos] = $v;
            }
            $max = match ($mode) { 'hour' => 23, 'day' => 31, 'month' => 12 };
            $start = $mode === 'hour' ? 0 : 1;
            $data = [];
            for ($i = $start; $i <= $max; $i++) $data[] = $aligned[$i] ?? 0;
            $cats = range($start, $max);
            $series[] = ['name' => $label, 'data' => $data, 'total' => round(array_sum($data), 2)];
        }
        return ['kind' => 'line', 'mode' => $mode, 'categories' => $cats, 'series' => $series, 'format' => 'currency'];
    }
}
