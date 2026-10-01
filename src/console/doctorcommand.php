<?php

namespace AuraTech\SmartDashboard\Console;

use AuraTech\SmartDashboard\DashboardService;
use AuraTech\SmartDashboard\Support\LaravelConnection;
use AuraTech\SmartDashboard\Support\Schema;
use Illuminate\Console\Command;

/** php artisan smart-dashboard:doctor — checks the DB mapping and runs every widget once. */
class DoctorCommand extends Command
{
    protected $signature = 'smart-dashboard:doctor {--period=month}';
    protected $description = 'Check the Smart Dashboard schema mapping against this database and time every widget';

    public function handle(DashboardService $sd): int
    {
        $cfg = config('smart-dashboard');
        $schema = new Schema($cfg['schema'], new LaravelConnection($cfg['connection'] ?? null));

        $this->info('1. Database mapping');
        $rows = [];
        foreach ($cfg['schema'] as $entity => $map) {
            $missing = [];
            $hasTable = $schema->available($entity);
            if ($hasTable) {
                foreach ($map as $k => $col) {
                    if (in_array($k, ['table', 'where', 'statuses', 'draft_status', 'pending_status', 'cancelled_status', 'open_status'], true) || !is_string($col)) continue;
                    if (!$schema->hasColumn($entity, $k)) $missing[] = "$k→$col";
                }
            }
            $rows[] = [$entity, $map['table'] ?? '', $hasTable ? ($missing ? '<comment>partial</comment>' : '<info>ok</info>') : '<error>missing table</error>', implode(', ', $missing)];
        }
        $this->table(['entity', 'table', 'status', 'missing columns'], $rows);

        $this->info('2. Widgets (' . $this->option('period') . ')');
        $user = ['id' => null, 'role_id' => ($cfg['editor_role_ids'][0] ?? 1), 'name' => 'doctor'];
        $items = array_map(fn ($k) => ['id' => $k, 'key' => $k], array_keys($sd->registry()->all()));
        $res = $sd->runBatch($items, ['period' => $this->option('period'), 'fresh' => 1], $user)['results'];
        $out = [];
        $ok = 0;
        foreach ($res as $k => $r) {
            $r['ok'] && $ok++;
            $out[] = [$k, $r['ok'] ? '<info>ok</info>' : '<error>' . $r['error'] . '</error>', $r['ms'] ?? '', $r['message'] ?? ''];
        }
        $this->table(['widget', 'status', 'ms', 'message'], $out);
        $this->line("$ok / " . count($res) . ' widgets available. Unavailable widgets are hidden from the editor automatically.');

        $this->info('3. Detected business activities');
        $d = $sd->detectActivities($user);
        foreach ($d['activities'] as $k => $a) $this->line(sprintf('  %s %-16s %s', $a['on'] ? '✔' : '·', $k, $a['evidence']));
        $this->line('  suggested business type: ' . $d['suggested_type']);
        return self::SUCCESS;
    }
}
