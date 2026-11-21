/*
 * Kit-Designer – Arbeitsfläche (Verwaltung, nur Seite /admin/kit-designer/{id}). MIT, KLXM and contributors.
 *
 * Links Ebenen (Baum der Seite bzw. der Galerie), Mitte Zeichenflächen: dieselbe Seite der Website als iframe (srcdoc) je Gerät
 * (Telefon 390, Tablet 820, Desktop 1280 px) und Farbschema, skaliert mit dem Zoom – oder die Komponenten-Galerie (alle Blöcke von
 * fluid in jeder Variante) oder der Vergleich zweier Stände. Rechts Eigenschaften in Reitern (Reiter-Verhalten vom Core: [data-tabs]).
 *
 * Figma-artig: Elemente in der Vorschau anklicken (Umriss beim Zeigen, Auswahl mit Maß und Innenabständen, Abstand zum gezeigten
 * Nachbarn) → Reiter „Element“ zeigt die token-gebundenen Eigenschaften dieses Elementtyps je Geltung (überall, Blocktyp, Variante)
 * und Zustand (hover, Fokus, gedrückt, deaktiviert …) mit Live-Änderung und Kontrast je Zustand.
 * Vorschau: Änderungen tauschen nur Variablen, Klassen und Stylesheets (POST …/api/vorschau mode=live, ohne Neuladen); Tokens mit
 * anderem Markup und Seitenwechsel rendern neu (mode=full bzw. gallery). Kontrast der Farbpaare rechnet das Skript selbst (WCAG 2.x
 * wie Core\Contrast), die der Stile der Server (Klxm\KitDesigner\Styles::checks); Vorschlag und Beheben der Server.
 * Rückgängig/Wiederholen über einen Verlauf der Zustände (Strg/⌘+Z, Umschalt+Strg/⌘+Z).
 */
