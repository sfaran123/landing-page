/* ==========================================================================
   AuraTech Smart Dashboard – dashboard page
   lazy, batched loading · filters · auto refresh · command palette
   ========================================================================== */
(function () {
  'use strict';
  const { h, esc, icon, fmt: F } = SD;
  const boot = window.SD_BOOT, root = document.getElementById('sd-root');
  SD.urls = window.SD_URLS || {};
  SD.initTheme(root);
  root.classList.add('sd');

  /* ---------------------------------------------------------- state */
  const LS = 'sd-filters';
  const load = () => { try { return JSON.parse(localStorage.getItem(LS)) || {}; } catch (e) { return {}; } };
  const qs = new URLSearchParams(location.search);
  const saved = load();
  const f = {
    period: qs.get('period') || saved.period || 'month',
    from: qs.get('from') || saved.from || '', to: qs.get('to') || saved.to || '',
    user_id: qs.get('user_id') ?? saved.user_id ?? '', warehouse_id: qs.get('warehouse_id') ?? saved.warehouse_id ?? '',
  };
  let periodInfo = null, lastUpdate = 0, inflight = 0;
  const cards = new Map();   // id -> {item, meta, card, loaded, visible, res}

  /* ---------------------------------------------------------- header */
  const now = new Date(boot.now || Date.now());
  const hr = now.getHours();
  const greet = hr < 5 ? 'לילה טוב' : hr < 12 ? 'בוקר טוב' : hr < 17 ? 'צהריים טובים' : hr < 22 ? 'ערב טוב' : 'לילה טוב';
  const dateStr = new Intl.DateTimeFormat('he-IL', { weekday: 'long', day: 'numeric', month: 'long' }).format(now);
  const firstName = (boot.user.name || '').split(' ')[0];

  const updated = h('span.sd-updated', { 'aria-live': 'polite' }, h('span.dot'), h('span.txt', null, 'טוען…'));
  const top = h('header.sd-top', null,
    h('div.sd-bar', null,
      h('div.sd-brand', null, h('div.sd-logo', { html: icon('sparkle') }),
        h('div.sd-title', null, h('h1', null, 'לוח בקרה' + (boot.settings.company_name ? ' · ' + boot.settings.company_name : '')),
          h('p', null, `${greet}${firstName ? ', ' + firstName : ''} · ${dateStr}`))),
      h('div.sd-spacer'),
      h('button.sd-search', { type: 'button', onclick: () => openPalette(), 'aria-label': 'חיפוש וניווט', html: icon('search') + '<span>חיפוש מסך, דוח או פעולה…</span><span class="kbd">Ctrl K</span>' }),
      h('span.hide-sm', null, updated),
      h('button.btn.icon', { type: 'button', title: 'רענון (R)', 'aria-label': 'רענון', onclick: () => refreshAll(true), html: icon('refresh') }),
      h('button.btn.icon', { type: 'button', title: 'מצב כהה / בהיר', 'aria-label': 'מצב כהה או בהיר', onclick: () => SD.toggleTheme(), html: icon('moon') }),
      boot.can_edit ? h('a.btn.primary', { href: SD.urls.editor, title: 'עריכת הלוח (E)', html: icon('layout') + '<span class="hide-sm">התאמה אישית</span>' }) : null),
    h('div.sd-filters', null, periodChips(), customRange(), filterSelects(), h('span.sd-range.hide-sm')));
  root.append(top);
  addEventListener('scroll', () => top.classList.toggle('scrolled', scrollY > 4), { passive: true });

  function periodChips() {
    const wrap = h('div.sd-periods', { role: 'tablist', 'aria-label': 'תקופה' });
    Object.entries(boot.periods).forEach(([k, label]) => wrap.append(h('button.chip', { type: 'button', role: 'tab', dataset: { p: k }, onclick: () => setPeriod(k) }, k === 'custom' ? [SD.iconEl('calendar'), label] : label)));
    return wrap;
  }
  function customRange() {
    const today = new Date().toISOString().slice(0, 10);
    const a = h('input.input', { type: 'date', value: f.from || today.slice(0, 8) + '01', max: today, 'aria-label': 'מתאריך' });
    const b = h('input.input', { type: 'date', value: f.to || today, max: today, 'aria-label': 'עד תאריך' });
    const apply = () => { f.from = a.value; f.to = b.value; persist(); refreshAll(); };
    a.onchange = apply; b.onchange = apply;
    return h('div.sd-custom', null, a, h('span', { style: { color: 'var(--muted)' } }, '–'), b);
  }
  function filterSelects() {
    const frag = [];
    if (boot.filters.users.length) {
      const s = h('select.select', { 'aria-label': 'משתמש', onchange: e => { f.user_id = e.target.value; persist(); refreshAll(); } },
        h('option', { value: '' }, 'כל המשתמשים'), boot.filters.users.map(u => h('option', { value: u.id }, u.name)));
      s.value = f.user_id; frag.push(s);
    }
    if (boot.filters.warehouses.length) {
      const s = h('select.select', { 'aria-label': 'סניף / מחסן', onchange: e => { f.warehouse_id = e.target.value; persist(); refreshAll(); } },
        h('option', { value: '' }, 'כל הסניפים'), boot.filters.warehouses.map(w => h('option', { value: w.id }, w.name)));
      s.value = f.warehouse_id; frag.push(s);
    }
    return frag;
  }
  function syncChips() {
    top.querySelectorAll('.sd-periods .chip').forEach(c => c.classList.toggle('on', c.dataset.p === f.period));
    top.querySelector('.sd-custom').classList.toggle('on', f.period === 'custom');
  }
  function setPeriod(p) { f.period = p; syncChips(); persist(); refreshAll(); }
  function persist() {
    try { localStorage.setItem(LS, JSON.stringify(f)); } catch (e) {}
    const u = new URL(location.href);
    ['period', 'from', 'to', 'user_id', 'warehouse_id'].forEach(k => f[k] && !(k === 'period' && f[k] === 'month') && (k !== 'from' && k !== 'to' || f.period === 'custom') ? u.searchParams.set(k, f[k]) : u.searchParams.delete(k));
    history.replaceState(null, '', u);
  }
  syncChips();

  /* ---------------------------------------------------------- grid */
  const main = h('main.sd-main');
  const grid = h('div.sd-grid');
  main.append(grid); root.append(main);
  const items = (boot.layout.items || []).slice().sort((a, b) => a.y - b.y || a.x - b.x);
  if (!items.length) {
    grid.append(h('div.card', { style: { gridColumn: '1 / -1', gridRow: 'span 4' } }, h('div.empty', { html: icon('layout') + '<div>הלוח ריק. עבור לעמוד ההתאמה כדי להוסיף ווידג׳טים.</div>' })));
  }
  const io = new IntersectionObserver(entries => {
    entries.forEach(e => { const c = cards.get(e.target.dataset.id); if (!c) return; c.visible = e.isIntersecting; if (e.isIntersecting && !c.loaded) queue(c); });
  }, { rootMargin: '300px 0px' });

  items.forEach(item => {
    const meta = boot.widgets[item.key];
    if (!meta) return;
    const c = { item, meta, loaded: false, visible: false, res: null };
    c.card = SD.card(item, meta, { onRefresh: () => { c.card.busy(true); fetchBatch([c], true); }, onExpand: () => expand(c) });
    const el = c.card.el;
    el.style.setProperty('--x', item.x + 1); el.style.setProperty('--y', item.y + 1);
    el.style.setProperty('--w', item.w); el.style.setProperty('--h', item.h);
    el.style.setProperty('--tw', item.w >= 7 ? 12 : 6);
    el.style.setProperty('--mw', meta.type === 'kpi' ? 6 : 12);
    el.style.animationDelay = Math.min(400, item.y * 25 + item.x * 8) + 'ms';
    grid.append(el); cards.set(item.id, c); io.observe(el);
  });

  /* ---------------------------------------------------------- data */
  let pending = new Set(), timer = null;
  function queue(c) { pending.add(c); clearTimeout(timer); timer = setTimeout(() => { const list = [...pending]; pending = new Set(); fetchBatch(list); }, 25); }

  async function fetchBatch(list, fresh) {
    if (!list.length) return;
    list.forEach(c => { c.loaded = true; if (c.res) c.card.busy(true); });
    inflight++; setUpdated();
    const token = f.period + f.from + f.to + f.user_id + f.warehouse_id;
    try {
      const r = await SD.api('data', { body: { ...f, fresh: fresh ? 1 : 0, items: list.map(c => ({ id: c.item.id, key: c.item.key, options: c.item.options || {} })) } });
      if (token !== f.period + f.from + f.to + f.user_id + f.warehouse_id) return;   // filters changed meanwhile
      periodInfo = r.period; showRange();
      list.forEach(c => { c.res = r.results[c.item.id]; c.card.render(c.res, periodInfo.label); });
      lastUpdate = Date.now();
    } catch (e) {
      list.forEach(c => { c.loaded = false; c.card.render({ ok: false, message: 'אין חיבור לשרת' }); });
      SD.toast('טעינת הנתונים נכשלה: ' + e.message, true);
    } finally { inflight--; setUpdated(); }
  }

  function refreshAll(fresh) {
    const vis = [];
    cards.forEach(c => { if (c.visible) vis.push(c); else c.loaded = false; });
    vis.forEach(c => { if (!c.res) c.card.skeleton(); });
    // drop stale content of off-screen cards so they re-query when scrolled to
    fetchBatch(vis, fresh);
    if (fresh) SD.toast('הנתונים עודכנו');
  }

  function showRange() {
    const el = top.querySelector('.sd-range'); if (!periodInfo || !el) return;
    const a = periodInfo.from.split('-'), b = periodInfo.to.split('-');
    el.innerHTML = icon('calendar') + esc(periodInfo.from === periodInfo.to ? `${+a[2]}/${+a[1]}/${a[0]}` : `${+a[2]}/${+a[1]} – ${+b[2]}/${+b[1]}/${b[0]}`);
  }
  function setUpdated() {
    updated.classList.toggle('loading', inflight > 0);
    updated.querySelector('.txt').textContent = inflight > 0 ? 'מעדכן…' : lastUpdate ? 'עודכן ' + F.ago(lastUpdate) : '';
  }
  setInterval(setUpdated, 30000);

  const every = (+boot.settings.refresh_minutes || 0) * 60000;
  if (every) setInterval(() => { if (document.visibilityState === 'visible' && !document.querySelector('.overlay.on')) refreshAll(); }, every);
  document.addEventListener('visibilitychange', () => { if (document.visibilityState === 'visible' && every && Date.now() - lastUpdate > every) refreshAll(); });
  document.addEventListener('sd:theme', () => cards.forEach(c => c.res && c.card.render(c.res, periodInfo && periodInfo.label)));

  /* ---------------------------------------------------------- expand */
  async function expand(c) {
    const big = SD.card({ ...c.item, options: { ...c.item.options, limit: 20 } }, c.meta, { tools: false });
    big.el.style.height = '100%'; big.el.style.boxShadow = 'none'; big.el.style.border = '0';
    const m = SD.modal({ title: c.card.title, body: big.el, wide: true });
    m.el.querySelector('.modal-b').style.padding = '0';
    try {
      const r = await SD.api('data', { body: { ...f, items: [{ id: 'x', key: c.item.key, options: { ...c.item.options, limit: 20 } }] } });
      big.render(r.results.x, r.period.label);
    } catch (e) { big.render({ ok: false, message: e.message }); }
  }

  /* ---------------------------------------------------------- palette */
  function paletteEntries() {
    const out = [];
    (boot.palette || []).forEach(([label, url, group]) => out.push({ label, group: group || 'מסכים', icon: 'arrow', run: () => location.href = SD.link(url), hint: url }));
    cards.forEach(c => out.push({ label: c.card.title, group: 'בלוח הבקרה', icon: c.meta.icon, run: () => { c.card.el.scrollIntoView({ behavior: 'smooth', block: 'center' }); c.card.el.animate([{ boxShadow: '0 0 0 4px var(--primary)' }, { boxShadow: '0 0 0 0 transparent' }], { duration: 1400 }); } }));
    Object.entries(boot.periods).filter(([k]) => k !== 'custom').forEach(([k, l]) => out.push({ label: 'הצג: ' + l, group: 'תקופה', icon: 'calendar', run: () => setPeriod(k) }));
    out.push({ label: 'רענון נתונים', group: 'פעולות', icon: 'refresh', run: () => refreshAll(true) });
    out.push({ label: 'מצב כהה / בהיר', group: 'פעולות', icon: 'moon', run: () => SD.toggleTheme() });
    if (boot.can_edit) out.push({ label: 'התאמה אישית של הלוח', group: 'פעולות', icon: 'layout', run: () => location.href = SD.urls.editor });
    return out;
  }
  const norm = s => String(s).toLowerCase().replace(/[׳'"״\-_/]/g, '').replace(/\s+/g, ' ');
  function openPalette() {
    if (root.querySelector('.overlay.pal')) return;
    const entries = paletteEntries();
    const input = h('input', { placeholder: 'לאן לקפוץ? (מוצרים, הוסף לקוח, דוח רווח…)', 'aria-label': 'חיפוש' });
    const list = h('div.pal-list', { role: 'listbox' });
    let sel = 0, shown = [];
    const ov = h('div.overlay.on.pal', null, h('div.modal', null, h('div.pal-in', { html: icon('search') }, input), list,
      h('div.pal-foot', { html: '<span><span class="kbd">↑↓</span> ניווט</span><span><span class="kbd">Enter</span> פתיחה</span><span><span class="kbd">Esc</span> סגירה</span>' })));
    const close = () => ov.remove();
    const draw = () => {
      const q = norm(input.value.trim());
      shown = !q ? entries.filter(e => e.group !== 'תקופה').slice(0, 40) : entries.map(e => {
        const n = norm(e.label + ' ' + e.group); let score = n.indexOf(q);
        if (score < 0) { const words = q.split(' '); score = words.every(w => n.includes(w)) ? 50 : -1; }
        return { e, score };
      }).filter(x => x.score >= 0).sort((a, b) => a.score - b.score).slice(0, 40).map(x => x.e);
      sel = Math.min(sel, Math.max(0, shown.length - 1));
      list.innerHTML = ''; let g = null;
      if (!shown.length) list.append(h('div.empty', { style: { padding: '28px' } }, 'לא נמצאו תוצאות'));
      shown.forEach((e, i) => {
        if (e.group !== g) { g = e.group; list.append(h('div.pal-g', null, g)); }
        let label = esc(e.label);
        if (q) { const idx = e.label.toLowerCase().indexOf(input.value.trim().toLowerCase()); if (idx >= 0) label = esc(e.label.slice(0, idx)) + '<mark>' + esc(e.label.slice(idx, idx + input.value.trim().length)) + '</mark>' + esc(e.label.slice(idx + input.value.trim().length)); }
        list.append(h('div.pal-i', { role: 'option', class: i === sel ? 'on' : '', onclick: () => { close(); e.run(); }, onpointermove: () => { if (sel !== i) { sel = i; mark(); } },
          html: `<span class="card-ic">${icon(e.icon)}</span><span>${label}</span>` }));
      });
    };
    const mark = () => { [...list.querySelectorAll('.pal-i')].forEach((el, i) => el.classList.toggle('on', i === sel)); const on = list.querySelector('.pal-i.on'); on && on.scrollIntoView({ block: 'nearest' }); };
    input.addEventListener('input', () => { sel = 0; draw(); });
    input.addEventListener('keydown', e => {
      if (e.key === 'ArrowDown') { sel = Math.min(shown.length - 1, sel + 1); mark(); e.preventDefault(); }
      else if (e.key === 'ArrowUp') { sel = Math.max(0, sel - 1); mark(); e.preventDefault(); }
      else if (e.key === 'Enter' && shown[sel]) { close(); shown[sel].run(); }
      else if (e.key === 'Escape') close();
    });
    ov.addEventListener('pointerdown', e => { if (e.target === ov) close(); });
    root.append(ov); draw(); input.focus();
  }

  /* ---------------------------------------------------------- keys */
  document.addEventListener('keydown', e => {
    const typing = /INPUT|SELECT|TEXTAREA/.test(document.activeElement.tagName);
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); openPalette(); return; }
    if (typing || e.ctrlKey || e.metaKey || e.altKey || root.querySelector('.overlay.on')) return;
    if (e.key === '/') { e.preventDefault(); openPalette(); }
    else if (e.key === 'r' || e.key === 'ר') refreshAll(true);
    else if ((e.key === 'e' || e.key === 'ק') && boot.can_edit) location.href = SD.urls.editor;
  });
})();
