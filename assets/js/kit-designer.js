/*
 * Kit-Designer – Arbeitsfläche (Verwaltung, nur Seite /admin/kit-designer/{id}). MIT, KLXM and contributors.
 *
 * Links Eigenschaften in Reitern (Reiter-Verhalten vom Core: [data-tabs]), rechts Zeichenflächen: dieselbe Seite der Website
 * als iframe (srcdoc) je Gerät (Telefon 390, Tablet 820, Desktop 1280 px) und Farbschema, skaliert mit dem Zoom.
 * Vorschau: Änderungen an Farben, Schriften, Formen tauschen nur Variablen, Klassen und Stylesheets in den Zeichenflächen
 * (POST …/api/vorschau mode=live, ohne Neuladen); Tokens mit anderem Markup (Kopf, Fuß …) und Seitenwechsel rendern neu
 * (mode=full). Kontrast rechnet das Skript sofort selbst (WCAG 2.x wie Core\Contrast), Vorschlag und Beheben der Server.
 */
(() => {
  'use strict';
  const root = document.querySelector('[data-kd]');
  const bootEl = document.getElementById('kd-boot');
  if (!root || !bootEl) return;
  const B = JSON.parse(bootEl.textContent);
  const T = B.texts || {};
  const $ = (s, el = root) => el.querySelector(s);
  const $$ = (s, el = root) => [...el.querySelectorAll(s)];
  const fmt = (s, o = {}) => String(s ?? '').replace(/\{(\w+)\}/g, (_, k) => (o[k] ?? ''));
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const csrf = () => document.getElementById('adm-csrf')?.value || '';
  const num = (v, d = 2) => Number(v).toLocaleString(document.documentElement.lang || 'de', { maximumFractionDigits: d });
  let uid = 0;
  const id = p => `kd-${p}-${++uid}`;

  // ------------------------------------------------------------------ Zustand
  const S = {
    label: B.draft.label, name: B.draft.name, values: { ...B.draft.values }, extra: { ...B.draft.extra },
    auto: { ...(B.draft.auto || {}) }, meta: { description: '', author: '', ...(B.draft.meta || {}) },
    page: '', zoom: 0.35, dirty: false, show: { phone: true, tablet: true, desktop: true, light: true, dark: true },
  };
  try { Object.assign(S.show, JSON.parse(localStorage.getItem('kd-show') || '{}')); S.zoom = Number(localStorage.getItem('kd-zoom')) || S.zoom; } catch { /* privat */ }
  const tokens = B.tokens;
  const has = n => !!tokens[n];

  const post = async (url, body) => {
    const res = await fetch(url, { method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-Token': csrf() }, body: JSON.stringify(body) });
    let j = null;
    try { j = await res.json(); } catch { /* keine JSON-Antwort */ }
    if (!res.ok || !j || j.ok === false) throw Object.assign(new Error(j?.error || j?.message || `HTTP ${res.status}`), { data: j });
    return j;
  };
  const statusEl = $('[data-kd-status]');
  let statusTimer = 0;
  const status = (msg, keep = false) => {
    statusEl.textContent = msg;
    clearTimeout(statusTimer);
    if (!keep) statusTimer = setTimeout(() => { statusEl.textContent = S.dirty ? T.dirty : ''; }, 5000);
  };
  const setDirty = (on = true) => {
    S.dirty = on;
    root.classList.toggle('is-dirty', on);
    if (on && !statusEl.textContent) statusEl.textContent = T.dirty;
  };
  window.addEventListener('beforeunload', e => { if (S.dirty) { e.preventDefault(); e.returnValue = T.leave; } });

  // ------------------------------------------------------------------ Kontrast (WCAG 2.x, wie Core\Contrast)
  const rgb = h => [1, 3, 5].map(i => parseInt(h.slice(i, i + 2), 16) / 255);
  const lum = h => { const c = rgb(h).map(x => (x <= 0.03928 ? x / 12.92 : ((x + 0.055) / 1.055) ** 2.4)); return 0.2126 * c[0] + 0.7152 * c[1] + 0.0722 * c[2]; };
  const ratio = (a, b) => { const x = lum(a), y = lum(b); return Math.round(((Math.max(x, y) + 0.05) / (Math.min(x, y) + 0.05)) * 100) / 100; };
  const hexOk = v => /^#[0-9A-F]{6}$/i.test(v || '');
  const pairs = () => {
    const out = [];
    for (const [n, t] of Object.entries(tokens)) {
      if (t.type !== 'color' || !t.contrast?.with) continue;
      const rec = Number(t.contrast.min ?? 4.5);
      const min = rec >= B.aa.text ? B.aa.text : B.aa.ui;
      for (const mode of ['light', 'dark']) {
        const dark = mode === 'dark';
        if (dark && !t.dark) continue;
        const w = dark ? (t.contrast.dark_with ?? t.contrast.with) : t.contrast.with;
        const get = ref => (tokens[ref] ? (dark ? (S.values[ref + '@dark'] ?? S.values[ref]) : S.values[ref]) : ref);
        const fg = get(n), bg = get(w);
        if (!hexOk(fg) || !hexOk(bg)) continue;
        const r = ratio(fg, bg);
        out.push({ token: n, label: t.label, mode, with: w, withLabel: tokens[w]?.label || w, fg, bg, ratio: r, min, rec, ok: r >= min, strict: r >= rec, kind: min >= B.aa.text ? 'text' : 'ui' });
      }
    }
    return out;
  };

  // ------------------------------------------------------------------ Steuerelemente
  const field = (n, opts = {}) => {
    const t = tokens[n];
    if (!t) return null;
    const wrap = document.createElement('div');
    wrap.className = 'kd-field kd-field--' + t.type;
    wrap.dataset.token = n;
    const iid = id(n);
    const help = t.help ? `<p class="f-help" id="${iid}-h">${esc(t.help)}</p>` : '';
    const desc = t.help ? ` aria-describedby="${iid}-h"` : '';
    if (t.type === 'color') return colorField(n, t);
    if (t.type === 'range') {
      wrap.innerHTML = `<label for="${iid}">${esc(t.label)}</label><div class="kd-range"><input type="range" id="${iid}" min="${t.min}" max="${t.max}" step="${t.step || 1}"${desc}>`
        + `<input type="number" class="kd-num" min="${t.min}" max="${t.max}" step="${t.step || 1}" aria-label="${esc(t.label)}">${t.unit ? `<span class="kd-unit">${esc(t.unit)}</span>` : ''}</div>${help}`;
      const [r, nb] = $$('input', wrap);
      const set = v => { r.value = v; nb.value = v; };
      set(S.values[n]);
      const on = v => { if (v === '' || isNaN(v)) return; S.values[n] = Number(v); set(v); changed(n); };
      r.addEventListener('input', () => on(r.value));
      nb.addEventListener('change', () => on(Math.min(Number(t.max), Math.max(Number(t.min), Number(nb.value)))));
      wrap._sync = () => set(S.values[n]);
    } else if (t.type === 'choice') {
      wrap.innerHTML = `<label for="${iid}">${esc(t.label)}</label><select id="${iid}"${desc}>${Object.entries(t.options || {}).map(([k, l]) => `<option value="${esc(k)}">${esc(l)}</option>`).join('')}</select>${help}`;
      const s = $('select', wrap);
      s.value = S.values[n];
      s.addEventListener('change', () => { S.values[n] = s.value; changed(n); });
      wrap._sync = () => { s.value = S.values[n]; };
    } else if (t.type === 'bool') {
      wrap.innerHTML = `<label class="kd-check"><input type="checkbox" id="${iid}"${desc}> ${esc(opts.label || t.label)}</label>${help}`;
      const c = $('input', wrap);
      c.checked = !!S.values[n];
      c.addEventListener('change', () => { S.values[n] = c.checked; changed(n); });
      wrap._sync = () => { c.checked = !!S.values[n]; };
    } else if (t.type === 'font') {
      wrap.innerHTML = `<label for="${iid}">${esc(t.label)}</label><select id="${iid}"${desc}>${B.fonts.map(f => `<option value="${esc(f.key)}">${esc(f.label)}</option>`).join('')}</select>`
        + `<p class="kd-sample" aria-hidden="true">${esc(T.sample)}</p>${help}`;
      const s = $('select', wrap), sample = $('.kd-sample', wrap);
      const paint = () => { s.value = S.values[n]; sample.style.fontFamily = B.fonts.find(f => f.key === S.values[n])?.stack || ''; if (n === 'font_head') sample.style.fontWeight = S.values.heading_weight; };
      paint();
      s.addEventListener('change', () => { S.values[n] = s.value; paint(); changed(n); });
      wrap._sync = paint;
    }
    return wrap;
  };

  const colorField = (n, t) => {
    const wrap = document.createElement('div');
    wrap.className = 'kd-color';
    wrap.dataset.token = n;
    wrap.id = 'kd-color-' + n;
    const a = id(n), b = id(n + 'd');
    const lv = fmt(T.lightValue, { label: t.label }), dv = fmt(T.darkValue, { label: t.label });
    wrap.innerHTML = `<div class="kd-color__head"><span class="kd-color__label" id="${a}-l">${esc(t.label)}</span><span class="kd-color__badges" data-badges></span></div>`
      + `<div class="kd-color__row"><span class="kd-color__mode" aria-hidden="true">${esc(T.light)}</span><input type="color" id="${a}" aria-label="${esc(lv)}"><input class="kd-hex" maxlength="7" spellcheck="false" aria-label="${esc(lv + ', ' + T.hex)}"></div>`
      + (t.dark ? `<div class="kd-color__row"><span class="kd-color__mode" aria-hidden="true">${esc(T.dark)}</span><input type="color" id="${b}" aria-label="${esc(dv)}"><input class="kd-hex" maxlength="7" spellcheck="false" aria-label="${esc(dv + ', ' + T.hex)}">`
        + `<label class="kd-check kd-auto" title="${esc(T.autoTitle)}"><input type="checkbox"> ${esc(T.auto)}<span class="kd-sr"> – ${esc(t.label)}</span></label></div>` : '')
      + (t.help ? `<p class="f-help">${esc(t.help)}</p>` : '');
    const rows = $$('.kd-color__row', wrap);
    const bind = (row, key, isDark) => {
      const [pick, hex] = $$('input', row);
      const auto = isDark ? $('.kd-auto input', row) : null;
      const sync = () => { pick.value = S.values[key]?.toLowerCase() || '#000000'; hex.value = S.values[key] || ''; hex.removeAttribute('aria-invalid'); if (auto) auto.checked = !!S.auto[n]; };
      const set = (v, from) => {
        v = String(v).trim().toUpperCase();
        if (/^[0-9A-F]{6}$/.test(v)) v = '#' + v;
        if (!hexOk(v)) { if (from === hex) hex.setAttribute('aria-invalid', 'true'); return; }
        S.values[key] = v;
        if (isDark && S.auto[n]) { S.auto[n] = false; if (auto) auto.checked = false; }
        if (from !== pick) pick.value = v.toLowerCase();
        if (from !== hex) hex.value = v;
        hex.removeAttribute('aria-invalid');
        changed(n, { color: true, dark: isDark });
      };
      pick.addEventListener('input', () => set(pick.value, pick));
      hex.addEventListener('change', () => set(hex.value, hex));
      hex.addEventListener('keydown', e => { if (e.key === 'Enter') { e.preventDefault(); set(hex.value, hex); } });
      auto?.addEventListener('change', () => { S.auto[n] = auto.checked; setDirty(); if (auto.checked) suggest([n]); });
      return sync;
    };
    const syncs = [bind(rows[0], n, false)];
    if (rows[1]) syncs.push(bind(rows[1], n + '@dark', true));
    wrap._sync = () => syncs.forEach(f => f());
    wrap._sync();
    return wrap;
  };

  // Abschnitte der Reiter (nur Tokens, die das Eltern-Kit hat)
  const panel = k => $(`[data-kd-panel="${k}"]`);
  const group = (title, names, el, extraNodes = []) => {
    const g = document.createElement('div');
    g.className = 'kd-group';
    if (title) { const h = document.createElement('h2'); h.className = 'kd-group__h'; h.textContent = title; g.append(h); }
    names.filter(has).forEach(n => g.append(field(n)));
    extraNodes.forEach(x => g.append(x));
    el.append(g);
    return g;
  };

  // ---- Farben
  const pf = panel('farben');
  const presetBox = document.createElement('div');
  presetBox.className = 'kd-group kd-preset';
  const pid = id('preset');
  presetBox.innerHTML = `<label for="${pid}">${esc(T.preset)}</label><div class="kd-inline"><select id="${pid}">${B.presets.map(p => `<option value="${esc(p.key)}">${esc(p.label)}</option>`).join('')}</select>`
    + `<button type="button" class="adm-btn adm-btn--small">${esc(T.presetApply)}</button></div><p class="f-help" data-desc></p>`;
  pf.append(presetBox);
  const pSel = $('select', presetBox);
  const pDesc = () => { $('[data-desc]', presetBox).textContent = B.presets.find(p => p.key === pSel.value)?.description || ''; };
  pSel.addEventListener('change', pDesc); pDesc();
  $('button', presetBox).addEventListener('click', () => {
    const p = B.presets.find(x => x.key === pSel.value);
    if (!p) return;
    S.values = { ...p.values };
    S.auto = {};
    syncAll();
    changed('*', { full: true });
    status(fmt(T.presetApplied, { label: p.label }));
  });
  if (has('dark')) group('', ['dark'], pf);
  const contrastBox = document.createElement('div');
  contrastBox.className = 'kd-group kd-contrast';
  contrastBox.innerHTML = `<h2 class="kd-group__h">${esc(T.contrast)}</h2><p class="kd-contrast__sum" data-sum></p><ul class="kd-contrast__fails" data-fails></ul>`
    + `<details class="kd-contrast__all"><summary>${esc(T.allPairs)}</summary><ul data-all></ul></details>`
    + `<button type="button" class="adm-btn adm-btn--small" data-suggest>${esc(T.suggestDark)}</button>`;
  pf.append(contrastBox);
  $('[data-suggest]', contrastBox).addEventListener('click', () => suggest(null));
  const MAIN = ['accent', 'accent_strong', 'on_accent', 'highlight', 'secondary', 'on_secondary', 'ink', 'text', 'muted', 'background', 'surface', 'line', 'dark_section'];
  group('', MAIN, pf);
  const more = document.createElement('details');
  more.className = 'kd-more';
  more.innerHTML = `<summary>${esc(T.moreColors)}</summary>`;
  pf.append(more);
  group('', ['palette', 'color3', 'color4', 'color5', 'secondary_role'], more);
  // übrige Farb-Tokens des Kits (falls fluid weitere bekommt)
  const rest = Object.keys(tokens).filter(n => tokens[n].type === 'color' && !MAIN.includes(n) && !['color3', 'color4', 'color5'].includes(n));
  if (rest.length) group('', rest, more);

  // ---- Schrift
  const ps = panel('schrift');
  group('', ['font_body', 'font_head', 'font_mono'], ps);
  const lhWrap = document.createElement('div');
  lhWrap.className = 'kd-field kd-field--range';
  const lhId = id('lh'), lx = B.extra.line_height;
  lhWrap.innerHTML = `<label for="${lhId}">${esc(T.lineHeight)}</label><div class="kd-range"><input type="range" id="${lhId}" min="${lx.min}" max="${lx.max}" step="${lx.step}" aria-describedby="${lhId}-h">`
    + `<input type="number" class="kd-num" min="${lx.min}" max="${lx.max}" step="${lx.step}" aria-label="${esc(T.lineHeight)}"></div><p class="f-help" id="${lhId}-h">${esc(T.lineHeightHelp)}</p>`;
  {
    const [r, nb] = $$('input', lhWrap);
    const set = v => { r.value = v; nb.value = v; };
    set(S.extra.line_height);
    const on = v => { if (isNaN(v)) return; S.extra.line_height = Number(v); set(v); changed('line_height', { extra: true }); };
    r.addEventListener('input', () => on(r.value));
    nb.addEventListener('change', () => on(Math.min(lx.max, Math.max(lx.min, Number(nb.value)))));
    lhWrap._sync = () => set(S.extra.line_height);
  }
  group('', ['fs_min', 'fs_max', 'ratio'], ps, [lhWrap]);
  const scaleBox = document.createElement('div');
  scaleBox.className = 'kd-group kd-scale';
  ps.append(scaleBox);
  const paintScale = () => {
    const r = Number(S.values.ratio), rmin = 1 + (r - 1) * 0.7, lo = Number(S.values.fs_min), hi = Number(S.values.fs_max);
    const font = B.fonts.find(f => f.key === S.values.font_head)?.stack || '';
    const rows = [5, 4, 3, 2, 1, 0].map(s => `<tr><th scope="row">${s}</th><td>${num(lo * rmin ** s, 1)} px</td><td>${num(hi * r ** s, 1)} px</td>`
      + `<td><span class="kd-scale__aa" style="font-family:${esc(font)};font-weight:${Number(S.values.heading_weight) || 600};font-size:${Math.min(40, 10 + s * 5)}px">Aa</span></td></tr>`).join('');
    scaleBox.innerHTML = `<h2 class="kd-group__h">${esc(T.scale)}</h2><table class="adm-table kd-scale__t"><thead><tr><th scope="col">${esc(T.step)}</th><th scope="col">${esc(T.phone)}</th><th scope="col">${esc(T.desktop)}</th><th scope="col"><span class="kd-sr">${esc(T.sample)}</span></th></tr></thead><tbody>${rows}</tbody></table>`;
  };
  paintScale();
  group('', ['heading_weight', 'heading_tracking', 'heading_case', 'label_font', 'emphasis', 'links'], ps);

  // ---- Raum & Form
  const pr = panel('form');
  group('', ['radius', 'shadow', 'buttons', 'cards', 'images'], pr);
  group('', ['density', 'space', 'wrap', 'eyebrow', 'dividers', 'icons'], pr);

  // ---- Kopf & Fuß
  const pk = panel('kopf');
  group('', ['header', 'header_bg', 'header_sticky', 'nav_style', 'logo_size', 'topbar'], pk);
  group('', ['footer', 'footer_bg', 'footer_line', 'pagebg', 'sections', 'motion'], pk);

  const syncAll = () => {
    $$('.kd-field, .kd-color').forEach(w => w._sync?.());
    lhWrap._sync();
    paintScale();
    paintContrast();
  };

  // ------------------------------------------------------------------ Kontrast-Anzeige
  const jump = token => {
    document.getElementById('kd-tab-farben')?.click();
    const el = document.getElementById('kd-color-' + token);
    if (!el) return;
    el.closest('details')?.setAttribute('open', '');
    el.scrollIntoView({ block: 'center', behavior: 'smooth' });
    el.querySelector('input[type=color]')?.focus({ preventScroll: true });
  };
  const fix = async (c) => {
    try {
      const j = await post(B.urls.palette, { action: 'fix', values: S.values, token: c.token, mode: c.mode });
      S.values[j.key] = j.value;
      if (c.mode === 'dark') S.auto[c.token] = false;
      syncAll();
      changed(c.token, { color: true, dark: c.mode === 'dark' });
      status(fmt(T.fixed, { label: c.label + ' (' + (c.mode === 'dark' ? T.dark : T.light) + ')', value: j.value }));
    } catch (e) { status(fmt(T.error, { msg: e.message }), true); }
  };
  const pairText = c => fmt(T.pair, { fg: c.label, bg: c.withLabel, mode: c.mode === 'dark' ? T.dark : T.light });
  const paintContrast = () => {
    const list = pairs();
    const fails = list.filter(c => !c.ok);
    $('[data-sum]', contrastBox).textContent = fails.length ? fmt(T.contrastFail, { fail: fails.length, n: list.length }) : fmt(T.contrastOk, { n: list.length });
    contrastBox.classList.toggle('has-fails', !!fails.length);
    const ul = $('[data-fails]', contrastBox);
    ul.innerHTML = '';
    fails.forEach(c => {
      const li = document.createElement('li');
      li.innerHTML = `<span class="kd-pair" aria-hidden="true"><span style="background:${c.bg};color:${c.fg}">Aa</span></span><span class="kd-pair__t"><b>${esc(pairText(c))}</b> ${esc(fmt(T.ratio, { ratio: num(c.ratio), min: num(c.min, 1) }))}</span>`
        + `<span class="kd-pair__b"><button type="button" class="adm-btn adm-btn--small" data-fix>${esc(T.fix)}<span class="kd-sr"> – ${esc(pairText(c))}</span></button>`
        + `<button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-goto>${esc(T.goto)}<span class="kd-sr"> – ${esc(c.label)}</span></button></span>`;
      $('[data-fix]', li).addEventListener('click', () => fix(c));
      $('[data-goto]', li).addEventListener('click', () => jump(c.token));
      ul.append(li);
    });
    $('[data-all]', contrastBox).innerHTML = list.map(c => `<li class="${c.ok ? 'is-ok' : 'is-fail'}"><span class="kd-pair" aria-hidden="true"><span style="background:${c.bg};color:${c.fg}">Aa</span></span>`
      + `<span>${esc(pairText(c))}: ${esc(num(c.ratio))} : 1 – ${c.ok ? 'AA' : '✕'}${c.ok && !c.strict ? ' · ' + esc(fmt(T.recommend, { rec: num(c.rec, 1) })) : ''}</span></li>`).join('');
    // Abzeichen je Farbe
    $$('.kd-color', pf).forEach(w => {
      const mine = list.filter(c => c.token === w.dataset.token);
      $('[data-badges]', w).innerHTML = mine.map(c => `<span class="kd-badge ${c.ok ? 'is-ok' : 'is-fail'}">${esc(c.mode === 'dark' ? T.dark : T.light)} ${esc(num(c.ratio, 1))}${c.ok ? '' : ' ✕'}<span class="kd-sr"> – ${esc(pairText(c))}, ${esc(fmt(T.ratio, { ratio: num(c.ratio), min: num(c.min, 1) }))}</span></span>`).join('');
    });
    paintExportGate(fails);
  };

  // Dunkel-Vorschlag: only = Liste (Auto-Farben nach Änderung) oder null (alle, setzt Auto für alle)
  const suggest = async (only) => {
    try {
      const j = await post(B.urls.palette, { action: 'dark', values: S.values, only: only || undefined });
      for (const [k, v] of Object.entries(j.dark)) { S.values[k + '@dark'] = v; if (!only) S.auto[k] = true; }
      syncAll();
      changed('*', { color: true });
      if (!only) status(T.suggestDone);
    } catch (e) { status(fmt(T.error, { msg: e.message }), true); }
  };

  // ------------------------------------------------------------------ Zeichenflächen
  const DEVICES = [{ id: 'phone', w: 390, h: 844 }, { id: 'tablet', w: 820, h: 1180 }, { id: 'desktop', w: 1280, h: 860 }];
  const canvas = $('[data-kd-canvas]');
  const frames = [];
  for (const mode of ['light', 'dark']) {
    const row = document.createElement('section');
    row.className = 'kd-row';
    row.dataset.mode = mode;
    const hid = id('row');
    row.setAttribute('aria-labelledby', hid);
    row.innerHTML = `<h2 class="kd-row__h" id="${hid}">${esc(mode === 'dark' ? T.darkRow : T.lightRow)}</h2>${mode === 'dark' ? `<p class="kd-row__note" data-darkoff hidden>${esc(T.darkOff)}</p>` : ''}<div class="kd-row__frames"></div>`;
    for (const d of DEVICES) {
      const fig = document.createElement('figure');
      fig.className = 'kd-frame';
      fig.dataset.dev = d.id;
      fig.style.setProperty('--w', d.w);
      fig.style.setProperty('--h', d.h);
      const label = T[d.id];
      fig.innerHTML = `<figcaption>${esc(label)} · ${d.w} px</figcaption><div class="kd-frame__vp"><iframe title="${esc(fmt(T.frame, { device: label, mode: mode === 'dark' ? T.dark : T.light }))}"></iframe></div>`;
      $('.kd-row__frames', row).append(fig);
      frames.push({ el: fig, frame: $('iframe', fig), mode, dev: d.id, scroll: 0 });
    }
    canvas.append(row);
  }
  const applyShow = () => {
    frames.forEach(f => { f.el.hidden = !(S.show[f.dev] && S.show[f.mode]); });
    $$('.kd-row', canvas).forEach(r => { r.hidden = !S.show[r.dataset.mode]; });
    canvas.style.setProperty('--z', S.zoom);
    $('[data-kd-zoomval]').textContent = Math.round(S.zoom * 100) + ' %';
    try { localStorage.setItem('kd-show', JSON.stringify(S.show)); localStorage.setItem('kd-zoom', String(S.zoom)); } catch { /* privat */ }
  };
  // Schalter: Geräte und Farbschemata
  const showBox = $('[data-kd-show]');
  for (const k of ['phone', 'tablet', 'desktop', 'light', 'dark']) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'adm-btn adm-btn--ghost adm-btn--small kd-toggle';
    b.textContent = k === 'light' ? T.lightRow : k === 'dark' ? T.darkRow : T[k];
    b.setAttribute('aria-pressed', String(!!S.show[k]));
    b.addEventListener('click', () => { S.show[k] = !S.show[k]; b.setAttribute('aria-pressed', String(S.show[k])); applyShow(); });
    showBox.append(b);
  }
  const ZOOMS = [0.2, 0.25, 0.35, 0.5, 0.65, 0.8, 1];
  $$('[data-kd-zoom]').forEach(b => b.addEventListener('click', () => {
    const dir = Number(b.dataset.kdZoom);
    const i = ZOOMS.findIndex(z => z >= S.zoom - 0.001);
    S.zoom = ZOOMS[Math.max(0, Math.min(ZOOMS.length - 1, (i < 0 ? 2 : i) + dir))];
    applyShow();
  }));
  applyShow();

  // Seitenauswahl
  const pageSel = $('[data-kd-page]');
  B.pages.forEach(p => pageSel.add(new Option('  '.repeat(p.depth) + p.title, String(p.id), false, p.home)));
  S.page = pageSel.value;
  pageSel.addEventListener('change', () => { S.page = pageSel.value; frames.forEach(f => { f.scroll = 0; }); render(); });
  const pageByUrl = href => {
    try {
      const u = new URL(href, location.href);
      if (u.origin !== location.origin) return null;
      return B.pages.find(p => new URL(p.url, location.href).pathname.replace(/\/$/, '') === u.pathname.replace(/\/$/, '')) || null;
    } catch { return null; }
  };
  const wire = f => {
    const doc = f.frame.contentDocument;
    if (!doc) return;
    try { f.frame.contentWindow.scrollTo(0, f.scroll); } catch { /* */ }
    doc.addEventListener('click', e => {
      const a = e.target.closest?.('a[href]');
      if (!a) return;
      const href = a.getAttribute('href') || '';
      if (href.startsWith('#')) return;
      e.preventDefault();
      const p = pageByUrl(a.href);
      if (p) { pageSel.value = String(p.id); S.page = String(p.id); frames.forEach(x => { x.scroll = 0; }); render(); } else status(T.linkOut);
    }, true);
    doc.addEventListener('submit', e => e.preventDefault(), true);
    f.frame.contentWindow.addEventListener('scroll', () => { f.scroll = f.frame.contentWindow.scrollY; }, { passive: true });
  };
  frames.forEach(f => f.frame.addEventListener('load', () => wire(f)));

  let reqId = 0, scheme = { light: 'scheme-light', dark: 'scheme-dark' }, force = 'is-dark';
  const darkNote = () => { const n = $('[data-darkoff]', canvas); if (n) n.hidden = S.values.dark !== false; };
  const render = async () => {
    const my = ++reqId;
    status(T.loading, true);
    root.classList.add('is-busy');
    try {
      const j = await post(B.urls.preview, { mode: 'full', values: S.values, extra: S.extra, page: S.page });
      if (my !== reqId) return;
      force = j.force || force;
      scheme = j.scheme || scheme;
      frames.forEach(f => {
        let html = f.mode === 'dark' ? j.dark : j.light;
        // Schema fest wie mit dem Hell/Dunkel-Schalter der Website: hell bleibt hell (auch bei dunklem Gerät), dunkel bekommt die
        // dunklen Werte samt abgeleiteter Farben aus der Design-Datei
        const cls = scheme[f.mode];
        if (cls) html = html.replace(/<html\b([^>]*)\bclass="([^"]*)"/i, (m, a, c) => `<html${a}class="${c} ${cls}"`);
        f.frame.srcdoc = html;
      });
      if (j.page) pageSel.value = String(j.page);
      darkNote();
      status(S.dirty ? T.dirty : '');
    } catch (e) {
      if (my === reqId) status(fmt(T.error, { msg: e.message }), true);
    } finally {
      if (my === reqId) root.classList.remove('is-busy');
    }
  };
  const patch = async () => {
    const my = ++reqId;
    try {
      const j = await post(B.urls.preview, { mode: 'live', values: S.values, extra: S.extra });
      if (my !== reqId) return;
      frames.forEach(f => {
        const doc = f.frame.contentDocument;
        if (!doc?.documentElement) return;
        const dark = f.mode === 'dark';
        const st = doc.querySelector('style[data-design-preview]');
        if (st) st.textContent = j.css;
        const ds = doc.querySelector('style[data-design-dark]');
        if (ds) ds.textContent = j.darkCss;
        const ex = doc.querySelector('style[data-kd-extra]');
        if (ex) ex.textContent = j.extraCss;
        const html = doc.documentElement;
        const keep = [...html.classList].filter(c => ['js', 'no-js', 'is-editing'].includes(c));
        scheme = j.scheme || scheme;
        html.className = [j.classes, ...keep, ...(dark ? [j.force || force] : []), scheme[f.mode]].filter(Boolean).join(' ');
        // Varianten-Stylesheets (css/opt-…) austauschen: in derselben Reihenfolge direkt nach dem Grund-CSS
        doc.querySelectorAll('link[rel=stylesheet][href*="/css/opt-"]').forEach(l => l.remove());
        const site = doc.querySelector('link[rel=stylesheet][href*="/css/site.css"]');
        let after = doc.querySelector('style[data-kd-extra]') || site;
        for (const href of j.opt) {
          const l = doc.createElement('link');
          l.rel = 'stylesheet';
          l.href = href;
          if (after) { after.after(l); after = l; } else doc.head.append(l);
        }
        for (const href of j.fonts) {
          if (doc.querySelector(`link[href="${CSS.escape(href)}"]`)) continue;
          const l = doc.createElement('link');
          l.rel = 'stylesheet';
          l.href = href;
          doc.head.append(l);
        }
      });
      darkNote();
    } catch (e) {
      if (my === reqId) status(fmt(T.error, { msg: e.message }), true);
    }
  };
  let timer = 0, needFull = false, autoTimer = 0;
  const schedule = full => {
    needFull = needFull || full;
    clearTimeout(timer);
    timer = setTimeout(() => { const f = needFull; needFull = false; (f ? render : patch)(); }, full ? 250 : 90);
  };
  // Eine Eingabe hat sich geändert
  function changed(n, o = {}) {
    setDirty();
    paintContrast();
    if (['ratio', 'fs_min', 'fs_max', 'font_head', 'heading_weight'].includes(n)) paintScale();
    if (o.color && !o.dark && S.auto[n]) {
      clearTimeout(autoTimer);
      autoTimer = setTimeout(() => suggest(Object.keys(S.auto).filter(k => S.auto[k])), 160);
    }
    schedule(!!o.full || B.markup.includes(n));
  }

  // ------------------------------------------------------------------ Tokens
  const pt = panel('tokens');
  pt.innerHTML = `<div class="kd-group"><h2 class="kd-group__h">${esc(T.tokensExport)}</h2><p class="f-help">${esc(T.tokensExportHelp)}</p>`
    + `<a class="adm-btn adm-btn--small" href="${esc(B.urls.tokensExport)}" download data-tok-export>${esc(T.tokensExport)}</a></div>`
    + `<div class="kd-group"><h2 class="kd-group__h">${esc(T.tokensImport)}</h2><label for="kd-tok-file">${esc(T.tokensFile)}</label><input type="file" id="kd-tok-file" accept=".json,application/json">`
    + `<label for="kd-tok-text">${esc(T.tokensPaste)}</label><textarea id="kd-tok-text" rows="6" spellcheck="false" class="kd-code"></textarea>`
    + `<div class="kd-inline"><button type="button" class="adm-btn adm-btn--small" data-tok-check>${esc(T.tokensCheck)}</button></div><div data-tok-report aria-live="polite"></div></div>`;
  let imported = null;
  $('#kd-tok-file', pt).addEventListener('change', async e => { const f = e.target.files?.[0]; if (f) { $('#kd-tok-text', pt).value = await f.text(); $('[data-tok-check]', pt).click(); } });
  $('[data-tok-check]', pt).addEventListener('click', async () => {
    const rep = $('[data-tok-report]', pt);
    imported = null;
    try {
      const j = await post(B.urls.tokensImport, { json: $('#kd-tok-text', pt).value, values: S.values, auto: S.auto, extra: S.extra });
      imported = j;
      rep.innerHTML = `<p><b>${esc(fmt(T.tokensMapped, { n: j.mapped.length }))}</b> · ${esc(fmt(T.tokensIgnored, { n: j.ignored.length }))}</p>`
        + `<ul class="kd-map">${j.mapped.map(m => `<li><code>${esc(m.path)}</code> → <code>${esc(m.token)}</code> ${hexOk(m.value) ? `<span class="kd-dot" style="background:${m.value}" aria-hidden="true"></span>` : ''}${esc(m.value)}</li>`).join('')}</ul>`
        + (j.warnings.length ? `<ul class="kd-warn">${j.warnings.map(w => `<li>${esc(w)}</li>`).join('')}</ul>` : '')
        + (j.ignored.length ? `<details><summary>${esc(fmt(T.tokensIgnored, { n: j.ignored.length }))}</summary><ul class="kd-map">${j.ignored.map(p => `<li><code>${esc(p)}</code></li>`).join('')}</ul></details>` : '')
        + `<button type="button" class="adm-btn adm-btn--primary adm-btn--small" data-tok-apply>${esc(T.tokensApply)}</button>`;
      $('[data-tok-apply]', rep).addEventListener('click', () => {
        if (!imported) return;
        S.values = { ...imported.values };
        S.extra = { ...imported.extra };
        S.auto = { ...(imported.auto || {}) };
        syncAll();
        changed('*', { full: true });
        status(T.tokensApplied);
        rep.innerHTML = `<p>${esc(T.tokensApplied)}</p>`;
      });
    } catch (e) {
      rep.innerHTML = `<p class="kd-warn">${esc(e.data?.error ? e.data.error : fmt(T.error, { msg: e.message }))}</p>`;
    }
  });

  // ------------------------------------------------------------------ Export
  const px = panel('export');
  const nid = id('name');
  px.innerHTML = `<div class="kd-group"><div class="f"><label for="${nid}-l">${esc(T.label)}</label><input id="${nid}-l" maxlength="120" data-x="label"></div>`
    + `<div class="f"><label for="${nid}">${esc(T.name)}</label><input id="${nid}" maxlength="30" pattern="[a-z][a-z0-9\\-]{1,29}" aria-describedby="${nid}-h" data-x="name" spellcheck="false"><p class="f-help" id="${nid}-h">${esc(T.nameHelp)}</p><p class="kd-warn" data-exists hidden></p></div>`
    + `<div class="f"><label for="${nid}-d">${esc(T.description)}</label><textarea id="${nid}-d" rows="2" maxlength="300" data-x="description"></textarea></div>`
    + `<div class="f"><label for="${nid}-a">${esc(T.author)}</label><input id="${nid}-a" maxlength="120" data-x="author"></div></div>`
    + `<div class="kd-group kd-gate" data-gate aria-live="polite"></div>`
    + `<div class="kd-group"><h2 class="kd-group__h">${esc(T.studio)}</h2><p class="f-help">${esc(T.studioHelp)}</p><div class="kd-inline">`
    + `<button type="button" class="adm-btn adm-btn--small" data-exp="studio">${esc(T.zip)}</button>`
    + (B.canInstall ? `<button type="button" class="adm-btn adm-btn--small" data-exp="install">${esc(T.install)}</button>` : '') + `</div>`
    + `<p class="f-help">${esc(B.canInstall ? T.installHelp : T.installNo)}</p><div data-install-out aria-live="polite"></div></div>`
    + `<div class="kd-group"><h2 class="kd-group__h">${esc(T.redaxo)}</h2><p class="f-help">${esc(T.redaxoHelp)}</p>`
    + `<div class="f"><label for="${nid}-ns">${esc(T.namespace)}</label><input id="${nid}-ns" value="Project" spellcheck="false" data-ns></div>`
    + `<button type="button" class="adm-btn adm-btn--small" data-exp="redaxo">${esc(T.zip)}</button></div>`
    + `<p class="f-help">${esc(T.saveFirst)}</p>`;
  const xf = k => $(`[data-x="${k}"]`, px);
  xf('label').value = S.label; xf('name').value = S.name; xf('description').value = S.meta.description || ''; xf('author').value = S.meta.author || '';
  const existsNote = () => {
    const n = $('[data-exists]', px);
    n.hidden = !B.kitExists;
    n.className = B.kitOwn ? 'f-help' : 'kd-warn';
    n.textContent = !B.kitExists ? '' : B.kitOwn ? T.existsOwn : fmt(T.exists, { name: S.name, source: B.kitExists });
  };
  existsNote();
  xf('label').addEventListener('input', () => { S.label = xf('label').value; $('[data-kd-title]').textContent = S.label; setDirty(); });
  xf('name').addEventListener('change', () => { const v = xf('name').value.trim().toLowerCase(); if (/^[a-z][a-z0-9-]{1,29}$/.test(v)) { S.name = v; xf('name').removeAttribute('aria-invalid'); setDirty(); } else xf('name').setAttribute('aria-invalid', 'true'); });
  xf('description').addEventListener('input', () => { S.meta.description = xf('description').value; setDirty(); });
  xf('author').addEventListener('input', () => { S.meta.author = xf('author').value; setDirty(); });
  function paintExportGate(fails) {
    const g = $('[data-gate]', px);
    if (!g) return;
    $$('[data-exp]', px).forEach(b => { b.setAttribute('aria-disabled', String(!!fails.length)); });
    if (!fails.length) { g.innerHTML = `<p class="kd-ok">${esc(T.exportReady)}</p>`; return; }
    g.innerHTML = `<p class="kd-warn"><b>${esc(T.exportGate)}</b></p><ul class="kd-gate__list">${fails.map((c, i) => `<li><button type="button" class="kd-linkbtn" data-i="${i}">${esc(pairText(c))}</button> – ${esc(fmt(T.ratio, { ratio: num(c.ratio), min: num(c.min, 1) }))}</li>`).join('')}</ul>`;
    $$('[data-i]', g).forEach(b => b.addEventListener('click', () => jump(fails[Number(b.dataset.i)].token)));
  }
  $$('[data-exp]', px).forEach(b => b.addEventListener('click', async () => {
    if (b.getAttribute('aria-disabled') === 'true') { $('[data-gate] button', px)?.focus(); return; }
    if (S.dirty && !(await save())) return;
    const kind = b.dataset.exp;
    if (kind === 'studio') { location.href = B.urls.zipStudio; return; }
    if (kind === 'redaxo') { location.href = B.urls.zipRedaxo + '?ns=' + encodeURIComponent($('[data-ns]', px).value.trim() || 'Project'); return; }
    const out = $('[data-install-out]', px);
    out.innerHTML = `<p>${esc(T.saving)}</p>`;
    try {
      const j = await post(B.urls.install, {});
      const warn = (j.check || []).filter(c => c.level === 'warn');
      out.innerHTML = `<p class="kd-ok">${esc(j.message || T.installed)}</p>`
        + (j.warnings?.length ? `<ul class="kd-warn">${j.warnings.map(w => `<li>${esc(w)}</li>`).join('')}</ul>` : '')
        + `<p>${warn.length ? `<b>${esc(T.kitCheck)}:</b></p><ul class="kd-warn">${warn.map(c => `<li>${esc(c.file)}: ${esc(c.text)}</li>`).join('')}</ul>` : esc(T.kitCheckOk) + '</p>'}`
        + `<p><a href="${esc(j.kitsUrl || B.urls.kits)}">${esc(B.texts.studio)} → Kits</a></p>`;
      B.kitExists = 'upload';
      B.kitOwn = true;
      existsNote();
    } catch (e) {
      out.innerHTML = `<p class="kd-warn">${esc(e.data?.message || e.data?.error || e.message)}</p>`;
    }
  }));

  // ------------------------------------------------------------------ Speichern, Versionen
  const pv = panel('versionen');
  const noteId = id('note');
  pv.innerHTML = `<div class="kd-group"><div class="f"><label for="${noteId}">${esc(T.note)}</label><input id="${noteId}" maxlength="191" data-note></div></div>`
    + `<div class="kd-group"><h2 class="kd-group__h">${esc(T.versions)}</h2><ol class="kd-versions" data-versions></ol></div>`;
  const paintVersions = list => {
    $('[data-versions]', pv).innerHTML = list.map((v, i) => `<li><span><b>v${v.version}</b> · ${esc(new Date(v.created_at.replace(' ', 'T')).toLocaleString(document.documentElement.lang || 'de'))}`
      + `${v.created_by ? ' · ' + esc(v.created_by) : ''}${v.note ? ' · ' + esc(v.note) : ''}${i === 0 ? ` <span class="adm-badge">${esc(T.current)}</span>` : ''}</span>`
      + `<button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-v="${v.version}">${esc(T.restore)}<span class="kd-sr"> – v${v.version}</span></button></li>`).join('');
    $$('[data-v]', pv).forEach(b => b.addEventListener('click', async () => {
      try {
        const res = await fetch(B.urls.version + b.dataset.v, { headers: { Accept: 'application/json' } });
        const j = await res.json();
        if (!j.ok) throw new Error(j.error || res.status);
        S.values = { ...j.data.values }; S.extra = { ...j.data.extra }; S.auto = { ...(j.data.auto || {}) }; S.meta = { ...S.meta, ...(j.data.meta || {}) };
        syncAll();
        changed('*', { full: true });
        status(fmt(T.restored, { v: j.version }), true);
      } catch (e) { status(fmt(T.error, { msg: e.message }), true); }
    }));
  };
  paintVersions(B.versions);
  const saveBtn = $('[data-kd-save]');
  async function save() {
    saveBtn.disabled = true;
    status(T.saving, true);
    try {
      const j = await post(B.urls.save, { label: S.label, name: S.name, values: S.values, extra: S.extra, auto: S.auto, meta: S.meta, note: $('[data-note]', pv).value });
      setDirty(false);
      $('[data-note]', pv).value = '';
      S.name = j.name; S.label = j.label;
      xf('name').value = S.name;
      $('[data-kd-name]').textContent = S.name;
      paintVersions(j.versions);
      status(fmt(T.saved, { v: j.version }));
      return true;
    } catch (e) {
      status(fmt(T.error, { msg: e.message }), true);
      return false;
    } finally {
      saveBtn.disabled = false;
    }
  }
  saveBtn.addEventListener('click', () => save());
  document.addEventListener('keydown', e => {
    if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 's') { e.preventDefault(); save(); }
  });

  paintContrast();
  render();
})();
