<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Support\Period;
use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

/** Revenue − cost of goods sold (qty × product cost) − expenses. */
class KpiProfit extends Widget
{
    public static function key(): string { return 'kpi_profit'; }

    public function meta(): array
    {
        return [
            'title' => 'רווח נקי משוער', 'description' => 'הכנסות פחות עלות המוצרים שנמכרו ופחות הוצאות, כולל שולי רווח.',
            'category' => 'kpi', 'type' => 'kpi', 'icon' => 'trend', 'size' => ['w' => 3, 'h' => 2], 'min' => ['w' => 2, 'h' => 2],
            'link' => '/report/profit-and-loss', 'permission' => 'revenue_profit_summary',
            'options' => ['period' => self::periodOption()],
        ];
    }

    public function requires(): array
    {
        return ['sales' => ['date', 'total'], 'product_sales' => ['sale_id', 'product_id', 'qty'], 'products' => ['cost']];
    }

    public function compute(WidgetContext $ctx, Period $p): array
    {
        $rev = $this->sum($ctx, 'sales', 'total', $p);
        $cogs = $this->cogs($ctx, $p);
        $exp = $ctx->schema->available('expenses', ['date', 'amount']) ? $this->sum($ctx, 'expenses', 'amount', $p) : 0.0;
        return [$rev - $cogs - $exp, $rev, $cogs, $exp];
    }

    public function cogs(WidgetContext $ctx, Period $p): float
    {
        $s = $ctx->schema;
        [$w, $b] = $this->where($ctx, 'sales', 's', $p);
        $sql = "SELECT COALESCE(SUM({$s->c('product_sales','qty','ps')} * COALESCE({$s->c('products','cost','pr')},0)),0) AS v
                FROM {$s->t('product_sales')} ps
                JOIN {$s->t('sales')} s ON s.id = {$s->c('product_sales','sale_id','ps')}
                LEFT JOIN {$s->t('products')} pr ON pr.id = {$s->c('product_sales','product_id','ps')}
                WHERE $w";
        return round((float) ($ctx->db->select($sql, $b)[0]['v'] ?? 0), 2);
    }

    public function data(WidgetContext $ctx): array
    {
        $p = $this->periodFor($ctx);
        [$profit, $rev, $cogs, $exp] = $this->compute($ctx, $p);
        [$prevProfit] = $this->compute($ctx, $p->previous());
        $margin = $rev > 0 ? round($profit / $rev * 100, 1) : 0;
        return $this->kpi($profit, $prevProfit, [
            'sub' => "שולי רווח {$margin}%",
            'breakdown' => ['הכנסות' => $rev, 'עלות מכר' => $cogs, 'הוצאות' => $exp],
        ]);
    }
}
