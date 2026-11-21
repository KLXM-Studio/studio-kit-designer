<?php /** Entwicklerhandbuch · Kit-Designer (Erweiterung „kit_designer“, Paket klxm/studio-kit-designer) – Extension::docs('technical'), nach „Kits & Design“ */ ?>
  <p class="lead">Der Kit-Designer ist ein eigenes Paket (<code>klxm/studio-kit-designer</code>, Composer-Typ <code>klxm-studio-extension</code>, Namensraum
    <code>Klxm\KitDesigner</code>, MIT). Ein Entwurf ist ein Satz Design-Werte des Eltern-Kits <code>fluid</code> plus wenige eigene Angaben; gerechnet,
    geprüft und gerendert wird mit dem Style-Editor des Cores (<code>Core\Design</code>). Ausgabe: Kind-Kit mit <code>'extends' =&gt; 'fluid'</code> und
    <code>design_defaults</code> für KLXM Studio bzw. ein Kit im Format von <code>bin/console fluid:kit:create</code> für REDAXO fluid.</p>

  <h3 id="kit-designer-aufbau">Aufbau und Andockstellen</h3>
  <ul>
    <li><b>Dateien:</b> <code>extension.php</code> (Manifest), <code>src/</code> – <code>KitDesigner</code> (Namen, Eltern-Kit, <code>withParent()</code>),
      <code>Palette</code> (OKLCH, Kontrast, Dunkel-Vorschlag, Beheben), <code>Tokens</code> (DTCG), <code>Drafts</code> (Tabellen, Versionen),
      <code>Styles</code> (Elemente, Blöcke, Zustände, Abschnitts-Stile → CSS, Kontrast), <code>Gallery</code> (Komponenten-Galerie),
      <code>Shares</code> (Vorschau-Links), <code>Importer</code> (Kind-Kits und ZIPs zum Weiterbearbeiten),
      <code>Exporter</code> (Studio, REDAXO, ZIP, Installation), <code>Controller</code>, <code>SelfTest</code>; <code>views/admin/</code>, <code>views/share.php</code>,
      <code>assets/</code> (Quellen) und <code>public/</code> (gebaut, <code>extensions:publish</code>), <code>examples/tokens-studio-beispiel.json</code>.</li>
    <li><b>Integrationspunkte:</b> <code>permissions()</code> (<code>kit_designer.design</code> „Kits gestalten“), <code>table()</code>
      (<code>kit_designer_drafts</code>, <code>kit_designer_versions</code>, <code>kit_designer_shares</code>), <code>adminPage()</code> (Art <code>tool</code>), <code>adminAssets()</code>
      (CSS auf den Seiten des Pakets, Skript nur auf der Arbeitsfläche), <code>routes()</code> (alle unter <code>/admin/kit-designer</code> mit Recht,
      CSRF über den Router; einzige Ausnahme ohne Anmeldung: <code>GET /kit-designer/vorschau/{token}</code>), <code>health()</code>, <code>docs()</code>, <code>command()</code>.</li>
    <li><b>Kern-APIs (nur lesend bzw. dokumentiert):</b> <code>Core\Design</code> (<code>normalize</code>, <code>defaults</code>, <code>fonts</code>,
      <code>live</code>, <code>override</code>), <code>SiteController::previewHtml()</code> und <code>DesignController::forceDark()</code> (wie die Vorschau
      unter Verwaltung → Design), <code>Core\Contrast</code>, <code>Core\Fonts::previewFace()</code>, <code>Theme::mergeDefinition()</code>,
      <code>Theme::withKit()</code> (falls vorhanden), <code>Theme::kitStylesheets()</code>, <code>Core\KitTest</code>-Muster, <code>Core\RateLimiter</code>,
      <code>Core\KitPackages::install()/check()/remove()</code>, <code>Core\KitCheck::run()</code>.</li>
    <li><b>Core-Fassungen:</b> Mit Core ab <code>Theme::withKit()</code>, <code>'css' =&gt; [...]</code> für Kind-Kits, fluid-Token <code>line_height</code>
      (<code>--f-lh</code>) und <code>KitPackages::check</code> ohne <code>templates/</code>-Pflicht nutzt das Paket diese Stellen; auf älteren Cores (1.0.0 vor
      diesen Ergänzungen) fällt es zurück: eigenes Umschalten des Kits, CSS über <code>css/site.css</code> mit <code>@import</code> von fluid, Zeilenhöhe als
      CSS, Hinweisdatei <code>templates/LIESMICH.txt</code>. Erkennung: <code>KitDesigner::coreKitCss()</code>, <code>KitDesigner::lineHeightToken()</code>.</li>
  </ul>

  <h3 id="kit-designer-vorschau">Vorschau ohne Änderung der Website</h3>
  <p><code>KitDesigner::withParent($fn)</code> (bzw. <code>withKit($kit, $fn)</code>) setzt für die Dauer eines Aufrufs das Eltern-Kit als <code>app()-&gt;theme</code>
    ein – über <code>Theme::withKit()</code> des Cores, sonst selbst – und stellt danach Kit, Design-Zustand und <code>currentPage</code> wieder her. Die Vorschau (<code>POST /admin/kit-designer/api/vorschau</code>) rendert damit eine
    Seite der aktuellen Website mit den ungespeicherten Werten:</p>
  <ul>
    <li><code>mode=full</code>: HTML hell und dunkel (<code>forceDark</code>, dazu am Client die Klasse <code>scheme-dark</code> wie beim Hell/Dunkel-Schalter
      von fluid) – die Arbeitsfläche setzt es als <code>srcdoc</code> in die Zeichenflächen. Nötig beim Seitenwechsel und bei Tokens mit <code>'markup' =&gt; true</code>.</li>
    <li><code>mode=live</code>: nur <code>Design::live()</code> (Variablen, Klassen, Varianten-Stylesheets, Schriften) plus das Dunkel-Stylesheet; die
      Arbeitsfläche tauscht sie in den Dokumenten der Zeichenflächen aus (ohne Neuladen).</li>
    <li><code>mode=gallery</code>: wie <code>full</code>, aber die Komponenten-Galerie (<code>Gallery::build()</code>/<code>page()</code>); Antwort mit <code>items</code>
      (id, Typ, Variante, Titel, Herkunft) und <code>missing</code> (Typen ohne Beispiel).</li>
    <li>Eigenes CSS des Entwurfs (Element-, Block- und Abschnitts-Stile, bei älterem Core die Zeilenhöhe) steht als <code>&lt;style data-kd-extra&gt;</code> nach dem
      Block-CSS und vor der Design-Datei bzw. <code>&lt;style data-design-preview&gt;</code> – an derselben Stelle wie <code>'css' =&gt; ['css/kit.css']</code> im
      exportierten Kit (<code>Controller::inject()</code>). In der Vorschau tragen Zustands-Regeln zusätzlich <code>[data-kd-state="hover"]</code> usw., damit die
      Arbeitsfläche Zustände erzwingen kann (<code>Styles::css($styles, null, true)</code>). Antworten von <code>live</code>/<code>full</code> enthalten
      <code>checks</code> (Kontrast der Stile).</li>
  </ul>
  <p>Nutzt die Website ein anderes Kit als fluid, zeigt die Vorschau ihre Inhalte trotzdem in fluid; Blöcke, die es nur im anderen Kit gibt, fehlen dann.</p>

  <h3 id="kit-designer-farben">Farben: Prüfung, Dunkel-Vorschlag, Beheben</h3>
  <ul>
    <li><b>Prüfpaare</b> kommen aus dem Eltern-Kit (<code>'contrast' =&gt; ['with' =&gt; …, 'min' =&gt; …, 'dark_with' =&gt; …]</code>). Verlangt wird AA: 4,5 : 1, wenn
      das Kit mindestens 4,5 nennt (Schrift), sonst 3 : 1 (Grafik, Bedienelemente). Strengere Werte des Kits bleiben Empfehlung
      (<code>Palette::checks()</code> → <code>recommended</code>, <code>strict</code>). <code>Palette::failures()</code> sperrt den Export.</li>
    <li><b>Dunkel-Vorschlag</b> (<code>Palette::suggestDark()</code>) in OKLCH nach Rollen (<code>Palette::ROLES</code>): Flächen L 0,17–0,31 im Farbton
      des Hintergrunds, Schrift L 0,74–0,97, Markenfarben heller und etwas weniger bunt, Schrift auf Marke dunkel; danach bis zu drei Runden
      <code>fix()</code> für jedes nicht bestandene Paar. Farben ohne Rolle (z. B. Diagramm-Reihen) bleiben unberührt.</li>
    <li><b>Beheben</b> (<code>Palette::fix($fg, $bg, $min)</code>): Helligkeit in 0,005-Schritten von der Fläche weg, Farbton und Buntheit bleiben
      (außerhalb von sRGB wird die Buntheit verringert).</li>
  </ul>

  <h3 id="kit-designer-stile">Stile für Elemente, Blöcke und Abschnitte (<code>Styles</code>)</h3>
  <p>Daten im Entwurf unter <code>data.styles</code>:</p>
  <pre><code>'scopes' =&gt; [
    '*'             =&gt; ['button' =&gt; ['base' =&gt; ['background' =&gt; 'token:accent', 'radius' =&gt; 4], 'hover' =&gt; ['lift' =&gt; 2]]],
    'cards'         =&gt; ['card' =&gt; ['base' =&gt; ['shadow' =&gt; 'layered']]],            // alle Blöcke „Karten“
    'cards.overlay' =&gt; ['section' =&gt; ['base' =&gt; ['padding_y' =&gt; 6]]],             // nur Variante „overlay“, der Abschnitt selbst
],
'sections' =&gt; [['key' =&gt; 'ozean', 'label' =&gt; 'Ozean', 'kind' =&gt; 'gradient', 'bg' =&gt; '#0B3B5A', 'bg2' =&gt; '#145C7A', 'text' =&gt; '#FFFFFF',
                'angle' =&gt; 160, 'accent' =&gt; 'token:highlight', 'bg@dark' =&gt; '…']]</code></pre>
  <ul>
    <li><b>Elemente</b> (<code>Styles::ELEMENTS</code>, Markup von fluid – gleich in KLXM Studio und REDAXO fluid): <code>button</code> (<code>.btn--primary</code>),
      <code>button2</code>, <code>link</code> (<code>.prose a</code>), <code>nav</code> (<code>.hnav__link</code> …), <code>h1</code>–<code>h3</code>, <code>eyebrow</code>,
      <code>lead</code>, <code>text</code>, <code>card</code> (<code>.card</code>, <code>.tcard</code>), <code>badge</code>, <code>header</code> (<code>.hdr</code>, nur überall),
      <code>footer</code> (<code>.ftr</code>), <code>section</code> (<code>.sec</code> bzw. der Block selbst). Je Element: erlaubte Eigenschaften, Zustände,
      Bezugsfarben und Mindestkontrast; <code>map</code> setzt Variablen von fluid mit (z. B. Karte → <code>--f-card</code>, Abschnitt → <code>--f-sec-bg</code>).</li>
    <li><b>Eigenschaften</b> (<code>Styles::PROPS</code>): Farben als <code>token:{name}</code> (→ <code>var(--f-…)</code>, folgt hell/dunkel und dem Style-Editor) oder
      <code>#RRGGBB</code> mit optionalem <code>{prop}@dark</code>; Stufen für Rahmen, Rundung, Schatten, Schrift (<code>var(--f-font…)</code>), Schriftstufe
      (<code>var(--f-t0…t6)</code>), Stärke, Laufweite, Zeilenhöhe, Schreibweise, Unterstreichung, Innenabstände, Versatz, Deckkraft, Fokusrahmen.</li>
    <li><b>CSS</b> (<code>Styles::cssParts()</code>): Selektoren mit <code>:root</code> davor; Geltung <code>typ</code> → <code>.sec--{typ}</code>, <code>typ.variante</code> →
      <code>.sec--{typ}.v-{variante}</code>; Zustände <code>:hover</code>, <code>:focus-visible</code> (Karte: <code>:focus-within</code>), <code>:active</code>,
      <code>:is(:disabled,[aria-disabled="true"])</code>, <code>[aria-current="page"]</code>; Dunkel-Werte wie die Design-Datei
      (<code>prefers-color-scheme</code> mit <code>html.has-dark:not(.scheme-light)</code>, <code>html.has-dark.scheme-dark</code>, <code>html.is-dark</code>).
      Teil <code>''</code> = global (<code>css/kit.css</code>), Teil <code>{typ}</code> = <code>css/kd-{typ}.css</code>.</li>
    <li><b>Abschnitts-Stile</b> (<code>Styles::sectionCss()</code>): Klasse <code>.bg-{schlüssel}</code> setzt <code>--f-sec-bg</code>, <code>--f-fg</code>,
      <code>--f-text</code>, <code>--f-ink</code>, <code>--f-muted</code>, <code>--f-line</code>, <code>--f-card</code>, <code>--f-btn-ink</code> (optional <code>--f-a</code>,
      <code>--f-link</code>); Verlauf als <code>background</code> auf <code>.sec.bg-…</code>, Bild-Overlay auf <code>.bg-… .sec__bg::after</code>. Schlüssel kollidieren
      nie mit Hintergründen von fluid (sonst <code>stil-…</code>); dunkle Flächen (Leuchtdichte &lt; 0,18) kommen zu <code>dark_backgrounds</code>.</li>
    <li><b>Kontrast</b> (<code>Styles::checks()</code>/<code>failures()</code>): je Geltung, Element und Zustand mit eigener Schrift- oder Flächenfarbe (fehlende
      Farbe aus den Bezugsfarben des Elements), hell und dunkel; „deaktiviert“ ausgenommen; Fokusrahmen 3 : 1 zum Seitenhintergrund; Rahmen des sekundären
      Knopfs ohne Fläche 3 : 1; Abschnitts-Stile: Schrift und Akzent 4,5 : 1 gegen das schlechteste Ende des Verlaufs bzw. die Overlay-Farbe über weißem und
      schwarzem Bild. <code>Exporter::blockers()</code> sperrt mit Farbpaaren und Stilen.</li>
  </ul>

  <h3 id="kit-designer-arbeitsflaeche">Arbeitsfläche (assets/js/kit-designer.js)</h3>
  <ul>
    <li>Zeichenflächen sind <code>iframe srcdoc</code> (gleiche Herkunft): Das Skript markiert Kandidaten mit <code>data-kd-id</code> (Auswahl aus
      <code>Styles::catalog()</code>, dieselbe Nummer in allen Zeichenflächen), zeichnet Umriss, Maß, Innenabstand und Abstand als
      <code>.kd-ui</code>-Ebenen in das Dokument und liest Block und Variante aus <code>.sec--{typ}</code>/<code>.v-{variante}</code>.</li>
    <li>Ebenen: <code>role="tree"</code> mit Pfeiltasten, Pos1/Ende, Eingabe; Kürzel auch bei Fokus in einer Zeichenfläche (<code>Strg/⌘+Z</code>,
      <code>Umschalt+Strg/⌘+Z</code>, <code>Strg+Y</code>, <code>Alt+↑/↓</code>, <code>Esc</code>, <code>Strg/⌘+S</code>).</li>
    <li>Verlauf: Schnappschüsse von <code>values</code>, <code>extra</code>, <code>auto</code>, <code>styles</code> (400 ms gebündelt, höchstens 120).</li>
    <li>Vergleich: <code>GET …/version/{v}</code> (zwischengespeichert) und zwei Vorschauen mit <code>mode=full</code>, gekoppelter Bildlauf.</li>
  </ul>

  <h3 id="kit-designer-teilen">Vorschau-Links (<code>Shares</code>)</h3>
  <p><code>POST …/{id}/teilen</code> (<code>days</code> 1, 7 oder 30, <code>note</code>) legt für die gespeicherte Version einen Link an: Token 24 Byte zufällig (hex),
    gespeichert nur als SHA-256, Klartext einmal in der Antwort. <code>POST …/{id}/teilen/{sid}/widerrufen</code>. Die öffentliche Seite
    <code>/kit-designer/vorschau/{token}</code> (<code>Controller::shareView()</code>) rendert eine veröffentlichte Seite mit den Werten und Stilen der Version im
    Eltern-Kit, entfernt Skripte, zeigt sie in <code>iframe sandbox="allow-same-origin"</code> und antwortet mit der CSP der Verwaltung, <code>noindex</code>,
    <code>no-store</code> und <code>Referrer-Policy: no-referrer</code>. Ungültige Tokens: 404, höchstens 30 Fehlversuche je IP in 15 Minuten
    (<code>Core\RateLimiter</code>). Löschen eines Entwurfs löscht seine Links.</p>

  <h3 id="kit-designer-import">Weiterbearbeiten (<code>Importer</code>)</h3>
  <p><code>Importer::fromKit($name)</code> liest Kits der Installation, deren Kette fluid enthält: mit <code>kit-designer.json</code> (Export ab 0.2.0:
    <code>{"format": "klxm-kit-designer", "version": 2, "data": {...}}</code>) den ganzen Entwurf, sonst die Standardwerte des Designs über
    <code>withKit()</code>. <code>Importer::fromZip($bytes)</code> liest aus einem ZIP nur <code>kit-designer.json</code> bzw. <code>design-tokens.json</code>
    (je höchstens 2 MB, nie PHP).</p>

  <h3 id="kit-designer-tokens">Design-Tokens (W3C DTCG)</h3>
  <p><code>Tokens::export($entwurf)</code> schreibt Gruppen <code>color</code>, <code>font</code> (<code>family</code>, <code>weight</code>, <code>size</code>,
    <code>scale</code>, <code>line-height</code>), <code>radius</code>, <code>size</code> mit <code>$type</code>/<code>$value</code> (Maße als
    <code>{"value": 6, "unit": "px"}</code>); Dunkel-Werte, Zuordnung zum Kit und die übrigen Auswahl-Werte stehen unter
    <code>$extensions["de.klxm.studio"]</code>. <code>Tokens::import($json)</code> liest den eigenen Export verlustfrei und fremde Dateien über Namen
    (<code>Tokens::COLOR_ALIASES</code>), Sätze (<code>light</code>/<code>dark</code>, <code>global</code> …), Verweise <code>{a.b}</code>, das ältere
    Tokens-Studio-Format (<code>value</code>/<code>type</code>), <code>typography</code>-Tokens, Schriftnamen (Abgleich mit <code>Design::fonts()</code>),
    Radius, Grundschrift, Zeilenhöhe, Verhältnis und Inhaltsbreite. Rückgabe: <code>values</code>, <code>extra</code>, <code>mapped</code>,
    <code>ignored</code>, <code>warnings</code>.</p>

  <h3 id="kit-designer-export">Export</h3>
  <pre><code>kits/{name}/ bzw. storage/kits/{name}/      (Studio)
  theme.php             'extends' =&gt; 'fluid', 'design_defaults' =&gt; nur Abweichungen ([default, dark] bei Farben, auch line_height),
                        'css' =&gt; ['css/kit.css'], 'conditional_css' =&gt; ['css/kd-{typ}.css' =&gt; ['{typ}']],
                        'backgrounds' =&gt; [schlüssel =&gt; titel], 'dark_backgrounds' =&gt; [… von fluid, … eigene dunkle]
  composer.json         "type": "klxm-studio-kit", extra.klxm-studio.kit / public / requires-core
  assets/css/kit.css    Element- und Abschnitts-Stile (Quelle) · public/css/kit.css dasselbe fertig
  assets/css/kd-{typ}.css, public/css/kd-{typ}.css   Stile je Blocktyp (lädt nur auf Seiten mit dem Block)
  public/               Hinweisdatei (alles Übrige erbt das Kit von fluid)
  design-tokens.json  kit-designer.json (Quelle zum Weiterbearbeiten)  README.md  LICENSE
  älterer Core ohne 'css': assets/css/site.css + public/css/site.css mit @import von fluid, templates/LIESMICH.txt

