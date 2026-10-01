<?php

/**
 * Runs every widget against a seeded database for every period preset and
 * cross-checks key numbers with independent SQL.   php tests/run.php
 */

require __DIR__ . '/bootstrap.php';

use AuraTech\SmartDashboard\Layouts\Layout;
use AuraTech\SmartDashboard\Layouts\LayoutResolver;
use AuraTech\SmartDashboard\Layouts\LayoutStore;
use AuraTech\SmartDashboard\Layouts\Presets;
use AuraTech\SmartDashboard\Support\Period;
use AuraTech\SmartDashboard\Widgets\Registry;

$fail = 0;
$pass = 0;
function check(bool $ok, string $msg) { global $fail, $pass; if ($ok) { $pass++; } else { $fail++; echo "  ✗ $msg\n"; } }

$now = new DateTimeImmutable('2026-09-30 19:30:00', new DateTimeZone('Asia/Jerusalem'));
$t = microtime(true);
$db = sd_demo_db($now);
printf("seeded in %.1fs: %d sales, %d lines\n", microtime(true) - $t,
    $db->select('SELECT COUNT(*) c FROM sales')[0]['c'], $db->select('SELECT COUNT(*) c FROM product_sales')[0]['c']);

$cfg = sd_config();
$reg = new Registry($cfg['widgets']);
check(count($reg->all()) === count($cfg['widgets']), 'all widgets registered');

// 1. every widget, every preset
foreach (array_keys(Period::PRESETS) as $preset) {
    $ctx = sd_ctx($db, $cfg, $preset, $now);
    foreach ($reg->all() as $key => $w) {
        $r = $reg->run($key, $ctx, true);
        check($r['ok'] === true, "$key @ $preset: " . ($r['message'] ?? $r['error'] ?? ''));
        if ($r['ok']) check(json_encode($r['data']) !== false, "$key json");
    }
}

// 2. options variants
$ctx = sd_ctx($db, $cfg, 'month', $now);
foreach ([
    ['top_products', ['metric' => 'revenue', 'period' => 'year', 'limit' => 10]],
    ['debt_aging', ['side' => 'suppliers']],
    ['revenue_compare', ['mode' => 'day']], ['revenue_compare', ['mode' => 'month']],
    ['revenue_compare', ['mode' => 'hour', 'dates' => ['2026-09-29', '2026-09-22', 'bad']]],
    ['sales_heatmap', ['metric' => 'revenue', 'weeks' => 12]],
    ['revenue_trend', ['chart' => 'bar', 'show_expenses' => false]],
] as [$k, $o]) {
    $r = $reg->run($k, $ctx->withOptions($o), true);
    check($r['ok'], "$k with " . json_encode($o) . ' ' . ($r['message'] ?? ''));
}

// 3. cross-checks with independent SQL
$monthFrom = '2026-09-01 00:00:00'; $nowS = '2026-09-30 19:30:00';
$direct = (float) $db->select("SELECT SUM(grand_total) v FROM sales WHERE created_at >= ? AND created_at < ? AND (sale_status IS NULL OR sale_status NOT IN (5))", [$monthFrom, $nowS])[0]['v'];
$kpi = $reg->run('kpi_revenue', $ctx)['data'];
check(abs($kpi['value'] - round($direct, 2)) < 0.01, "kpi_revenue month = direct SUM ({$kpi['value']} vs $direct)");
check(abs(array_sum($kpi['spark']) - $kpi['value']) < 1, 'revenue spark sums to KPI');
$prevDirect = (float) $db->select("SELECT SUM(grand_total) v FROM sales WHERE created_at >= ? AND created_at < ? AND sale_status <> 5", ['2026-08-01 00:00:00', '2026-08-30 19:30:00'])[0]['v'];
check(abs($kpi['previous'] - round($prevDirect, 2)) < 0.01, "previous = Aug 1 → Aug 30 19:30 like-for-like ({$kpi['previous']} vs $prevDirect)");

$debt = (float) $db->select("SELECT SUM(grand_total - paid_amount) v FROM sales WHERE grand_total - paid_amount > 0.009 AND sale_status <> 5")[0]['v'];
check(abs($reg->run('kpi_customer_debt', $ctx)['data']['value'] - round($debt, 2)) < 0.01, 'customer debt = direct');
$sdebt = (float) $db->select("SELECT SUM(grand_total - paid_amount) v FROM purchases WHERE grand_total - paid_amount > 0.009")[0]['v'];
$aging = $reg->run('debt_aging', $ctx->withOptions(['side' => 'suppliers']))['data'];
check(abs($aging['total'] - round($sdebt, 2)) < 0.05, "supplier aging total = supplier debt ({$aging['total']} vs $sdebt)");
check(abs($reg->run('kpi_supplier_debt', $ctx)['data']['value'] - round($sdebt, 2)) < 0.01, 'supplier debt KPI = direct');