(() => {
  'use strict';
  const root = document.querySelector('[data-kd]');
  const bootEl = document.getElementById('kd-boot');
  if (!root || !bootEl) return;
  const B = JSON.parse(bootEl.textContent);
  const T = B.texts || {};
  const I = B.icons || {};
  const C = B.catalog;
  const $ = (s, el = root) => el.querySelector(s);
  const $$ = (s, el = root) => [...el.querySelectorAll(s)];
  const fmt = (s, o = {}) => String(s ?? '').replace(/\{(\w+)\}/g, (_, k) => (o[k] ?? ''));
  const esc = s => String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
  const csrf = () => document.getElementById('adm-csrf')?.value || '';
  const lang = document.documentElement.lang || 'de';
  const num = (v, d = 2) => Number(v).toLocaleString(lang, { maximumFractionDigits: d });
  const clone = o => JSON.parse(JSON.stringify(o));
  let uid = 0;
  const id = p => `kd-${p}-${++uid}`;

  // ------------------------------------------------------------------ Zustand
  const emptyStyles = () => ({ scopes: {}, sections: [] });
  const S = {
    label: B.draft.label, name: B.draft.name, values: { ...B.draft.values }, extra: { ...B.draft.extra },
    auto: { ...(B.draft.auto || {}) }, meta: { description: '', author: '', ...(B.draft.meta || {}) },
    styles: { ...emptyStyles(), ...clone(B.draft.styles || {}) },
    page: '', zoom: 0.35, dirty: false, show: { phone: true, tablet: true, desktop: true, light: true, dark: true },
    mode: 'page', source: 'examples', sel: null, state: 'base', force: false, assign: {}, checks: [], items: [],
  };
  if (Array.isArray(S.styles.scopes)) S.styles.scopes = {};
  try { Object.assign(S.show, JSON.parse(localStorage.getItem('kd-show') || '{}')); S.zoom = Number(localStorage.getItem('kd-zoom')) || S.zoom; } catch { /* privat */ }
  const tokens = B.tokens;
  const has = n => !!tokens[n];
  const colorTokens = Object.entries(tokens).filter(([, t]) => t.type === 'color');

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
  const openTab = k => document.getElementById('kd-tab-' + k)?.click();
  // fluid mit Token line_height: die eigene Angabe aus Stufe 1 folgt dem Token (Drafts::clean übernimmt sonst einen alten Wert)
  const syncLH = () => { if (B.lineHeightToken && S.values.line_height !== undefined) S.extra.line_height = S.values.line_height; };

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
  /** Farbe des Entwurfs: token:… → Wert hell/dunkel, fester Wert bleibt */
  const resolve = (v, dark = false) => {
    if (!v) return null;
    if (String(v).startsWith('token:')) { const n = v.slice(6); return dark ? (S.values[n + '@dark'] ?? S.values[n]) : S.values[n]; }
    return v;
  };

  // ------------------------------------------------------------------ Verlauf (Rückgängig/Wiederholen)
  const H = { stack: [], i: -1, timer: 0 };
  const snap = () => JSON.stringify({ values: S.values, extra: S.extra, auto: S.auto, styles: S.styles });
  const undoBtn = $('[data-kd-undo]'), redoBtn = $('[data-kd-redo]');
  const histButtons = () => { undoBtn.disabled = H.i <= 0; redoBtn.disabled = H.i >= H.stack.length - 1; };
  const commitNow = () => {
    clearTimeout(H.timer);
    const s = snap();
    if (H.stack[H.i] === s) return;
    H.stack = H.stack.slice(0, H.i + 1);
    H.stack.push(s);
    if (H.stack.length > 120) H.stack.shift();
    H.i = H.stack.length - 1;
    histButtons();
  };
  const commit = () => { clearTimeout(H.timer); H.timer = setTimeout(commitNow, 400); };
  const travel = dir => {
    commitNow();
    const j = H.i + dir;
    if (j < 0 || j >= H.stack.length) return;
    const before = JSON.parse(H.stack[H.i]);
    H.i = j;
    const d = JSON.parse(H.stack[j]);
    S.values = d.values; S.extra = d.extra; S.auto = d.auto; S.styles = d.styles;
    histButtons();
    syncAll();
    paintSections();
    paintInspector();
    setDirty();
    schedule(B.markup.some(n => before.values[n] !== d.values[n]));
    status(dir < 0 ? T.undone : T.redone);
  };
  undoBtn.addEventListener('click', () => travel(-1));
  redoBtn.addEventListener('click', () => travel(1));

  // ------------------------------------------------------------------ Steuerelemente der Design-Werte
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
      auto?.addEventListener('change', () => { S.auto[n] = auto.checked; setDirty(); commit(); if (auto.checked) suggest([n]); });
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
    + `<button type="button" class="adm-btn adm-btn--small" data-suggest>${esc(T.suggestDark)}</button>`
    + `<h3 class="kd-group__h kd-contrast__styles">${esc(T.stylesHead)}</h3><p class="kd-contrast__sum" data-ssum></p><ul class="kd-contrast__fails" data-sfails></ul>`;
  pf.append(contrastBox);
  $('[data-suggest]', contrastBox).addEventListener('click', () => suggest(null));
  const MAIN = ['accent', 'accent_strong', 'on_accent', 'highlight', 'secondary', 'on_secondary', 'ink', 'text', 'muted', 'background', 'surface', 'line', 'dark_section'];
  group('', MAIN, pf);
  const more = document.createElement('details');
  more.className = 'kd-more';
  more.innerHTML = `<summary>${esc(T.moreColors)}</summary>`;
  pf.append(more);
  group('', ['palette', 'color3', 'color4', 'color5', 'secondary_role'], more);
  const rest = Object.keys(tokens).filter(n => tokens[n].type === 'color' && !MAIN.includes(n) && !['color3', 'color4', 'color5'].includes(n));
  if (rest.length) group('', rest, more);

  // ---- Schrift (Zeilenhöhe: Token line_height von fluid, sonst eigene Angabe wie in Stufe 1)
  const ps = panel('schrift');
  group('', ['font_body', 'font_head', 'font_mono'], ps);
  const lhWrap = document.createElement('div');
  if (!B.lineHeightToken) {
    lhWrap.className = 'kd-field kd-field--range';
    const lhId = id('lh'), lx = B.extra.line_height;
    lhWrap.innerHTML = `<label for="${lhId}">${esc(T.lineHeight)}</label><div class="kd-range"><input type="range" id="${lhId}" min="${lx.min}" max="${lx.max}" step="${lx.step}" aria-describedby="${lhId}-h">`
      + `<input type="number" class="kd-num" min="${lx.min}" max="${lx.max}" step="${lx.step}" aria-label="${esc(T.lineHeight)}"></div><p class="f-help" id="${lhId}-h">${esc(T.lineHeightHelp)}</p>`;
    const [r, nb] = $$('input', lhWrap);
    const set = v => { r.value = v; nb.value = v; };
    set(S.extra.line_height);
    const on = v => { if (isNaN(v)) return; S.extra.line_height = Number(v); set(v); changed('line_height', { extra: true }); };
    r.addEventListener('input', () => on(r.value));
    nb.addEventListener('change', () => on(Math.min(lx.max, Math.max(lx.min, Number(nb.value)))));
    lhWrap._sync = () => set(S.extra.line_height);
  }
  group('', ['fs_min', 'fs_max', 'ratio', 'line_height'], ps, B.lineHeightToken ? [] : [lhWrap]);
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

  // ---- Raum & Form, Kopf & Fuß
  const pr = panel('form');
  group('', ['radius', 'shadow', 'buttons', 'cards', 'images'], pr);
  group('', ['density', 'space', 'wrap', 'eyebrow', 'dividers', 'icons'], pr);
  const pk = panel('kopf');
  group('', ['header', 'header_bg', 'header_sticky', 'nav_style', 'logo_size', 'topbar'], pk);
  group('', ['footer', 'footer_bg', 'footer_line', 'pagebg', 'sections', 'motion'], pk);

  const syncAll = () => {
    $$('.kd-field, .kd-color').forEach(w => w._sync?.());
    lhWrap._sync?.();
    paintScale();
    paintContrast();
  };

  // ------------------------------------------------------------------ Kontrast-Anzeige (Farbpaare + Stile)
  const jump = token => {
    openTab('farben');
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
  const blockLabel = type => B.blocks[type]?.label || type;
  const scopeText = scope => {
    if (scope === '*') return T.scopeAll;
    const [type, v] = scope.split('.');
    return v ? fmt(T.scopeVariant, { block: blockLabel(type), variant: B.blocks[type]?.variants?.[v] || v }) : fmt(T.scopeBlock, { block: blockLabel(type) });
  };
  const styleCheckText = c => {
    if (c.scope === 'section') {
      const s = S.styles.sections.find(x => x.key === c.element);
      return `${T.sectionStyle} „${s?.label || c.element}“ – ${T['what_' + c.what] || c.what} (${c.mode === 'dark' ? T.dark : T.light})`;
    }
    return `${C.elements[c.element]?.label || c.element} · ${C.states[c.state] || c.state} · ${scopeText(c.scope)} – ${T['what_' + c.what] || c.what} (${c.mode === 'dark' ? T.dark : T.light})`;
  };
  const badge = (c, short = false) => `<span class="kd-badge ${c.ok ? 'is-ok' : 'is-fail'}">${short ? '' : esc(c.mode === 'dark' ? T.dark : T.light) + ' '}${esc(num(c.ratio, 1))}${c.ok ? '' : ' ' + I.warning}<span class="kd-sr"> – ${esc(fmt(T.ratio, { ratio: num(c.ratio), min: num(c.min, 1) }))}</span></span>`;
  const styleFails = () => S.checks.filter(c => !c.ok);
  const paintContrast = () => {
    const list = pairs();
    const fails = list.filter(c => !c.ok);
    $('[data-sum]', contrastBox).textContent = fails.length ? fmt(T.contrastFail, { fail: fails.length, n: list.length }) : fmt(T.contrastOk, { n: list.length });
    contrastBox.classList.toggle('has-fails', !!fails.length);
    const ul = $('[data-fails]', contrastBox);
    ul.innerHTML = '';
    fails.forEach(c => {
      const li = document.createElement('li');
      li.innerHTML = `<span class="kd-pair" aria-hidden="true"><span style="background:${c.bg};color:${c.fg}" class="kd-aa"></span></span><span class="kd-pair__t"><b>${esc(pairText(c))}</b> ${esc(fmt(T.ratio, { ratio: num(c.ratio), min: num(c.min, 1) }))}</span>`
        + `<span class="kd-pair__b"><button type="button" class="adm-btn adm-btn--small" data-fix>${esc(T.fix)}<span class="kd-sr"> – ${esc(pairText(c))}</span></button>`
        + `<button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-goto>${esc(T.goto)}<span class="kd-sr"> – ${esc(c.label)}</span></button></span>`;
      $('[data-fix]', li).addEventListener('click', () => fix(c));
      $('[data-goto]', li).addEventListener('click', () => jump(c.token));
      ul.append(li);
    });
    $('[data-all]', contrastBox).innerHTML = list.map(c => `<li class="${c.ok ? 'is-ok' : 'is-fail'}"><span class="kd-pair" aria-hidden="true"><span style="background:${c.bg};color:${c.fg}" class="kd-aa"></span></span>`
      + `<span>${esc(pairText(c))}: ${esc(num(c.ratio))} : 1 – ${c.ok ? 'AA' : esc(T.fix)}${c.ok && !c.strict ? ' · ' + esc(fmt(T.recommend, { rec: num(c.rec, 1) })) : ''}</span></li>`).join('');
    $$('.kd-color', pf).forEach(w => {
      const mine = list.filter(c => c.token === w.dataset.token);
      $('[data-badges]', w).innerHTML = mine.map(c => badge(c)).join('');
    });
    // Stile
    const sf = styleFails();
    const real = S.checks.filter(c => !c.exempt);
    $('[data-ssum]', contrastBox).textContent = sf.length ? fmt(T.stylesFail, { n: sf.length }) : fmt(T.stylesOk, { n: real.length });
    $('[data-ssum]', contrastBox).classList.toggle('is-fail', !!sf.length);
    const sul = $('[data-sfails]', contrastBox);
    sul.innerHTML = '';
    sf.forEach(c => sul.append(styleFailItem(c)));
    paintExportGate(fails, sf);
  };
  const styleFailItem = c => {
    const li = document.createElement('li');
    li.innerHTML = `<span class="kd-pair" aria-hidden="true"><span style="background:${c.bg};color:${c.fg}" class="kd-aa"></span></span><span class="kd-pair__t"><b>${esc(styleCheckText(c))}</b> ${esc(fmt(T.ratio, { ratio: num(c.ratio), min: num(c.min, 1) }))}</span>`
      + `<span class="kd-pair__b"><button type="button" class="adm-btn adm-btn--small" data-fix>${esc(T.fix)}<span class="kd-sr"> – ${esc(styleCheckText(c))}</span></button>`
      + `<button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-goto>${esc(T.goto)}<span class="kd-sr"> – ${esc(styleCheckText(c))}</span></button></span>`;
    $('[data-fix]', li).addEventListener('click', () => fixStyle(c));
    $('[data-goto]', li).addEventListener('click', () => gotoStyle(c));
    return li;
  };
  /** Stil-Paar beheben: Schrift- (bzw. Rahmen-/Akzent-)Farbe heller/dunkler, Farbton bleibt; bei dunkel als eigener Dunkel-Wert */
  const fixStyle = async (c) => {
    try {
      const j = await post(B.urls.palette, { action: 'fixpair', fg: c.fg, bg: c.bg, min: c.min });
      const dark = c.mode === 'dark';
      const setColor = (obj, prop) => {
        const cur = obj[prop];
        if (dark) {
          if (!cur || String(cur).startsWith('token:')) obj[prop] = resolve(cur, false) || c.fg;
          obj[prop + '@dark'] = j.value;
        } else {
          const keepDark = cur && String(cur).startsWith('token:') ? resolve(cur, true) : obj[prop + '@dark'];
          obj[prop] = j.value;
          if (keepDark && keepDark !== j.value) obj[prop + '@dark'] = keepDark;
        }
      };
      if (c.scope === 'section') {
        const s = S.styles.sections.find(x => x.key === c.element);
        if (s) setColor(s, c.state);
        paintSections();
      } else {
        const prop = { text: 'color', outline: 'outline_color', border: 'border_color' }[c.what] || 'color';
        const st = ((S.styles.scopes[c.scope] ??= {})[c.element] ??= {})[c.state] ??= {};
        setColor(st, prop);
        paintInspector();
      }
      stylesChanged();
      status(fmt(T.fixed, { label: styleCheckText(c), value: j.value }));
    } catch (e) { status(fmt(T.error, { msg: e.message }), true); }
  };
  const gotoStyle = c => {
    if (c.scope === 'section') {
      openTab('abschnitte');
      document.getElementById('kd-sec-' + c.element)?.querySelector('input,select')?.focus();
      return;
    }
    const [type, variant] = c.scope === '*' ? ['', ''] : c.scope.split('.');
    S.sel = { el: c.element, kdId: null, block: type || '', variant: variant || '', scope: c.scope };
    S.state = c.state;
    paintSelection();
    paintInspector();
    openTab('element');
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

  // ------------------------------------------------------------------ Stil-Daten
  const stState = (scope, el, st, create = false) => {
    const sc = S.styles.scopes;
    if (!create) return sc[scope]?.[el]?.[st] || null;
    return ((sc[scope] ??= {})[el] ??= {})[st] ??= {};
  };
  const prune = () => {
    for (const [sc, els] of Object.entries(S.styles.scopes)) {
      for (const [el, states] of Object.entries(els)) {
        for (const [st, p] of Object.entries(states)) if (!Object.keys(p).length) delete states[st];
        if (!Object.keys(states).length) delete els[el];
      }
      if (!Object.keys(els).length) delete S.styles.scopes[sc];
    }
  };
  const stylesChanged = () => { prune(); setDirty(); commit(); schedule(false); };
  /** Element-Zeilen in den Ebenen und im Inspektor: Geltungen eines Elements, die Angaben haben */
  const scopesWith = el => Object.keys(S.styles.scopes).filter(sc => S.styles.scopes[sc]?.[el]);

  // ------------------------------------------------------------------ Zeichenflächen
  const DEVICES = [{ id: 'phone', w: 390, h: 844 }, { id: 'tablet', w: 820, h: 1180 }, { id: 'desktop', w: 1280, h: 860 }];
  const canvas = $('[data-kd-canvas]');
  let frames = [];
  const PICKS = Object.entries(C.elements);   // Reihenfolge = Vorrang (innerstes Element gewinnt, bei Gleichstand der erste Typ)
  const ALL_PICKS = PICKS.map(([, e]) => e.pick).join(', ');
  const cssType = t => t.replace(/_/g, '-');
  const typeByCss = Object.fromEntries(Object.keys(B.blocks).map(t => [cssType(t), t]));

  const buildFrames = () => {
    canvas.innerHTML = '';
    canvas.scrollLeft = 0;
    frames = [];
    canvas.classList.remove('is-compare');
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
        const f = { el: fig, frame: $('iframe', fig), mode, dev: d.id, scroll: 0 };
        f.frame.addEventListener('load', () => wire(f));
        frames.push(f);
      }
      canvas.append(row);
    }
    applyShow();
  };
  const applyShow = () => {
    frames.forEach(f => { if (f.dev !== 'compare') f.el.hidden = !(S.show[f.dev] && S.show[f.mode]); });
    $$('.kd-row', canvas).forEach(r => { if (r.dataset.mode) r.hidden = !S.show[r.dataset.mode]; });
    // Vergleich: beide Desktop-Flächen nebeneinander passend verkleinern
    const z = S.mode === 'compare' ? Math.min(S.zoom, Math.max(0.15, (canvas.clientWidth - 80) / 2560)) : S.zoom;
    canvas.style.setProperty('--z', z);
    $('[data-kd-zoomval]').textContent = Math.round(S.zoom * 100) + ' %';
    try { localStorage.setItem('kd-show', JSON.stringify(S.show)); localStorage.setItem('kd-zoom', String(S.zoom)); } catch { /* privat */ }
  };
  const showBox = $('[data-kd-show]');
  for (const k of ['phone', 'tablet', 'desktop', 'light', 'dark']) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'adm-btn adm-btn--ghost adm-btn--small kd-toggle';
    b.textContent = k === 'light' ? T.lightRow : k === 'dark' ? T.darkRow : T[k];
    b.setAttribute('aria-pressed', String(!!S.show[k]));
    b.addEventListener('click', () => { S.show[k] = !S.show[k]; b.setAttribute('aria-pressed', String(S.show[k])); applyShow(); paintLayers(); });
    showBox.append(b);
  }
  const ZOOMS = [0.2, 0.25, 0.35, 0.5, 0.65, 0.8, 1];
  $$('[data-kd-zoom]').forEach(b => b.addEventListener('click', () => {
    const dir = Number(b.dataset.kdZoom);
    const i = ZOOMS.findIndex(z => z >= S.zoom - 0.001);
    S.zoom = ZOOMS[Math.max(0, Math.min(ZOOMS.length - 1, (i < 0 ? 2 : i) + dir))];
    applyShow();
  }));

  // Seitenauswahl
  const pageSel = $('[data-kd-page]');
  B.pages.forEach(p => pageSel.add(new Option('  '.repeat(p.depth) + p.title, String(p.id), false, p.home)));
  S.page = pageSel.value;
  pageSel.addEventListener('change', () => { S.page = pageSel.value; frames.forEach(f => { f.scroll = 0; }); S.sel = null; S.assign = {}; render(); });
  const pageByUrl = href => {
    try {
      const u = new URL(href, location.href);
      if (u.origin !== location.origin) return null;
      return B.pages.find(p => new URL(p.url, location.href).pathname.replace(/\/$/, '') === u.pathname.replace(/\/$/, '')) || null;
    } catch { return null; }
  };
  const sourceSel = $('[data-kd-source]');
  sourceSel.addEventListener('change', () => { S.source = sourceSel.value; render(); });

  // ---- Elemente in einer Zeichenfläche
  const UI_CSS = `[data-kd-hover]{outline:1.5px solid #0D99FF!important;outline-offset:-1px}[data-kd-sel]{outline:2px solid #0D99FF!important;outline-offset:-2px}
.kd-ui{position:absolute;pointer-events:none;z-index:2147483646}.kd-ui-tag{font:600 12px/1 Lato,system-ui,sans-serif;background:#0D99FF;color:#fff;padding:4px 6px;border-radius:3px;white-space:nowrap;z-index:2147483647}
.kd-ui-pad{background:rgb(255 166 0/.22)}.kd-ui-gap{background:#E5484D;z-index:2147483647}.kd-ui-gap-l{font:600 11px/1 Lato,system-ui,sans-serif;background:#E5484D;color:#fff;padding:3px 5px;border-radius:3px;z-index:2147483647}
.kd-g-label{display:flex;gap:.6em;align-items:baseline;margin:0;padding:22px 24px 8px;font:700 13px/1.2 Lato,system-ui,sans-serif;letter-spacing:.04em;text-transform:uppercase;color:#5B6472;background:#F4F5F7;border-top:1px solid #DADDE3}
.kd-g-label span{font-weight:400;text-transform:none;letter-spacing:0;color:#6B7380}html.is-dark .kd-g-label,html.scheme-dark .kd-g-label{background:#1A1D22;color:#B9C0CC;border-color:#2C313A}`;
  const tag = doc => {
    doc.querySelectorAll(ALL_PICKS).forEach((n, i) => { n.dataset.kdId = String(i); });
  };
  const typeOf = n => (PICKS.find(([, e]) => n.matches(e.pick)) || [])[0] || null;
  const candidate = target => {
    for (let n = target; n && n.nodeType === 1; n = n.parentElement) if (n.dataset?.kdId !== undefined) return n;
    return null;
  };
  const contextOf = n => {
    const sec = n.closest('main .sec');
    if (!sec) return { block: '', variant: '' };
    let block = '', variant = '';
    for (const c of sec.classList) {
      if (c.startsWith('sec--') && typeByCss[c.slice(5)]) block = typeByCss[c.slice(5)];
    }
    if (block) for (const c of sec.classList) if (c.startsWith('v-') && B.blocks[block]?.variants?.[c.slice(2)] !== undefined) variant = c.slice(2);
    return { block, variant, sec };
  };
  const defaultScope = (el, block, variant) => {
    if (C.elements[el]?.global || !block) return '*';
    if (el === 'section') return variant && S.styles.scopes[block + '.' + variant]?.section ? block + '.' + variant : block;
    if (variant && S.styles.scopes[block + '.' + variant]?.[el]) return block + '.' + variant;
    if (S.styles.scopes[block]?.[el]) return block;
    return S.mode === 'gallery' ? (variant ? block + '.' + variant : block) : '*';
  };
  const selectNode = (n, focusPanel = true) => {
    const el = typeOf(n);
    if (!el) return;
    const ctx = contextOf(n);
    S.sel = { el, kdId: n.dataset.kdId, block: ctx.block, variant: ctx.variant, scope: defaultScope(el, ctx.block, ctx.variant) };
    if (!C.elements[el].states.includes(S.state)) S.state = 'base';
    paintSelection();
    paintInspector();
    paintLayers(true);
    if (focusPanel) openTab('element');
  };
  const clearUi = doc => {
    doc.querySelectorAll('.kd-ui').forEach(x => x.remove());
    doc.querySelectorAll('[data-kd-hover]').forEach(x => x.removeAttribute('data-kd-hover'));
  };
  const box = (doc, cls, r, text = '') => {
    const d = doc.createElement('div');
    d.className = 'kd-ui ' + cls;
    const win = doc.defaultView;
    d.style.left = (r.left + win.scrollX) + 'px';
    d.style.top = (r.top + win.scrollY) + 'px';
    if (r.width !== undefined) { d.style.width = Math.max(0, r.width) + 'px'; d.style.height = Math.max(0, r.height) + 'px'; }
    if (text) d.textContent = text;
    doc.documentElement.append(d);
    return d;
  };
  const px = v => Math.round(parseFloat(v) || 0);
  const drawSel = f => {
    const doc = f.frame.contentDocument;
    if (!doc?.documentElement) return;
    doc.querySelectorAll('.kd-ui-sel').forEach(x => x.remove());
    doc.querySelectorAll('[data-kd-sel]').forEach(x => x.removeAttribute('data-kd-sel'));
    doc.querySelectorAll('[data-kd-state]').forEach(x => x.removeAttribute('data-kd-state'));
    if (!S.sel) return;
    const nodes = selNodes(doc);
    nodes.forEach(n => { if (S.force && S.state !== 'base') n.setAttribute('data-kd-state', S.state); });
    const n = S.sel.kdId !== null ? doc.querySelector(`[data-kd-id="${S.sel.kdId}"]`) : nodes[0];
    if (!n) return;
    n.setAttribute('data-kd-sel', '');
    const r = n.getBoundingClientRect();
    const cs = doc.defaultView.getComputedStyle(n);
    const [pt, prr, pb, pl] = [cs.paddingTop, cs.paddingRight, cs.paddingBottom, cs.paddingLeft].map(px);
    if (pt) box(doc, 'kd-ui-pad kd-ui-sel', { left: r.left, top: r.top, width: r.width, height: pt });
    if (pb) box(doc, 'kd-ui-pad kd-ui-sel', { left: r.left, top: r.bottom - pb, width: r.width, height: pb });
    if (pl) box(doc, 'kd-ui-pad kd-ui-sel', { left: r.left, top: r.top + pt, width: pl, height: r.height - pt - pb });
    if (prr) box(doc, 'kd-ui-pad kd-ui-sel', { left: r.right - prr, top: r.top + pt, width: prr, height: r.height - pt - pb });
    const t = box(doc, 'kd-ui-tag kd-ui-sel', { left: r.left, top: Math.max(0, r.top - 22) }, `${C.elements[S.sel.el].label} · ${Math.round(r.width)} × ${Math.round(r.height)}`);
    t.dataset.kdSelTag = '';
  };
  /** Alle Knoten des gewählten Elementtyps in der Geltung (für erzwungene Zustände) */
  const selNodes = doc => {
    if (!S.sel) return [];
    const pick = C.elements[S.sel.el].pick;
    let list = [...doc.querySelectorAll(pick)];
    const sc = S.sel.scope;
    if (sc && sc !== '*') {
      const [type, v] = sc.split('.');
      list = list.filter(n => { const c = contextOf(n); return c.block === type && (!v || c.variant === v); });
    }
    return list;
  };
  const paintSelection = () => frames.forEach(drawSel);
  const hover = (f, n) => {
    const doc = f.frame.contentDocument;
    doc.querySelectorAll('.kd-ui-hov').forEach(x => x.remove());
    doc.querySelectorAll('[data-kd-hover]').forEach(x => x.removeAttribute('data-kd-hover'));
    if (!n) return;
    n.setAttribute('data-kd-hover', '');
    const el = typeOf(n);
    const r = n.getBoundingClientRect();
    if (!n.hasAttribute('data-kd-sel')) box(doc, 'kd-ui-tag kd-ui-hov', { left: r.left, top: Math.max(0, r.top - 22) }, `${C.elements[el]?.label || ''} · ${Math.round(r.width)} × ${Math.round(r.height)}`);
    // Abstand zum gewählten Element (wie Figma beim Zeigen)
    const s = doc.querySelector('[data-kd-sel]');
    if (!s || s === n) return;
    const a = s.getBoundingClientRect();
    const gaps = [];
    if (r.top >= a.bottom) gaps.push({ x: Math.max(a.left, r.left) + 12, y: a.bottom, w: 2, h: r.top - a.bottom, v: r.top - a.bottom });
    else if (a.top >= r.bottom) gaps.push({ x: Math.max(a.left, r.left) + 12, y: r.bottom, w: 2, h: a.top - r.bottom, v: a.top - r.bottom });
    if (r.left >= a.right) gaps.push({ x: a.right, y: Math.max(a.top, r.top) + 12, w: r.left - a.right, h: 2, v: r.left - a.right });
    else if (a.left >= r.right) gaps.push({ x: r.right, y: Math.max(a.top, r.top) + 12, w: a.left - r.right, h: 2, v: a.left - r.right });
    for (const g of gaps) {
      box(doc, 'kd-ui-gap kd-ui-hov', { left: g.x, top: g.y, width: g.w, height: g.h });
      box(doc, 'kd-ui-gap-l kd-ui-hov', { left: g.x + g.w / 2 + 4, top: g.y + g.h / 2 - 8 }, Math.round(g.v) + ' px');
    }
    if (gaps.length) S.gap = { label: C.elements[el]?.label || '', px: Math.round(gaps[0].v) }; else S.gap = null;
    paintMeasure();
  };
  const wire = f => {
    const doc = f.frame.contentDocument;
    if (!doc?.documentElement) return;
    const st = doc.createElement('style');
    st.dataset.kdUi = '';
    st.textContent = UI_CSS;
    doc.head?.append(st);
    tag(doc);
    if (S.mode === 'gallery') decorateGallery(doc);
    applyAssign(doc);
    try { f.frame.contentWindow.scrollTo(0, f.scroll); } catch { /* */ }
    doc.addEventListener('mousemove', e => { if (S.mode !== 'compare') hover(f, candidate(e.target)); }, { passive: true });
    doc.addEventListener('mouseleave', () => hover(f, null));
    doc.addEventListener('click', e => {
      if (S.mode === 'compare') { e.preventDefault(); return; }
      const n = candidate(e.target);
      e.preventDefault();
      e.stopPropagation();
      if (n) selectNode(n);
    }, true);
    // Doppelklick auf einen Link: diese Seite der Website in der Vorschau öffnen
    doc.addEventListener('dblclick', e => {
      const a = e.target.closest?.('a[href]');
      if (!a || S.mode !== 'page') return;
      const href = a.getAttribute('href') || '';
      if (href.startsWith('#')) return;
      e.preventDefault();
      const p = pageByUrl(a.href);
      if (p) { pageSel.value = String(p.id); S.page = String(p.id); frames.forEach(x => { x.scroll = 0; }); S.sel = null; S.assign = {}; render(); } else status(T.linkOut);
    }, true);
    doc.addEventListener('submit', e => e.preventDefault(), true);
    doc.addEventListener('keydown', onKey);   // Kürzel auch, wenn der Fokus in einer Zeichenfläche liegt
    f.frame.contentWindow.addEventListener('scroll', () => { f.scroll = f.frame.contentWindow.scrollY; if (S.sel) drawSel(f); }, { passive: true });
    drawSel(f);
    if (f === primary()) { paintLayers(); paintMeasure(); }
  };
  const primary = () => frames.find(f => f.dev === 'desktop' && f.mode === 'light' && !f.el.hidden) || frames.find(f => !f.el.hidden) || frames[0];

  // ---- Galerie: Abschnitte beschriften, Abschnitts-Stile zeigen
  const decorateGallery = doc => {
    for (const it of S.items) {
      const sec = doc.getElementById(it.id);
      if (!sec) continue;
      if (it.type === '@section') {
        sec.classList.remove('bg-white');
        sec.classList.add('bg-' + it.variant);
      }
      const l = doc.createElement('p');
      l.className = 'kd-g-label';
      l.innerHTML = it.type === '@section' ? `${esc(T.sectionStyle)} <span>${esc(it.label)}</span>`
        : `${esc(it.label)}${it.variantLabel ? ` <span>${esc(it.variantLabel)}</span>` : ''}${it.source === 'site' ? ` <span>· ${esc(T.fromSite)}</span>` : ''}`;
      sec.before(l);
    }
  };
  // Abschnitts-Stil nur in der Vorschau einem Abschnitt zuweisen (S.assign: kd-id → Schlüssel)
  const applyAssign = doc => {
    for (const [kid, key] of Object.entries(S.assign)) {
      const sec = doc.querySelector(`[data-kd-id="${kid}"]`);
      if (!sec) continue;
      if (!sec.dataset.kdBg) sec.dataset.kdBg = [...sec.classList].find(c => c.startsWith('bg-')) || '';
      [...sec.classList].filter(c => c.startsWith('bg-')).forEach(c => sec.classList.remove(c));
      sec.classList.add(key ? 'bg-' + key : sec.dataset.kdBg);
    }
  };

  // ------------------------------------------------------------------ Ebenen
  const tree = $('[data-kd-tree]');
  const treeEmpty = document.createElement('p');
  treeEmpty.className = 'kd-tree__empty';
  tree.after(treeEmpty);
  const treeNote = msg => { tree.hidden = !!msg; treeEmpty.hidden = !msg; treeEmpty.textContent = msg || ''; };
  let treeNodes = [];
  const paintLayers = (keepFocus = false) => {
    const f = primary();
    const doc = f?.frame.contentDocument;
    const had = keepFocus && tree.contains(document.activeElement);
    tree.innerHTML = '';
    treeNodes = [];
    if (S.mode === 'compare') { treeNote(T.layersCompare); return; }
    if (!doc?.body) { treeNote(T.noLayers); return; }
    treeNote('');
    const items = [];
    const add = (label, sub, n, level, children = []) => items.push({ label, sub, n, level, children });
    const kids = sec => {
      const seen = {};
      const out = [];
      sec.querySelectorAll('[data-kd-id]').forEach(n => {
        const t = typeOf(n);
        if (!t || t === 'section' || t === 'header' || t === 'footer') return;
        if (n.closest('main .sec') !== (sec.matches('main .sec') ? sec : n.closest('main .sec'))) return;
        if (seen[t]) { seen[t].count++; return; }
        seen[t] = { t, n, count: 1 };
        out.push(seen[t]);
      });
      return out.slice(0, 12);
    };
    const hdr = doc.querySelector('.hdr');
    if (hdr?.dataset.kdId !== undefined) add(T.header, '', hdr, 1, kids(hdr));
    doc.querySelectorAll('main .sec').forEach(sec => {
      if (sec.dataset.kdId === undefined || sec.parentElement?.closest('main .sec')) return;
      const c = contextOf(sec);
      const it = S.items.find(x => x.id === sec.id);
      const label = it ? (it.type === '@section' ? T.sectionStyle + ': ' + it.label : it.label) : (c.block ? blockLabel(c.block) : T.section);
      const sub = it ? it.variantLabel : (c.variant ? (B.blocks[c.block]?.variants?.[c.variant] || c.variant) : '');
      add(label, sub, sec, 1, kids(sec));
    });
    const ftr = doc.querySelector('.ftr');
    if (ftr?.dataset.kdId !== undefined) add(T.footer, '', ftr, 1, kids(ftr));
    const mk = (it, level, parentUl) => {
      const li = document.createElement('li');
      li.setAttribute('role', 'treeitem');
      li.setAttribute('aria-level', String(level));
      li.tabIndex = -1;
      const sel = S.sel && S.sel.kdId === it.n.dataset.kdId;
      li.setAttribute('aria-selected', String(!!sel));
      li.className = 'kd-tree__i';
      li.dataset.kid = it.n.dataset.kdId;
      li.innerHTML = `<span class="kd-tree__l">${level === 1 && it.children?.length ? `<span class="kd-tree__c" aria-hidden="true">${I.caret}</span>` : ''}<span>${esc(it.label)}</span>${it.sub ? `<span class="kd-tree__s">${esc(it.sub)}</span>` : ''}${it.count > 1 ? `<span class="kd-tree__s">${it.count} ×</span>` : ''}</span>`;
      parentUl.append(li);
      treeNodes.push(li);
      li._node = it.n;
      li.addEventListener('click', e => { e.stopPropagation(); chooseTree(li); });
      if (level === 1 && it.children?.length) {
        const open = S.sel && it.n.contains(frameNode(S.sel.kdId));
        li.setAttribute('aria-expanded', String(!!open));
        const ul = document.createElement('ul');
        ul.setAttribute('role', 'group');
        ul.hidden = !open;
        li.append(ul);
        li._group = ul;
        it.children.forEach(ch => mk({ label: C.elements[ch.t].label, sub: '', n: ch.n, count: ch.count }, 2, ul));
      }
      return li;
    };
    items.forEach(it => mk(it, 1, tree));
    const cur = treeNodes.find(li => li.getAttribute('aria-selected') === 'true') || treeNodes[0];
    if (cur) cur.tabIndex = 0;
    if (had && cur) cur.focus();
  };
  const frameNode = kid => primary()?.frame.contentDocument?.querySelector(`[data-kd-id="${kid}"]`);
  const visibleTree = () => treeNodes.filter(li => !li.closest('[role=group][hidden]'));
  const chooseTree = li => {
    const n = li._node;
    if (!n?.isConnected) return;
    frames.forEach(f => {
      const m = f.frame.contentDocument?.querySelector(`[data-kd-id="${n.dataset.kdId}"]`);
      if (m) { m.scrollIntoView({ block: 'center' }); f.scroll = f.frame.contentWindow.scrollY; }
    });
    selectNode(n, false);
    const again = treeNodes.find(x => x.dataset.kid === n.dataset.kdId);
    if (again) { treeNodes.forEach(x => { x.tabIndex = -1; }); again.tabIndex = 0; again.focus(); }
  };
  tree.addEventListener('keydown', e => {
    const list = visibleTree();
    const cur = document.activeElement.closest?.('[role=treeitem]');
    const i = list.indexOf(cur);
    if (i < 0) return;
    const go = j => { const n = list[Math.max(0, Math.min(list.length - 1, j))]; if (!n) return; list.forEach(x => { x.tabIndex = -1; }); n.tabIndex = 0; n.focus(); };
    if (e.key === 'ArrowDown') { e.preventDefault(); go(i + 1); }
    else if (e.key === 'ArrowUp') { e.preventDefault(); go(i - 1); }
    else if (e.key === 'Home') { e.preventDefault(); go(0); }
    else if (e.key === 'End') { e.preventDefault(); go(list.length - 1); }
    else if (e.key === 'ArrowRight' && cur._group) { e.preventDefault(); if (cur._group.hidden) { cur._group.hidden = false; cur.setAttribute('aria-expanded', 'true'); } else go(i + 1); }
    else if (e.key === 'ArrowLeft') {
      e.preventDefault();
      if (cur._group && !cur._group.hidden) { cur._group.hidden = true; cur.setAttribute('aria-expanded', 'false'); }
      else { const p = cur.parentElement.closest('[role=treeitem]'); if (p) go(list.indexOf(p)); }
    } else if (e.key === 'Enter' || e.key === ' ') { e.preventDefault(); chooseTree(cur); }
  });
  tree.addEventListener('click', e => {
    const c = e.target.closest('.kd-tree__c');
    const li = c?.closest('[role=treeitem]');
    if (li?._group) { e.stopPropagation(); li._group.hidden = !li._group.hidden; li.setAttribute('aria-expanded', String(!li._group.hidden)); }
  }, true);

  // ------------------------------------------------------------------ Inspektor (Reiter „Element“)
  const pe = panel('element');
  const PROP_GROUPS = [
    ['groupPaint', ['color', 'background', 'border_color', 'border_width', 'shadow']],
    ['groupType', ['font', 'size', 'weight', 'tracking', 'line_height', 'case', 'underline']],
    ['groupShape', ['radius', 'opacity']],
    ['groupSpace', ['padding_y', 'padding_x', 'lift']],
    ['groupFocus', ['outline_color', 'outline_width']],
  ];
  const colorOptions = cur => `<option value="">${esc(T.inherit)}</option><optgroup label="${esc(T.tokenColor)}">`
    + colorTokens.map(([n, t]) => `<option value="token:${esc(n)}"${cur === 'token:' + n ? ' selected' : ''}>${esc(t.label)}</option>`).join('')
    + `</optgroup><option value="#"${cur && !String(cur).startsWith('token:') ? ' selected' : ''}>${esc(T.ownColor)}</option>`;
  /** Farbwahl: Token oder eigene Farbe (mit optionalem Dunkel-Wert). get() → {v, dark}, set(v, dark) */
  const colorControl = (label, get, set, opts = {}) => {
    const w = document.createElement('div');
    w.className = 'kd-prop kd-prop--color';
    const iid = id('c');
    const cur = get();
    w.innerHTML = `<label for="${iid}">${esc(label)}</label><div class="kd-prop__row"><span class="kd-sw" aria-hidden="true"></span><select id="${iid}">${opts.required ? colorOptions(cur.v).replace(`<option value="">${esc(T.inherit)}</option>`, '') : colorOptions(cur.v)}</select>`
      + (opts.required ? '' : `<button type="button" class="kd-x" data-x title="${esc(T.reset)}">${I.reset}<span class="kd-sr">${esc(T.reset)} – ${esc(label)}</span></button>`) + `</div>`
      + `<div class="kd-prop__own" data-own><input type="color" aria-label="${esc(fmt(T.lightValue, { label }))}"><input class="kd-hex" maxlength="7" spellcheck="false" aria-label="${esc(fmt(T.lightValue, { label }) + ', ' + T.hex)}">`
      + `<span class="kd-color__mode">${esc(T.darkOwn)}</span><input type="color" aria-label="${esc(fmt(T.darkValue, { label }))}"><input class="kd-hex" maxlength="7" spellcheck="false" placeholder="—" aria-label="${esc(fmt(T.darkValue, { label }) + ', ' + T.hex)}"></div>`;
    const sel = $('select', w), own = $('[data-own]', w), sw = $('.kd-sw', w);
    const [p1, h1, p2, h2] = $$('input', own);
    const paint = () => {
      const c = get();
      sel.value = !c.v ? '' : String(c.v).startsWith('token:') ? c.v : '#';
      own.hidden = sel.value !== '#';
      const light = resolve(c.v) || '';
      sw.style.background = light || 'transparent';
      sw.classList.toggle('is-empty', !light);
      if (sel.value === '#') { p1.value = (c.v || '#000000').toLowerCase(); h1.value = c.v || ''; p2.value = (c.dark || c.v || '#000000').toLowerCase(); h2.value = c.dark || ''; }
    };
    const norm = v => { v = String(v).trim().toUpperCase(); if (/^[0-9A-F]{6}$/.test(v)) v = '#' + v; return hexOk(v) ? v : null; };
    sel.addEventListener('change', () => {
      if (sel.value === '#') { const c = get(); const base = resolve(c.v) || resolve('token:accent'); set(base, null); }
      else set(sel.value || null, null);
      paint();
    });
    p1.addEventListener('input', () => { set(norm(p1.value), get().dark); paint(); });
    h1.addEventListener('change', () => { const v = norm(h1.value); if (v) { set(v, get().dark); paint(); } else h1.setAttribute('aria-invalid', 'true'); });
    p2.addEventListener('input', () => { set(get().v, norm(p2.value)); paint(); });
    h2.addEventListener('change', () => { const v = h2.value.trim() === '' ? null : norm(h2.value); set(get().v, v); paint(); });
    $('[data-x]', w)?.addEventListener('click', () => { set(null, null); paint(); });
    paint();
    w._sync = paint;
    return w;
  };
  const propControl = (p, get, set) => {
    const d = C.props[p];
    if (d.type === 'color') return colorControl(d.label, () => ({ v: get(p), dark: get(p + '@dark') }), (v, dk) => { set(p, v); set(p + '@dark', dk && v && !String(v).startsWith('token:') ? dk : null); });
    const w = document.createElement('div');
    w.className = 'kd-prop';
    const iid = id(p);
    const cur = get(p);
    if (d.type === 'range') {
      const mid = cur ?? (d.min + d.max) / 2;
      w.innerHTML = `<label for="${iid}">${esc(d.label)}</label><div class="kd-prop__row kd-range"><input type="range" id="${iid}" min="${d.min}" max="${d.max}" step="${d.step}" value="${mid}"${cur === undefined ? ' class="is-unset"' : ''}>`
        + `<input type="number" class="kd-num" min="${d.min}" max="${d.max}" step="${d.step}" value="${cur ?? ''}" placeholder="${esc(T.inherit)}" aria-label="${esc(d.label)}${d.unit ? ' (' + esc(d.unit) + ')' : ''}">`
        + `<button type="button" class="kd-x" data-x title="${esc(T.reset)}">${I.reset}<span class="kd-sr">${esc(T.reset)} – ${esc(d.label)}</span></button></div>`;
      const [r, nb] = $$('input', w);
      const on = v => { if (v === '' || isNaN(v)) return; v = Math.min(d.max, Math.max(d.min, Number(v))); r.value = v; nb.value = v; r.classList.remove('is-unset'); set(p, v); };
      r.addEventListener('input', () => on(r.value));
      nb.addEventListener('change', () => { if (nb.value === '') { set(p, null); r.classList.add('is-unset'); } else on(nb.value); });
      $('[data-x]', w).addEventListener('click', () => { set(p, null); nb.value = ''; r.classList.add('is-unset'); });
    } else {
      w.innerHTML = `<label for="${iid}">${esc(d.label)}</label><div class="kd-prop__row"><select id="${iid}"><option value="">${esc(T.inherit)}</option>${Object.entries(d.options).map(([k, l]) => `<option value="${esc(k)}"${cur === k ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select></div>`;
      const s = $('select', w);
      s.addEventListener('change', () => set(p, s.value || null));
    }
    return w;
  };
  const paintInspector = () => {
    pe.innerHTML = '';
    const sel = S.sel;
    if (!sel) {
      const g = document.createElement('div');
      g.className = 'kd-group';
      g.innerHTML = `<h2 class="kd-group__h">${esc(T.select)}</h2><p class="f-help">${esc(T.selectHelp)}</p><div class="kd-chips">`
        + Object.entries(C.elements).map(([k, e]) => `<button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-el="${esc(k)}">${esc(e.label)}${scopesWith(k).length ? ' ' + I.check : ''}</button>`).join('') + '</div>';
      $$('[data-el]', g).forEach(b => b.addEventListener('click', () => {
        S.sel = { el: b.dataset.el, kdId: null, block: '', variant: '', scope: '*' };
        S.state = 'base';
        paintSelection();
        paintInspector();
        $('[data-kd-insp-h]', pe)?.focus();
      }));
      pe.append(g);
      return;
    }
    const def = C.elements[sel.el];
    const head = document.createElement('div');
    head.className = 'kd-group kd-insp__head';
    head.innerHTML = `<h2 class="kd-insp__t" tabindex="-1" data-kd-insp-h>${I.cursor} ${esc(def.label)}</h2>`
      + (sel.block ? `<p class="f-help">${esc(fmt(T.inBlock, { block: blockLabel(sel.block) }))}${sel.variant ? ' · ' + esc(B.blocks[sel.block]?.variants?.[sel.variant] || sel.variant) : ''}</p>` : '')
      + `<button type="button" class="adm-btn adm-btn--ghost adm-btn--small kd-insp__close" data-close>${I.reset}<span class="kd-sr">${esc(T.select)}</span></button>`;
    $('[data-close]', head).addEventListener('click', () => { S.sel = null; S.force = false; paintSelection(); paintInspector(); paintLayers(); });
    pe.append(head);
    // Geltung
    if (!def.global && sel.block) {
      const opts = [['*', T.scopeAll], [sel.block, fmt(T.scopeBlock, { block: blockLabel(sel.block) })]];
      if (sel.variant) opts.push([sel.block + '.' + sel.variant, fmt(T.scopeVariant, { block: blockLabel(sel.block), variant: B.blocks[sel.block]?.variants?.[sel.variant] || sel.variant })]);
      if (sel.el === 'section') opts.shift();
      const fs = document.createElement('fieldset');
      fs.className = 'kd-group kd-radios';
      const nm = id('scope');
      fs.innerHTML = `<legend class="kd-group__h">${esc(T.scope)}</legend>` + opts.map(([k, l]) => `<label class="kd-check"><input type="radio" name="${nm}" value="${esc(k)}"${sel.scope === k ? ' checked' : ''}> ${esc(l)}${S.styles.scopes[k]?.[sel.el] ? ' ' + I.check : ''}</label>`).join('');
      $$('input', fs).forEach(r => r.addEventListener('change', () => { sel.scope = r.value; paintInspector(); paintSelection(); }));
      pe.append(fs);
    } else if (sel.scope !== '*' && !sel.block) {
      const p = document.createElement('p');
      p.className = 'f-help';
      p.textContent = T.scope + ': ' + scopeText(sel.scope);
      pe.append(p);
    }
    // Zustand
    if (def.states.length > 1) {
      const g = document.createElement('div');
      g.className = 'kd-group';
      g.innerHTML = `<span class="kd-group__h" id="${id('st')}">${esc(T.state)}</span><div class="kd-seg kd-seg--wrap" role="group" aria-label="${esc(T.state)}">`
        + def.states.map(s => `<button type="button" class="kd-seg__b" data-st="${s}" aria-pressed="${S.state === s}">${esc(C.states[s])}${stState(sel.scope, sel.el, s) ? ' •' : ''}</button>`).join('')
        + `</div><label class="kd-check"><input type="checkbox" data-force${S.force ? ' checked' : ''}> ${esc(T.stateShow)}</label>`;
      $$('[data-st]', g).forEach(b => b.addEventListener('click', () => { S.state = b.dataset.st; paintInspector(); paintSelection(); $(`[data-st="${S.state}"]`, pe)?.focus(); }));
      $('[data-force]', g).addEventListener('change', e => { S.force = e.target.checked; paintSelection(); });
      pe.append(g);
    }
    // Abschnitts-Stil nur in der Vorschau
    if (sel.el === 'section' && S.styles.sections.length && sel.kdId !== null && S.mode === 'page') {
      const g = document.createElement('div');
      g.className = 'kd-group';
      const sid = id('as');
      g.innerHTML = `<label for="${sid}">${esc(T.assignStyle)}</label><select id="${sid}"><option value="">${esc(T.assignNone)}</option>${S.styles.sections.map(s => `<option value="${esc(s.key)}"${S.assign[sel.kdId] === s.key ? ' selected' : ''}>${esc(s.label)}</option>`).join('')}</select>`;
      $('select', g).addEventListener('change', e => { S.assign[sel.kdId] = e.target.value; frames.forEach(f => { const d = f.frame.contentDocument; if (d) applyAssign(d); drawSel(f); }); });
      pe.append(g);
    }
    // Eigenschaften
    const get = p => stState(sel.scope, sel.el, S.state)?.[p] ?? undefined;
    const set = (p, v) => {
      const st = stState(sel.scope, sel.el, S.state, true);
      if (v === null || v === undefined || v === '') delete st[p]; else st[p] = v;
      stylesChanged();
      paintSelectionSoon();
    };
    for (const [gk, props] of PROP_GROUPS) {
      const list = props.filter(p => def.props.includes(p));
      if (!list.length) continue;
      if (gk === 'groupFocus' && !['focus', 'base'].includes(S.state)) continue;
      const g = document.createElement('div');
      g.className = 'kd-group';
      g.innerHTML = `<h3 class="kd-group__h">${esc(T[gk])}</h3>`;
      list.forEach(p => g.append(propControl(p, get, set)));
      pe.append(g);
    }
    if (stState(sel.scope, sel.el, S.state)) {
      const b = document.createElement('button');
      b.type = 'button';
      b.className = 'adm-btn adm-btn--ghost adm-btn--small';
      b.innerHTML = `${I.reset} ${esc(T.resetAll)}`;
      b.addEventListener('click', () => { delete S.styles.scopes[sel.scope][sel.el][S.state]; stylesChanged(); paintInspector(); });
      pe.append(b);
    }
    // Kontrast dieses Elements
    const cg = document.createElement('div');
    cg.className = 'kd-group';
    cg.dataset.kdInspContrast = '';
    pe.append(cg);
    paintInspContrast();
    // Maße
    const mg = document.createElement('div');
    mg.className = 'kd-group kd-measure';
    mg.dataset.kdMeasure = '';
    pe.append(mg);
    paintMeasure();
  };
  let selTimer = 0;
  const paintSelectionSoon = () => { clearTimeout(selTimer); selTimer = setTimeout(() => { paintSelection(); paintMeasure(); }, 260); };
  const paintInspContrast = () => {
    const cg = $('[data-kd-insp-contrast]', pe);
    if (!cg || !S.sel) return;
    const mine = S.checks.filter(c => c.scope === S.sel.scope && c.element === S.sel.el);
    cg.innerHTML = `<h3 class="kd-group__h">${esc(T.contrastEl)}</h3>` + (mine.length
      ? `<ul class="kd-contrast__all kd-insp__checks">${mine.map(c => `<li class="${c.ok ? 'is-ok' : 'is-fail'}"><span class="kd-pair" aria-hidden="true"><span style="background:${c.bg};color:${c.fg}" class="kd-aa"></span></span><span>${esc(C.states[c.state] || c.state)} · ${esc(T['what_' + c.what] || c.what)} · ${esc(c.mode === 'dark' ? T.dark : T.light)}: ${esc(num(c.ratio))} : 1${c.exempt ? ' – ' + esc(T.exempt) : ''}</span>${!c.ok ? `<button type="button" class="adm-btn adm-btn--small" data-cid="${esc(c.id)}">${esc(T.fix)}</button>` : ''}</li>`).join('')}</ul>`
      : `<p class="f-help">${esc(T.contrastNone)}</p>`);
    $$('[data-cid]', cg).forEach(b => b.addEventListener('click', () => fixStyle(S.checks.find(c => c.id === b.dataset.cid))));
  };
  const paintMeasure = () => {
    const mg = $('[data-kd-measure]', pe);
    if (!mg || !S.sel) return;
    const f = primary();
    const doc = f?.frame.contentDocument;
    const n = doc && (S.sel.kdId !== null ? doc.querySelector(`[data-kd-id="${S.sel.kdId}"]`) : selNodes(doc)[0]);
    if (!n) { mg.innerHTML = ''; return; }
    const r = n.getBoundingClientRect();
    const cs = doc.defaultView.getComputedStyle(n);
    const ff = cs.fontFamily.split(',')[0].replace(/["']/g, '');
    const rows = [
      [T.mSize, `${Math.round(r.width)} × ${Math.round(r.height)} px`],
      [T.mPadding, [cs.paddingTop, cs.paddingRight, cs.paddingBottom, cs.paddingLeft].map(px).join(' · ') + ' px'],
      [T.mFont, `${ff} ${cs.fontWeight} · ${num(parseFloat(cs.fontSize), 1)} / ${cs.lineHeight === 'normal' ? 'normal' : num(parseFloat(cs.lineHeight), 1)} px`],
      [T.mRadius, cs.borderRadius],
      [T.mColor, `${cs.color} / ${cs.backgroundColor}`],
    ];
    mg.innerHTML = `<h3 class="kd-group__h">${esc(T.measure)}</h3><dl class="kd-dl">${rows.map(([k, v]) => `<dt>${esc(k)}</dt><dd>${esc(v)}</dd>`).join('')}</dl>`
      + (S.gap ? `<p class="f-help">${esc(fmt(T.mGap, S.gap))}</p>` : '');
  };

  // ------------------------------------------------------------------ Abschnitts-Stile
  const pa = panel('abschnitte');
  const slug = s => String(s).toLowerCase().replace(/ä/g, 'ae').replace(/ö/g, 'oe').replace(/ü/g, 'ue').replace(/ß/g, 'ss').replace(/[^a-z0-9]+/g, '-').replace(/^[^a-z]+/, '').replace(/-+$/, '').slice(0, 24);
  const sectionKey = label => {
    let k = slug(label) || 'stil';
    if (B.backgrounds[k] !== undefined || k.length < 2) k = 'stil-' + k;
    let t = k, i = 2;
    while (S.styles.sections.some(s => s.key === t)) t = k.slice(0, 21) + '-' + i++;
    return t;
  };
  const paintSections = () => {
    pa.innerHTML = `<div class="kd-group"><h2 class="kd-group__h">${esc(T.sections)}</h2><p class="f-help">${esc(T.sectionsHelp)}</p>`
      + `<button type="button" class="adm-btn adm-btn--small" data-add>${I.plus} ${esc(T.sectionAdd)}</button></div>`;
    $('[data-add]', pa).addEventListener('click', () => {
      const label = T.sectionNew + ' ' + (S.styles.sections.length + 1);
      S.styles.sections.push({ key: sectionKey(label), label, kind: 'gradient', bg: '#1E3A5F', bg2: '#155E75', text: '#FFFFFF', angle: 135, opacity: 70 });
      stylesChanged();
      paintSections();
      pa.querySelector('.kd-sec:last-of-type input')?.focus();
      if (S.mode === 'gallery') schedule(true);
    });
    if (!S.styles.sections.length) { pa.insertAdjacentHTML('beforeend', `<p class="f-help">${esc(T.sNone)}</p>`); return; }
    S.styles.sections.forEach((s, i) => {
      const card = document.createElement('div');
      card.className = 'kd-group kd-sec';
      card.id = 'kd-sec-' + s.key;
      const lid = id('sl'), kid = id('sk'), aid = id('sa'), oid = id('so');
      card.innerHTML = `<div class="kd-sec__prev" aria-hidden="true"><span>Aa</span></div><div class="kd-sec__badges" data-badges></div>`
        + `<div class="f"><label for="${lid}">${esc(T.label)}</label><input id="${lid}" maxlength="60" value="${esc(s.label)}" data-label><p class="f-help"><code>bg-${esc(s.key)}</code></p></div>`
        + `<div class="f"><label for="${kid}">${esc(T.kind)}</label><select id="${kid}" data-kind>${Object.entries(C.kinds).map(([k, l]) => `<option value="${k}"${s.kind === k ? ' selected' : ''}>${esc(l)}</option>`).join('')}</select></div>`
        + `<div data-colors></div>`
        + `<div class="kd-prop" data-angle><label for="${aid}">${esc(T.sAngle)}</label><div class="kd-prop__row kd-range"><input type="range" id="${aid}" min="0" max="360" step="5" value="${s.angle}"><input type="number" class="kd-num" min="0" max="360" step="5" value="${s.angle}" aria-label="${esc(T.sAngle)}"></div></div>`
        + `<div class="kd-prop" data-opacity><label for="${oid}">${esc(T.sOpacity)}</label><div class="kd-prop__row kd-range"><input type="range" id="${oid}" min="10" max="95" step="5" value="${s.opacity}"><input type="number" class="kd-num" min="10" max="95" step="5" value="${s.opacity}" aria-label="${esc(T.sOpacity)}"></div></div>`
        + `<div class="kd-inline"><button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-show>${I.eye} ${esc(T.sShow)}</button><button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-del>${I.trash} ${esc(T.sectionRemove)}<span class="kd-sr"> – ${esc(s.label)}</span></button></div>`;
      const colors = $('[data-colors]', card);
      const cc = (k, label, required) => colorControl(label, () => ({ v: s[k], dark: s[k + '@dark'] }), (v, dk) => {
        if (v) s[k] = v; else if (!required) delete s[k];
        if (dk && v && !String(v).startsWith('token:')) s[k + '@dark'] = dk; else delete s[k + '@dark'];
        paintPrev(); stylesChanged();
      }, { required });
      colors.append(cc('bg', T.sBg, true), cc('bg2', T.sBg2, true), cc('text', T.sText, true), cc('accent', T.sAccent, false));
      const paintPrev = () => {
        const p = $('.kd-sec__prev', card);
        const a = resolve(s.bg) || '#000000', b = resolve(s.bg2 || s.bg) || a;
        p.style.background = s.kind === 'flat' ? a : `linear-gradient(${s.angle}deg, ${a}, ${b})`;
        p.style.color = resolve(s.text) || '#FFFFFF';
        colors.children[1].hidden = s.kind === 'flat';
        $('[data-angle]', card).hidden = s.kind === 'flat';
        $('[data-opacity]', card).hidden = s.kind !== 'overlay';
        const mine = S.checks.filter(c => c.scope === 'section' && c.element === s.key);
        $('[data-badges]', card).innerHTML = mine.map(c => `${esc(T['what_' + c.what] || c.what)} ${badge(c)}`).join(' ');
      };
      $('[data-label]', card).addEventListener('input', e => { s.label = e.target.value; stylesChanged(); });
      $('[data-kind]', card).addEventListener('change', e => { s.kind = e.target.value; if (s.kind !== 'flat') s.bg2 ??= s.bg; paintPrev(); stylesChanged(); });
      const rng = (sel, k) => { const [r, n] = $$(`${sel} input`, card); const on = v => { s[k] = Number(v); r.value = v; n.value = v; paintPrev(); stylesChanged(); }; r.addEventListener('input', () => on(r.value)); n.addEventListener('change', () => on(n.value)); };
      rng('[data-angle]', 'angle');
      rng('[data-opacity]', 'opacity');
      $('[data-del]', card).addEventListener('click', () => { S.styles.sections.splice(i, 1); stylesChanged(); paintSections(); if (S.mode === 'gallery') schedule(true); });
      $('[data-show]', card).addEventListener('click', () => {
        const go = () => frames.forEach(f => f.frame.contentDocument?.getElementById('kdg-stil-' + s.key)?.scrollIntoView({ block: 'center' }));
        if (S.mode !== 'gallery') { setMode('gallery'); afterRender = go; } else go();
      });
      card._paint = paintPrev;
      pa.append(card);
      paintPrev();
    });
  };
  let afterRender = null;

  // ------------------------------------------------------------------ Vorschau rendern
  let reqId = 0, scheme = { light: 'scheme-light', dark: 'scheme-dark' }, force = 'is-dark';
  const darkNote = () => { const n = $('[data-darkoff]', canvas); if (n) n.hidden = S.values.dark !== false; };
  const withScheme = (html, mode) => { const cls = scheme[mode]; return cls ? html.replace(/<html\b([^>]*)\bclass="([^"]*)"/i, (m, a, c) => `<html${a}class="${c} ${cls}"`) : html; };
  const setChecks = list => {
    S.checks = list || [];
    paintContrast();
    paintInspContrast();
    $$('.kd-sec', pa).forEach(c => c._paint?.());
  };
  const render = async () => {
    if (S.mode === 'compare') return renderCompare();
    const my = ++reqId;
    status(T.loading, true);
    root.classList.add('is-busy');
    try {
      const j = await post(B.urls.preview, { mode: S.mode === 'gallery' ? 'gallery' : 'full', values: S.values, extra: S.extra, styles: S.styles, page: S.page, source: S.source });
      if (my !== reqId) return;
      force = j.force || force;
      scheme = j.scheme || scheme;
      S.items = j.items || [];
      if (S.mode === 'gallery' && j.missing?.length) status(fmt(T.galleryMissing, { list: j.missing.join(', ') }));
      else status(S.dirty ? T.dirty : '');
      frames.forEach(f => { f.frame.srcdoc = withScheme(f.mode === 'dark' ? j.dark : j.light, f.mode); });
      if (j.page && S.mode === 'page') pageSel.value = String(j.page);
      setChecks(j.checks);
      darkNote();
      if (afterRender) { const fn = afterRender; afterRender = null; setTimeout(fn, 400); }
    } catch (e) {
      if (my === reqId) status(fmt(T.error, { msg: e.message }), true);
    } finally {
      if (my === reqId) root.classList.remove('is-busy');
    }
  };
  const patch = async () => {
    if (S.mode === 'compare') return renderCompare();
    const my = ++reqId;
    try {
      const j = await post(B.urls.preview, { mode: 'live', values: S.values, extra: S.extra, styles: S.styles });
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
        let after = doc.querySelector('link[rel=stylesheet][href*="/css/site.css"]');
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
      setChecks(j.checks);
      darkNote();
      setTimeout(() => { paintSelection(); paintMeasure(); }, 120);
    } catch (e) {
      if (my === reqId) status(fmt(T.error, { msg: e.message }), true);
    }
  };
  let timer = 0, needFull = false, autoTimer = 0;
  const schedule = full => {
    syncLH();
    needFull = needFull || full;
    clearTimeout(timer);
    timer = setTimeout(() => { const f = needFull; needFull = false; (f ? render : patch)(); }, full ? 250 : 90);
  };
  // Eine Eingabe der Design-Werte hat sich geändert
  function changed(n, o = {}) {
    setDirty();
    commit();
    paintContrast();
    if (['ratio', 'fs_min', 'fs_max', 'font_head', 'heading_weight'].includes(n)) paintScale();
    if (o.color && !o.dark && S.auto[n]) {
      clearTimeout(autoTimer);
      autoTimer = setTimeout(() => suggest(Object.keys(S.auto).filter(k => S.auto[k])), 160);
    }
    if (o.color) $$('.kd-sec', pa).forEach(c => c._paint?.());
    schedule(!!o.full || B.markup.includes(n));
  }

  // ------------------------------------------------------------------ Ansichten: Seite, Galerie, Vergleich
  const setMode = m => {
    if (S.mode === m) return;
    S.mode = m;
    $$('[data-kd-mode]').forEach(b => b.setAttribute('aria-pressed', String(b.dataset.kdMode === m)));
    $('[data-kd-for="page"]').hidden = m !== 'page';
    $('[data-kd-for="gallery"]').hidden = m !== 'gallery';
    showBox.hidden = m === 'compare';
    S.sel = null; S.assign = {}; S.items = [];
    paintInspector();
    if (m === 'compare') buildCompare(); else { buildFrames(); render(); }
    paintLayers();
  };
  $$('[data-kd-mode]').forEach(b => b.addEventListener('click', () => setMode(b.dataset.kdMode)));

  // Vergleich: zwei Stände (gespeicherte Versionen oder der aktuelle) nebeneinander, Desktop, hell oder dunkel, Bildlauf gekoppelt
  const verCache = {};
  const CMP = { a: '', b: 'current', mode: 'light' };
  const stateOf = async key => {
    if (key === 'current') return { values: S.values, extra: S.extra, styles: S.styles, label: T.compareCurrent };
    if (!verCache[key]) {
      const res = await fetch(B.urls.version + key, { headers: { Accept: 'application/json' } });
      const j = await res.json();
      if (!j.ok) throw new Error(j.error || res.status);
      verCache[key] = { ...j.data, label: fmt(T.compareVersion, { v: j.version }) };
    }
    return verCache[key];
  };
  const buildCompare = () => {
    canvas.innerHTML = '';
    canvas.scrollLeft = 0;
    canvas.classList.add('is-compare');
    frames = [];
    CMP.a ||= String((B.versions[1] || B.versions[0])?.version || 'current');   // ohne Änderungen: die vorige Version
    const opts = cur => `<option value="current"${cur === 'current' ? ' selected' : ''}>${esc(T.compareCurrent)}</option>` + B.versions.map(v => `<option value="${v.version}"${String(v.version) === cur ? ' selected' : ''}>${esc(fmt(T.compareVersion, { v: v.version }))}${v.note ? ' – ' + esc(v.note) : ''}</option>`).join('');
    const bar = document.createElement('div');
    bar.className = 'kd-cmp__bar';
    const ia = id('ca'), ib = id('cb'), im = id('cm');
    bar.innerHTML = `<label for="${ia}">${esc(T.compareA)}</label><select id="${ia}" data-a>${opts(CMP.a)}</select><label for="${ib}">${esc(T.compareB)}</label><select id="${ib}" data-b>${opts(CMP.b)}</select>`
      + `<label for="${im}">${esc(T.compareMode)}</label><select id="${im}" data-m><option value="light">${esc(T.lightRow)}</option><option value="dark"${CMP.mode === 'dark' ? ' selected' : ''}>${esc(T.darkRow)}</option></select>`;
    canvas.append(bar);
    const row = document.createElement('div');
    row.className = 'kd-row__frames kd-cmp';
    for (const side of ['a', 'b']) {
      const fig = document.createElement('figure');
      fig.className = 'kd-frame';
      fig.dataset.dev = 'compare';
      fig.style.setProperty('--w', 1280);
      fig.style.setProperty('--h', 900);
      fig.innerHTML = `<figcaption data-cap></figcaption><div class="kd-frame__vp"><iframe title="${esc(side === 'a' ? T.compareA : T.compareB)}"></iframe></div>`;
      row.append(fig);
      const f = { el: fig, frame: $('iframe', fig), mode: CMP.mode, dev: 'compare', side, scroll: 0 };
      f.frame.addEventListener('load', () => {
        wire(f);
        const w = f.frame.contentWindow;
        w.addEventListener('scroll', () => { const o = frames.find(x => x !== f); try { if (o && Math.abs(o.frame.contentWindow.scrollY - w.scrollY) > 2) o.frame.contentWindow.scrollTo(0, w.scrollY); } catch { /* */ } }, { passive: true });
      });
      frames.push(f);
    }
    canvas.append(row);
    const diff = document.createElement('div');
    diff.className = 'kd-cmp__diff';
    diff.dataset.diff = '';
    canvas.append(diff);
    $('[data-a]', bar).addEventListener('change', e => { CMP.a = e.target.value; renderCompare(); });
    $('[data-b]', bar).addEventListener('change', e => { CMP.b = e.target.value; renderCompare(); });
    $('[data-m]', bar).addEventListener('change', e => { CMP.mode = e.target.value; frames.forEach(f => { f.mode = CMP.mode; }); renderCompare(); });
    applyShow();
    renderCompare();
  };
  const countStyles = st => Object.values(st?.scopes || {}).reduce((n, els) => n + Object.values(els).reduce((m, sts) => m + Object.values(sts).reduce((k, p) => k + Object.keys(p).filter(x => !x.includes('@')).length, 0), 0), 0) + (st?.sections?.length || 0);
  const renderCompare = async () => {
    const my = ++reqId;
    status(T.loading, true);
    try {
      const [a, b] = await Promise.all([stateOf(CMP.a), stateOf(CMP.b)]);
      const [ja, jb] = await Promise.all([a, b].map(x => post(B.urls.preview, { mode: 'full', values: x.values, extra: x.extra, styles: x.styles || emptyStyles(), page: S.page })));
      if (my !== reqId) return;
      scheme = ja.scheme || scheme;
      [[frames[0], ja, a], [frames[1], jb, b]].forEach(([f, j, x]) => {
        f.frame.srcdoc = withScheme(CMP.mode === 'dark' ? j.dark : j.light, CMP.mode);
        $('[data-cap]', f.el).textContent = x.label;
      });
      const rows = [];
      for (const [n, t] of Object.entries(tokens)) {
        for (const k of [n, n + '@dark']) {
          if (a.values[k] === undefined && b.values[k] === undefined) continue;
          if (JSON.stringify(a.values[k]) === JSON.stringify(b.values[k])) continue;
          const sw = v => (hexOk(v) ? `<span class="kd-dot" style="background:${v}" aria-hidden="true"></span>` : '');
          rows.push(`<li><b>${esc(t.label)}${k.endsWith('@dark') ? ' (' + esc(T.dark) + ')' : ''}</b>: ${sw(a.values[k])}${esc(a.values[k])} → ${sw(b.values[k])}${esc(b.values[k])}</li>`);
        }
      }
      const sa = countStyles(a.styles), sb = countStyles(b.styles);
      if (JSON.stringify(a.styles || {}) !== JSON.stringify(b.styles || {})) rows.push(`<li>${esc(fmt(T.compareStyles, { a: sa, b: sb }))}</li>`);
      $('[data-diff]', canvas).innerHTML = `<h2 class="kd-group__h">${esc(T.compareDiff)}</h2>` + (rows.length ? `<ul class="kd-map">${rows.join('')}</ul>` : `<p class="f-help">${esc(T.compareSame)}</p>`);
      status(S.dirty ? T.dirty : '');
    } catch (e) {
      if (my === reqId) status(fmt(T.error, { msg: e.message }), true);
    }
  };

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
  const pxp = panel('export');
  const nid = id('name');
  pxp.innerHTML = `<div class="kd-group"><div class="f"><label for="${nid}-l">${esc(T.label)}</label><input id="${nid}-l" maxlength="120" data-x="label"></div>`
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
  const xf = k => $(`[data-x="${k}"]`, pxp);
  xf('label').value = S.label; xf('name').value = S.name; xf('description').value = S.meta.description || ''; xf('author').value = S.meta.author || '';
  const existsNote = () => {
    const n = $('[data-exists]', pxp);
    n.hidden = !B.kitExists;
    n.className = B.kitOwn ? 'f-help' : 'kd-warn';
    n.textContent = !B.kitExists ? '' : B.kitOwn ? T.existsOwn : fmt(T.exists, { name: S.name, source: B.kitExists });
  };
  existsNote();
  xf('label').addEventListener('input', () => { S.label = xf('label').value; $('[data-kd-title]').textContent = S.label; setDirty(); });
  xf('name').addEventListener('change', () => { const v = xf('name').value.trim().toLowerCase(); if (/^[a-z][a-z0-9-]{1,29}$/.test(v)) { S.name = v; xf('name').removeAttribute('aria-invalid'); setDirty(); } else xf('name').setAttribute('aria-invalid', 'true'); });
  xf('description').addEventListener('input', () => { S.meta.description = xf('description').value; setDirty(); });
  xf('author').addEventListener('input', () => { S.meta.author = xf('author').value; setDirty(); });
  function paintExportGate(fails, sfails = []) {
    const g = $('[data-gate]', pxp);
    if (!g) return;
    const n = fails.length + sfails.length;
    $$('[data-exp]', pxp).forEach(b => { b.setAttribute('aria-disabled', String(!!n)); });
    if (!n) { g.innerHTML = `<p class="kd-ok">${esc(T.exportReady)}</p>`; return; }
    g.innerHTML = `<p class="kd-warn"><b>${esc(T.exportGate)}</b></p><ul class="kd-gate__list">${fails.map((c, i) => `<li><button type="button" class="kd-linkbtn" data-i="${i}">${esc(pairText(c))}</button> – ${esc(fmt(T.ratio, { ratio: num(c.ratio), min: num(c.min, 1) }))}</li>`).join('')}`
      + `${sfails.map((c, i) => `<li><button type="button" class="kd-linkbtn" data-s="${i}">${esc(styleCheckText(c))}</button> – ${esc(fmt(T.ratio, { ratio: num(c.ratio), min: num(c.min, 1) }))}</li>`).join('')}</ul>`;
    $$('[data-i]', g).forEach(b => b.addEventListener('click', () => jump(fails[Number(b.dataset.i)].token)));
    $$('[data-s]', g).forEach(b => b.addEventListener('click', () => gotoStyle(sfails[Number(b.dataset.s)])));
  }
  $$('[data-exp]', pxp).forEach(b => b.addEventListener('click', async () => {
    if (b.getAttribute('aria-disabled') === 'true') { $('[data-gate] button', pxp)?.focus(); return; }
    if (S.dirty && !(await save())) return;
    const kind = b.dataset.exp;
    if (kind === 'studio') { location.href = B.urls.zipStudio; return; }
    if (kind === 'redaxo') { location.href = B.urls.zipRedaxo + '?ns=' + encodeURIComponent($('[data-ns]', pxp).value.trim() || 'Project'); return; }
    const out = $('[data-install-out]', pxp);
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

  // ------------------------------------------------------------------ Speichern, Versionen, Teilen
  const pv = panel('versionen');
  const noteId = id('note'), dId = id('sd'), snId = id('sn');
  pv.innerHTML = `<div class="kd-group"><div class="f"><label for="${noteId}">${esc(T.note)}</label><input id="${noteId}" maxlength="191" data-note></div></div>`
    + `<div class="kd-group"><h2 class="kd-group__h">${esc(T.versions)}</h2><ol class="kd-versions" data-versions></ol></div>`
    + `<div class="kd-group kd-share"><h2 class="kd-group__h">${I.link} ${esc(T.share)}</h2><p class="f-help">${esc(T.shareHelp)}</p>`
    + `<div class="kd-inline"><label for="${dId}">${esc(T.shareDays)}</label><select id="${dId}" data-days>${B.shareDays.map(d => `<option value="${d}"${d === 7 ? ' selected' : ''}>${esc(fmt(d === 1 ? T.shareDay : T.shareDaysN, { n: d }))}</option>`).join('')}</select></div>`
    + `<div class="f"><label for="${snId}">${esc(T.shareNote)}</label><input id="${snId}" maxlength="120" data-snote></div>`
    + `<button type="button" class="adm-btn adm-btn--small" data-share>${esc(T.shareCreate)}</button><div data-share-out aria-live="polite"></div>`
    + `<h3 class="kd-group__h">${esc(T.shareList)}</h3><ul class="kd-versions" data-shares></ul></div>`;
  const paintVersions = list => {
    B.versions = list;
    $('[data-versions]', pv).innerHTML = list.map((v, i) => `<li><span><b>v${v.version}</b> · ${esc(new Date(v.created_at.replace(' ', 'T')).toLocaleString(lang))}`
      + `${v.created_by ? ' · ' + esc(v.created_by) : ''}${v.note ? ' · ' + esc(v.note) : ''}${i === 0 ? ` <span class="adm-badge">${esc(T.current)}</span>` : ''}</span>`
      + `<button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-v="${v.version}">${esc(T.restore)}<span class="kd-sr"> – v${v.version}</span></button></li>`).join('');
    $$('[data-v]', pv).forEach(b => b.addEventListener('click', async () => {
      try {
        const res = await fetch(B.urls.version + b.dataset.v, { headers: { Accept: 'application/json' } });
        const j = await res.json();
        if (!j.ok) throw new Error(j.error || res.status);
        S.values = { ...j.data.values }; S.extra = { ...j.data.extra }; S.auto = { ...(j.data.auto || {}) }; S.meta = { ...S.meta, ...(j.data.meta || {}) };
        S.styles = { ...emptyStyles(), ...clone(j.data.styles || {}) };
        if (Array.isArray(S.styles.scopes)) S.styles.scopes = {};
        syncAll();
        paintSections();
        paintInspector();
        changed('*', { full: true });
        status(fmt(T.restored, { v: j.version }), true);
      } catch (e) { status(fmt(T.error, { msg: e.message }), true); }
    }));
  };
  paintVersions(B.versions);
  const paintShares = list => {
    $('[data-shares]', pv).innerHTML = list.map(s => `<li><span><b>v${s.version}</b> · ${esc(s.active ? fmt(T.shareActive, { date: new Date(s.expires_at.replace(' ', 'T')).toLocaleString(lang) }) : T.shareInactive)} · ${esc(fmt(T.shareHits, { n: s.hits }))}${s.note ? ' · ' + esc(s.note) : ''}</span>`
      + (s.active ? `<button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-revoke="${s.id}">${esc(T.shareRevoke)}<span class="kd-sr"> – v${s.version}</span></button>` : '') + `</li>`).join('');
    $$('[data-revoke]', pv).forEach(b => b.addEventListener('click', async () => {
      try { const j = await post(B.urls.shareRevoke + b.dataset.revoke + '/widerrufen', {}); paintShares(j.shares); status(j.message); } catch (e) { status(fmt(T.error, { msg: e.message }), true); }
    }));
  };
  paintShares(B.shares || []);
  $('[data-share]', pv).addEventListener('click', async () => {
    if (S.dirty && !(await save())) return;
    const out = $('[data-share-out]', pv);
    try {
      const j = await post(B.urls.share, { days: Number($('[data-days]', pv).value), note: $('[data-snote]', pv).value });
      const lid = id('sl');
      out.innerHTML = `<div class="f"><label for="${lid}">${esc(T.shareLink)}</label><div class="kd-inline"><input id="${lid}" readonly value="${esc(j.url)}" class="kd-code" data-url><button type="button" class="adm-btn adm-btn--small" data-copy>${I.copy} ${esc(T.shareCopy)}</button></div><p class="f-help">${esc(j.message)}</p></div>`;
      $('[data-copy]', out).addEventListener('click', async () => {
        const inp = $('[data-url]', out);
        inp.select();
        try { await navigator.clipboard.writeText(inp.value); } catch { document.execCommand?.('copy'); }
        status(T.shareCopied);
      });
      $('[data-url]', out).focus();
      $('[data-snote]', pv).value = '';
      paintShares(j.shares);
    } catch (e) { out.innerHTML = `<p class="kd-warn">${esc(e.message)}</p>`; }
  });
  const saveBtn = $('[data-kd-save]');
  async function save() {
    syncLH();
    saveBtn.disabled = true;
    status(T.saving, true);
    try {
      const j = await post(B.urls.save, { label: S.label, name: S.name, values: S.values, extra: S.extra, auto: S.auto, meta: S.meta, styles: S.styles, note: $('[data-note]', pv).value });
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

  // ------------------------------------------------------------------ Tastatur
  const typing = el => el && (el.isContentEditable || /^(INPUT|TEXTAREA|SELECT)$/.test(el.tagName)) && !['checkbox', 'radio', 'range', 'button', 'color'].includes(el.type);
  function onKey(e) {
    const mod = e.ctrlKey || e.metaKey;
    if (mod && e.key.toLowerCase() === 's') { e.preventDefault(); save(); return; }
    if (mod && !typing(document.activeElement) && e.key.toLowerCase() === 'z') { e.preventDefault(); travel(e.shiftKey ? 1 : -1); return; }
    if (mod && !typing(document.activeElement) && e.key.toLowerCase() === 'y') { e.preventDefault(); travel(1); return; }
    if (e.key === 'Escape' && S.sel && !typing(document.activeElement)) { S.sel = null; S.force = false; paintSelection(); paintInspector(); paintLayers(); }
    // Alt + Pfeil hoch: übergeordnetes Element wählen (z. B. Knopf → Karte → Abschnitt), Alt + Pfeil runter: erstes Kind
    if (e.altKey && (e.key === 'ArrowUp' || e.key === 'ArrowDown') && S.sel?.kdId != null && S.mode !== 'compare') {
      const doc = primary()?.frame.contentDocument;
      const n = doc?.querySelector(`[data-kd-id="${S.sel.kdId}"]`);
      if (!n) return;
      e.preventDefault();
      const next = e.key === 'ArrowUp' ? candidate(n.parentElement) : n.querySelector('[data-kd-id]');
      if (next) { selectNode(next, false); frames.forEach(f => f.frame.contentDocument?.querySelector(`[data-kd-id="${next.dataset.kdId}"]`)?.scrollIntoView({ block: 'nearest' })); }
    }
  }
  document.addEventListener('keydown', onKey);

  buildFrames();
  paintInspector();
  paintSections();
  paintContrast();
  commitNow();
  render();
})();
