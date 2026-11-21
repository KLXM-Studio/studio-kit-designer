# KLXM Studio – Kit-Designer (`kit_designer`)

Kits gestalten wie in einem Design-Werkzeug: **Farben** mit Live-Kontrastprüfung und automatisch vorgeschlagener Dunkel-Fassung,
**Schriften**, Schriftstufen und Zeilenhöhe, **Abstände, Rundungen, Schatten, Inhaltsbreite**, Kopf- und Fußvarianten – und das Ergebnis
sofort auf **echten Seiten** der Website sehen: Telefon, Tablet und Desktop, hell und dunkel nebeneinander. Fertige Entwürfe gehen als
**Kind-Kit von fluid** hinaus – für KLXM Studio und für REDAXO fluid. Design-Tokens kommen im **W3C-Format (DTCG)** herein und hinaus,
z. B. aus Figma mit Tokens Studio.

Die Website ändert sich dabei nicht: Vorschau und Rechnung laufen nur im Speicher; erst ein exportiertes und gewähltes Kit wirkt.

## Installation

```bash
composer require klxm/studio-kit-designer   # oder das Repository nach extensions/kit_designer klonen bzw. verknüpfen
php bin/console extensions:publish          # gebaute Dateien nach public/assets/ext/kit_designer
php bin/console migrate --all               # Tabellen kit_designer_drafts, kit_designer_versions
```

Einschalten je Website unter **Administration → Funktionen & Erweiterungen** oder in der Konfiguration: `'extensions' => ['kit_designer']`.
Das Kit **fluid** muss in der Installation vorhanden sein (Eltern-Kit). Recht: **„Kits gestalten“** (`kit_designer.design`, Standard nur
Administration). Direkt installieren dürfen nur Personen, die die Installation verwalten (`Core\KitPackages::canManage()`).

## Arbeitsweise

1. **Werkzeuge → Kit-Designer → Neuer Entwurf** – aus einer Vorlage von fluid, dem aktuellen Design der Website oder einer Token-Datei.
2. Gestalten: Reiter Farben, Schrift, Raum & Form, Kopf & Fuß. Die Kontrastprüfung (WCAG 2.2 AA: Schrift 4,5 : 1, Grafik und Bedienelemente
   3 : 1) läuft mit; „Beheben“ korrigiert ein Paar, „Dunkel-Fassung vorschlagen“ leitet alle dunklen Werte ab.
3. **Speichern** (Strg/⌘ + S) – jede Speicherung ist eine Version.
4. **Export** (nur ohne Kontrastmängel): ZIP für KLXM Studio, direkt installieren (`storage/kits/{name}`) oder ZIP für REDAXO fluid.

## Was der Export enthält

| KLXM Studio (`{name}/`) | REDAXO fluid (`src/Kit/{Name}/`) |
|---|---|
| `theme.php` mit `'extends' => 'fluid'` und `design_defaults` (nur Abweichungen) | `{Name}Kit.php` mit `#[AsKit('name', 'Titel', parent: 'fluid')]` |
| `assets/css/site.css` + `public/css/site.css` nur bei eigenem CSS (Zeilenhöhe) | `kit.php` legt die Standardwerte über `$parent['design']`; `assets/css/kit.css` |
| `composer.json` (Typ `klxm-studio-kit`), `design-tokens.json`, `README.md`, `LICENSE` | `design-tokens.json`, `README.md`, `LICENSE` |

**REDAXO fluid einspielen:** ZIP im Projekt entpacken (der Ordner landet unter `src/Kit/{Name}/`, wie bei `bin/console fluid:kit:create`),
den Namensraum an den PSR-4-Eintrag für `src/` anpassen (beim Export wählbar, Standard `Project`), `bin/console cache:clear`,
`bin/console migrate`, dann **Website-Einstellungen → Kit** wählen. Hatte die Website eigene Design-Werte im Kit fluid, unter Design auf
„Standard“ gehen, damit die Werte des neuen Kits gelten.

## Prüfen

```bash
php bin/console kit_designer:selftest --site=…      # Farben, Tokens, Entwürfe, Export Studio + REDAXO, Installation + kit:check
php bin/console i18n:missing en --extension=kit_designer
```

## Stufen

Stufe 1 (diese Fassung): Design-Grundlagen eines Kind-Kits von fluid. Später: einzelne Blöcke gestalten, Seiten zusammenklicken, weitere
Eltern-Kits (z. B. native).

## English

**Kit Designer** for KLXM Studio: design a kit visually – colours with live WCAG 2.2 AA contrast checks and an automatically suggested dark
version, fonts, type scale and line height, spacing, radius, shadows and content width – and see the result on real pages of the website
(phone, tablet and desktop, light and dark side by side) without changing the site. Import and export design tokens in the W3C Design Tokens
Community Group format (e.g. from Figma via Tokens Studio). Export the design as a child kit of fluid for KLXM Studio (ZIP or direct install)
and for REDAXO fluid (ZIP in the `fluid:kit:create` layout); export is blocked until all colour pairs pass AA.

Lizenz: MIT (siehe [LICENSE](LICENSE)).