$top = $reg->run('top_products', $ctx->withOptions(['metric' => 'qty', 'limit' => 5]))['data']['rows'];
$directTop = $db->select("SELECT ps.product_id, SUM(ps.qty) q FROM product_sales ps JOIN sales s ON s.id=ps.sale_id WHERE s.created_at >= ? AND s.created_at < ? AND s.sale_status <> 5 GROUP BY ps.product_id ORDER BY q DESC LIMIT 1", [$monthFrom, $nowS])[0];
check(abs($top[0]['qty'] - round($directTop['q'], 2)) < 0.01, 'top product qty matches, rounded (no float noise)');
check(!preg_match('/\.\d{3,}/', json_encode($top)), 'no long floats in top products');

$today = sd_ctx($db, $cfg, 'today', $now);
$heat = $reg->run('sales_heatmap', $today)['data'];
check(count($heat['rows']) === 7, 'heatmap has 7 days');
$trend = $reg->run('revenue_trend', $today)['data'];
check($trend['granularity'] === 'hour' && count($trend['categories']) === 24, 'today trend is hourly (24 buckets)');
$yearTrend = $reg->run('revenue_trend', sd_ctx($db, $cfg, 'year', $now))['data'];
check($yearTrend['granularity'] === 'month' && count($yearTrend['categories']) === 12, 'year trend is monthly (12 buckets)');

// user filter
$u2 = sd_ctx($db, $cfg, 'month', $now, [], [], 2);
$u2direct = (float) $db->select("SELECT SUM(grand_total) v FROM sales WHERE user_id = 2 AND created_at >= ? AND created_at < ? AND sale_status <> 5", [$monthFrom, $nowS])[0]['v'];
check(abs($reg->run('kpi_revenue', $u2)['data']['value'] - round($u2direct, 2)) < 0.01, 'user filter applied');

// insights
$ins = $reg->run('smart_insights', $ctx->withOptions(['limit' => 20]))['data'];
check($ins['total'] >= 4, "insights produced ({$ins['total']})");
echo "  insights: " . implode(' | ', array_map(fn ($i) => "[{$i['level']}] {$i['title']}", $ins['items'])) . "\n";

// 4. schema variant: purchases stored inside the sales table (AuraTech custom installs)
$db->statement('ALTER TABLE sales ADD COLUMN transaction_type TEXT DEFAULT \'sale\'');
$cfg2 = $cfg;
$cfg2['schema']['sales']['where'] = ['transaction_type' => 'sale'];
$cfg2['schema']['purchases'] = ['table' => 'sales', 'date' => 'created_at', 'total' => 'grand_total', 'paid' => 'paid_amount', 'supplier_id' => 'customer_id',
    'user_id' => 'user_id', 'warehouse_id' => 'warehouse_id', 'reference' => 'reference_no', 'where' => ['transaction_type' => 'purchase']];
$db->statement("INSERT INTO sales (reference_no, user_id, customer_id, warehouse_id, grand_total, paid_amount, sale_status, created_at, transaction_type) VALUES ('P-X',1,3,1,5000,1000,1,'2026-09-10 10:00:00','purchase')");
$ctx2 = sd_ctx($db, $cfg2, 'month', $now);
check(abs($reg->run('kpi_revenue', $ctx2)['data']['value'] - $kpi['value']) < 0.01, 'purchases-in-sales-table: revenue excludes purchase rows');
check(abs($reg->run('kpi_purchases', $ctx2)['data']['value'] - 5000) < 0.01, 'purchases-in-sales-table: purchases KPI reads scoped rows');
foreach ($reg->all() as $key => $w) {
    $r = $reg->run($key, $ctx2, true);
    check($r['ok'] || $r['error'] === 'unavailable', "variant $key: " . ($r['message'] ?? ''));
}

// 5. missing tables -> widget reports unavailable, never crashes
$db->statement('DROP TABLE cheques');
$r = $reg->run('cheques_due', sd_ctx($db, $cfg, 'month', $now));
check(!$r['ok'] && $r['error'] === 'unavailable', 'missing table => unavailable');
$cat = $reg->catalog(sd_ctx($db, $cfg, 'month', $now), null, 'grocery');
check(count(array_filter($cat, fn ($c) => !$c['available'])) === 1, 'catalog marks exactly 1 unavailable');

