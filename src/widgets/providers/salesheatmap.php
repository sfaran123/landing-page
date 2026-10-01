<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Support\Period;
use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

/** When do customers buy? weekday × hour – for staffing and opening hours. */
class SalesHeatmap extends Widget
{
    public static function key(): string { return 'sales_heatmap'; }

    public function meta(): array
    {
        return [
            'title' => 'שעות עומס', 'description' => 'מפת חום של מכירות לפי יום בשבוע ושעה – לתכנון משמרות ושעות פעילות.',
            'category' => 'charts', 'type' => 'heatmap', 'icon' => 'grid', 'size' => ['w' => 6, 'h' => 5], 'min' => ['w' => 4, 'h' => 4],
            'uses_period' => false, 'business_types' => ['restaurant', 'retail', 'grocery'],
            'options' => [
                'weeks' => ['type' => 'select', 'label' => 'שבועות אחרונים', 'default' => 8, 'choices' => [4 => '4', 8 => '8', 12 => '12']],
                'metric' => ['type' => 'select', 'label' => 'מדד', 'default' => 'count', 'choices' => ['count' => 'מספר עסקאות', 'revenue' => 'הכנסות']],
            ],
        ];
    }

    public function requires(): array { return ['sales' => ['date', 'total']]; }

    public function data(WidgetContext $ctx): array
    {
        $o = $this->opts($ctx);
        $s = $ctx->schema;
        $now = $ctx->period->now;
        $p = new Period($now->setTime(0, 0)->modify('-' . ((int) $o['weeks'] * 7) . ' days'), $now, 'custom', $now);
        [$w, $b] = $this->where($ctx, 'sales', 'x', $p);
        $d = $s->c('sales', 'date', 'x');
        $val = $o['metric'] === 'revenue' ? 'COALESCE(SUM(' . $s->c('sales', 'total', 'x') . '),0)' : 'COUNT(*)';
        $rows = $ctx->db->select("SELECT {$ctx->sql->weekday($d)} AS wd, {$ctx->sql->hour($d)} AS h, $val AS v FROM {$s->t('sales')} x WHERE $w GROUP BY {$ctx->sql->weekday($d)}, {$ctx->sql->hour($d)}", $b);
        $grid = [];
        $minH = 23; $maxH = 0;
        foreach ($rows as $r) {
            $grid[(int) $r['wd']][(int) $r['h']] = round((float) $r['v'], 2);
            $minH = min($minH, (int) $r['h']); $maxH = max($maxH, (int) $r['h']);
        }
        if (!$rows) { $minH = 8; $maxH = 20; }
        $days = ['ראשון', 'שני', 'שלישי', 'רביעי', 'חמישי', 'שישי', 'שבת'];
        $matrix = [];
        $peak = ['v' => 0, 'day' => null, 'hour' => null];
        foreach ($days as $i => $name) {
            $row = [];
            for ($h = $minH; $h <= $maxH; $h++) {
                $v = $grid[$i][$h] ?? 0;
                $row[] = $v;
                if ($v > $peak['v']) $peak = ['v' => $v, 'day' => $name, 'hour' => $h];
            }
            $matrix[] = ['name' => $name, 'data' => $row];
        }
        return ['hours' => range($minH, $maxH), 'rows' => $matrix, 'metric' => $o['metric'], 'peak' => $peak,
            'format' => $o['metric'] === 'revenue' ? 'currency' : 'number'];
    }
}
