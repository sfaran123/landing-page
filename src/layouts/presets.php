<?php

namespace AuraTech\SmartDashboard\Layouts;

/**
 * Default dashboards per business type – the starting point that the editor
 * can then customise. Every existing widget of the old dashboard is kept in
 * the 'grocery'/'retail' presets.
 */
class Presets
{
    public static function for(string $type): array
    {
        return Layout::pack(self::specs($type));
    }

    /**
     * Smart builder: start from the business-type preset, then remove widgets for
     * activities this business does NOT do and add widgets for activities it does.
     * @param array<string,bool> $activities  activity key => on/off (missing = keep preset)
     */
    public static function build(string $type, array $activities): array
    {
        $specs = self::specs($type);
        $catalog = Activities::all();
        $match = fn (array $spec, array $w) => $spec[0] === $w[0] && (($w[3]['side'] ?? null) === null || ($spec[3]['side'] ?? 'customers') === $w[3]['side']);

        foreach ($activities as $act => $on) {
            if (!isset($catalog[$act])) continue;
            foreach ($catalog[$act]['widgets'] as $w) {
                $present = false;
                foreach ($specs as $i => $spec) {
                    if ($match($spec, $w)) {
                        $present = true;
                        if (!$on) unset($specs[$i]);
                    }
                }
                if ($on && !$present) $specs[] = $w;
            }
        }
        return Layout::pack(self::normalize(array_values($specs)), true);
    }

    /** KPI cards grouped in one row right after the quick actions, widths spread evenly. */
    public static function normalize(array $specs): array
    {
        $kpis = array_values(array_filter($specs, fn ($s) => str_starts_with($s[0], 'kpi_') && $s[2] <= 2));
        if (!$kpis) return $specs;
        $rest = array_values(array_filter($specs, fn ($s) => !(str_starts_with($s[0], 'kpi_') && $s[2] <= 2)));
        $n = count($kpis);
        $rows = array_chunk($kpis, $n > 6 ? (int) ceil($n / 2) : 6);
        $kpiOut = [];
        foreach ($rows as $row) {
            $c = count($row);
            $base = intdiv(12, $c); $extra = 12 - $base * $c;
            foreach ($row as $i => $k) { $k[1] = $base + ($i < $extra ? 1 : 0); $k[2] = 2; $kpiOut[] = $k; }
        }
        $head = [];
        if ($rest && $rest[0][0] === 'quick_actions') $head[] = array_shift($rest);
        return array_merge($head, $kpiOut, $rest);
    }