// 6. layouts
$store = new LayoutStore($db);
$res = new LayoutResolver($store);
$l = $res->resolve(7, 4, 'wholesale');
check($l['source'] === 'preset' && $l['items'][0]['key'] === 'quick_actions', 'falls back to preset');
$store->save('business_type', 'wholesale', ['items' => [['key' => 'kpi_revenue', 'x' => 0, 'y' => 0, 'w' => 3, 'h' => 2, 'title' => '<b>X</b>']]], 1);
check($res->resolve(7, 4, 'wholesale')['source'] === 'business_type', 'business type template wins over preset');
$store->save('role', '4', ['items' => [['key' => 'kpi_orders', 'x' => 0, 'y' => 0, 'w' => 3, 'h' => 2]]], 1);
check($res->resolve(7, 4, 'wholesale')['source'] === 'role', 'role template wins');
$store->save('user', '7', ['items' => [['key' => 'low_stock', 'x' => 99, 'y' => -3, 'w' => 40, 'h' => 0]]], 7);
$u = $res->resolve(7, 4, 'wholesale');
check($u['source'] === 'user' && $u['items'][0]['x'] === 11 && $u['items'][0]['w'] === 12 && $u['items'][0]['h'] === 1, 'user layout wins + sanitized');
check($store->get('business_type', 'wholesale')['items'][0]['title'] === 'X', 'titles are stripped of HTML');
$store->deleteScope('user');
check($res->resolve(7, 4, 'wholesale')['source'] === 'role', 'reset personal layouts');
$store->setSetting('monthly_target', 400000);
$store->setSetting('monthly_target', 450000);
check($store->settings()['monthly_target'] === 450000, 'settings upsert');

$keys = array_keys($reg->all());
foreach (array_keys($cfg['business_types']) as $bt) {
    $p = Presets::for($bt);
    $bad = array_filter($p['items'], fn ($i) => !in_array($i['key'], $keys, true) || $i['x'] + $i['w'] > 12);
    check(!$bad && count($p['items']) >= 10, "preset $bt valid (" . count($p['items']) . ' widgets)');
}
$tgt = $reg->run('sales_target', sd_ctx($db, $cfg, 'month', $now, [], ['monthly_target' => 450000]))['data'];
check(!$tgt['auto'] && $tgt['items'][1]['target'] == 450000, 'target from settings');

// 8. DashboardService: permissions, forced own-data, smart builder, detection
use AuraTech\SmartDashboard\DashboardService;
use AuraTech\SmartDashboard\AccessDenied;
use AuraTech\SmartDashboard\Layouts\Activities;
$db3 = sd_demo_db($now);
$cfg3 = $cfg; $cfg3['own_data_role_ids'] = [4];
$svc = new DashboardService($db3, $cfg3, null, $now);
$admin = ['id' => 1, 'role_id' => 1, 'name' => 'a'];
$cashier = ['id' => 2, 'role_id' => 4, 'name' => 'c'];
$all = $svc->runBatch([['id' => 'r', 'key' => 'kpi_revenue']], ['period' => 'month'], $admin)['results']['r']['data']['value'];
$own = $svc->runBatch([['id' => 'r', 'key' => 'kpi_revenue']], ['period' => 'month', 'user_id' => ''], $cashier)['results']['r']['data']['value'];
$own2 = $svc->runBatch([['id' => 'r', 'key' => 'kpi_revenue']], ['period' => 'month', 'user_id' => 3], $cashier)['results']['r']['data']['value'];
check($own < $all && $own === $own2, "cashier forced to own data ($own < $all, cannot switch user)");
check(abs($own - $u2direct) < 0.01, 'forced own data = user 2 direct SUM');
check($svc->dashboardBootstrap($cashier)['filters']['users'] === [], 'cashier gets no user picker');
$r = $svc->runBatch([['id' => 'x', 'key' => 'nope'], ['id' => 'bad id!', 'key' => 'kpi_orders']], [], $admin)['results'];
check($r['x']['error'] === 'unknown_widget' && isset($r['badid']) && $r['badid']['ok'], 'batch: unknown widget + id sanitising');
$denied = false; try { $svc->saveLayout('business_type', 'grocery', ['items' => []], $cashier); } catch (AccessDenied) { $denied = true; }
check($denied, 'cashier cannot save business-type template');
$denied = false; try { $svc->saveLayout('user', '1', ['items' => []], $cashier); } catch (AccessDenied) { $denied = true; }
check($denied, 'cashier cannot save another user\'s layout');
$svc->saveLayout('user', '2', ['items' => [['key' => 'kpi_orders', 'x' => 0, 'y' => 0, 'w' => 3, 'h' => 2], ['key' => 'evil_widget', 'x' => 0, 'y' => 0, 'w' => 3, 'h' => 2]]], $cashier);
$l = $svc->layoutFor($cashier);
check($l['source'] === 'user' && count($l['items']) === 1, 'cashier saves own layout; unknown widget keys dropped');
$svc->resetLayout('user', '2', $cashier);
check($svc->layoutFor($cashier)['source'] === 'preset', 'personal reset falls back');
$bad = false; try { $svc->getLayout('evil', '1'); } catch (InvalidArgumentException) { $bad = true; }
check($bad, 'invalid scope rejected');
$st = $svc->saveSettings(['business_type' => 'hacker', 'monthly_target' => '-5', 'refresh_minutes' => 999, 'company_name' => '<b>אגוזי</b>', 'junk' => 1], $admin);
check($st['business_type'] === 'grocery' && $st['monthly_target'] == 0 && $st['refresh_minutes'] === 60 && $st['company_name'] === 'אגוזי' && !isset($st['junk']), 'settings validated');
$denied = false; try { $svc->saveSettings(['monthly_target' => 1], $cashier); } catch (AccessDenied) { $denied = true; }
check($denied, 'cashier cannot change settings');

