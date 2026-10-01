<?php

/**
 * Builds ONE self-contained HTML file with the dashboard + editor and data computed
 * by the real backend, so the design can be shown to anyone without a server.
 *   php demo/build-static.php  → demo/dist/aura-smart-dashboard-demo.html
 */

use AuraTech\SmartDashboard\DashboardService;
use AuraTech\SmartDashboard\Layouts\Activities;
use AuraTech\SmartDashboard\Layouts\Presets;
use AuraTech\SmartDashboard\Support\Period;

require __DIR__ . '/../tests/bootstrap.php';
$root = dirname(__DIR__);
$now = new DateTimeImmutable('now', new DateTimeZone('Asia/Jerusalem'));
$db = sd_demo_db($now);
$db->statement("CREATE TABLE roles (id INTEGER PRIMARY KEY, name TEXT)");
$db->statement("INSERT INTO roles VALUES (1,'מנהל'),(4,'קופאי'),(5,'שירות עצמי')");
$cfg = sd_config();
$cfg['cache_ttl'] = 0;
$sd = new DashboardService($db, $cfg, null, $now);
$admin = ['id' => 1, 'role_id' => 1, 'name' => 'שרבל פארן'];

// every widget instance that can appear: defaults + all preset variants
$instances = [];
foreach (array_keys($sd->registry()->all()) as $k) $instances[$k . '|[]'] = ['key' => $k, 'options' => []];
foreach (Presets::types() as $t) {
    foreach (Presets::for($t)['items'] as $i) $instances[$i['key'] . '|' . json_encode($i['options'] ?: [])] = ['key' => $i['key'], 'options' => $i['options'] ?: []];
}
$periods = ['today', 'yesterday', '7d', '30d', 'month', 'last_month', 'quarter', 'year'];
$data = [];
foreach ($periods as $p) {
    $items = [];
    foreach (array_values($instances) as $n => $i) $items[] = ['id' => 'i' . $n, 'key' => $i['key'], 'options' => $i['options']];
    $r = $sd->runBatch($items, ['period' => $p], $admin);
    $out = [];
    foreach (array_values($instances) as $n => $i) $out[$i['key'] . '|' . json_encode($i['options'])] = $r['results']['i' . $n];
    $data[$p] = ['period' => $r['period'], 'results' => $out];
}

$dash = $sd->dashboardBootstrap($admin);
$dash['filters'] = ['users' => [], 'warehouses' => []];      // demo data is not split per user
$dash['settings']['company_name'] = 'אגוזי הגליל (דמו)';
$ed = $sd->editorBootstrap($admin);
$ed['settings']['company_name'] = 'אגוזי הגליל (דמו)';
$specs = [];
foreach (Presets::types() as $t) $specs[$t] = Presets::specs($t);

$payload = [
    'dash' => $dash, 'editor' => $ed, 'data' => $data, 'specs' => $specs,
    'activities' => Activities::all(), 'detect' => $sd->detectActivities($admin),
];

$css = file_get_contents("$root/resources/assets/sd.css");
$js = fn ($f) => file_get_contents("$root/resources/assets/$f");
$json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_HEX_TAG);

