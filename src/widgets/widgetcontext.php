<?php

namespace AuraTech\SmartDashboard\Widgets;

use AuraTech\SmartDashboard\Support\Connection;
use AuraTech\SmartDashboard\Support\Period;
use AuraTech\SmartDashboard\Support\Schema;
use AuraTech\SmartDashboard\Support\Sql;

/** Everything a widget needs to compute its data – no framework access required. */
class WidgetContext
{
    public readonly Sql $sql;

    public function __construct(
        public readonly Connection $db,
        public readonly Schema $schema,
        public readonly Period $period,
        public readonly ?int $userId = null,        // "all users" filter from the top bar
        public readonly ?int $warehouseId = null,
        public readonly array $options = [],        // per-widget instance options from the layout
        public readonly array $settings = [],       // tenant settings (targets, business type…)
        public readonly array $config = [],         // module config (payment_methods, quick_actions…)
    ) {
        $this->sql = new Sql($db->driver());
    }

    public function option(string $key, mixed $default = null): mixed
    {
        return $this->options[$key] ?? $default;
    }

    public function setting(string $key, mixed $default = null): mixed
    {
        return $this->settings[$key] ?? $default;
    }

    /** A copy with a different period (used for option "period" overrides and comparisons). */
    public function withPeriod(Period $p): self
    {
        return new self($this->db, $this->schema, $p, $this->userId, $this->warehouseId, $this->options, $this->settings, $this->config);
    }

    public function withOptions(array $options): self
    {
        return new self($this->db, $this->schema, $this->period, $this->userId, $this->warehouseId, $options, $this->settings, $this->config);
    }
}