$noOverlap = function (array $items): bool {
    foreach ($items as $i => $a) foreach ($items as $j => $b) {
        if ($i < $j && $a['x'] < $b['x'] + $b['w'] && $a['x'] + $a['w'] > $b['x'] && $a['y'] < $b['y'] + $b['h'] && $a['y'] + $a['h'] > $b['y']) return false;
    }
    return true;
};
$rowsFull = function (array $items): bool {
    $rows = [];
    foreach ($items as $i) $rows[$i['y']] = ($rows[$i['y']] ?? 0) + $i['w'];
    foreach ($rows as $w) if ($w !== 12) return false;
    return true;
};
foreach (array_keys($cfg['business_types']) as $bt) {
    foreach ([[], array_fill_keys(array_keys(Activities::all()), true), array_fill_keys(array_keys(Activities::all()), false), ['tables' => true, 'cheques' => false, 'deliveries' => true]] as $n => $acts) {
        $b = $svc->buildLayout($bt, $acts)['items'];
        $keys = array_column($b, 'key');
        $ok = $noOverlap($b) && $rowsFull($b) && !array_filter($b, fn ($i) => $i['x'] + $i['w'] > 12);
        foreach ($acts as $a => $on) foreach (Activities::all()[$a]['widgets'] as $w) {
            if (!$on && in_array($w[0], $keys, true)) $ok = false;
            if ($on && !in_array($w[0], $keys, true)) $ok = false;
        }
        check($ok, "smart build $bt #$n: no overlap, rows full, activities respected (" . count($b) . ')');
    }
}
$b = $svc->buildLayout('grocery', ['credit_sales' => false]);
check(!in_array('top_customers', array_column($b['items'], 'key'), true) && in_array('debt_aging', array_column($b['items'], 'key'), true), 'credit off removes customer aging but keeps supplier aging');
$d = $svc->detectActivities($admin);
check(count($d['activities']) === count(Activities::all()) && $d['activities']['expiry']['on'] && $d['suggested_type'] === 'grocery', 'activity detection + suggestion (' . $d['suggested_type'] . ')');
check(Activities::suggestType(['tables' => ['on' => true, 'share' => .6]]) === 'restaurant', 'table-heavy => restaurant');
check(Activities::suggestType(['quotations' => ['on' => true], 'credit_sales' => ['on' => true]]) === 'services', 'quotes without stock => services');
$bootD = $svc->dashboardBootstrap($admin);
check(count($bootD['layout']['items']) > 10 && isset($bootD['widgets']['kpi_revenue']) && $bootD['can_edit'], 'dashboard bootstrap');
$bootE = $svc->editorBootstrap($admin);
check($bootE['can_edit_templates'] && count($bootE['catalog']) === count($cfg['widgets']) && count($bootE['activities']) === 11, 'editor bootstrap');
check(!$svc->editorBootstrap($cashier)['can_edit_templates'] && $svc->editorBootstrap($cashier)['roles'] === [], 'cashier editor bootstrap has no templates/roles');
$ot = $svc->runBatch([['id' => 't', 'key' => 'open_tables']], [], $admin)['results']['t']['data']['tiles'];
check(!array_filter($ot, fn ($t) => $t['minutes'] < 0), 'open tables: no negative minutes (timezone-safe)');
$tz = date_default_timezone_get(); date_default_timezone_set('America/New_York');
$ot2 = $svc->runBatch([['id' => 't', 'key' => 'open_tables']], [], $admin)['results']['t']['data']['tiles'];
date_default_timezone_set($tz);
check(array_column($ot, 'minutes') === array_column($ot2, 'minutes'), 'open tables independent of server timezone');

// 7. performance – full grocery dashboard
$ctx = sd_ctx($db, $cfg, 'month', $now);
$t = microtime(true);
foreach (Presets::for('grocery')['items'] as $i) $reg->run($i['key'], $ctx->withOptions($i['options']));
printf("full grocery dashboard computed in %.0f ms (SQLite, no cache)\n", (microtime(true) - $t) * 1000);

echo "\n$pass passed, $fail failed\n";
exit($fail ? 1 : 0);
