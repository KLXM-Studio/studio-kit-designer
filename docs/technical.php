<?php /** Entwicklerhandbuch · Kit-Designer (Erweiterung „kit_designer“, Paket klxm/studio-kit-designer) – Extension::docs('technical'), nach „Kits & Design“ */ ?>
  <p class="lead">Der Kit-Designer ist ein eigenes Paket (<code>klxm/studio-kit-designer</code>, Composer-Typ <code>klxm-studio-extension</code>, Namensraum
    <code>Klxm\KitDesigner</code>, MIT). Ein Entwurf ist ein Satz Design-Werte des Eltern-Kits <code>fluid</code> plus wenige eigene Angaben; gerechnet,
    geprüft und gerendert wird mit dem Style-Editor des Cores (<code>Core\Design</code>). Ausgabe: Kind-Kit mit <code>'extends' =&gt; 'fluid'</code> und
    <code>design_defaults</code> für KLXM Studio bzw. ein Kit im Format von <code>bin/console fluid:kit:create</code> für REDAXO fluid.</p>

  <h3 id="kit-designer-aufbau">Aufbau und Andockstellen</h3>
  <ul>
    <li><b>Dateien:</b> <code>extension.php</code> (Manifest), <code>src/</code> – <code>KitDesigner</code> (Namen, Eltern-Kit, <code>withParent()</code>),
      <code>Palette</code> (OKLCH, Kontrast, Dunkel-Vorschlag, Beheben), <code>Tokens</code> (DTCG), <code>Drafts</code> (Tabellen, Versionen),
      <code>Exporter</code> (Studio, REDAXO, ZIP, Installation), <code>Controller</code>, <code>SelfTest</code>; <code>views/admin/</code>,
      <code>assets/</code> (Quellen) und <code>public/</code> (gebaut, <code>extensions:publish</code>), <code>examples/tokens-studio-beispiel.json</code>.</li>
    <li><b>Integrationspunkte:</b> <code>permissions()</code> (<code>kit_designer.design</code> „Kits gestalten“), <code>table()</code>
      (<code>kit_designer_drafts</code>, <code>kit_designer_versions</code>), <code>adminPage()</code> (Art <code>tool</code>), <code>adminAssets()</code>
      (CSS auf den Seiten des Pakets, Skript nur auf der Arbeitsfläche), <code>routes()</code> (alle unter <code>/admin/kit-designer</code> mit Recht,
      CSRF über den Router), <code>health()</code>, <code>docs()</code>, <code>command()</code>.</li>
    <li><b>Kern-APIs (nur lesend bzw. dokumentiert):</b> <code>Core\Design</code> (<code>normalize</code>, <code>defaults</code>, <code>fonts</code>,
      <code>live</code>, <code>override</code>), <code>SiteController::previewHtml()</code> und <code>DesignController::forceDark()</code> (wie die Vorschau
      unter Verwaltung → Design), <code>Core\Contrast</code>, <code>Core\Fonts::previewFace()</code>, <code>Theme::mergeDefinition()</code>,
      <code>Core\KitPackages::install()/check()/remove()</code>, <code>Core\KitCheck::run()</code>.</li>
  </ul>

  <h3 id="kit-designer-vorschau">Vorschau ohne Änderung der Website</h3>
  <p><code>KitDesigner::withParent($fn)</code> setzt für die Dauer eines Aufrufs das Eltern-Kit als <code>app()-&gt;theme</code> ein und stellt danach Kit,
    <code>Design::override()</code> und <code>currentPage</code> wieder her. Die Vorschau (<code>POST /admin/kit-designer/api/vorschau</code>) rendert damit eine
    Seite der aktuellen Website mit den ungespeicherten Werten:</p>
  <ul>
    <li><code>mode=full</code>: HTML hell und dunkel (<code>forceDark</code>, dazu am Client die Klasse <code>scheme-dark</code> wie beim Hell/Dunkel-Schalter
      von fluid) – die Arbeitsfläche setzt es als <code>srcdoc</code> in die Zeichenflächen. Nötig beim Seitenwechsel und bei Tokens mit <code>'markup' =&gt; true</code>.</li>
    <li><code>mode=live</code>: nur <code>Design::live()</code> (Variablen, Klassen, Varianten-Stylesheets, Schriften) plus das Dunkel-Stylesheet; die
      Arbeitsfläche tauscht sie in den Dokumenten der Zeichenflächen aus (ohne Neuladen).</li>
    <li>Eigenes CSS des Entwurfs (Zeilenhöhe) steht als <code>&lt;style data-kd-extra&gt;</code> direkt hinter <code>css/site.css</code> – an derselben Stelle wie
      im exportierten Kit.</li>
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
  theme.php             'extends' =&gt; 'fluid', 'design_defaults' =&gt; nur Abweichungen ([default, dark] bei Farben)
  composer.json         "type": "klxm-studio-kit", extra.klxm-studio.kit / public / requires-core
  assets/css/site.css   nur bei eigenem CSS: @import "../../../fluid/assets/css/site.css"; + Regeln (Build bündelt)
  public/css/site.css   dasselbe fertig: @import url("../../fluid/css/site.css"); + Regeln
  templates/ public/    Hinweisdateien (alles Übrige erbt das Kit von fluid)
  design-tokens.json  README.md  LICENSE

src/Kit/{Name}/                              (REDAXO fluid, wie fluid:kit:create)
  {Name}Kit.php         #[AsKit('name', 'Titel', parent: 'fluid')] final class {Name}Kit extends Kit {}
  kit.php               $defaults über $parent['design'] legen (nur vorhandene Tokens/Schriften), 'css' =&gt; ['css/kit.css']
  assets/css/kit.css    abweichende Regeln
  design-tokens.json  README.md  LICENSE</code></pre>
  <p>Unterschiede Studio → REDAXO: <code>Exporter::REDAXO_RENAME</code> (<code>transitions</code> → <code>transition</code>); installierte Schriften
    (<code>installed:{id}</code>) werden zur Fontsource-Kennung. „In dieser Installation installieren“ nutzt <code>KitPackages::install()</code>
    (Recht: <code>KitPackages::canManage()</code>) und ersetzt nur Kits, deren <code>theme.php</code> vom Kit-Designer stammt; danach läuft <code>KitCheck::run()</code>.</p>

  <h3 id="kit-designer-befehle">Befehle und Prüfung</h3>
  <ul>
    <li><code>php bin/console kit_designer:selftest [--site=…] [--no-install]</code> – Farbräume, Kontrast, Beheben, Dunkel-Vorschlag (zwei Paletten),
      Tokens (eigener Rundlauf, Tokens-Studio-Beispiel), Entwürfe und Versionen, Export-Sperre, Studio-Kit (Syntax, nur Abweichungen, Zusammenführen
      mit fluid = Entwurf, <code>KitPackages::check</code>), Installation in <code>storage/kits</code> mit <code>kit:check</code> und Entfernen, REDAXO-Kit
      (Aufbau, Klasse, <code>kit.php</code> über die Design-Definition gelegt).</li>
    <li><code>php bin/console i18n:missing en --extension=kit_designer</code> → 0.</li>
  </ul>

  <h3 id="kit-designer-stufen">Ausblick</h3>
  <p>Stufe 1 gestaltet die Design-Grundlagen eines Kind-Kits von fluid. Geplant: einzelne Blöcke gestalten (Varianten, Abstände, eigene CSS-Regeln
    je Block), Seiten aus Blöcken zusammenklicken, weitere Eltern-Kits (z. B. native).</p>
