<?php

namespace AuraTech\SmartDashboard\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema as SchemaFacade;

class LaravelConnection implements Connection
{
    private array $tableCache = [];
    private array $columnCache = [];

    public function __construct(private ?string $name = null) {}

    private function conn()
    {
        return DB::connection($this->name);
    }

    public function select(string $sql, array $bindings = []): array
    {
        return array_map(fn ($row) => (array) $row, $this->conn()->select($sql, $bindings));
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        return $this->conn()->statement($sql, $bindings);
    }

    public function hasTable(string $table): bool
    {
        return $this->tableCache[$table] ??= SchemaFacade::connection($this->name)->hasTable($table);
    }

    public function hasColumn(string $table, string $column): bool
    {
        return $this->columnCache["$table.$column"] ??= SchemaFacade::connection($this->name)->hasColumn($table, $column);
    }

    public function driver(): string
    {
        return $this->conn()->getDriverName();
    }
}
