<?php

namespace AuraTech\SmartDashboard\Support;

use PDO;

/** Framework-free connection – used by the test harness and the demo builder. */
class PdoConnection implements Connection
{
    public function __construct(private PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
    }

    public function pdo(): PDO
    {
        return $this->pdo;
    }

    public function select(string $sql, array $bindings = []): array
    {
        $st = $this->pdo->prepare($sql);
        $st->execute(array_values($bindings));
        return $st->fetchAll();
    }

    public function statement(string $sql, array $bindings = []): bool
    {
        return $this->pdo->prepare($sql)->execute(array_values($bindings));
    }

    public function hasTable(string $table): bool
    {
        if ($this->driver() === 'sqlite') {
            return (bool) $this->select("SELECT name FROM sqlite_master WHERE type='table' AND name = ?", [$table]);
        }
        return (bool) $this->select('SHOW TABLES LIKE ?', [$table]);
    }

    public function hasColumn(string $table, string $column): bool
    {
        if ($this->driver() === 'sqlite') {
            foreach ($this->select("PRAGMA table_info(\"$table\")") as $c) {
                if ($c['name'] === $column) return true;
            }
            return false;
        }
        return (bool) $this->select("SHOW COLUMNS FROM `$table` LIKE ?", [$column]);
    }

    public function driver(): string
    {
        return $this->pdo->getAttribute(PDO::ATTR_DRIVER_NAME);
    }
}
