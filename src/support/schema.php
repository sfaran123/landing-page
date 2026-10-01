<?php

namespace AuraTech\SmartDashboard\Support;

use InvalidArgumentException;

/**
 * Resolves logical names ("sales.total") to physical table/column names
 * from config('smart-dashboard.schema'). Widgets never hard-code names.
 */
class Schema
{
    public function __construct(private array $map, private Connection $db) {}

    public function get(string $entity, string $key, mixed $default = null): mixed
    {
        return $this->map[$entity][$key] ?? $default;
    }

    public function has(string $entity): bool
    {
        return isset($this->map[$entity]['table']);
    }

    /** Physical table name (validated identifier). */
    public function t(string $entity): string
    {
        $t = $this->map[$entity]['table'] ?? throw new InvalidArgumentException("Unknown entity [$entity]");
        return $this->ident($t);
    }

    /** Physical column, optionally prefixed by alias: c('sales','total','s') => s.grand_total */
    public function c(string $entity, string $key, ?string $alias = null): string
    {
        $col = $this->map[$entity][$key] ?? throw new InvalidArgumentException("Unknown column [$entity.$key]");
        $col = $this->ident($col);
        return $alias ? "$alias.$col" : $col;
    }

    /**
     * Fixed conditions configured under 'where' (e.g. transaction_type = purchase)
     * plus exclusion of draft/cancelled statuses when asked.
     * @return array{0:string,1:array}
     */
    public function scope(string $entity, ?string $alias = null, bool $excludeDrafts = true): array
    {
        $sql = '';
        $b = [];
        $p = $alias ? "$alias." : '';
        foreach ($this->map[$entity]['where'] ?? [] as $col => $val) {
            $col = $this->ident($col);
            if (is_array($val)) {
                $sql .= " AND {$p}{$col} IN (" . implode(',', array_fill(0, count($val), '?')) . ')';
                array_push($b, ...array_values($val));
            } elseif ($val === null) {
                $sql .= " AND {$p}{$col} IS NULL";
            } else {
                $sql .= " AND {$p}{$col} = ?";
                $b[] = $val;
            }
        }
        if ($excludeDrafts && isset($this->map[$entity]['status'])) {
            $skip = array_merge($this->map[$entity]['draft_status'] ?? [], $this->map[$entity]['cancelled_status'] ?? []);
            if ($skip) {
                $col = $p . $this->ident($this->map[$entity]['status']);
                $sql .= " AND ($col IS NULL OR $col NOT IN (" . implode(',', array_fill(0, count($skip), '?')) . '))';
                array_push($b, ...$skip);
            }
        }
        return [$sql, $b];
    }

    /** True when the table and every listed column exist in the database. */
    public function available(string $entity, array $keys = []): bool
    {
        if (!$this->has($entity) || !$this->db->hasTable($this->t($entity))) {
            return false;
        }
        foreach ($keys as $k) {
            if (!isset($this->map[$entity][$k]) || !$this->db->hasColumn($this->t($entity), $this->map[$entity][$k])) {
                return false;
            }
        }
        return true;
    }

    public function hasColumn(string $entity, string $key): bool
    {
        return isset($this->map[$entity][$key]) && $this->db->hasColumn($this->t($entity), $this->map[$entity][$key]);
    }

    public function all(): array
    {
        return $this->map;
    }

    private function ident(string $name): string
    {
        if (!preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $name)) {
            throw new InvalidArgumentException("Invalid identifier [$name] in smart-dashboard schema config");
        }
        return $name;
    }
}
