<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class Deliveries extends Widget
{
    public static function key(): string { return 'deliveries'; }

    public function meta(): array
    {
        return [
            'title' => 'משלוחים', 'description' => 'סטטוס משלוחים: באריזה, במשלוח, נמסרו – ומשלוחים שמתעכבים.',
            'category' => 'operations', 'type' => 'pipeline', 'icon' => 'truck', 'size' => ['w' => 6, 'h' => 5], 'min' => ['w' => 4, 'h' => 4],
            'business_types' => ['wholesale', 'ecommerce', 'restaurant'], 'link' => '/delivery',
            'options' => ['period' => self::periodOption()],
        ];
    }

    public function requires(): array { return ['deliveries' => ['date', 'status']]; }

    public function data(WidgetContext $ctx): array
    {
        $s = $ctx->schema;
        $p = $this->periodFor($ctx);
        $labels = $s->get('deliveries', 'statuses', []);
        $st = $s->c('deliveries', 'status', 'd');
        $dt = $s->c('deliveries', 'date', 'd');
        $counts = $ctx->db->select("SELECT $st AS st, COUNT(*) AS n FROM {$s->t('deliveries')} d WHERE $dt >= ? AND $dt < ? GROUP BY $st", [$p->fromSql(), $p->toSql()]);
        $map = [];
        foreach ($counts as $r) $map[$r['st']] = (int) $r['n'];
        $stages = [];
        foreach ($labels as $k => $l) $stages[] = ['label' => $l, 'count' => $map[$k] ?? 0, 'final' => $k === array_key_last($labels)];
        $doneKey = array_key_last($labels);
        $ref = $s->hasColumn('deliveries', 'reference') ? $s->c('deliveries', 'reference', 'd') : 'd.id';
        $late = $ctx->db->select("SELECT $ref AS reference, $dt AS date, $st AS st FROM {$s->t('deliveries')} d WHERE $st <> ? AND $dt < ? ORDER BY $dt ASC LIMIT 5",
            [$doneKey, $ctx->period->now->modify('-1 day')->format('Y-m-d H:i:s')]);
        return [
            'stages' => $stages,
            'late' => array_map(fn ($r) => ['reference' => $r['reference'], 'date' => $r['date'], 'status' => $labels[$r['st']] ?? $r['st']], $late),
        ];
    }
}
