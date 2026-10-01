/* ==========================================================================
   AuraTech Smart Dashboard – core (no dependencies)
   icons · formatting · API · SVG charts · widget renderers · UI helpers
   ========================================================================== */
(function (global) {
  'use strict';
  const SD = global.SD = global.SD || {};

  /* ------------------------------------------------------------------ DOM */
  const h = SD.h = function (tag, attrs, ...kids) {
    const m = /^([a-z0-9]+)((?:[.#][\w-]+)*)$/i.exec(tag) || [0, tag, ''];
    const el = document.createElement(m[1]);
    (m[2].match(/[.#][\w-]+/g) || []).forEach(t => t[0] === '.' ? el.classList.add(t.slice(1)) : (el.id = t.slice(1)));
    if (attrs) for (const k in attrs) {
      const v = attrs[k];
      if (v == null || v === false) continue;
      if (k === 'style' && typeof v === 'object') { for (const sk in v) sk.startsWith('--') ? el.style.setProperty(sk, v[sk]) : (el.style[sk] = v[sk]); }
      else if (k.startsWith('on')) el.addEventListener(k.slice(2), v);
      else if (k === 'class') String(v).split(/\s+/).filter(Boolean).forEach(c => el.classList.add(c));
      else if (k === 'html') el.innerHTML = v;
      else if (k === 'dataset') Object.assign(el.dataset, v);
      else el.setAttribute(k, v === true ? '' : v);
    }
    kids.flat(9).forEach(k => { if (k != null && k !== false) el.append(k instanceof Node ? k : document.createTextNode(String(k))); });
    return el;
  };
  const esc = SD.esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));

  /* ---------------------------------------------------------------- icons */
  const P = {
    store: 'M3 9l1.5-5h15L21 9M3 9h18M3 9a3 3 0 006 0 3 3 0 006 0 3 3 0 006 0M5 12v8h14v-8M10 20v-5h4v5',
    basket: 'M5 10h14l-1.5 9h-11L5 10zM9 10l3-6 3 6M3 10h18M10 14v2M14 14v2',
    cup: 'M4 8h13v5a6 6 0 01-6 6H10a6 6 0 01-6-6V8zM17 9h1.5a2.5 2.5 0 010 5H17M8 2v3M12 2v3',
    truck: 'M3 6h11v10H3zM14 9h4l3 3v4h-7M7.5 19a1.8 1.8 0 100-3.6 1.8 1.8 0 000 3.6zM17.5 19a1.8 1.8 0 100-3.6 1.8 1.8 0 000 3.6z',
    globe: 'M12 21a9 9 0 100-18 9 9 0 000 18zM3 12h18M12 3c2.5 2.7 3.8 5.7 3.8 9s-1.3 6.3-3.8 9c-2.5-2.7-3.8-5.7-3.8-9S9.5 5.7 12 3z',
    briefcase: 'M3 8h18v11H3zM8 8V5h8v3M3 13h18',
    sparkle: 'M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8zM19 16l.8 2.2L22 19l-2.2.8L19 22l-.8-2.2L16 19l2.2-.8z',
    bolt: 'M13 2L4 14h7l-1 8 9-12h-7z',
    cash: 'M2 7h20v10H2zM12 15a3 3 0 100-6 3 3 0 000 6zM6 10v4M18 10v4',
    trend: 'M3 17l6-6 4 4 8-8M15 7h6v6',
    'trend-up': 'M3 17l6-6 4 4 8-8M15 7h6v6',
    'trend-down': 'M3 7l6 6 4-4 8 8M15 17h6v-6',
    receipt: 'M5 3h14v18l-3-2-2 2-2-2-2 2-2-2-3 2zM9 8h6M9 12h6M9 16h3',
    users: 'M9 11a4 4 0 100-8 4 4 0 000 8zM2 21v-1a6 6 0 0112 0v1M16 3.3a4 4 0 010 7.4M22 21v-1a6 6 0 00-4-5.7',
    factory: 'M3 21V10l5 3V10l5 3V6h4v15zM3 21h18M17 6V3h3v18',
    wallet: 'M3 7a2 2 0 012-2h13v4M3 7v11a2 2 0 002 2h15V9H5a2 2 0 01-2-2zM16 14.5h.01',
    cart: 'M3 4h2l2.4 11h11L21 7H6.2M9 20a1 1 0 100-2 1 1 0 000 2zM18 20a1 1 0 100-2 1 1 0 000 2z',
    undo: 'M9 14L4 9l5-5M4 9h10a6 6 0 010 12h-3',
    redo: 'M15 14l5-5-5-5M20 9H10a6 6 0 000 12h3',
    target: 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 16a4 4 0 100-8 4 4 0 000 8zM12 12h.01',
    chart: 'M3 3v18h18M7 15l4-4 3 3 5-6',
    swap: 'M7 4L3 8l4 4M3 8h14M17 20l4-4-4-4M21 16H7',
    pie: 'M21 12A9 9 0 1112 3v9zM15 3.5A9 9 0 0120.5 9H15z',
    compare: 'M3 17l5-6 4 3 5-7 4 3M3 21h18',
    grid: 'M3 3h7v7H3zM14 3h7v7h-7zM3 14h7v7H3zM14 14h7v7h-7z',
    card: 'M2 6h20v12H2zM2 10h20M6 15h4',
    tag: 'M3 12V3h9l9 9-9 9zM7.5 7.5h.01',
    list: 'M8 6h13M8 12h13M8 18h13M3 6h.01M3 12h.01M3 18h.01',
    star: 'M12 3l2.8 5.7 6.2.9-4.5 4.4 1 6.2-5.5-2.9-5.5 2.9 1-6.2L3 9.6l6.2-.9z',
    clock: 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 7v5l3 2',
    alert: 'M12 3l10 18H2zM12 10v4M12 17.5h.01',
    hourglass: 'M6 3h12M6 21h12M7 3c0 5 5 6 5 9s-5 4-5 9M17 3c0 5-5 6-5 9s5 4 5 9',
    trophy: 'M7 4h10v5a5 5 0 01-10 0zM7 6H4a3 3 0 003 4M17 6h3a3 3 0 01-3 4M12 14v4M8 21h8M9 18h6',
    inbox: 'M3 13h5l1.5 3h5L16 13h5M5 5h14l2 8v6H3v-6z',
    cheque: 'M2 6h20v12H2zM6 10h7M6 14h4M15 14.5c1-2 2 1 3-1',
    doc: 'M6 2h9l5 5v15H6zM14 2v6h6M9 13h6M9 17h6',
    table: 'M3 10h18M5 10v10M19 10v10M8 10V6h8v4M3 10l2-4M21 10l-2-4',
    box: 'M21 8l-9-5-9 5v8l9 5 9-5zM3 8l9 5 9-5M12 13v8',
    rocket: 'M5 15c-1.5 1.5-2 5-2 5s3.5-.5 5-2M9 12l3 3M14.5 3.5C17 3 20 3 21 3c0 1 0 4-.5 6.5-1 4.5-6 8-6 8L9.5 12s3.5-5 5-8.5zM15 9h.01',
    percent: 'M19 5L5 19M6.5 9a2.5 2.5 0 100-5 2.5 2.5 0 000 5zM17.5 20a2.5 2.5 0 100-5 2.5 2.5 0 000 5z',
    pos: 'M4 3h16v8H4zM2 21h20l-2-10H4zM8 7h8M9 15h.01M12 15h.01M15 15h.01M9 18h6',
    invoice: 'M6 2h12v20l-3-2-3 2-3-2-3 2zM9 7h6M9 11h6M9 15h3',
    'user-plus': 'M9 11a4 4 0 100-8 4 4 0 000 8zM2 21v-1a6 6 0 0112 0v1M19 8v6M16 11h6',
    plus: 'M12 5v14M5 12h14',
    search: 'M11 19a8 8 0 100-16 8 8 0 000 16zM21 21l-4.3-4.3',
    refresh: 'M21 12a9 9 0 01-15.5 6.2L3 16M3 12a9 9 0 0115.5-6.2L21 8M21 3v5h-5M3 21v-5h5',
    sun: 'M12 17a5 5 0 100-10 5 5 0 000 10zM12 1v2M12 21v2M4.2 4.2l1.4 1.4M18.4 18.4l1.4 1.4M1 12h2M21 12h2M4.2 19.8l1.4-1.4M18.4 5.6l1.4-1.4',
    moon: 'M21 12.8A9 9 0 1111.2 3a7 7 0 009.8 9.8z',
    edit: 'M4 20h4L19 9l-4-4L4 16zM13.5 6.5l4 4',
    layout: 'M3 3h18v18H3zM3 9h18M9 9v12',
    x: 'M18 6L6 18M6 6l12 12',
    gear: 'M12 15a3 3 0 100-6 3 3 0 000 6zM19.4 15a1.7 1.7 0 00.3 1.8l.1.1a2 2 0 11-2.8 2.8l-.1-.1a1.7 1.7 0 00-1.8-.3 1.7 1.7 0 00-1 1.5V21a2 2 0 01-4 0v-.1a1.7 1.7 0 00-1.1-1.5 1.7 1.7 0 00-1.8.3l-.1.1a2 2 0 11-2.8-2.8l.1-.1a1.7 1.7 0 00.3-1.8 1.7 1.7 0 00-1.5-1H3a2 2 0 010-4h.1a1.7 1.7 0 001.5-1.1 1.7 1.7 0 00-.3-1.8l-.1-.1a2 2 0 112.8-2.8l.1.1a1.7 1.7 0 001.8.3H9a1.7 1.7 0 001-1.5V3a2 2 0 014 0v.1a1.7 1.7 0 001 1.5 1.7 1.7 0 001.8-.3l.1-.1a2 2 0 112.8 2.8l-.1.1a1.7 1.7 0 00-.3 1.8V9a1.7 1.7 0 001.5 1H21a2 2 0 010 4h-.1a1.7 1.7 0 00-1.5 1z',
    copy: 'M9 9h11v11H9zM5 15H4V4h11v1',
    trash: 'M3 6h18M8 6V4h8v2M6 6l1 15h10l1-15M10 11v6M14 11v6',
    external: 'M14 4h6v6M20 4l-9 9M18 14v6H4V6h6',
    expand: 'M15 3h6v6M9 21H3v-6M21 3l-7 7M3 21l7-7',
    download: 'M12 3v12M7 10l5 5 5-5M4 21h16',
    dots: 'M12 6h.01M12 12h.01M12 18h.01',
    check: 'M4 12l5 5L20 6',
    calendar: 'M3 5h18v16H3zM3 10h18M8 3v4M16 3v4',
    save: 'M5 3h11l4 4v14H5zM8 3v6h7M8 21v-7h8v7',
    magic: 'M15 4V2M15 16v-2M8 9h2M20 9h2M17.8 11.8L19 13M17.8 6.2L19 5M12.2 6.2L11 5M3 21l9-9',
    arrow: 'M19 12H5M11 18l-6-6 6-6',
    back: 'M5 12h14M13 6l6 6-6 6',
    eye: 'M1 12s4-7 11-7 11 7 11 7-4 7-11 7S1 12 1 12zM12 15a3 3 0 100-6 3 3 0 000 6z',
    info: 'M12 21a9 9 0 100-18 9 9 0 000 18zM12 11v6M12 7.5h.01',
    lock: 'M5 11h14v10H5zM8 11V7a4 4 0 018 0v4',
    user: 'M12 11a4 4 0 100-8 4 4 0 000 8zM4 21v-1a8 8 0 0116 0v1',
    shield: 'M12 3l8 3v6c0 5-3.5 8-8 9-4.5-1-8-4-8-9V6z',
    reset: 'M3 12a9 9 0 109-9 9.7 9.7 0 00-6.7 2.8L3 8M3 3v5h5',
    factory2: 'M3 21h18',
  };
  SD.icon = function (name, cls) {
    const d = P[name] || P.chart;
    return `<svg class="i ${cls || ''}" viewBox="0 0 24 24" aria-hidden="true"><path d="${d}"/></svg>`;
  };
  SD.iconEl = (name, cls) => { const t = document.createElement('template'); t.innerHTML = SD.icon(name, cls); return t.content.firstChild; };

  /* ------------------------------------------------------------ formatting */
  const nf0 = new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 });
  const nf2 = new Intl.NumberFormat('en-US', { maximumFractionDigits: 2 });
  const MONTHS = ['ינו׳', 'פבר׳', 'מרץ', 'אפר׳', 'מאי', 'יוני', 'יולי', 'אוג׳', 'ספט׳', 'אוק׳', 'נוב׳', 'דצמ׳'];
  const DAYS = ['א׳', 'ב׳', 'ג׳', 'ד׳', 'ה׳', 'ו׳', 'ש׳'];
  const F = SD.fmt = {
    money: v => (v < 0 ? '-' : '') + '₪' + nf0.format(Math.abs(+v || 0)),
    money2: v => (v < 0 ? '-' : '') + '₪' + nf2.format(Math.abs(+v || 0)),
    compact(v, cur) {
      const a = Math.abs(+v || 0), s = v < 0 ? '-' : '', p = cur ? '₪' : '';
      if (a >= 1e6) return s + p + (a / 1e6).toFixed(a >= 1e7 ? 0 : 1).replace(/\.0$/, '') + 'M';
      if (a >= 1e4) return s + p + Math.round(a / 1e3) + 'K';
      if (a >= 1e3) return s + p + (a / 1e3).toFixed(1).replace(/\.0$/, '') + 'K';
      return s + p + nf0.format(a);
    },
    num: v => nf0.format(+v || 0),
    qty: v => nf2.format(+v || 0),
    pct: v => (v > 0 ? '+' : '') + nf2.format(v) + '%',
    date(s) {
      if (!s) return '—';
      const [d, t] = String(s).split(' '); const [y, m, dd] = d.split('-');
      const today = new Date(); const iso = today.getFullYear() + '-' + String(today.getMonth() + 1).padStart(2, '0') + '-' + String(today.getDate()).padStart(2, '0');
      if (d === iso && t) return 'היום ' + t.slice(0, 5);
      return `${+dd}/${+m}/${String(y).slice(2)}` + (t ? ' ' + t.slice(0, 5) : '');
    },
    days: v => v == null ? '—' : v < 0 ? 'פג לפני ' + (-v === 1 ? 'יום' : -v + ' ימים') : (v === 0 ? 'היום' : v === 1 ? 'יום' : v + ' ימים'),
    bucket(k, g) {
      if (g === 'hour' || typeof k === 'number' || /^\d{1,2}$/.test(k)) return String(k).padStart(2, '0') + ':00';
      if (/^\d{4}-\d{2}$/.test(k)) return MONTHS[+k.slice(5) - 1] + ' ' + k.slice(2, 4);
      if (/^\d{4}-\d{2}-\d{2}$/.test(k)) { const [, m, d] = k.split('-'); return `${+d}/${+m}`; }
      return String(k);
    },
    bucketLong(k, g) {
      if (/^\d{4}-\d{2}-\d{2}$/.test(k)) { const dt = new Date(k + 'T12:00:00'); return 'יום ' + DAYS[dt.getDay()] + ' ' + F.bucket(k); }
      return F.bucket(k, g);
    },
    value(v, format) {
      switch (format) {
        case 'currency': return F.money(v);
        case 'number': return F.num(v);
        case 'qty': return F.qty(v);
        case 'percent': return nf2.format(v) + '%';
        case 'days': return F.days(v);
        case 'date': return F.date(v);
        default: return v == null ? '—' : String(v);
      }
    },
    ago(ts) {
      const s = Math.round((Date.now() - ts) / 1000);
      if (s < 45) return 'עכשיו';
      if (s < 3600) return 'לפני ' + Math.round(s / 60) + ' דק׳';
      return 'לפני ' + Math.round(s / 3600) + ' שע׳';
    },
    minutes: m => m < 60 ? m + ' דק׳' : Math.floor(m / 60) + ':' + String(m % 60).padStart(2, '0') + ' שע׳',
  };

  /* ---------------------------------------------------------------- colors */
  const COLOR_NAMES = ['primary', 'purple', 'orange', 'green', 'red', 'teal', 'blue', 'amber', 'indigo', 'slate'];
  SD.color = function (name, i) {
    const n = name && COLOR_NAMES.includes(name) ? name : COLOR_NAMES[(i || 0) % COLOR_NAMES.length];
    return getComputedStyle(SD.root || document.documentElement).getPropertyValue('--' + n).trim() || '#3d5afe';
  };
  SD.palette = i => SD.color(null, i);

  /* ------------------------------------------------------------------- API */
  SD.api = async function (path, opts = {}) {
    const u = SD.urls || {};
    if (SD.mockApi) return SD.mockApi(path, opts);
    const url = (u.api || '') + '/' + path.replace(/^\//, '');
    const init = { method: opts.method || (opts.body ? 'POST' : 'GET'), headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, credentials: 'same-origin' };
    if (opts.body) { init.headers['Content-Type'] = 'application/json'; init.body = JSON.stringify(opts.body); }
    if (u.csrf) init.headers['X-CSRF-TOKEN'] = u.csrf;
    const r = await fetch(url + (opts.query ? '?' + new URLSearchParams(opts.query) : ''), init);
    const j = await r.json().catch(() => ({}));
    if (!r.ok) throw new Error(j.error || j.message || ('HTTP ' + r.status));
    return j;
  };
  SD.link = url => !url ? null : /^https?:/.test(url) ? url : ((SD.urls && SD.urls.base) || '') + url;

  /* ---------------------------------------------------------- UI helpers */
  SD.toast = function (msg, err) {
    let t = SD.root.querySelector('.toast');
    if (!t) { t = h('div.toast'); SD.root.append(t); }
    t.innerHTML = SD.icon(err ? 'alert' : 'check') + esc(msg);
    t.classList.toggle('err', !!err); t.classList.add('on');
    clearTimeout(t._t); t._t = setTimeout(() => t.classList.remove('on'), 2600);
  };

  SD.menu = function (anchor, items) {
    SD.closeMenu();
    const m = h('div.menu', { role: 'menu' });
    items.forEach(it => {
      if (it === '-') return m.append(h('hr'));
      const el = it.href ? h('a', { href: it.href, role: 'menuitem' }) : h('button', { type: 'button', role: 'menuitem', onclick: () => { SD.closeMenu(); it.run(); } });
      el.innerHTML = SD.icon(it.icon) + esc(it.label);
      if (it.danger) el.classList.add('tone-danger');
      m.append(el);
    });
    SD.root.append(m);
    const r = anchor.getBoundingClientRect();
    const mw = m.offsetWidth, mh = m.offsetHeight;
    m.style.top = Math.min(r.bottom + 6, innerHeight - mh - 8) + 'px';
    m.style.left = Math.max(8, Math.min(r.left, innerWidth - mw - 8)) + 'px';
    setTimeout(() => document.addEventListener('pointerdown', SD._menuAway = e => { if (!m.contains(e.target)) SD.closeMenu(); }), 0);
    SD._menu = m;
  };
  SD.closeMenu = () => { if (SD._menu) { SD._menu.remove(); SD._menu = null; document.removeEventListener('pointerdown', SD._menuAway); } };

  SD.modal = function ({ title, body, foot, wide, onClose }) {
    const ov = h('div.overlay.on');
    const close = () => { ov.remove(); document.removeEventListener('keydown', key); onClose && onClose(); };
    const key = e => { if (e.key === 'Escape') close(); };
    const md = h('div.modal' + (wide ? '.wide' : ''), { role: 'dialog', 'aria-modal': 'true' },
      h('div.modal-h', null, h('h3', null, title), h('button.btn.ghost.icon.sm', { type: 'button', 'aria-label': 'סגור', onclick: close, html: SD.icon('x') })),
      h('div.modal-b', null, body), foot ? h('div.modal-f', null, foot) : null);
    ov.append(md);
    ov.addEventListener('pointerdown', e => { if (e.target === ov) close(); });
    document.addEventListener('keydown', key);
    SD.root.append(ov);
    return { close, el: md };
  };
  SD.confirm = (title, text, okLabel, danger) => new Promise(res => {
    let done = false;
    const ok = h('button.btn' + (danger ? '.danger' : '.primary'), { type: 'button', onclick: () => { done = true; m.close(); res(true); } }, okLabel || 'אישור');
    const m = SD.modal({ title, body: h('p', { style: { margin: 0, color: 'var(--text-2)' } }, text),
      foot: [h('button.btn', { type: 'button', onclick: () => m.close() }, 'ביטול'), ok], onClose: () => { if (!done) res(false); } });
    ok.focus();
  });

  /* ================================================================ CHARTS */
  const NS = 'http://www.w3.org/2000/svg';
  const s = (tag, attrs) => { const e = document.createElementNS(NS, tag); for (const k in attrs) e.setAttribute(k, attrs[k]); return e; };

  function niceScale(min, max, ticks) {
    if (min === max) { max = max === 0 ? 1 : max * 1.2; min = Math.min(0, min); }
    const range = max - min, rough = range / ticks;
    const pow = Math.pow(10, Math.floor(Math.log10(rough))), n = rough / pow;
    const step = (n <= 1 ? 1 : n <= 2 ? 2 : n <= 2.5 ? 2.5 : n <= 5 ? 5 : 10) * pow;
    return { min: Math.floor(min / step) * step, max: Math.ceil(max / step) * step, step };
  }

  /** Monotone cubic (Fritsch–Carlson): smooth, but never overshoots the data (no fake dips below zero). */
  function smoothPath(pts) {
    const n = pts.length;
    if (n < 3) return pts.map((p, i) => (i ? 'L' : 'M') + p[0] + ',' + p[1]).join('');
    const dx = [], dy = [], m = [], t = [];
    for (let i = 0; i < n - 1; i++) { dx[i] = pts[i + 1][0] - pts[i][0]; dy[i] = pts[i + 1][1] - pts[i][1]; m[i] = dy[i] / (dx[i] || 1); }
    t[0] = m[0]; t[n - 1] = m[n - 2];
    for (let i = 1; i < n - 1; i++) t[i] = m[i - 1] * m[i] <= 0 ? 0 : 3 * (dx[i - 1] + dx[i]) / ((2 * dx[i] + dx[i - 1]) / m[i - 1] + (dx[i] + 2 * dx[i - 1]) / m[i]);
    let d = 'M' + pts[0][0] + ',' + pts[0][1];
    for (let i = 0; i < n - 1; i++) {
      const h3 = dx[i] / 3;
      d += `C${pts[i][0] + h3},${pts[i][1] + t[i] * h3} ${pts[i + 1][0] - h3},${pts[i + 1][1] - t[i + 1] * h3} ${pts[i + 1][0]},${pts[i + 1][1]}`;
    }
    return d;
  }

  /** Line / area / column / mixed chart. cfg: {categories, series:[{name,data,color,type}], kind, format, granularity} */
  SD.xyChart = function (wrap, cfg) {
    const box = h('div.chart'); const tip = h('div.tip'); box.append(tip);
    const hidden = new Set();
    const series = cfg.series.map((sr, i) => ({ ...sr, col: SD.color(sr.color, i), type: sr.type || (cfg.kind === 'bar' ? 'column' : cfg.kind === 'line' ? 'line' : cfg.kind === 'mixed' ? 'column' : 'area') }));
    if (cfg.legend !== false && series.length > 1) {
      const lg = h('div.legend');
      series.forEach((sr, i) => {
        const tot = sr.total != null ? sr.total : null;
        const sp = h('span', { style: { '--c': sr.col }, onclick: () => { hidden.has(i) ? hidden.delete(i) : hidden.add(i); if (hidden.size === series.length) hidden.delete(i); sp.classList.toggle('off', hidden.has(i)); draw(); } },
          h('i'), sr.name, tot != null ? h('b', null, F.value(tot, cfg.format)) : null);
        lg.append(sp);
      });
      wrap.append(lg);
    }
    wrap.append(box);
    const fmtAxis = v => cfg.format === 'currency' ? F.compact(v, true) : F.compact(v);
    let geo = null;

    function draw() {
      [...box.querySelectorAll('svg')].forEach(x => x.remove());
      const W = box.clientWidth, H = box.clientHeight;
      if (W < 40 || H < 40) return;
      const vis = series.filter((_, i) => !hidden.has(i));
      const all = vis.flatMap(sr => sr.data.map(Number));
      const sc = niceScale(Math.min(0, ...all), Math.max(0, ...all), H < 160 ? 3 : 4);
      const pl = Math.max(...[sc.min, sc.max].map(v => fmtAxis(v).length)) * 6.4 + 10, pr = 8, pt = 8, pb = 22;
      const iw = W - pl - pr, ih = H - pt - pb, n = cfg.categories.length;
      const y = v => pt + ih - (v - sc.min) / (sc.max - sc.min) * ih;
      const cols = vis.filter(sr => sr.type === 'column');
      const band = iw / Math.max(1, n);
      const x = i => cols.length ? pl + band * i + band / 2 : pl + (n <= 1 ? iw / 2 : iw * i / (n - 1));
      const svg = s('svg', { viewBox: `0 0 ${W} ${H}`, role: 'img' });
      // grid + y labels
      for (let v = sc.min; v <= sc.max + sc.step / 2; v += sc.step) {
        const yy = Math.round(y(v)) + .5;
        svg.append(s('line', { x1: pl, x2: W - pr, y1: yy, y2: yy, class: 'grid-l' }));
        const t = s('text', { x: pl - 8, y: yy + 3.5, 'text-anchor': 'end', class: 'axis-t' }); t.textContent = fmtAxis(v); svg.append(t);
      }
      // x labels (thinned)
      const maxLabels = Math.max(2, Math.floor(iw / 58));
      const every = Math.ceil(n / maxLabels);
      cfg.categories.forEach((c, i) => {
        if (i % every && i !== n - 1) return;
        if (i === n - 1 && i % every && (n - 1) % every < every * 0.6) return;
        const t = s('text', { x: x(i), y: H - 5, 'text-anchor': 'middle', class: 'axis-t' }); t.textContent = F.bucket(c, cfg.granularity); svg.append(t);
      });
      // columns
      if (cols.length) {
        const gw = Math.min(band * .72, 16 * cols.length + 8), bw = Math.max(2, gw / cols.length - 2);
        cols.forEach((sr, si) => {
          sr.data.forEach((v, i) => {
            const x0 = pl + band * i + (band - gw) / 2 + si * (bw + 2), y0 = y(Math.max(0, v)), y1 = y(Math.min(0, v));
            const r = s('rect', { x: x0, y: y0, width: bw, height: Math.max(0, y1 - y0), rx: Math.min(4, bw / 2), fill: sr.col, opacity: .9 });
            svg.append(r);
          });
        });
      }
      // lines / areas
      const zeroY = y(Math.max(sc.min, 0));
      const uid = 'g' + Math.random().toString(36).slice(2, 8);
      vis.filter(sr => sr.type !== 'column').forEach((sr, si) => {
        const pts = sr.data.map((v, i) => [x(i), y(+v)]);
        const d = smoothPath(pts);
        if (sr.type === 'area') {
          const gid = uid + si; const g = s('linearGradient', { id: gid, x1: 0, x2: 0, y1: 0, y2: 1 });
          g.append(s('stop', { offset: '0%', 'stop-color': sr.col, 'stop-opacity': si === 0 ? .28 : .12 }), s('stop', { offset: '100%', 'stop-color': sr.col, 'stop-opacity': 0 }));
          const defs = s('defs', {}); defs.append(g); svg.append(defs);
          svg.append(s('path', { d: d + `L${pts[pts.length - 1][0]},${zeroY}L${pts[0][0]},${zeroY}Z`, fill: `url(#${gid})` }));
        }
        const path = s('path', { d, fill: 'none', stroke: sr.col, 'stroke-width': si === 0 ? 2.4 : 1.8, 'stroke-linecap': 'round', 'stroke-dasharray': sr.dashed ? '5 4' : 'none' });
        svg.append(path);
        if (n <= 14) pts.forEach(p => svg.append(s('circle', { cx: p[0], cy: p[1], r: 2.6, fill: 'var(--surface)', stroke: sr.col, 'stroke-width': 1.8 })));
      });
      const hl = s('line', { x1: 0, x2: 0, y1: pt, y2: pt + ih, class: 'hover-l', opacity: 0 });
      svg.append(hl);
      box.prepend(svg);
      geo = { pl, iw, n, x, band, cols: cols.length, hl, vis, W };
    }

    box.addEventListener('pointermove', e => {
      if (!geo) return;
      const r = box.getBoundingClientRect(); const mx = e.clientX - r.left;
      const i = geo.cols ? Math.floor((mx - geo.pl) / geo.band) : Math.round((mx - geo.pl) / (geo.iw / Math.max(1, geo.n - 1)));
      if (i < 0 || i >= geo.n) { tip.classList.remove('on'); geo.hl.setAttribute('opacity', 0); return; }
      const xx = geo.x(i);
      geo.hl.setAttribute('x1', xx); geo.hl.setAttribute('x2', xx); geo.hl.setAttribute('opacity', 1);
      tip.innerHTML = `<div class="h">${esc(cfg.tipLabel ? cfg.tipLabel(cfg.categories[i]) : F.bucketLong(cfg.categories[i], cfg.granularity))}</div>` +
        geo.vis.map(sr => `<div class="r" style="--c:${sr.col}"><span><i></i>${esc(sr.name)}</span><b>${esc(F.value(sr.data[i], cfg.format))}</b></div>`).join('');
      tip.classList.add('on');
      const tw = tip.offsetWidth;
      tip.style.left = (xx + 14 + tw > geo.W ? xx - tw - 14 : xx + 14) + 'px';
      tip.style.top = Math.max(0, e.clientY - r.top - tip.offsetHeight / 2) + 'px';
    });
    box.addEventListener('pointerleave', () => { tip.classList.remove('on'); geo && geo.hl.setAttribute('opacity', 0); });
    SD.observeSize(box, draw);
  };

  SD.donut = function (wrap, { labels, values, colors, format, center, counts }) {
    const total = values.reduce((a, b) => a + (+b || 0), 0);
    if (!total) return wrap.append(emptyEl('אין נתונים לתקופה'));
    const cols = labels.map((_, i) => SD.color(colors && colors[i], i));
    const el = h('div.donut'); const ring = h('div.ring'); const ul = h('ul');
    const R = 42, C = 2 * Math.PI * R;
    const svg = s('svg', { viewBox: '0 0 100 100' });
    svg.append(s('circle', { cx: 50, cy: 50, r: R, fill: 'none', stroke: 'var(--surface-3)', 'stroke-width': 11 }));
    const cB = h('b'), cS = h('small');
    const setCenter = i => { cS.textContent = i == null ? (center || 'סה״כ') : labels[i]; cB.textContent = F.value(i == null ? total : values[i], format || 'currency'); };
    let off = 0; const paths = [];
    values.forEach((v, i) => {
      const frac = (+v || 0) / total; if (frac <= 0) return;
      const gap = values.length > 1 ? Math.min(1.2, frac * C * .3) : 0;
      const c = s('circle', { cx: 50, cy: 50, r: R, fill: 'none', stroke: cols[i], 'stroke-width': 11, 'stroke-dasharray': `${Math.max(0.01, frac * C - gap)} ${C}`, 'stroke-dashoffset': -off, 'stroke-linecap': 'butt' });
      c.style.transition = 'stroke-width .15s, opacity .15s'; c.style.cursor = 'pointer';
      off += frac * C; svg.append(c); paths[i] = c;
    });
    const hl = i => {
      paths.forEach((p, j) => p && (p.style.opacity = i == null || i === j ? 1 : .3, p.setAttribute('stroke-width', i === j ? 13 : 11)));
      [...ul.children].forEach((li, j) => li.classList.toggle('hl', i === j)); setCenter(i);
    };
    paths.forEach((p, i) => p && (p.addEventListener('pointerenter', () => hl(i)), p.addEventListener('pointerleave', () => hl(null))));
    ring.append(svg, h('div.center', null, cS, cB));
    labels.forEach((l, i) => {
      const li = h('li', { style: { '--c': cols[i] }, onpointerenter: () => hl(i), onpointerleave: () => hl(null) },
        h('i'), h('span', { title: l }, l), h('b', null, F.value(values[i], format || 'currency'), h('em', null, Math.round(values[i] / total * 100) + '%')));
      if (counts && counts[i] != null) li.title = counts[i] + ' עסקאות';
      ul.append(li);
    });
    setCenter(null);
    el.append(ring, ul); wrap.append(el);
  };

  SD.hbar = function (wrap, { categories, series, format }) {
    const data = series[0].data; const max = Math.max(...data.map(Number), 1);
    const el = h('div.hbar');
    categories.forEach((c, i) => el.append(h('div.r', null, h('span', { title: c }, c),
      h('div.bar', null, h('i', { style: { width: (data[i] / max * 100) + '%', animationDelay: (i * 40) + 'ms' } })),
      h('b', null, F.value(data[i], format)))));
    wrap.append(el);
  };

  SD.spark = function (values, color, good) {
    if (!values || values.length < 2) return null;
    const W = 200, H = 50, max = Math.max(...values), min = Math.min(...values), rg = max - min || 1;
    const pts = values.map((v, i) => [i / (values.length - 1) * W, H - 4 - (v - min) / rg * (H - 10)]);
    const d = smoothPath(pts);
    const id = 'sp' + Math.random().toString(36).slice(2, 7);
    return `<svg class="spark" viewBox="0 0 ${W} ${H}" preserveAspectRatio="none"><defs><linearGradient id="${id}" x1="0" x2="0" y1="0" y2="1"><stop offset="0" stop-color="${color}" stop-opacity=".22"/><stop offset="1" stop-color="${color}" stop-opacity="0"/></linearGradient></defs><path d="${d}L${W},${H}L0,${H}Z" fill="url(#${id})"/><path d="${d}" fill="none" stroke="${color}" stroke-width="1.6" vector-effect="non-scaling-stroke" opacity=".85"/></svg>`;
  };

  SD.observeSize = function (el, fn) {
    let last = '', raf;
    const ro = new ResizeObserver(() => {
      const k = el.clientWidth + 'x' + el.clientHeight; if (k === last) return; last = k;
      cancelAnimationFrame(raf); raf = requestAnimationFrame(fn);
    });
    ro.observe(el);
  };

  /* ============================================================= RENDERERS */
  function emptyEl(text, icon) { const e = h('div.empty'); e.innerHTML = SD.icon(icon || 'inbox') + `<div>${esc(text)}</div>`; return e; }
  SD.emptyEl = emptyEl;

  function tableEl(columns, rows, opts = {}) {
    const wrap = h('div.tbl-wrap');
    const t = h('table.t');
    const cols = columns.concat(opts.extraAmount && !columns.some(c => c.key === 'amount') ? [{ key: 'amount', label: 'סכום', format: 'currency', align: 'end' }] : []);
    t.append(h('thead', null, h('tr', null, cols.map(c => h('th', { class: c.align === 'end' ? 'end' : null }, c.label)))));
    const tb = h('tbody');
    rows.forEach(r => {
      const tr = h('tr');
      cols.forEach(c => {
        const td = h('td', { class: c.align === 'end' ? 'end' : null });
        const v = r[c.key];
        if (c.key === 'rank') td.append(h('span.rank', null, v));
        else if (c.format === 'badge') { if (v) td.append(h('span.badge.' + (r.status_tone || 'neutral'), null, v)); }
        else {
          const txt = F.value(v, c.format);
          const tone = c.toneKey && r[c.toneKey] && r[c.toneKey] !== 'neutral' ? 'tone-' + r[c.toneKey] : null;
          td.append(tone ? h('b', { class: tone }, txt) : txt);
          if (c.sub && r[c.sub]) td.append(h('span.sub.num', null, r[c.sub]));
          if (c.key === 'name' && r.share != null) td.append(h('div.share', null, h('i', { style: { width: Math.min(100, r.share) + '%' } })));
        }
        tr.append(td);
      });
      tb.append(tr);
    });
    t.append(tb); wrap.append(t);
    return wrap;
  }

  const R = SD.renderers = {
    kpi(body, d, ctx) {
      const up = d.delta == null ? null : d.delta >= 0;
      const good = up == null ? null : (d.good === 'down' ? !up : up);
      const k = h('div.kpi');
      const val = d.format === 'currency' ? F.money(d.value) : d.format === 'percent' ? F.qty(d.value) + '%' : F.num(d.value);
      k.append(
        h('div.kpi-top', { html: `<span class="card-ic c-${ctx.tone || 'primary'}">${SD.icon(ctx.meta.icon)}</span><span class="t">${esc(ctx.title)}</span>` }),
        h('div.kpi-v', { title: F.value(d.value, d.format) }, val),
        h('div.kpi-d', null,
          d.delta == null ? (d.previous === null && d.value > 0 && d.good !== 'down' && d.sub == null ? h('span.badge.info', null, 'חדש') : null)
            : h('span.badge.' + (Math.abs(d.delta) < 0.5 ? 'neutral' : good ? 'success' : 'danger'), null, (up ? '▲ ' : '▼ ') + Math.abs(d.delta) + '%'),
          d.sub ? h('span.sub', { title: d.sub }, d.sub) : (d.previous != null ? h('span.sub', null, 'קודם: ' + F.value(d.previous, d.format)) : null)));
      const sp = SD.spark(d.spark, SD.color(good === false ? 'red' : ctx.tone || 'primary'));
      if (sp) k.insertAdjacentHTML('beforeend', sp);
      body.append(k);
    },
    actions(body, d) {
      const g = h('div.qa', { style: { '--n': d.actions.length } });
      d.actions.forEach(a => g.append(h('a', { href: SD.link(a.url), html: `<span class="qi c-${esc(a.color || 'primary')}">${SD.icon(a.icon)}</span><span>${esc(a.label)}</span>` })));
      body.append(g);
    },
    insights(body, d) {
      if (!d.items.length) return body.append(emptyEl('הכול תקין – אין התראות כרגע', 'check'));
      const counts = {}; d.items.forEach(i => counts[i.level] = (counts[i.level] || 0) + 1);
      const L = { critical: ['דחוף', 'danger'], warning: ['לתשומת לב', 'warning'], success: ['חיובי', 'success'], info: ['מידע', 'info'] };
      body.append(h('div.ins-sum', null, Object.keys(L).filter(k => counts[k]).map(k => h('span.badge.' + L[k][1], null, counts[k] + ' ' + L[k][0]))));
      const l = h('div.ins');
      d.items.forEach(i => {
        const el = h('div.ins-i.lv-' + i.level, { html: `<span class="ii">${SD.icon(i.icon)}</span><div><b>${esc(i.title)}</b><p>${esc(i.text)}</p></div>` });
        if (i.action) el.lastChild.append(h('a.act', { href: SD.link(i.action.url), html: esc(i.action.label) + SD.icon('arrow') }));
        l.append(el);
      });
      body.append(l);
      if (d.total > d.items.length) body.append(h('div', { style: { fontSize: '12px', color: 'var(--muted)', marginTop: '8px' } }, `ועוד ${d.total - d.items.length} תובנות – הגדל את הווידג׳ט להצגת הכול`));
    },
    progress(body, d) {
      const w = h('div.tg');
      d.items.forEach(it => {
        const pct = Math.round(it.pct || 0), done = pct >= 100;
        const fpct = it.target ? Math.min(100, (it.forecast || 0) / it.target * 100) : 0;
        w.append(h('div.row', null,
          h('div.top', null, h('b', null, it.label), h('span.pct', { class: done ? 'tone-success' : '' }, pct + '%')),
          h('div.track', null, h('i', { class: done ? 'done' : '', style: { width: Math.min(100, pct) + '%' } }), fpct && !done ? h('em', { style: { right: fpct + '%' }, title: 'צפי' }) : null),
          h('div.meta', null, h('span', null, F.money(it.actual) + ' מתוך ' + F.money(it.target)),
            it.needed_per_day ? h('span', null, 'נדרש ' + F.money(it.needed_per_day) + ' ליום') : it.forecast ? h('span', null, 'צפי: ' + F.money(it.forecast)) : null)));
      });
      if (d.auto) w.append(h('div.note', { style: { padding: '7px 10px', fontSize: '12px' } }, 'יעד אוטומטי לפי החודש הקודם. ניתן לקבוע יעד בעמוד העריכה ← הגדרות.'));
      body.append(w);
    },
    chart(body, d) {
      if (d.kind === 'donut') return SD.donut(body, d);
      if (d.kind === 'hbar') return d.categories.length ? SD.hbar(body, d) : body.append(emptyEl('אין נתונים לתקופה'));
      const has = d.series.some(s => s.data.some(v => +v !== 0));
      if (!has) return body.append(emptyEl('אין נתונים לתקופה', 'chart'));
      if (d.totals) {
        body.append(h('div.summary', null,
          h('div.s', null, h('small', null, 'התקבל'), h('b.num.tone-success', null, F.compact(d.totals.in, true))),
          h('div.s', null, h('small', null, 'שולם'), h('b.num.tone-danger', null, F.compact(d.totals.out, true))),
          h('div.s', null, h('small', null, 'נטו'), h('b.num', { class: d.totals.net >= 0 ? 'tone-success' : 'tone-danger' }, F.compact(d.totals.net, true)))));
      }
      // revenue as an area, sparse amounts (purchases / expenses) as bars underneath
      if (d.kind === 'area' && d.series.length > 1) d = { ...d, series: d.series.map((s, i) => i ? { ...s, type: 'column' } : { ...s, type: 'area' }) };
      SD.xyChart(body, d);
    },
    compare(body, d) {
      if (!d.series.length) return body.append(emptyEl('אין נתונים'));
      const cols = ['primary', 'purple', 'orange', 'teal', 'slate'];
      SD.xyChart(body, { ...d, granularity: d.mode === 'hour' ? 'hour' : null, series: d.series.map((s, i) => ({ ...s, name: /^\d{4}-\d{2}(-\d{2})?$/.test(s.name) ? F.bucketLong(s.name) : s.name, color: cols[i], type: 'line', dashed: i > 0 })),
        tipLabel: d.mode === 'hour' ? c => String(c).padStart(2, '0') + ':00' : d.mode === 'day' ? c => 'יום ' + c : null });
    },
    heatmap(body, d) {
      const max = Math.max(1, ...d.rows.flatMap(r => r.data));
      if (d.peak) body.append(h('div.heat-note', { html: SD.icon('clock') + `שעת השיא: <b>יום ${esc(d.peak.day)} ${String(d.peak.hour).padStart(2, '0')}:00</b>` }));
      const g = h('div.heat', { style: { '--n': d.hours.length, gridTemplateRows: `repeat(${d.rows.length}, 1fr) auto` } });
      d.rows.forEach(r => {
        const row = h('div.row');
        r.data.forEach((v, i) => row.append(h('span.c', { style: { opacity: (0.08 + 0.92 * v / max).toFixed(2) }, title: `${r.name} ${d.hours[i]}:00 · ${F.value(v, d.format)}` })));
        row.append(h('span.dn', null, r.name));
        g.append(row);
      });
      const hr = h('div.row');
      d.hours.forEach((x, i) => hr.append(h('span.hd', null, i % 2 ? '' : x)));
      hr.append(h('span'));
      g.append(hr);
      body.append(g);
    },
    table(body, d) {
      if (d.summary) body.append(h('div.summary', null, d.summary.map(s => h('div.s', null, h('small', null, s.label), h('b', null, s.count, ' ', h('small', { style: { display: 'inline' } }, '· ' + F.money(s.value)))))));
      if (d.total != null && !d.summary) body.append(h('div.summary', null, h('div.s', null, h('small', null, 'סה״כ בטווח'), h('b.num', null, F.money(d.total)))));
      if (!d.rows.length) return body.append(emptyEl(d.empty || 'אין נתונים'));
      body.append(tableEl(d.columns, d.rows));
    },
    'tabs-table'(body, d, ctx) {
      const tabs = h('div.tabs', { role: 'tablist' }); const holder = h('div', { style: { flex: 1, minHeight: 0, display: 'flex', flexDirection: 'column' } });
      const show = i => {
        [...tabs.children].forEach((b, j) => b.classList.toggle('on', i === j)); holder.innerHTML = ''; ctx.state.tab = i;
        const t = d.tabs[i];
        t.rows.length ? holder.append(tableEl(d.columns, t.rows, { extraAmount: true })) : holder.append(emptyEl('אין רשומות'));
        ctx.setLink && ctx.setLink(t.link);
      };
      d.tabs.forEach((t, i) => tabs.append(h('button', { type: 'button', role: 'tab', onclick: () => show(i) }, t.label)));
      body.append(tabs, holder); show(Math.min(ctx.state.tab || 0, d.tabs.length - 1));
    },
    aging(body, d) {
      const cols = ['green', 'amber', 'orange', 'red'].map(c => SD.color(c));
      body.append(h('div.aging-total', null, h('b', null, F.money(d.total)), h('span', { style: { color: 'var(--muted)', fontSize: '13px' } }, d.count + ' ' + (d.side === 'suppliers' ? 'ספקים' : 'לקוחות') + ' עם יתרה')));
      if (!d.total) return body.append(emptyEl('אין יתרות פתוחות 🎉', 'check'));
      body.append(h('div.aging-bar', null, d.buckets.map((b, i) => b.value > 0 ? h('i', { style: { flex: b.value, background: cols[i] }, title: b.label + ': ' + F.money(b.value) }) : null)));
      body.append(h('div.aging-legend', null, d.buckets.map((b, i) => h('div', { style: { '--c': cols[i] } }, b.label, h('b', null, F.compact(b.value, true))))));
      if (d.rows && d.rows.length) body.append(tableEl([{ key: 'name', label: d.side === 'suppliers' ? 'ספק' : 'לקוח' }, { key: 'oldest', label: 'ותק חוב', format: 'days', align: 'end' }, { key: 'due', label: 'יתרה', format: 'currency', align: 'end' }],
        d.rows.map(r => ({ ...r, tone: r.oldest > 90 ? 'danger' : r.oldest > 60 ? 'warning' : 'neutral' })).map(r => r)));
    },
    pipeline(body, d) {
      body.append(h('div.pipe', null, d.stages.map(st => h('div.st', { class: st.final ? 'final' : '' }, h('small', null, st.label), h('b', null, F.num(st.count)), st.value != null ? h('div.v', null, F.money(st.value)) : null))));
      if (d.late && d.late.length) {
        body.append(h('div.late-h', { html: SD.icon('alert') + `${d.late.length} מתעכבים` }));
        body.append(h('div.late', null, d.late.map(l => h('div', null, h('span.num', null, l.reference), h('span', null, l.status), h('span', null, F.date(l.date))))));
      }
    },
    tiles(body, d) {
      if (!d.tiles.length) return body.append(emptyEl('אין שולחנות פתוחים כרגע', 'table'));
      body.append(h('div.tiles', null, d.tiles.map(t => h('div.tile', { class: t.tone === 'warning' ? 'warning' : '' },
        h('b', null, t.name), h('div.amt', null, F.money(t.amount)), h('small', { class: 'tone-' + t.tone, html: SD.icon('clock') + esc(F.minutes(t.minutes)) })))));
    },
  };

  /** CSV export of whatever a widget currently shows. */
  SD.toCsv = function (meta, d, state) {
    let rows = [];
    if (!d) return '';
    if (d.rows && d.columns) rows = [d.columns.map(c => c.label), ...d.rows.map(r => d.columns.map(c => r[c.key]))];
    else if (d.tabs) { const t = d.tabs[state.tab || 0]; rows = [[...d.columns.map(c => c.label), 'סכום'], ...t.rows.map(r => [...d.columns.map(c => r[c.key]), r.amount])]; }
    else if (d.categories && d.series) rows = [['', ...d.series.map(s => s.name)], ...d.categories.map((c, i) => [c, ...d.series.map(s => s.data[i])])];
    else if (d.labels && d.values) rows = [['', 'ערך'], ...d.labels.map((l, i) => [l, d.values[i]])];
    else if (d.rows && d.hours) rows = [['', ...d.hours], ...d.rows.map(r => [r.name, ...r.data])];
    else if (d.items) rows = d.items.map(i => [i.label || i.title, i.actual ?? i.text, i.target ?? '']);
    else if (d.buckets) rows = [...d.buckets.map(b => [b.label, b.value]), [], ...(d.rows || []).map(r => [r.name, r.due, r.oldest])];
    else if (d.stages) rows = d.stages.map(s => [s.label, s.count, s.value ?? '']);
    else if (d.tiles) rows = d.tiles.map(t => [t.name, t.amount, t.minutes]);
    else if (d.value != null) rows = [['ערך', d.value], ['קודם', d.previous ?? ''], ['שינוי %', d.delta ?? '']];
    return '﻿' + rows.map(r => r.map(v => { const x = String(v ?? ''); return /[",\n]/.test(x) ? '"' + x.replace(/"/g, '""') + '"' : x; }).join(',')).join('\n');
  };
  SD.download = function (name, text) {
    const a = h('a', { href: URL.createObjectURL(new Blob([text], { type: 'text/csv;charset=utf-8' })), download: name });
    document.body.append(a); a.click(); setTimeout(() => { URL.revokeObjectURL(a.href); a.remove(); }, 500);
  };

  /* ================================================================ CARD */
  const TONES = { kpi_revenue: 'primary', kpi_profit: 'green', kpi_orders: 'blue', kpi_avg_ticket: 'teal', kpi_customer_debt: 'orange', kpi_supplier_debt: 'purple', kpi_expenses: 'red', kpi_purchases: 'indigo', kpi_returns: 'amber' };
  const CAT_TONE = { finance: 'green', products: 'orange', customers: 'teal', operations: 'blue', team: 'amber', lists: 'slate', charts: 'primary', kpi: 'primary', general: 'purple' };

  /**
   * Build a widget card. item: layout item; meta: widget metadata.
   * opts: {periodLabel, tools: bool, onRefresh, onExpand}
   * Returns {el, body, render(result), busy(bool)}
   */
  SD.card = function (item, meta, opts = {}) {
    meta = meta || { title: item.key, type: 'unknown', icon: 'alert' };
    const title = item.title || meta.title;
    const tone = TONES[item.key] || CAT_TONE[meta.category] || 'primary';
    const isKpi = meta.type === 'kpi', flat = meta.type === 'actions';
    const el = h('section.card' + (flat ? '.flat' : '') + (isKpi ? '.is-kpi' : ''), { 'aria-label': title, dataset: { id: item.id, key: item.key } });
    el.append(h('div.loading-bar'));
    const state = { tab: 0, data: null, link: meta.link };
    const sub = h('small');
    const tools = h('div.card-tools');
    const linkBtn = h('a.btn.ghost.icon.sm', { title: 'לדוח המלא', 'aria-label': 'לדוח המלא', html: SD.icon('external') });
    const setLink = url => { state.link = url; if (url) { linkBtn.href = SD.link(url); linkBtn.style.display = ''; } else linkBtn.style.display = 'none'; };
    setLink(meta.link);
    if (opts.tools !== false) {
      tools.append(linkBtn, h('button.btn.ghost.icon.sm', { type: 'button', title: 'אפשרויות', 'aria-label': 'אפשרויות', html: SD.icon('dots'),
        onclick: e => SD.menu(e.currentTarget, [
          { icon: 'refresh', label: 'רענון', run: () => opts.onRefresh && opts.onRefresh() },
          { icon: 'expand', label: 'הגדלה', run: () => opts.onExpand && opts.onExpand() },
          { icon: 'download', label: 'ייצוא ל-Excel (CSV)', run: () => SD.download((title || 'widget') + '.csv', SD.toCsv(meta, state.data, state)) },
          ...(state.link ? ['-', { icon: 'external', label: 'פתח דוח מלא', href: SD.link(state.link) }] : []),
        ]) }));
    }
    let head = null;
    if (!isKpi && !flat) {
      head = h('div.card-h', null, h('span.card-ic.c-' + tone, { html: SD.icon(meta.icon) }), h('div.card-t', null, h('h3', null, title), sub), tools);
      el.append(head);
    }
    const body = h('div.card-b');
    el.append(body);
    const setSub = t => { sub.textContent = t || ''; sub.style.display = t ? '' : 'none'; };
    setSub(meta.uses_period ? opts.periodLabel : meta.description && meta.type !== 'actions' ? '' : '');

    function skeleton() {
      body.innerHTML = '';
      if (isKpi) { body.append(h('div.skel-wrap', { style: { padding: '14px 0' } }, h('div.skel', { style: { height: '14px', width: '45%' } }), h('div.skel', { style: { height: '28px', width: '70%' } }), h('div.skel', { style: { height: '12px', width: '55%' } }))); return; }
      if (flat) { body.append(h('div.qa', null, [1, 2, 3, 4, 5, 6].map(() => h('div.skel', { style: { height: '52px' } })))); return; }
      body.append(h('div.skel-wrap', null, [80, 60, 90, 70, 50].map(w => h('div.skel', { style: { height: '14px', width: w + '%' } }))));
    }
    skeleton();

    function render(res, periodLabel) {
      body.innerHTML = '';
      body.classList.remove('scroll');
      el.classList.remove('busy');
      if (!res || !res.ok) {
        state.data = null;
        const msg = res && res.error === 'unavailable' ? 'לא זמין במערכת זו' : (res && res.message) || 'שגיאה בטעינת הנתונים';
        const e = h('div.' + (res && res.error === 'unavailable' ? 'empty' : 'err'), { html: SD.icon('alert') + `<div>${esc(msg)}</div>` });
        if (res && res.error !== 'unavailable' && opts.onRefresh) e.append(h('button.btn.sm', { type: 'button', onclick: opts.onRefresh }, 'נסה שוב'));
        if (isKpi) body.append(h('div.kpi', null, h('div.kpi-top', { html: `<span class="card-ic c-${tone}">${SD.icon(meta.icon)}</span><span class="t">${esc(title)}</span>` }), e));
        else body.append(e);
        return;
      }
      const d = state.data = res.data;
      setSub([d.title_suffix, meta.uses_period && !(item.options && item.options.period && item.options.period !== 'inherit') ? periodLabel : (item.options && item.options.period && item.options.period !== 'inherit' ? ({ today: 'היום', '7d': '7 ימים', month: 'החודש', last_month: 'חודש קודם', quarter: 'רבעון', year: 'השנה' })[item.options.period] : null)].filter(Boolean).filter((v, i, a) => a.indexOf(v) === i).join(' · '));
      if (d.link) setLink(d.link);
      const fn = R[meta.type];
      if (!fn) { body.append(emptyEl('סוג תצוגה לא נתמך: ' + meta.type)); return; }
      if (['insights', 'table', 'aging', 'pipeline'].includes(meta.type) && !(d.rows && d.columns)) body.classList.add('scroll');
      if (meta.type === 'insights') body.classList.add('scroll');
      try { fn(body, d, { meta, title, tone, state, setLink, item }); }
      catch (err) { console.error(item.key, err); body.innerHTML = ''; body.append(h('div.err', { html: SD.icon('alert') + '<div>שגיאת תצוגה</div>' })); }
      if (isKpi && opts.tools !== false) { const t = h('div.card-tools'); t.append(...tools.childNodes); body.firstChild && body.firstChild.append(t); }
    }
    return { el, body, render, skeleton, state, busy: b => el.classList.toggle('busy', !!b), setSub, title, meta };
  };

  /* ---------------------------------------------------------------- theme */
  SD.initTheme = function (root) {
    SD.root = root;
    let t = document.documentElement.dataset.theme || null;
    if (!t) try { t = localStorage.getItem('sd-theme'); } catch (e) {}
    if (!t) t = matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light';
    root.dataset.theme = t;
  };
  SD.toggleTheme = function () {
    const t = SD.root.dataset.theme === 'dark' ? 'light' : 'dark';
    SD.root.dataset.theme = t; try { localStorage.setItem('sd-theme', t); } catch (e) {}
    document.dispatchEvent(new CustomEvent('sd:theme'));
  };
})(window);
