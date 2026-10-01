<?php

namespace AuraTech\SmartDashboard\Support;

/** Tiny SQL dialect helper so the same widget SQL runs on MySQL/MariaDB, SQLite and Postgres. */
class Sql
{
    public function __construct(private string $driver) {}

    public function driver(): string
    {
        return $this->driver;
    }

    public function date(string $e): string
    {
        return match ($this->driver) {
            'sqlite' => "date($e)",
            'pgsql'  => "to_char($e, 'YYYY-MM-DD')",
            default  => "DATE_FORMAT($e, '%Y-%m-%d')",
        };
    }

    public function yearMonth(string $e): string
    {
        return match ($this->driver) {
            'sqlite' => "strftime('%Y-%m', $e)",
            'pgsql'  => "to_char($e, 'YYYY-MM')",
            default  => "DATE_FORMAT($e, '%Y-%m')",
        };
    }

    public function hour(string $e): string
    {
        return match ($this->driver) {
            'sqlite' => "CAST(strftime('%H', $e) AS INTEGER)",
            'pgsql'  => "EXTRACT(HOUR FROM $e)::int",
            default  => "HOUR($e)",
        };
    }

    /** 0 = Sunday … 6 = Saturday */
    public function weekday(string $e): string
    {
        return match ($this->driver) {
            'sqlite' => "CAST(strftime('%w', $e) AS INTEGER)",
            'pgsql'  => "EXTRACT(DOW FROM $e)::int",
            default  => "(DAYOFWEEK($e) - 1)",
        };
    }

    /** Whole days from $e until the bound '?' parameter (a Y-m-d H:i:s string). */
    public function daysUntilParam(string $e): string
    {
        return match ($this->driver) {
            'sqlite' => "CAST(julianday(?) - julianday($e) AS INTEGER)",
            'pgsql'  => "DATE_PART('day', ?::timestamp - $e)::int",
            default  => "DATEDIFF(?, $e)",
        };
    }

    /** Bucket expression for a chart granularity. */
    public function bucket(string $e, string $granularity): string
    {
        return match ($granularity) {
            'hour'  => $this->hour($e),
            'month' => $this->yearMonth($e),
            default => $this->date($e),
        };
    }
}
