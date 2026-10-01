<?php

namespace AuraTech\SmartDashboard\Widgets;

use AuraTech\SmartDashboard\Support\Period;

/** Shared, parameterised query helpers used by the widget providers. */
trait Queries
{
    /** The widget's period: its own "period" option overrides the global filter. */
    protected function periodFor(WidgetContext $ctx): Period
    {
        $p = $ctx->options['period'] ?? 'inherit';
        if (!$p || $p === 'inherit' || $p === $ctx->period->preset) {
            return $ctx->period;
        }
        return Period::make($p, null, null, $ctx->period->from->getTimezone()->getName(), $ctx->period->now);
    }

    /** " AND a.user_id = ? AND a.warehouse_id = ?" for the global top-bar filters. */
    protected function filters(WidgetContext $ctx, string $entity, string $alias): array
    {
        $sql = '';
        $b = [];
        if ($ctx->userId && $ctx->schema->hasColumn($entity, 'user_id')) {
            $sql .= ' AND ' . $ctx->schema->c($entity, 'user_id', $alias) . ' = ?';
            $b[] = $ctx->userId;
        }
        if ($ctx->warehouseId && $ctx->schema->hasColumn($entity, 'warehouse_id')) {
            $sql .= ' AND ' . $ctx->schema->c($entity, 'warehouse_id', $alias) . ' = ?';
            $b[] = $ctx->warehouseId;
        }
        return [$sql, $b];
    }

    /** WHERE clause for entity within period, incl. configured scope + global filters. */
    protected function where(WidgetContext $ctx, string $entity, string $alias, ?Period $p = null, bool $excludeDrafts = true): array
    {
        $s = $ctx->schema;
        $sql = '1=1';
        $b = [];
        if ($p) {
            $d = $s->c($entity, 'date', $alias);
            $sql .= " AND $d >= ? AND $d < ?";
            $b[] = $p->fromSql();
            $b[] = $p->toSql();
        }
        [$sc, $sb] = $s->scope($entity, $alias, $excludeDrafts);
        [$fc, $fb] = $this->filters($ctx, $entity, $alias);
        return [$sql . $sc . $fc, array_merge($b, $sb, $fb)];
    }

    protected function sum(WidgetContext $ctx, string $entity, string $amountKey, ?Period $p): float
    {
        $s = $ctx->schema;
        [$w, $b] = $this->where($ctx, $entity, 'x', $p);
        $col = $s->c($entity, $amountKey, 'x');
        $row = $ctx->db->select("SELECT COALESCE(SUM($col),0) AS v FROM {$s->t($entity)} x WHERE $w", $b);
        return round((float) ($row[0]['v'] ?? 0), 2);
    }

    protected function countRows(WidgetContext $ctx, string $entity, ?Period $p): int
    {
        $s = $ctx->schema;
        [$w, $b] = $this->where($ctx, $entity, 'x', $p);
        $row = $ctx->db->select("SELECT COUNT(*) AS v FROM {$s->t($entity)} x WHERE $w", $b);
        return (int) ($row[0]['v'] ?? 0);
    }

    /** Zero-filled series: bucket => sum(amount). */
    protected function series(WidgetContext $ctx, string $entity, string $amountKey, Period $p, ?string $granularity = null, string $agg = 'SUM'): array
    {
        $s = $ctx->schema;
        $g = $granularity ?? $p->granularity();
        [$w, $b] = $this->where($ctx, $entity, 'x', $p);
        $bucket = $ctx->sql->bucket($s->c($entity, 'date', 'x'), $g);
        $val = $agg === 'COUNT' ? 'COUNT(*)' : 'COALESCE(SUM(' . $s->c($entity, $amountKey, 'x') . '),0)';
        $rows = $ctx->db->select("SELECT $bucket AS k, $val AS v FROM {$s->t($entity)} x WHERE $w GROUP BY $bucket", $b);
        $out = array_fill_keys($p->buckets($g), 0.0);
        foreach ($rows as $r) {
            $k = $g === 'hour' ? (string) (int) $r['k'] : (string) $r['k'];
            if (array_key_exists($k, $out)) {
                $out[$k] = round((float) $r['v'], 2);
            }
        }
        return $out;
    }

    /** Open balance (total − paid) across all time, e.g. customer debt. */
    protected function openBalance(WidgetContext $ctx, string $entity): float
    {
        $s = $ctx->schema;
        [$w, $b] = $this->where($ctx, $entity, 'x', null);
        $t = $s->c($entity, 'total', 'x');
        $p = $s->c($entity, 'paid', 'x');
        $row = $ctx->db->select("SELECT COALESCE(SUM($t - COALESCE($p,0)),0) AS v FROM {$s->t($entity)} x WHERE $w AND $t - COALESCE($p,0) > 0.009", $b);
        return round((float) ($row[0]['v'] ?? 0), 2);
    }

    protected function delta(float $cur, float $prev): ?float
    {
        if (abs($prev) < 0.005) {
            return $cur > 0 ? null : 0.0;   // null = "new" (no base to compare)
        }
        return round(($cur - $prev) / abs($prev) * 100, 1);
    }

    /** Standard KPI payload. */
    protected function kpi(float $value, ?float $previous, array $extra = []): array
    {
        return array_merge([
            'value' => round($value, 2),
            'previous' => $previous === null ? null : round($previous, 2),
            'delta' => $previous === null ? null : $this->delta($value, $previous),
            'format' => 'currency',
            'good' => 'up',
        ], $extra);
    }

    /** Unix timestamp of a DB date/datetime string interpreted in the business timezone (not PHP's default). */
    protected function ts(WidgetContext $ctx, string $value): int
    {
        return (new \DateTimeImmutable($value, $ctx->period->now->getTimezone()))->getTimestamp();
    }

    protected function methodLabel(WidgetContext $ctx, ?string $m): string
    {
        $m = (string) $m;
        return $ctx->config['payment_methods'][$m] ?? ($m !== '' ? $m : 'אחר');
    }
}
