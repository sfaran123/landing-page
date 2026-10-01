/* ==========================================================================
   AuraTech Smart Dashboard – layout editor
   scopes (business type / role / personal) · drag & resize grid · library ·
   inspector · smart builder (business type + activities) · settings
   ========================================================================== */
(function () {
  'use strict';
  const { h, esc, icon, fmt: F } = SD;
  const boot = window.SD_BOOT, root = document.getElementById('sd-root');
  SD.urls = window.SD_URLS || {};
  SD.initTheme(root);
  root.classList.add('sd', 'ed');

  const admin = boot.can_edit_templates;
  const catalog = {}; boot.catalog.forEach(c => catalog[c.key] = c);
  const BT = boot.business_types;
  const SCOPE_LABEL = { business_type: 'סוג עסק', role: 'תפקיד', user: 'הלוח שלי' };

  /* ------------------------------------------------------------ state */
  const st = {
    scope: admin ? { type: 'business_type', id: boot.settings.business_type } : { type: 'user', id: String(boot.user.id) },
    items: [], saved: false, source: null, selected: null, dirty: false,
    past: [], future: [], period: 'month',
    results: {},          // item.id -> {sig, res}
    detected: null, activities: { ...(boot.settings.activities || {}) },
  };
  const clone = x => JSON.parse(JSON.stringify(x));
  const uid = key => key + '_' + Math.random().toString(36).slice(2, 7);

  /* ------------------------------------------------------------ top bar */
  const scopeSel = h('select.select', { 'aria-label': 'בחירת יעד', onchange: e => switchScope(st.scope.type, e.target.value) });
  const seg = h('div.seg', { role: 'tablist' });
  (admin ? ['business_type', 'role', 'user'] : ['user']).forEach(t => seg.append(h('button', { type: 'button', dataset: { t }, onclick: () => switchScope(t),
    html: icon(t === 'business_type' ? 'store' : t === 'role' ? 'shield' : 'user') + SCOPE_LABEL[t] })));
  const stateEl = h('span.ed-state');
  const undoB = h('button.btn.icon', { type: 'button', title: 'בטל (Ctrl+Z)', 'aria-label': 'בטל', onclick: () => undo(), html: icon('undo') });
  const redoB = h('button.btn.icon', { type: 'button', title: 'בצע שוב (Ctrl+Y)', 'aria-label': 'בצע שוב', onclick: () => redo(), html: icon('redo') });
  const saveB = h('button.btn.primary', { type: 'button', onclick: () => save(), html: icon('save') + 'שמירה' });
  const top = h('header.ed-top', null, h('div.sd-bar', null,
    h('a.btn.ghost.icon', { href: SD.urls.dashboard, title: 'חזרה ללוח', 'aria-label': 'חזרה ללוח', html: icon('back') }),
    h('div.sd-brand', null, h('div.sd-logo', { html: icon('layout') }), h('div.sd-title', null, h('h1', null, 'התאמת לוח הבקרה'), h('p', null, 'גרור, שנה גודל, הוסף והסר – לכל סוג עסק, תפקיד או משתמש'))),
    h('div.sd-spacer'),
    h('div.ed-scope', null, seg, scopeSel, stateEl),
    h('div.sd-spacer'),
    undoB, redoB,
    h('button.btn.icon', { type: 'button', title: 'אפשרויות', 'aria-label': 'אפשרויות', html: icon('dots'), onclick: e => SD.menu(e.currentTarget, [
      { icon: 'magic', label: 'טען תבנית בסיס לסוג העסק', run: () => loadPreset() },
      { icon: 'trash', label: 'נקה את הלוח', run: () => { commit(() => { st.items = []; select(null); }); } },
      { icon: 'reset', label: 'מחק את השמירה של יעד זה', danger: true, run: () => resetScope() },
      '-',
      { icon: 'moon', label: 'מצב כהה / בהיר', run: () => { SD.toggleTheme(); rerenderCards(); } },
    ]) }),
    saveB));
  root.append(top);

  /* ------------------------------------------------------------ layout */
  const body = h('div.ed-body');
  const side = h('aside.ed-side', { 'aria-label': 'ספריית ווידג׳טים' });
  const wrap = h('main.ed-canvas-wrap');
  const insp = h('aside.ed-insp', { 'aria-label': 'הגדרות ווידג׳ט' });
  body.append(side, wrap, insp); root.append(body);

  const tabsDef = [['lib', 'ווידג׳טים', 'grid'], ['smart', 'בנייה חכמה', 'magic']].concat(admin ? [['settings', 'הגדרות', 'gear']] : []);
  const tabs = h('div.tabs', { role: 'tablist' });
  const panes = {};
  tabsDef.forEach(([k, l, ic]) => {
    tabs.append(h('button', { type: 'button', role: 'tab', dataset: { k }, onclick: () => showTab(k), html: icon(ic) + ' ' + l }));
    panes[k] = h('div.ed-pane');
  });
  side.append(tabs, ...Object.values(panes));
  function showTab(k) {
    [...tabs.children].forEach(b => b.classList.toggle('on', b.dataset.k === k));
    Object.entries(panes).forEach(([pk, p]) => p.classList.toggle('on', pk === k));
    if (k === 'smart' && !st.detected) detect();
  }

  /* ------------------------------------------------------------ canvas */
  const chainEl = h('div.chain');
  const countEl = h('span');
  const periods = h('div.sd-periods', { 'aria-label': 'תקופת תצוגה מקדימה' });
  ['today', '7d', 'month', 'year'].forEach(p => periods.append(h('button.chip', { type: 'button', dataset: { p }, onclick: () => { st.period = p; syncPeriods(); fetchAll(); } }, boot.periods[p])));
  const syncPeriods = () => [...periods.children].forEach(c => c.classList.toggle('on', c.dataset.p === st.period));
  syncPeriods();
  const canvas = h('div.ed-canvas', { tabindex: '0', 'aria-label': 'אזור עריכה' });
  const ph = h('div.ph'); ph.style.display = 'none';
  canvas.append(ph);
  wrap.append(h('div.ed-canvas-head', null, chainEl, h('div.sd-spacer'), countEl, h('span', null, 'תצוגה מקדימה:'), periods), canvas);

  const ROW = () => parseFloat(getComputedStyle(root).getPropertyValue('--row')) || 58;
  const GAP = () => parseFloat(getComputedStyle(root).getPropertyValue('--gap')) || 16;
  const geo = () => { const W = canvas.clientWidth, g = GAP(); return { W, g, cu: (W + g) / 12, ru: ROW() + g }; };

  const els = new Map();    // id -> {el, card, sig}

  /* grid engine ---------------------------------------------------- */
  const collide = (a, b) => a.id !== b.id && a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y;
  function pushDown(layout, moved) {
    layout.filter(o => o.id !== moved.id).sort((a, b) => a.y - b.y || a.x - b.x).forEach(o => {
      if (collide(moved, o)) { o.y = moved.y + moved.h; pushDown(layout, o); }
    });
  }
  function compact(layout) {
    const placed = [];
    [...layout].sort((a, b) => a.y - b.y || a.x - b.x).forEach(it => {
      while (it.y > 0 && !placed.some(p => collide({ ...it, y: it.y - 1 }, p))) it.y--;
      while (placed.some(p => collide(it, p))) it.y++;
      placed.push(it);
    });
    return layout;
  }
  function placeAt(layout, item, x, y) {
    item.x = Math.max(0, Math.min(12 - item.w, x)); item.y = Math.max(0, y);
    pushDown(layout, item); return compact(layout);
  }
  function firstFree(layout, w, hgt) {
    const bottom = layout.reduce((m, i) => Math.max(m, i.y + i.h), 0);
    for (let y = 0; y <= bottom; y++) for (let x = 0; x + w <= 12; x++) {
      const probe = { id: '_', x, y, w, h: hgt };
      if (!layout.some(o => collide(probe, o))) return { x, y };
    }
    return { x: 0, y: bottom };
  }

  /* positions -------------------------------------------------------- */
  function rect(it, g) {
    const w = it.w * g.cu - g.g, hh = it.h * g.ru - g.g;
    return { left: g.W - (it.x * g.cu) - w, top: it.y * g.ru, w, h: hh };
  }
  function position(layout, skipId) {
    const g = geo();
    let maxY = 0;
    layout.forEach(it => {
      maxY = Math.max(maxY, it.y + it.h);
      const e = els.get(it.id); if (!e || it.id === skipId) return;
      const r = rect(it, g);
      e.el.style.width = r.w + 'px'; e.el.style.height = r.h + 'px';
      e.el.style.transform = `translate(${r.left}px, ${r.top}px)`;
      e.el.querySelector('.sz').textContent = it.w + '×' + it.h;
    });
    canvas.style.height = Math.max(420, (maxY + 4) * g.ru) + 'px';
    countEl.textContent = st.items.length + ' ווידג׳טים ·';
  }
  SD.observeSize(canvas, () => position(st.items));

  /* build item elements --------------------------------------------- */
  function draw() {
    const ids = new Set(st.items.map(i => i.id));
    els.forEach((e, id) => { if (!ids.has(id)) { e.el.remove(); els.delete(id); } });
    st.items.forEach(it => {
      const sig = JSON.stringify([it.key, it.title, it.options]);
      let e = els.get(it.id);
      if (e && e.sig !== sig) { e.el.remove(); els.delete(it.id); e = null; }
      if (!e) e = mount(it, sig);
      e.el.classList.toggle('sel', st.selected === it.id);
    });
    canvas.querySelector('.empty-canvas')?.remove();
    if (!st.items.length) canvas.append(h('div.empty-canvas', { html: icon('layout') + '<b>הלוח ריק</b><div>הוסף ווידג׳טים מהספרייה, או השתמש ב״בנייה חכמה״ ליצירת לוח מותאם לעסק.</div>' }));
    position(st.items);
    fetchMissing();
    renderLibrary(); updateState();
  }
  function mount(it, sig) {
    const meta = catalog[it.key] || { title: it.key, type: 'unknown', icon: 'alert', available: false };
    const card = SD.card(it, meta, { tools: false, periodLabel: boot.periods[st.period] });
    const el = h('div.gi', { dataset: { id: it.id }, class: meta.available === false ? 'unavailable' : '' });
    const grab = h('div.grab', { title: 'גרור להזזה · לחץ לבחירה' });
    const ov = h('div.ov', null,
      h('button.btn.ghost.icon.sm', { type: 'button', title: 'הגדרות', 'aria-label': 'הגדרות', html: icon('gear'), onclick: () => select(it.id) }),
      h('button.btn.ghost.icon.sm', { type: 'button', title: 'שכפול', 'aria-label': 'שכפול', html: icon('copy'), onclick: () => duplicate(it.id) }),
      h('button.btn.ghost.icon.sm.danger', { type: 'button', title: 'הסרה', 'aria-label': 'הסרה', html: icon('trash'), onclick: () => remove(it.id) }));
    const rz = h('div.rz', { title: 'שינוי גודל' });
    el.append(card.el, grab, ov, rz, h('span.sz'));
    canvas.append(el);
    const e = { el, card, sig };
    els.set(it.id, e);
    const cached = st.results[it.id];
    if (cached && cached.sig === resSig(it)) card.render(cached.res, boot.periods[st.period]);
    if (meta.available === false) card.render({ ok: false, error: 'unavailable' });
    grab.addEventListener('pointerdown', ev => startDrag(ev, it.id));
    rz.addEventListener('pointerdown', ev => startResize(ev, it.id));
    return e;
  }

  /* drag -------------------------------------------------------------- */
  function startDrag(ev, id) {
    if (ev.button !== 0) return;
    ev.preventDefault();
    const e = els.get(id), start = clone(st.items), it0 = start.find(i => i.id === id);
    const g = geo(), r0 = rect(it0, g);
    const sx = ev.clientX, sy = ev.clientY;
    let moved = false, layout = start, cand = { x: it0.x, y: it0.y };
    const move = m => {
      const dx = m.clientX - sx, dy = m.clientY - sy;
      if (!moved && Math.hypot(dx, dy) < 5) return;
      if (!moved) { moved = true; e.el.classList.add('dragging'); ph.style.display = ''; }
      e.el.style.transform = `translate(${r0.left + dx}px, ${r0.top + dy}px)`;
      const nx = Math.round(it0.x - dx / g.cu), ny = Math.max(0, Math.round(it0.y + dy / g.ru));
      if (nx === cand.x && ny === cand.y) return;
      cand = { x: nx, y: ny };
      layout = clone(start);
      const me = layout.find(i => i.id === id);
      placeAt(layout, me, nx, ny);
      const pr = rect(me, g);
      Object.assign(ph.style, { width: pr.w + 'px', height: pr.h + 'px', transform: `translate(${pr.left}px, ${pr.top}px)` });
      position(layout, id);
      autoScroll(m);
    };
    const up = () => {
      removeEventListener('pointermove', move); removeEventListener('pointerup', up);
      e.el.classList.remove('dragging'); ph.style.display = 'none';
      if (!moved) { select(id); return; }
      commit(() => { st.items = layout; });
      select(id, true);
    };
    addEventListener('pointermove', move); addEventListener('pointerup', up);
  }
  function autoScroll(m) {
    const r = wrap.getBoundingClientRect();
    if (m.clientY > r.bottom - 60) wrap.scrollTop += 18; else if (m.clientY < r.top + 70) wrap.scrollTop -= 18;
  }

  function startResize(ev, id) {
    ev.preventDefault(); ev.stopPropagation();
    const e = els.get(id), start = clone(st.items), it0 = start.find(i => i.id === id);
    const meta = catalog[it0.key] || {}, min = meta.min || { w: 2, h: 2 };
    const g = geo(), sx = ev.clientX, sy = ev.clientY;
    let layout = start, last = '';
    e.el.classList.add('resizing'); select(id, true);
    const move = m => {
      const w = Math.max(min.w, Math.min(12 - it0.x, Math.round(it0.w - (m.clientX - sx) / g.cu)));
      const hh = Math.max(min.h, Math.min(14, Math.round(it0.h + (m.clientY - sy) / g.ru)));
      if (last === w + 'x' + hh) return; last = w + 'x' + hh;
      layout = clone(start);
      const me = layout.find(i => i.id === id); me.w = w; me.h = hh;
      pushDown(layout, me); compact(layout);
      position(layout);
    };
    const up = () => {
      removeEventListener('pointermove', move); removeEventListener('pointerup', up);
      e.el.classList.remove('resizing');
      commit(() => { st.items = layout; }); renderInspector();
    };
    addEventListener('pointermove', move); addEventListener('pointerup', up);
  }

  /* drop from library ------------------------------------------------- */
  canvas.addEventListener('dragover', ev => {
    const key = SD._dragKey; if (!key) return;
    ev.preventDefault();
    const meta = catalog[key], g = geo(), cr = canvas.getBoundingClientRect();
    const w = meta.size.w, hh = meta.size.h;
    const x = Math.max(0, Math.min(12 - w, Math.floor((cr.right - ev.clientX) / g.cu - w / 2 + .5)));
    const y = Math.max(0, Math.floor((ev.clientY - cr.top) / g.ru));
    SD._drop = { x, y };
    const pr = rect({ x, y, w, h: hh }, g);
    Object.assign(ph.style, { display: '', width: pr.w + 'px', height: pr.h + 'px', transform: `translate(${pr.left}px, ${pr.top}px)` });
  });
  canvas.addEventListener('dragleave', ev => { if (!canvas.contains(ev.relatedTarget)) ph.style.display = 'none'; });
  canvas.addEventListener('drop', ev => {
    ev.preventDefault(); ph.style.display = 'none';
    if (SD._dragKey && SD._drop) add(SD._dragKey, SD._drop);
    SD._dragKey = null;
  });

  /* mutations --------------------------------------------------------- */
  function commit(fn) {
    st.past.push(clone(st.items)); if (st.past.length > 80) st.past.shift();
    st.future = [];
    fn(); st.dirty = true; draw();
  }
  function undo() { if (!st.past.length) return; st.future.push(clone(st.items)); st.items = st.past.pop(); st.dirty = true; if (!st.items.find(i => i.id === st.selected)) st.selected = null; draw(); renderInspector(); }
  function redo() { if (!st.future.length) return; st.past.push(clone(st.items)); st.items = st.future.pop(); st.dirty = true; draw(); renderInspector(); }

  function add(key, at) {
    const meta = catalog[key]; if (!meta || meta.available === false) return;
    const it = { id: uid(key), key, x: 0, y: 0, w: meta.size.w, h: meta.size.h, title: null, options: {} };
    commit(() => {
      const pos = at || firstFree(st.items, it.w, it.h);
      st.items.push(it); placeAt(st.items, it, pos.x, pos.y);
    });
    select(it.id);
    setTimeout(() => { const e = els.get(it.id); e && e.el.scrollIntoView({ behavior: 'smooth', block: 'center' }); e && e.el.animate([{ opacity: .2, transform: e.el.style.transform + ' scale(.96)' }, { opacity: 1, transform: e.el.style.transform }], { duration: 350, easing: 'ease-out' }); }, 60);
    SD.toast('נוסף: ' + meta.title);
  }
  function duplicate(id) {
    const src = st.items.find(i => i.id === id); if (!src) return;
    const it = { ...clone(src), id: uid(src.key) };
    commit(() => { st.items.push(it); placeAt(st.items, it, src.x, src.y + src.h); });
    select(it.id);
  }
  function remove(id) {
    const it = st.items.find(i => i.id === id); if (!it) return;
    commit(() => { st.items = compact(st.items.filter(i => i.id !== id)); if (st.selected === id) st.selected = null; });
    renderInspector();
    SD.toast('הווידג׳ט הוסר · Ctrl+Z לביטול');
  }
  function update(id, patch, refetch) {
    commit(() => { const it = st.items.find(i => i.id === id); Object.assign(it, patch); if (patch.w || patch.h) { pushDown(st.items, it); compact(st.items); } });
    if (refetch) fetchMissing();
  }
  function select(id, keepTab) {
    st.selected = id;
    els.forEach((e, k) => e.el.classList.toggle('sel', k === id));
    body.classList.toggle('with-insp', !!id);
    renderInspector();
    requestAnimationFrame(() => position(st.items));
  }

  /* data -------------------------------------------------------------- */
  const resSig = it => JSON.stringify([it.key, it.options || {}, st.period]);
  let fetching = false;
  async function fetchMissing() {
    if (fetching) return;
    const need = st.items.filter(it => { const c = st.results[it.id]; return (!c || c.sig !== resSig(it)) && catalog[it.key] && catalog[it.key].available !== false; });
    if (!need.length) return;
    fetching = true;
    need.forEach(it => { const e = els.get(it.id); e && e.card.busy(true); });
    try {
      const r = await SD.api('data', { body: { period: st.period, items: need.map(it => ({ id: it.id, key: it.key, options: it.options || {} })) } });
      need.forEach(it => {
        st.results[it.id] = { sig: resSig(it), res: r.results[it.id] };
        const e = els.get(it.id); if (e) e.card.render(r.results[it.id], r.period.label);
      });
    } catch (err) { SD.toast('טעינת תצוגה מקדימה נכשלה', true); }
    finally { fetching = false; }
    if (st.items.some(it => { const c = st.results[it.id]; return (!c || c.sig !== resSig(it)) && catalog[it.key] && catalog[it.key].available !== false; })) fetchMissing();
  }
  function fetchAll() { els.forEach(e => e.card.setSub(boot.periods[st.period])); fetchMissing(); }
  function rerenderCards() { els.forEach((e, id) => { const c = st.results[id]; c && e.card.render(c.res, boot.periods[st.period]); }); }
  document.addEventListener('sd:theme', rerenderCards);

  /* ------------------------------------------------------------ scopes */
  function fillScopeSelect() {
    scopeSel.innerHTML = '';
    const t = st.scope.type;
    scopeSel.style.display = t === 'user' ? 'none' : '';
    if (t === 'business_type') Object.entries(BT).forEach(([k, v]) => scopeSel.append(h('option', { value: k }, v.label + (k === boot.settings.business_type ? ' (פעיל)' : ''))));
    if (t === 'role') boot.roles.forEach(r => scopeSel.append(h('option', { value: r.id }, r.name)));
    scopeSel.value = st.scope.id;
    [...seg.children].forEach(b => b.classList.toggle('on', b.dataset.t === t));
  }
  async function switchScope(type, id) {
    if (st.dirty && !(await SD.confirm('יש שינויים שלא נשמרו', 'לעבור בלי לשמור? השינויים יאבדו.', 'עבור בלי לשמור', true))) { fillScopeSelect(); return; }
    if (id == null) id = type === 'business_type' ? boot.settings.business_type : type === 'role' ? String((boot.roles[0] || {}).id ?? '') : String(boot.user.id);
    if (type === 'role' && !boot.roles.length) { SD.toast('לא נמצאו תפקידים במערכת', true); return; }
    st.scope = { type, id: String(id) };
    fillScopeSelect();
    await loadScope();
  }
  async function loadScope() {
    try {
      const l = await SD.api('layout', { query: { scope: st.scope.type, id: st.scope.id } });
      st.items = (l.items || []).map(i => ({ ...i, options: Array.isArray(i.options) ? {} : (i.options || {}) }));
      st.saved = !!l.saved; st.source = l.source; st.past = []; st.future = []; st.dirty = false; st.selected = null;
      body.classList.remove('with-insp');
      draw(); renderInspector(); renderChain(); renderSmart();
    } catch (e) { SD.toast('טעינת הלוח נכשלה: ' + e.message, true); }
  }
  function renderChain() {
    const cur = st.scope.type;
    const steps = [['user', 'אישי'], ['role', 'תפקיד'], ['business_type', 'סוג עסק'], ['preset', 'תבנית מובנית']];
    chainEl.innerHTML = '';
    chainEl.append(h('span', { html: icon('info') }), h('span', null, 'סדר העדיפות:'));
    steps.forEach(([k, l], i) => {
      chainEl.append(h('span.badge.' + (k === cur ? 'info' : 'neutral'), { class: k === cur ? 'cur' : '' }, l));
      if (i < steps.length - 1) chainEl.append(h('span', null, '›'));
    });
  }
  function updateState() {
    const label = st.scope.type === 'user' ? 'הלוח האישי שלך' : SCOPE_LABEL[st.scope.type] + ': ' + (scopeSel.selectedOptions[0] ? scopeSel.selectedOptions[0].textContent : st.scope.id);
    stateEl.className = 'ed-state' + (st.dirty ? ' dirty' : '');
    stateEl.innerHTML = st.dirty ? icon('edit') + 'שינויים לא נשמרו' : st.saved ? icon('check') + 'שמור' : icon('info') + (st.source === 'preset' ? 'תבנית ברירת מחדל' : 'עובר בירושה');
    stateEl.title = label;
    undoB.disabled = !st.past.length; redoB.disabled = !st.future.length;
    saveB.disabled = !st.dirty && st.saved;
  }
  async function save() {
    try {
      saveB.disabled = true;
      const layout = { items: st.items.map(({ id, key, x, y, w, h: hh, title, options }) => ({ id, key, x, y, w, h: hh, title, options })) };
      const r = await SD.api('layout', { body: { scope: st.scope.type, id: st.scope.id, layout } });
      st.saved = true; st.dirty = false; st.source = st.scope.type;
      st.items.forEach((it, i) => { if (r.items && r.items[i]) it.id = r.items[i].id; });
      draw();
      SD.toast(st.scope.type === 'user' ? 'הלוח האישי נשמר' : 'התבנית נשמרה – משתמשים יראו אותה ברענון הבא');
    } catch (e) { SD.toast('השמירה נכשלה: ' + e.message, true); updateState(); }
  }
  async function resetScope() {
    if (!(await SD.confirm('מחיקת השמירה', 'הלוח של יעד זה יחזור לתבנית שהוא יורש (תפקיד / סוג עסק / מובנית). להמשיך?', 'מחק שמירה', true))) return;
    try {
      const l = await SD.api('layout/reset', { body: { scope: st.scope.type, id: st.scope.id } });
      st.items = l.items || []; st.saved = false; st.source = l.source; st.dirty = false; st.past = []; st.future = []; st.selected = null;
      draw(); renderInspector(); SD.toast('השמירה נמחקה');
    } catch (e) { SD.toast(e.message, true); }
  }
  async function loadPreset() {
    const type = st.scope.type === 'business_type' ? st.scope.id : boot.settings.business_type;
    const l = await SD.api('build', { body: { business_type: type, activities: {} } });
    commit(() => { st.items = l.items; st.selected = null; });
    renderInspector(); SD.toast('נטענה תבנית הבסיס: ' + BT[type].label);
  }

  /* ------------------------------------------------------------ library */
  let libQ = '', libCat = 'all';
  function renderLibrary() {
    const p = panes.lib; const keepScroll = p.scrollTop;
    const bt = st.scope.type === 'business_type' ? st.scope.id : boot.settings.business_type;
    const counts = {}; st.items.forEach(i => counts[i.key] = (counts[i.key] || 0) + 1);
    if (!p.firstChild) {
      const inp = h('input.input', { type: 'search', placeholder: 'חיפוש ווידג׳ט…', 'aria-label': 'חיפוש ווידג׳ט', oninput: e => { libQ = e.target.value.trim(); renderLibrary(); } });
      p.append(h('div.lib-search', { html: icon('search') }, inp), h('div.lib-cats'), h('div.lib-list'));
      const cats = p.querySelector('.lib-cats');
      [['all', 'הכול'], ...Object.entries(boot.categories)].forEach(([k, l]) => {
        if (k !== 'all' && !boot.catalog.some(c => c.category === k)) return;
        cats.append(h('button.chip', { type: 'button', dataset: { k }, onclick: () => { libCat = k; renderLibrary(); } }, l));
      });
    }
    p.querySelectorAll('.lib-cats .chip').forEach(c => c.classList.toggle('on', c.dataset.k === libCat));
    const list = p.querySelector('.lib-list'); list.innerHTML = '';
    const q = libQ.toLowerCase();
    const shown = boot.catalog.filter(c => (libCat === 'all' || c.category === libCat) && (!q || (c.title + ' ' + c.description).toLowerCase().includes(q)));
    const rec = c => !c.business_types.length || c.business_types.includes(bt);
    const groups = [['מומלץ ל' + (BT[bt] ? BT[bt].label : 'עסק'), shown.filter(c => c.available !== false && c.business_types.includes(bt))],
      ['כל הווידג׳טים', shown.filter(c => c.available !== false && !c.business_types.includes(bt))],
      ['לא זמין במערכת זו', shown.filter(c => c.available === false)]];
    groups.forEach(([title, arr]) => {
      if (!arr.length) return;
      list.append(h('div.lib-g', null, h('span', null, title), h('span', null, arr.length)));
      arr.forEach(c => {
        const el = h('div.lib-i', { class: c.available === false ? 'na' : '', draggable: c.available !== false ? 'true' : null, title: c.available === false ? 'חסרה טבלה/עמודה במסד הנתונים של מערכת זו' : 'גרור ללוח או לחץ על +' },
          h('span.card-ic', { html: icon(c.icon) }),
          h('div', null, h('b', null, c.title, counts[c.key] ? h('span.cnt', null, '✓ ' + (counts[c.key] > 1 ? counts[c.key] + ' בלוח' : 'בלוח')) : null,
            rec(c) && c.business_types.length ? h('span.badge.info', null, 'מומלץ') : null), h('p', null, c.description)),
          c.available !== false ? h('button.add', { type: 'button', 'aria-label': 'הוסף ' + c.title, html: icon('plus'), onclick: ev => { ev.stopPropagation(); add(c.key); } }) : h('span', { html: icon('lock') }));
        if (c.available !== false) {
          el.addEventListener('dragstart', ev => { SD._dragKey = c.key; ev.dataTransfer.setData('text/plain', c.key); ev.dataTransfer.effectAllowed = 'copy'; });
          el.addEventListener('dragend', () => { SD._dragKey = null; ph.style.display = 'none'; });
          el.addEventListener('dblclick', () => add(c.key));
        }
        list.append(el);
      });
    });
    if (!shown.length) list.append(h('div.empty', { style: { padding: '30px 0' } }, 'לא נמצאו ווידג׳טים'));
    p.scrollTop = keepScroll;
  }

  /* ------------------------------------------------------------ inspector */
  const SIZES = [['רבע', 3], ['שליש', 4], ['חצי', 6], ['2/3', 8], ['מלא', 12]];
  function renderInspector() {
    insp.innerHTML = '';
    const it = st.items.find(i => i.id === st.selected);
    if (!it) { body.classList.remove('with-insp'); return; }
    const meta = catalog[it.key] || { title: it.key, options: {}, min: { w: 1, h: 1 }, icon: 'alert' };
    insp.append(h('div.modal-h', null, h('span.card-ic', { html: icon(meta.icon) }), h('h3', null, it.title || meta.title),
      h('button.btn.ghost.icon.sm', { type: 'button', 'aria-label': 'סגור', html: icon('x'), onclick: () => select(null) })));
    const b = h('div.body');
    if (meta.description) b.append(h('div.note', null, meta.description));
    // title
    let tmr;
    b.append(h('label.field', null, 'כותרת', h('input.input', { value: it.title || '', placeholder: meta.title, maxlength: 80,
      oninput: e => { clearTimeout(tmr); const v = e.target.value.trim(); tmr = setTimeout(() => update(it.id, { title: v || null }), 350); } })));
    // size
    const stepper = (label, key, min, max) => {
      const val = h('span.num', null, it[key]);
      return h('label.field', null, label, h('div.stepper', null,
        h('button', { type: 'button', 'aria-label': 'הקטן', onclick: () => it[key] > min && (update(it.id, { [key]: it[key] - 1 }), renderInspector()) }, '−'), val,
        h('button', { type: 'button', 'aria-label': 'הגדל', onclick: () => it[key] < max && (update(it.id, { [key]: it[key] + 1 }), renderInspector()) }, '+')));
    };
    b.append(h('div.row2', null, stepper('רוחב (מתוך 12)', 'w', meta.min?.w || 1, 12 - it.x), stepper('גובה', 'h', meta.min?.h || 1, 14)));
    b.append(h('div.size-presets', null, SIZES.map(([l, w]) => h('button', { type: 'button', class: it.w === w ? 'on' : '', disabled: w < (meta.min?.w || 1),
      onclick: () => { commit(() => { const x = st.items.find(i => i.id === it.id); x.w = w; x.x = Math.min(x.x, 12 - w); pushDown(st.items, x); compact(st.items); }); renderInspector(); } }, l))));
    // options
    const opts = meta.options || {};
    const keys = Object.keys(opts).filter(k => opts[k].type !== 'hidden');
    if (keys.length) b.append(h('div.sec-t', null, 'אפשרויות תצוגה'));
    keys.forEach(k => {
      const o = opts[k], cur = it.options && it.options[k] !== undefined ? it.options[k] : o.default;
      const set = v => update(it.id, { options: { ...(it.options || {}), [k]: v } }, true);
      if (o.type === 'select') {
        const sel = h('select.select', { onchange: e => { const raw = e.target.value; set(/^-?\d+$/.test(raw) ? +raw : raw); } },
          Object.entries(o.choices).map(([v, l]) => h('option', { value: v }, l)));
        sel.value = String(cur);
        b.append(h('label.field', null, o.label, sel));
      } else if (o.type === 'toggle') {
        const inp = h('input', { type: 'checkbox', onchange: e => set(e.target.checked) }); inp.checked = !!cur;
        b.append(h('div.opt-row', null, h('span', null, o.label), h('label.switch', null, inp, h('span'))));
      } else {
        let t2;
        b.append(h('label.field', null, o.label, h('input.input', { value: cur || '', oninput: e => { clearTimeout(t2); const v = e.target.value; t2 = setTimeout(() => set(v), 500); } }),
          it.key === 'quick_actions' ? h('span.hint', null, 'לדוגמה: הוסף ספק, תשלום לספק') : null));
      }
    });
    if (meta.link) b.append(h('div.note', { html: 'קישור לדוח: <b class="ltr">' + esc(meta.link) + '</b>' }));
    if (meta.business_types && meta.business_types.length) b.append(h('div.note', null, 'מומלץ עבור: ' + meta.business_types.map(t => BT[t] ? BT[t].label : t).join(', ')));
    b.append(h('div.row2', null,
      h('button.btn', { type: 'button', onclick: () => duplicate(it.id), html: icon('copy') + 'שכפל' }),
      h('button.btn.danger', { type: 'button', onclick: () => remove(it.id), html: icon('trash') + 'הסר' })));
    insp.append(b);
  }

  /* ------------------------------------------------------------ smart builder */
  let smartType = null;
  async function detect() {
    try {
      const r = await SD.api('detect');
      st.detected = r;
      Object.entries(r.activities).forEach(([k, a]) => { if (st.activities[k] === undefined) st.activities[k] = a.on; });
      renderSmart();
    } catch (e) { SD.toast('הזיהוי האוטומטי נכשל', true); }
  }
  function renderSmart() {
    const p = panes.smart; p.innerHTML = '';
    smartType = smartType || (st.scope.type === 'business_type' ? st.scope.id : boot.settings.business_type);
    const sug = st.detected && st.detected.suggested_type;
    p.append(h('div.note', { html: '<b>בנייה חכמה:</b> בחר את סוג העסק ומה העסק עושה בפועל – והמערכת תבנה לוח עם הווידג׳טים הנכונים, בגדלים ובסדר הנכון. אפשר להמשיך לערוך אחר כך.' }));
    p.append(h('div.sec-t', null, '1. סוג העסק'));
    const grid = h('div.bt-grid');
    Object.entries(BT).forEach(([k, v]) => grid.append(h('button.bt', { type: 'button', class: smartType === k ? 'on' : '', onclick: () => { smartType = k; renderSmart(); } },
      sug === k ? h('span.badge.success.sug', null, 'מזוהה') : null, h('span.card-ic', { html: icon(v.icon) }), v.label)));
    p.append(grid);
    p.append(h('div.sec-t', null, h('span', null, '2. מה העסק עושה?'),
      h('button.btn.sm', { type: 'button', onclick: async () => { st.detected = null; st.activities = {}; renderSmart(); await detect(); SD.toast('הפעילויות זוהו מתוך הנתונים'); }, html: icon('sparkle') + 'זהה אוטומטית' })));
    const list = h('div.act-l');
    Object.entries(boot.activities).forEach(([k, a]) => {
      const inp = h('input', { type: 'checkbox', 'aria-label': a.label, onchange: e => { st.activities[k] = e.target.checked; } });
      inp.checked = !!st.activities[k];
      const ev = st.detected && st.detected.activities[k];
      list.append(h('label.act', null, h('span.card-ic', { html: icon(a.icon) }),
        h('div', null, h('b', null, a.label), h('small', null, a.hint), ev ? h('small', { class: ev.on ? 'ev' : '' }, (ev.on ? '✓ ' : '· ') + ev.evidence) : null),
        h('span.switch', null, inp, h('span'))));
    });
    if (!st.detected) list.prepend(h('div.skel', { style: { height: '4px' } }));
    p.append(list);
    p.append(h('div', { style: { display: 'grid', gap: '8px', marginTop: '14px', position: 'sticky', bottom: '-12px', background: 'var(--surface)', padding: '10px 0 12px' } },
      h('button.btn.primary', { type: 'button', style: { justifyContent: 'center', height: '42px' }, html: icon('magic') + 'בנה לוח מותאם', onclick: build })));
  }
  async function build() {
    try {
      const l = await SD.api('build', { body: { business_type: smartType, activities: st.activities } });
      commit(() => { st.items = l.items; st.selected = null; });
      renderInspector(); wrap.scrollTo({ top: 0, behavior: 'smooth' });
      if (admin) SD.api('settings', { body: { settings: { activities: st.activities } } }).catch(() => {});
      SD.toast(`נבנה לוח עם ${l.items.length} ווידג׳טים ל${BT[smartType].label} – בדוק ולחץ שמירה`);
    } catch (e) { SD.toast(e.message, true); }
  }

  /* ------------------------------------------------------------ settings */
  function renderSettings() {
    if (!panes.settings) return;
    const p = panes.settings, s = boot.settings; p.innerHTML = '';
    const f = {};
    const field = (label, el, hint) => h('label.field', null, label, el, hint ? h('span.hint', null, hint) : null);
    f.company_name = h('input.input', { value: s.company_name || '', placeholder: 'שם העסק (מוצג בכותרת)' });
    f.business_type = h('select.select', null, Object.entries(BT).map(([k, v]) => h('option', { value: k }, v.label))); f.business_type.value = s.business_type;
    f.monthly_target = h('input.input.num', { type: 'number', min: 0, step: 1000, value: s.monthly_target || '', placeholder: '0 = אוטומטי' });
    f.daily_target = h('input.input.num', { type: 'number', min: 0, step: 100, value: s.daily_target || '', placeholder: '0 = חודשי ÷ ימים' });
    f.refresh_minutes = h('select.select', null, [[0, 'ללא'], [2, 'כל 2 דקות'], [5, 'כל 5 דקות'], [10, 'כל 10 דקות'], [15, 'כל 15 דקות'], [30, 'כל 30 דקות']].map(([v, l]) => h('option', { value: v }, l)));
    f.refresh_minutes.value = String(s.refresh_minutes);
    p.append(h('div.stack', null,
      h('div.sec-t', null, 'העסק'),
      field('שם העסק', f.company_name),
      field('סוג העסק של מערכת זו', f.business_type, 'קובע איזו תבנית רואים משתמשים שאין להם לוח אישי או תבנית תפקיד.'),
      h('div.sec-t', null, 'יעדים'),
      h('div.row2', null, field('יעד חודשי (₪)', f.monthly_target), field('יעד יומי (₪)', f.daily_target)),
      h('span.hint', { style: { fontSize: '12px', color: 'var(--muted)' } }, 'השאר ריק ליעד אוטומטי המבוסס על החודש הקודם.'),
      h('div.sec-t', null, 'רענון'),
      field('רענון אוטומטי של הלוח', f.refresh_minutes),
      h('button.btn.primary', { type: 'button', style: { justifyContent: 'center' }, html: icon('save') + 'שמור הגדרות', onclick: async () => {
        try {
          const out = await SD.api('settings', { body: { settings: Object.fromEntries(Object.entries(f).map(([k, el]) => [k, el.value])) } });
          Object.assign(boot.settings, out); fillScopeSelect(); renderLibrary(); SD.toast('ההגדרות נשמרו');
        } catch (e) { SD.toast(e.message, true); }
      } }),
      h('div.sec-t', null, 'לוחות שמורים'),
      savedList(),
      h('button.btn.danger', { type: 'button', style: { justifyContent: 'center' }, html: icon('reset') + 'איפוס כל הלוחות האישיים', onclick: async () => {
        if (!(await SD.confirm('איפוס לוחות אישיים', 'כל המשתמשים יחזרו לתבנית של התפקיד / סוג העסק. לא ניתן לבטל.', 'אפס הכול', true))) return;
        try { await SD.api('layout/reset-personal', { body: {} }); SD.toast('הלוחות האישיים אופסו'); } catch (e) { SD.toast(e.message, true); }
      } })));
  }
  function savedList() {
    const idx = boot.layouts_index || [];
    if (!idx.length) return h('div.note', null, 'עדיין לא נשמרו תבניות. כל עוד לא נשמרה תבנית, המשתמשים רואים את התבנית המובנית של סוג העסק.');
    const roleName = id => (boot.roles.find(r => String(r.id) === String(id)) || {}).name || id;
    return h('div.act-l', null, idx.map(r => h('button.act', { type: 'button', style: { textAlign: 'start', background: 'var(--surface)' }, onclick: () => switchScope(r.scope, r.scope_id) },
      h('span.card-ic', { html: icon(r.scope === 'business_type' ? 'store' : r.scope === 'role' ? 'shield' : 'user') }),
      h('div', null, h('b', null, SCOPE_LABEL[r.scope] + ': ' + (r.scope === 'business_type' ? (BT[r.scope_id] || {}).label || r.scope_id : r.scope === 'role' ? roleName(r.scope_id) : 'משתמש #' + r.scope_id)),
        h('small', null, 'עודכן ' + F.date(r.updated_at))), h('span', { html: icon('arrow') }))));
  }

  /* ------------------------------------------------------------ keys */
  document.addEventListener('keydown', e => {
    const typing = /INPUT|SELECT|TEXTAREA/.test(document.activeElement.tagName);
    const mod = e.ctrlKey || e.metaKey;
    if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); save(); return; }
    if (typing || root.querySelector('.overlay.on')) return;
    if (mod && (e.key.toLowerCase() === 'z' || e.key === 'ז')) { e.preventDefault(); e.shiftKey ? redo() : undo(); return; }
    if (mod && (e.key.toLowerCase() === 'y' || e.key === 'ט')) { e.preventDefault(); redo(); return; }
    const it = st.items.find(i => i.id === st.selected); if (!it) return;
    if (e.key === 'Delete' || e.key === 'Backspace') { e.preventDefault(); remove(it.id); }
    else if (e.key === 'Escape') select(null);
    else if (e.key.startsWith('Arrow')) {
      e.preventDefault();
      const dx = e.key === 'ArrowLeft' ? 1 : e.key === 'ArrowRight' ? -1 : 0, dy = e.key === 'ArrowDown' ? 1 : e.key === 'ArrowUp' ? -1 : 0;
      commit(() => { const x = st.items.find(i => i.id === it.id); placeAt(st.items, x, x.x + dx, x.y + dy); });
    }
  });
  addEventListener('beforeunload', e => { if (st.dirty) { e.preventDefault(); e.returnValue = ''; } });
  canvas.addEventListener('pointerdown', e => { if (e.target === canvas) select(null); });

  /* ------------------------------------------------------------ go */
  fillScopeSelect(); renderChain(); renderSettings(); showTab('lib');
  loadScope();
})();