$mock = <<<'JS'
/* ---- offline API: answers the same endpoints as the real backend from pre-computed data ---- */
(function () {
  const P = window.SD_DEMO, KEY = 'sd-demo-v1';
  let store = { layouts: {}, settings: {} };
  try { store = JSON.parse(localStorage.getItem(KEY)) || store; } catch (e) {}
  const persist = () => { try { localStorage.setItem(KEY, JSON.stringify(store)); } catch (e) {} };
  const settings = () => Object.assign({}, P.editor.settings, store.settings);
  const pack = (specs, fill) => {
    const rows = [[]]; let used = 0;
    specs.forEach(s => { const w = Math.max(1, Math.min(12, s[1])); if (used + w > 12 && rows[rows.length - 1].length) { rows.push([]); used = 0; } rows[rows.length - 1].push(s.slice()); used += w; });
    const items = []; let y = 0; const count = {};
    rows.forEach(row => {
      if (!row.length) return;
      if (fill) { let gap = 12 - row.reduce((a, s) => a + s[1], 0); for (let i = 0; gap > 0; i = (i + 1) % row.length, gap--) row[i][1]++; }
      let x = 0, rh = 0;
      row.forEach(s => { count[s[0]] = (count[s[0]] || 0) + 1; items.push({ id: s[0] + (count[s[0]] > 1 ? '_' + count[s[0]] : ''), key: s[0], x, y, w: s[1], h: s[2], title: s[4] || null, options: s[3] || {} }); x += s[1]; rh = Math.max(rh, s[2]); });
      y += rh;
    });
    return { version: 1, items };
  };
  const normalize = specs => {
    const isK = s => s[0].startsWith('kpi_') && s[2] <= 2;
    const kpis = specs.filter(isK), rest = specs.filter(s => !isK(s));
    if (!kpis.length) return specs;
    const per = kpis.length > 6 ? Math.ceil(kpis.length / 2) : 6, out = [];
    for (let i = 0; i < kpis.length; i += per) { const row = kpis.slice(i, i + per), base = Math.floor(12 / row.length), extra = 12 - base * row.length; row.forEach((k, j) => out.push([k[0], base + (j < extra ? 1 : 0), 2, k[3], k[4]])); }
    const head = rest.length && rest[0][0] === 'quick_actions' ? [rest.shift()] : [];
    return head.concat(out, rest);
  };
  const build = (type, acts) => {
    let specs = P.specs[type].map(s => s.slice());
    const match = (s, w) => s[0] === w[0] && (!(w[3] && w[3].side) || ((s[3] && s[3].side) || 'customers') === w[3].side);
    Object.entries(acts || {}).forEach(([a, on]) => {
      const def = P.activities[a]; if (!def) return;
      def.widgets.forEach(w => { const had = specs.some(s => match(s, w)); if (!on) specs = specs.filter(s => !match(s, w)); else if (!had) specs.push(w); });
    });
    return pack(normalize(specs), true);
  };
  const preset = type => pack(P.specs[type] || P.specs.grocery, false);
  const resolve = () => {
    const bt = settings().business_type;
    for (const [s, id] of [['user', '1'], ['role', '1'], ['business_type', bt]]) { const l = store.layouts[s + ':' + id]; if (l) return { ...l, source: s }; }
    return { ...preset(bt), source: 'preset' };
  };
  const result = (period, key, options) => {
    const d = P.data[period] || P.data.month;
    const o = Object.assign({}, options || {}); const exact = d.results[key + '|' + JSON.stringify(Object.keys(o).length ? o : [])];
    if (exact) return exact;
    delete o.limit; delete o.period;
    const sorted = {}; Object.keys(o).sort().forEach(k => sorted[k] = o[k]);
    const cand = Object.entries(d.results).find(([k]) => k.startsWith(key + '|') && Object.entries(o).every(([ok, ov]) => k.includes('"' + ok + '":' + JSON.stringify(ov))));
    return cand ? cand[1] : d.results[key + '|[]'] || { ok: false, error: 'unavailable' };
  };
  SD.mockApi = async function (path, opts) {
    await new Promise(r => setTimeout(r, 120 + Math.random() * 180));
    const b = opts.body || {}, q = opts.query || {};
    switch (path) {
      case 'data': {
        const res = {};
        (b.items || []).forEach(it => {
          const per = it.options && it.options.period && it.options.period !== 'inherit' ? it.options.period : (b.period || 'month');
          res[it.id] = result(P.data[per] ? per : 'month', it.key, it.options);
        });
        const d = P.data[b.period] || P.data.month;
        return { period: d.period, results: res };
      }
      case 'layout':
        if (b.layout) { store.layouts[b.scope + ':' + b.id] = { version: 1, items: b.layout.items, updated_at: new Date().toISOString().slice(0, 19).replace('T', ' ') }; persist(); return { ...store.layouts[b.scope + ':' + b.id], saved: true }; }
        { const l = store.layouts[q.scope + ':' + q.id]; if (l) return { ...l, saved: true, source: q.scope };
          if (q.scope === 'user') { const r = resolve(); return { ...r, saved: false }; }
          return { ...preset(q.scope === 'business_type' ? q.id : settings().business_type), source: 'preset', saved: false }; }
      case 'layout/reset': delete store.layouts[b.scope + ':' + b.id]; persist(); return SD.mockApi('layout', { query: { scope: b.scope, id: b.id } });
      case 'layout/reset-personal': Object.keys(store.layouts).forEach(k => k.startsWith('user:') && delete store.layouts[k]); persist(); return { ok: true };
      case 'build': return build(b.business_type, b.activities);
      case 'detect': return P.detect;
      case 'settings': Object.assign(store.settings, b.settings || {}); persist(); return settings();
    }
    throw new Error('not found');
  };
  // choose page from the hash, re-open on navigation
  const page = location.hash === '#editor' ? 'editor' : 'dashboard';
  window.SD_URLS = { dashboard: '#dashboard', editor: '#editor', base: 'javascript:void(0)//' };
  addEventListener('hashchange', () => location.reload());
  const s = settings();
  const boot = page === 'editor' ? { ...P.editor, settings: s, layouts_index: Object.entries(store.layouts).map(([k, v]) => ({ scope: k.split(':')[0], scope_id: k.split(':')[1], updated_at: v.updated_at })) }
                                 : { ...P.dash, layout: resolve(), settings: { ...P.dash.settings, ...s } };
  window.SD_BOOT = boot; window.SD_PAGE = page;
  const root = document.getElementById('sd-root'); if (page === 'editor') root.classList.add('ed');
  // links into the host system are inert in the demo
  document.addEventListener('click', e => { const a = e.target.closest('a[href^="javascript:void(0)//"]'); if (a) { e.preventDefault(); SD.toast('בדמו: קישור למסך ' + a.getAttribute('href').replace('javascript:void(0)//', '')); } });
})();
JS;

