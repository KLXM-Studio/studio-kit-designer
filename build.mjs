/**
 * Build-Haken des Kit-Designers für den Build des Cores (tools/build.mjs, `cd tools && pnpm run build`):
 *   built(ctx)  gebaute Dateien (public/assets/ext/kit_designer) nach public/ dieses Pakets spiegeln – auf dem Server legt
 *               `php bin/console extensions:publish` sie nach public/assets/ext/kit_designer (ohne Node)
 * Quellen: assets/css/kit-designer.css und assets/js/kit-designer.js (nur Verwaltung, Arbeitsfläche). Keine Abhängigkeiten. MIT
 */
import fs from 'node:fs';
import path from 'node:path';

/** public/assets/ext/kit_designer → {paket}/public (gleicher Stand: veraltete Dateien fallen weg) */
export function built({ dir, publicDir }) {
  const out = path.join(fs.realpathSync(dir), 'public');
  const want = new Set();
  const walk = (from, rel = '') => {
    for (const e of fs.readdirSync(path.join(from, rel), { withFileTypes: true })) {
      const r = path.join(rel, e.name);
      if (e.isDirectory()) { walk(from, r); continue; }
      want.add(r);
      fs.mkdirSync(path.join(out, rel), { recursive: true });
      fs.copyFileSync(path.join(from, r), path.join(out, r));
    }
  };
  if (!fs.existsSync(publicDir)) return;
  walk(publicDir);
  const prune = (rel = '') => {
    const d = path.join(out, rel);
    if (!fs.existsSync(d)) return;
    for (const e of fs.readdirSync(d, { withFileTypes: true })) {
      const r = path.join(rel, e.name);
      if (e.isDirectory()) prune(r);
      else if (!want.has(r)) fs.unlinkSync(path.join(out, r));
    }
  };
  prune();
  console.log(`  gespiegelt → ${out} (${want.size} Dateien)`);
}
