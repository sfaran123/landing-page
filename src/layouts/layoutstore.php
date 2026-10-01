<?php

namespace AuraTech\SmartDashboard\Layouts;

use AuraTech\SmartDashboard\Support\Connection;

/**
 * Persists layouts & settings in two small tables (see the migration):
 *   sd_layouts  (scope, scope_id, layout JSON)   scope = business_type | role | user
 *   sd_settings (key, value)                    business_type, monthly_target, …
 */
class LayoutStore
{
    public const SCOPES = ['business_type', 'role', 'user'];

    public function __construct(private Connection $db) {}

    public function get(string $scope, string $scopeId): ?array
    {
        $r = $this->db->select('SELECT layout, updated_at, updated_by FROM sd_layouts WHERE scope = ? AND scope_id = ?', [$scope, $scopeId]);
        if (!$r) return null;
        $layout = json_decode($r[0]['layout'], true);
        return is_array($layout) ? $layout + ['updated_at' => $r[0]['updated_at']] : null;
    }

    public function save(string $scope, string $scopeId, array $layout, ?int $by = null): void
    {
        $json = json_encode(Layout::sanitize($layout), JSON_UNESCAPED_UNICODE);
        $now = date('Y-m-d H:i:s');
        if ($this->db->select('SELECT id FROM sd_layouts WHERE scope = ? AND scope_id = ?', [$scope, $scopeId])) {
            $this->db->statement('UPDATE sd_layouts SET layout = ?, updated_by = ?, updated_at = ? WHERE scope = ? AND scope_id = ?', [$json, $by, $now, $scope, $scopeId]);
        } else {
            $this->db->statement('INSERT INTO sd_layouts (scope, scope_id, layout, updated_by, created_at, updated_at) VALUES (?,?,?,?,?,?)', [$scope, $scopeId, $json, $by, $now, $now]);
        }
    }

    public function delete(string $scope, string $scopeId): void
    {
        $this->db->statement('DELETE FROM sd_layouts WHERE scope = ? AND scope_id = ?', [$scope, $scopeId]);
    }

    /** Remove every personal layout so users receive the role/business template. */
    public function deleteScope(string $scope): void
    {
        $this->db->statement('DELETE FROM sd_layouts WHERE scope = ?', [$scope]);
    }

    public function index(): array
    {
        return $this->db->select('SELECT scope, scope_id, updated_at, updated_by FROM sd_layouts ORDER BY scope, scope_id');
    }

    public function settings(): array
    {
        $out = [];
        foreach ($this->db->select('SELECT setting_key AS k, value FROM sd_settings') as $r) {
            $out[$r['k']] = json_decode($r['value'], true);
        }
        return $out;
    }

    public function setSetting(string $key, mixed $value): void
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE);
        $now = date('Y-m-d H:i:s');
        if ($this->db->select('SELECT setting_key FROM sd_settings WHERE setting_key = ?', [$key])) {
            $this->db->statement('UPDATE sd_settings SET value = ?, updated_at = ? WHERE setting_key = ?', [$json, $now, $key]);
        } else {
            $this->db->statement('INSERT INTO sd_settings (setting_key, value, created_at, updated_at) VALUES (?,?,?,?)', [$key, $json, $now, $now]);
        }
    }
}