$banner = '<div id="demo-banner" style="position:fixed;bottom:14px;left:14px;z-index:90;background:#0e1628;color:#fff;font:500 12.5px Heebo,system-ui,sans-serif;padding:8px 12px;border-radius:10px;box-shadow:0 8px 24px rgba(0,0,0,.25);direction:rtl;display:flex;gap:10px;align-items:center">דמו · נתונים לדוגמה של חנות פיצוחים<a href="#dashboard" style="color:#9fb0ff">לוח</a><a href="#editor" style="color:#9fb0ff">עורך</a></div>';

$html = '<!doctype html><html lang="he" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
    . '<title>AuraTech Smart Dashboard</title>'
    . '<link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>'
    . '<link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Heebo:wght@400;500;600;700;800&display=swap">'
    . '<style>:root{color-scheme:light dark}html,body{margin:0;background:#f3f5fa}@media (prefers-color-scheme:dark){:root:not([data-theme="light"]) body{background:#0b1020}}' . "\n" . $css . '</style></head><body>'
    . '<div id="sd-root" class="sd" dir="rtl" lang="he"></div>' . $banner
    . '<script>window.SD_DEMO=' . $json . ';</script>'
    . '<script>' . $js('sd-core.js') . "\n" . $mock . '</script>'
    . '<script id="sd-dash" type="text/plain">' . str_replace('</script', '<\/script', $js('sd-dashboard.js')) . '</script>'
    . '<script id="sd-edit" type="text/plain">' . str_replace('</script', '<\/script', $js('sd-editor.js')) . '</script>'
    . '<script>(function(){var src=document.getElementById(window.SD_PAGE==="editor"?"sd-edit":"sd-dash").textContent;var s=document.createElement("script");s.textContent=src;document.body.appendChild(s);})();</script>'
    . '</body></html>';

// artifact flavour: a fragment (the host adds the document skeleton) with theme-aware tokens
$themeCss = ':root{--page:#f3f5fa}@media (prefers-color-scheme:dark){:root:not([data-theme="light"]){--page:#0b1020;color-scheme:dark}}:root[data-theme="dark"]{--page:#0b1020;color-scheme:dark}body{background:var(--page)}';
$frag = preg_replace('#^<!doctype html><html[^>]*><head>.*?(<title>)#s', '$1', $html);
$frag = str_replace(['</head><body>', '</body></html>'], '', $frag);
$frag = preg_replace('#<style>:root\{color-scheme:light dark\}html,body\{[^}]*\}@media[^{]*\{[^}]*\{[^}]*\}\}#', '<style>' . $themeCss, $frag);
@mkdir(__DIR__ . '/dist');
file_put_contents(__DIR__ . '/dist/artifact.html', $frag);
file_put_contents(__DIR__ . '/dist/aura-smart-dashboard-demo.html', $html);
printf("demo/dist/aura-smart-dashboard-demo.html  %.1f KB, %d widget variants × %d periods\n", strlen($html) / 1024, count($instances), count($periods));
