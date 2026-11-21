# Changelog – Kit-Designer (`klxm/studio-kit-designer`)

## 0.2.0 (2026-11-21)

Stufe 2: Figma-artige Arbeitsfläche, Komponenten-Galerie, Abschnitts-Stile.

- **Elemente in der Vorschau gestalten:** Umriss beim Zeigen, Auswahl mit Name, Größe und Innenabstand, Abstand zum gezeigten Nachbarn in Pixeln;
  Reiter **Element** mit Geltung (überall, Blocktyp, Variante), Zustand (Normal, hover, Fokus, gedrückt, deaktiviert, aktuelle Seite – in der
  Vorschau erzwingbar) und token-gebundenen Eigenschaften: Farben aus dem Design oder eigene mit Dunkel-Wert, Schrift, Schriftstufe, Stärke,
  Laufweite, Schreibweise, Rundung, Rahmen, Schatten, Innenabstände, Versatz, Deckkraft, Fokusrahmen; Maße des Elements. Elemente: Knöpfe,
  Links, Menüpunkte, Überschriften, Dachzeile, Einleitung, Fließtext, Karten, Etiketten, Kopf- und Fußbereich, Abschnitt.
- **Ebenen** links (Kopf, Abschnitte mit ihren Elementen, Fuß) als Baum mit Tastatur; **Alt + ↑/↓** wählt Eltern bzw. Kind, **Esc** hebt auf.
- **Komponenten-Galerie:** alle Blöcke von fluid in jeder Variante mit erfundenen Beispielen (Startinhalte von fluid und eigene), wahlweise mit
  Inhalten der Website; Klick stellt die Geltung auf die Variante – Stile je Block und Variante (im Kit als CSS je Block).
- **Abschnitts-Stile:** Fläche, Verlauf, Bild-Overlay mit Schrift- und Akzentfarbe, wiederverwendbar als Hintergrund für die Redaktion; zur Probe
  einem Abschnitt der Vorschau zuweisbar.
- **Kontrast** auch je Zustand, für Fokusrahmen, Rahmen und Abschnitts-Stile (Verlauf an beiden Enden, Overlay über hellem und dunklem Bild),
  mit „Beheben“; Mängel sperren den Export.
- **Rückgängig/Wiederholen** (Strg/⌘ + Z, Umschalt + Strg/⌘ + Z, Strg + Y), **Vergleich** zweier Versionen bzw. Version und aktueller Stand
  nebeneinander mit Liste der Unterschiede, **Vorschau-Links** (1, 7 oder 30 Tage, nur lesend, ohne Skripte, Token nur als SHA-256 gespeichert,
  widerrufbar, Aufrufe gezählt, Fehlversuche begrenzt).
- **Export** mit Stilen: KLXM Studio `'css' => ['css/kit.css']`, `conditional_css` je Block, `backgrounds`/`dark_backgrounds`, Zeilenhöhe als
  Token `line_height`; REDAXO fluid `'css'`, `'assets'` je Block, `backgrounds`; beide mit `kit-designer.json`. Rückfall für ältere Cores.
- **Weiterbearbeiten:** neuer Entwurf aus einem installierten Kind-Kit von fluid oder aus einem ZIP (Studio oder REDAXO) – ohne Code aus dem
  Archiv auszuführen.
- Nutzt `Theme::withKit()` des Cores, wenn vorhanden. Selbsttest 114 Prüfungen, Browser-Prüfung mit Playwright und axe-core (0 Befunde),
  Handbuch, Entwicklerhandbuch, englische Texte.

**English:** Stage 2 – Figma-like canvas (click to select, hover outlines, sizes and distances, layers tree), element styles per scope (everywhere,
block type, variant) and state with per-state contrast checks, component gallery of all fluid blocks and variants, reusable section styles
(fill, gradient, image overlay), undo/redo, side-by-side comparison, time-limited read-only preview links, export of styles for KLXM Studio and
REDAXO fluid, import of exported kits for further editing.

## 0.1.0 (2026-11-21)

Erste Fassung (Stufe 1: Design-Grundlagen, Eltern-Kit fluid).

- **Arbeitsfläche** unter Werkzeuge → Kit-Designer: Eigenschaften in Reitern (Farben, Schrift, Raum & Form, Kopf & Fuß, Tokens, Export,
  Versionen), Zeichenflächen mit echten Seiten der Website auf Telefon (390 px), Tablet (820 px) und Desktop (1280 px), hell und dunkel
  nebeneinander; Zoom, Ansichten ein-/ausblenden, Seitenwechsel per Auswahl oder Klick auf einen Link in der Vorschau.
  Änderungen tauschen nur Variablen, Klassen und Stylesheets aus (ohne Neuladen); Kopf-/Fußvarianten rendern neu. Die Website ändert sich nie.
- **Farben** hell und dunkel mit Live-Kontrastprüfung nach WCAG 2.2 AA (Schrift 4,5 : 1, Grafik/Bedienelemente 3 : 1, strengere Empfehlung
  des Kits als Hinweis), „Beheben“ je Paar (Helligkeit in OKLCH, Farbton bleibt), **Dunkel-Fassung vorschlagen** (nach Rollen, alle Paare AA)
  und „Auto“ je Farbe. Vorlagen (Paletten) von fluid.
- **Schriften** aus dem Kit und dem Schriften-Manager mit Probe, Grundschrift, Verhältnis mit Tabelle der Schriftstufen, **Zeilenhöhe**
  (eigene Angabe, im Export als CSS), Stärke und Laufweite; Abstände, Dichte, Radius, Schatten, Inhaltsbreite, Buttons, Karten, Kopf und Fuß.
- **Design-Tokens im W3C-Format (DTCG):** Export und Import; fremde Dateien aus Tokens Studio/Figma über sprechende Namen, Sätze light/dark,
  Verweise, das ältere Format (value/type), typography-Tokens. Beispiel: `examples/tokens-studio-beispiel.json`.
- **Entwürfe** je Website mit Versionen (Notiz, Laden älterer Stände), Recht **„Kits gestalten“** (`kit_designer.design`, Standard nur Administration).
- **Export** nur ohne Kontrastmängel (sonst Liste mit Sprung zur Farbe): Kind-Kit von fluid für **KLXM Studio** (ZIP oder direkt in
  `storage/kits` installieren über `Core\KitPackages`, danach `kit:check`) und für **REDAXO fluid** (ZIP im Format von `fluid:kit:create`).
- Selbsttest `kit_designer:selftest` (60 Prüfungen), Handbuch und Entwicklerhandbuch, englische Texte.
