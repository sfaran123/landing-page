<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class OpenTables extends Widget
{
    public static function key(): string { return 'open_tables'; }

    public function meta(): array
    {
        return [
            'title' => 'שולחנות פתוחים', 'description' => 'שולחנות עם חשבון פתוח, סכום ומשך ישיבה.',
            'category' => 'operations', 'type' => 'tiles', 'icon' => 'table', 'size' => ['w' => 6, 'h' => 5], 'min' => ['w' => 4, 'h' => 4],
            'uses_period' => false, 'business_types' => ['restaurant'], 'link' => '/tables',
        ];
    }

    public function requires(): array { return ['sales' => ['date', 'total', 'status', 'table_id'], 'tables' => ['name']]; }

    public function data(WidgetContext $ctx): array
    {
        $s = $ctx->schema;
        $open = array_merge($s->get('sales', 'pending_status', []), $s->get('sales', 'draft_status', []));
        if (!$open) return ['tiles' => []];
        [$sc, $sb] = $s->scope('sales', 'd', false);
        $st = $s->c('sales', 'status', 'd');
        $rows = $ctx->db->select(
            "SELECT t.{$s->c('tables','name')} AS name, COALESCE(SUM({$s->c('sales','total','d')}),0) AS amount, MIN({$s->c('sales','date','d')}) AS since
             FROM {$s->t('sales')} d JOIN {$s->t('tables')} t ON t.id = {$s->c('sales','table_id','d')}
             WHERE $st IN (" . implode(',', array_fill(0, count($open), '?')) . ") AND {$s->c('sales','date','d')} >= ? $sc
             GROUP BY t.id, t.{$s->c('tables','name')} ORDER BY since ASC",
            array_merge($open, [$ctx->period->now->modify('-18 hours')->format('Y-m-d H:i:s')], $sb));
        $now = $ctx->period->now->getTimestamp();
        $tz = $ctx->period->now->getTimezone();
        return ['tiles' => array_map(function ($r) use ($now, $tz) {
            // stored timestamps are local (business) time – parse them in that zone, not PHP's default
            $since = new \DateTimeImmutable((string) $r['since'], $tz);
            $min = max(0, (int) floor(($now - $since->getTimestamp()) / 60));
            return ['name' => $r['name'], 'amount' => round((float) $r['amount'], 2), 'minutes' => $min, 'tone' => $min > 90 ? 'warning' : 'success'];
        }, $rows)];
    }
}
