<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class QuotationsPipeline extends Widget
{
    public static function key(): string { return 'quotations_pipeline'; }

    public function meta(): array
    {
        return [
            'title' => 'הצעות מחיר', 'description' => 'הצעות מחיר בתקופה לפי סטטוס, והאחרונות שממתינות למענה.',
            'category' => 'customers', 'type' => 'pipeline', 'icon' => 'doc', 'size' => ['w' => 4, 'h' => 5], 'min' => ['w' => 3, 'h' => 4],
            'business_types' => ['wholesale', 'services'], 'link' => '/quotations', 'options' => ['period' => self::periodOption()],
        ];
    }

    public function requires(): array { return ['quotations' => ['date', 'total']]; }

    public function data(WidgetContext $ctx): array
    {
        $s = $ctx->schema;
        $p = $this->periodFor($ctx);
        [$w, $b] = $this->where($ctx, 'quotations', 'q', $p, false);
        $labels = $s->get('quotations', 'statuses', []);
        $stages = [];
        if ($s->hasColumn('quotations', 'status')) {
            $st = $s->c('quotations', 'status', 'q');
            foreach ($ctx->db->select("SELECT $st AS st, COUNT(*) AS n, COALESCE(SUM({$s->c('quotations','total','q')}),0) AS v FROM {$s->t('quotations')} q WHERE $w GROUP BY $st", $b) as $r) {
                $stages[] = ['label' => $labels[$r['st']] ?? (string) $r['st'], 'count' => (int) $r['n'], 'value' => round((float) $r['v'], 2)];
            }
        } else {
            $n = $ctx->db->select("SELECT COUNT(*) AS n, COALESCE(SUM({$s->c('quotations','total','q')}),0) AS v FROM {$s->t('quotations')} q WHERE $w", $b)[0];
            $stages[] = ['label' => 'הצעות', 'count' => (int) $n['n'], 'value' => round((float) $n['v'], 2)];
        }
        return ['stages' => $stages, 'late' => []];
    }
}
