<?php

namespace AuraTech\SmartDashboard\Support;

/**
 * Minimal DB contract used by every widget. Keeping widgets on raw,
 * parameterised SQL (instead of Eloquent models) makes them fast, easy to
 * audit, and runnable outside Laravel (see tests/).
 */
interface Connection
{
    /** @return array<int, array<string, mixed>> */
    public function select(string $sql, array $bindings = []): array;

    public function statement(string $sql, array $bindings = []): bool;

    public function hasTable(string $table): bool;

    public function hasColumn(string $table, string $column): bool;

    /** 'mysql' | 'sqlite' | 'pgsql' */
    public function driver(): string;
}