    /** Raw [key, w, h, options?, title?] rows for a business type. */
    public static function specs(string $type): array
    {
        return match ($type) {
            'retail' => [
                ['quick_actions', 12, 2],
                ['kpi_revenue', 3, 2], ['kpi_profit', 3, 2], ['kpi_orders', 3, 2], ['kpi_avg_ticket', 3, 2],
                ['revenue_trend', 8, 6], ['smart_insights', 4, 6],
                ['top_products', 4, 6, ['metric' => 'qty', 'period' => 'month'], 'מוכר ביותר החודש'],
                ['top_products', 4, 6, ['metric' => 'revenue', 'period' => 'year'], 'מוכר ביותר השנה (הכנסה)'],
                ['low_stock', 4, 6],
                ['sales_target', 4, 4], ['kpi_customer_debt', 4, 2], ['kpi_supplier_debt', 4, 2],
                ['sales_heatmap', 6, 5], ['employee_sales', 6, 5],
                ['payment_methods', 4, 5], ['category_sales', 4, 5], ['period_mix', 4, 5],
                ['revenue_compare', 12, 6],
                ['recent_transactions', 6, 6], ['cash_flow', 6, 6],
            ],
            'restaurant' => [
                ['quick_actions', 12, 2, ['hide' => 'הוסף ספק,תשלום לספק']],
                ['kpi_revenue', 3, 2, ['period' => 'today']], ['kpi_orders', 3, 2, ['period' => 'today']], ['kpi_avg_ticket', 3, 2, ['period' => 'today']], ['kpi_profit', 3, 2],
                ['open_tables', 8, 5], ['smart_insights', 4, 5],
                ['sales_heatmap', 6, 5], ['revenue_compare', 6, 5],
                ['top_products', 4, 6, ['metric' => 'qty', 'period' => 'today'], 'הכי מוזמן היום'],
                ['category_sales', 4, 6], ['payment_methods', 4, 6],
                ['sales_target', 4, 4], ['employee_sales', 4, 4], ['expiring_products', 4, 4],
                ['low_stock', 6, 5], ['expense_breakdown', 6, 5],
                ['revenue_trend', 12, 5],
            ],
            'wholesale' => [
                ['quick_actions', 12, 2],
                ['kpi_revenue', 3, 2], ['kpi_customer_debt', 3, 2], ['kpi_supplier_debt', 3, 2], ['kpi_profit', 3, 2],
                ['revenue_trend', 8, 6], ['smart_insights', 4, 6],
                ['debt_aging', 6, 6, ['side' => 'customers']], ['debt_aging', 6, 6, ['side' => 'suppliers']],
                ['open_orders', 6, 5], ['deliveries', 6, 5],
                ['top_customers', 4, 6], ['top_products', 4, 6, ['metric' => 'revenue']], ['cheques_due', 4, 6],
                ['cash_flow', 6, 5], ['quotations_pipeline', 6, 5],
                ['low_stock', 6, 6], ['recent_transactions', 6, 6],
            ],
            'ecommerce' => [
                ['quick_actions', 12, 2],
                ['kpi_revenue', 3, 2], ['kpi_orders', 3, 2], ['kpi_avg_ticket', 3, 2], ['kpi_returns', 3, 2],
                ['revenue_trend', 8, 6], ['smart_insights', 4, 6],
                ['deliveries', 6, 5], ['open_orders', 6, 5],
                ['top_products', 4, 6, ['metric' => 'revenue']], ['category_sales', 4, 6], ['payment_methods', 4, 6],
                ['top_customers', 6, 6], ['low_stock', 6, 6],
                ['sales_heatmap', 6, 5], ['sales_target', 6, 5],
            ],
            'services' => [
                ['quick_actions', 12, 2, ['hide' => 'קופה,הוסף משלוח']],
                ['kpi_revenue', 3, 2], ['kpi_profit', 3, 2], ['kpi_customer_debt', 3, 2], ['kpi_expenses', 3, 2],
                ['revenue_trend', 8, 6], ['smart_insights', 4, 6],
                ['quotations_pipeline', 6, 5], ['open_orders', 6, 5],
                ['debt_aging', 6, 6, ['side' => 'customers']], ['top_customers', 6, 6],
                ['expense_breakdown', 6, 5], ['cash_flow', 6, 5],
                ['sales_target', 4, 4], ['cheques_due', 4, 4], ['kpi_supplier_debt', 4, 2],
                ['recent_transactions', 12, 6],
            ],
            // grocery / nuts & dried fruit shop (default – matches today's dashboard + more)
            default => [
                ['quick_actions', 12, 2],
                ['kpi_revenue', 3, 2], ['kpi_profit', 3, 2], ['kpi_customer_debt', 3, 2], ['kpi_supplier_debt', 3, 2],
                ['revenue_trend', 8, 6], ['smart_insights', 4, 6],
                ['top_products', 4, 6, ['metric' => 'qty', 'period' => 'month'], 'מוכר ביותר החודש'],
                ['top_products', 4, 6, ['metric' => 'qty', 'period' => 'year'], 'מוכר ביותר השנה (כמות)'],
                ['top_products', 4, 6, ['metric' => 'revenue', 'period' => 'year'], 'מוכר ביותר השנה (הכנסה)'],
                ['low_stock', 8, 6], ['sales_target', 4, 6],
                ['cash_flow', 8, 5], ['period_mix', 4, 5],
                ['revenue_compare', 12, 6],
                ['recent_transactions', 6, 6], ['debt_aging', 6, 6, ['side' => 'suppliers']],
                ['payment_methods', 4, 5], ['sales_heatmap', 4, 5], ['expiring_products', 4, 5],
            ],
        };
    }

    public static function types(): array
    {
        return ['retail', 'grocery', 'restaurant', 'wholesale', 'ecommerce', 'services'];
    }
}