src/Kit/{Name}/                              (REDAXO fluid, wie fluid:kit:create)
  {Name}Kit.php         #[AsKit('name', 'Titel', parent: 'fluid')] final class {Name}Kit extends Kit {}
  kit.php               $defaults über $parent['design'] legen (nur vorhandene Tokens/Schriften), 'css' =&gt; ['css/kit.css'],
                        'assets' =&gt; ['css/kd-{typ}.css' =&gt; ['{typ}']], 'backgrounds', 'dark_backgrounds' (an die von $parent angehängt)
  assets/css/kit.css    Element- und Abschnitts-Stile, abweichende Zeilenhöhe als CSS · assets/css/kd-{typ}.css je Blocktyp
  design-tokens.json  kit-designer.json  README.md  LICENSE</code></pre>
  <p>Unterschiede Studio → REDAXO: <code>Exporter::REDAXO_RENAME</code> (<code>transitions</code> → <code>transition</code>); installierte Schriften
    (<code>installed:{id}</code>) werden zur Fontsource-Kennung. „In dieser Installation installieren“ nutzt <code>KitPackages::install()</code>
    (Recht: <code>KitPackages::canManage()</code>) und ersetzt nur Kits, deren <code>theme.php</code> vom Kit-Designer stammt; danach läuft <code>KitCheck::run()</code>.</p>

  <h3 id="kit-designer-befehle">Befehle und Prüfung</h3>
  <ul>
    <li><code>php bin/console kit_designer:selftest [--site=…] [--no-install]</code> – Farbräume, Kontrast, Beheben, Dunkel-Vorschlag (zwei Paletten),
      Tokens (eigener Rundlauf, Tokens-Studio-Beispiel), Entwürfe und Versionen, Export-Sperre, Studio-Kit (Syntax, nur Abweichungen, Zusammenführen
      mit fluid = Entwurf, <code>KitPackages::check</code>), Installation in <code>storage/kits</code> mit <code>kit:check</code> und Entfernen, REDAXO-Kit
      (Aufbau, Klasse, <code>kit.php</code> über die Design-Definition gelegt); Stufe 2: Stile bereinigen, CSS je Geltung/Zustand/Dunkel, erzwungene Zustände,
      Kontrast je Zustand und Abschnitts-Stil, Export mit <code>'css'</code>, CSS je Block und Hintergründen (Studio + REDAXO, installiert geladen),
      Import aus <code>kit-designer.json</code> und ZIP, Vorschau-Links (Hash, Widerruf, Ablauf), Galerie (114 Prüfungen).</li>
    <li>Browser: Playwright gegen eine Test-Website mit Kit fluid (Elemente wählen, Eigenschaften live, Zustände, Beheben, Rückgängig/Wiederholen, Ebenen per
      Tastatur, Abschnitts-Stile, Galerie mit Geltung je Variante, Vergleich, Vorschau-Link, Export und Import), axe-core ohne Befund.</li>
    <li><code>php bin/console i18n:missing en --extension=kit_designer</code> → 0.</li>
  </ul>

  <h3 id="kit-designer-stufen">Ausblick</h3>
  <p>Stufe 1 gestaltet die Design-Grundlagen, Stufe 2 Elemente, Blöcke, Zustände und Abschnitts-Stile eines Kind-Kits von fluid. Geplant: Seiten aus
    Blöcken zusammenklicken, eigene Block-Varianten, weitere Eltern-Kits (z. B. native).</p>
