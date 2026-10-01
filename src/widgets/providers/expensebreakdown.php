<?php

namespace AuraTech\SmartDashboard\Widgets\Providers;

use AuraTech\SmartDashboard\Widgets\Widget;
use AuraTech\SmartDashboard\Widgets\WidgetContext;

class ExpenseBreakdown extends Widget
{
    public static function key(): string { return 'expense_breakdown'; }

    public function meta(): array
    {
        return [
            'title' => 'פילוח הוצאות', 'description' => 'לאן הולך הכסף – הוצאות לפי קטגוריה.',
            'category' => 'finance', 'type' => 'chart', 'icon' => 'wallet', 'size' => ['w' => 4, 'h' => 5], 'min' => ['w' => 3, 'h' => 4],
            'link' => '/expenses', 'options' => ['period' => self::periodOption()],
        ];
    }

    public function requires(): array { return ['expenses' => ['date', 'amount', 'category_id'], 'expense_categories' => ['name']]; }

    public function data(WidgetContext $ctx): array
    {
        $s = $ctx->schema;
        $p = $this->periodFor($ctx);
        [$w, $b] = $this->where($ctx, 'expenses', 'e', $p);
        $rows = $ctx->db->select(
            "SELECT COALESCE(c.{$s->c('expense_categories','name')}, 'אחר') AS name, COALESCE(SUM({$s->c('expenses','amount','e')}),0) AS v
             FROM {$s->t('expenses')} e LEFT JOIN {$s->t('expense_categories')} c ON c.id = {$s->c('expenses','category_id','e')}
             WHERE $w GROUP BY c.{$s->c('expense_categories','name')} ORDER BY v DESC LIMIT 8", $b);
        return ['kind' => 'donut', 'format' => 'currency', 'center' => 'סה״כ',
            'labels' => array_column($rows, 'name'), 'values' => array_map(fn ($r) => round((float) $r['v'], 2), $rows)];
    }
}
