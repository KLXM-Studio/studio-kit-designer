<?php
/**
 * Arbeitsfläche eines Entwurfs: links Eigenschaften (Reiter), rechts Zeichenflächen (Telefon, Tablet, Desktop × hell, dunkel).
 * Aufbau und Verhalten: assets/js/kit-designer.js (liest #kd-boot). @var array $draft  @var array $boot  @var string $fontHead
 */
use Klxm\KitDesigner\KitDesigner as K;

$boot['texts'] = [
    'light' => __('hell'), 'dark' => __('dunkel'), 'lightRow' => __('Hell'), 'darkRow' => __('Dunkel'),
    'phone' => __('Telefon'), 'tablet' => __('Tablet'), 'desktop' => __('Desktop'),
    'frame' => __('Vorschau {device}, {mode}'), 'auto' => __('Auto'), 'autoTitle' => __('Dunkel-Wert folgt dem Vorschlag, wenn sich die helle Farbe ändert'),
    'hex' => __('Hex-Wert'), 'lightValue' => __('{label} – hell'), 'darkValue' => __('{label} – dunkel'),
    'suggestDark' => __('Dunkel-Fassung vorschlagen'), 'suggestDone' => __('Dunkel-Fassung vorgeschlagen – alle Paare nach AA geprüft.'),
    'contrast' => __('Kontrast (WCAG 2.2 AA)'), 'contrastOk' => __('Alle {n} Farbpaare bestehen AA.'),
    'contrastFail' => __('{fail} von {n} Farbpaaren erreichen den Mindestkontrast nicht.'),
    'pair' => __('„{fg}“ auf „{bg}“ ({mode})'), 'ratio' => __('{ratio} : 1, mindestens {min} : 1'),
    'recommend' => __('Kit empfiehlt {rec} : 1'), 'fix' => __('Beheben'), 'goto' => __('Zur Farbe'), 'allPairs' => __('Alle Paare anzeigen'),
    'fixed' => __('{label} angepasst: {value}.'), 'kindText' => __('Schrift'), 'kindUi' => __('Grafik, Bedienelemente'),
    'preset' => __('Vorlage (Palette) von fluid'), 'presetApply' => __('Anwenden'), 'presetApplied' => __('Vorlage „{label}“ übernommen.'),
    'moreColors' => __('Weitere Farben (farbenfroh)'), 'scale' => __('Schriftstufen'), 'step' => __('Stufe'), 'sample' => __('Franz jagt im komplett verwahrlosten Taxi quer durch Bayern'),
    'lineHeight' => __('Zeilenhöhe Fließtext'), 'lineHeightHelp' => __('Abstand der Zeilen im Fließtext (fluid: 1,62). Abweichende Werte schreibt der Export als eigenes CSS.'),
    'page' => __('Seite'), 'zoom' => __('Zoom'), 'zoomIn' => __('Vergrößern'), 'zoomOut' => __('Verkleinern'), 'show' => __('Zeichenflächen'),
    'save' => __('Speichern'), 'saving' => __('Wird gespeichert …'), 'saved' => __('Gespeichert als Version {v}.'), 'dirty' => __('Ungespeicherte Änderungen'),
    'loading' => __('Vorschau wird erstellt …'), 'error' => __('Fehler: {msg}'), 'leave' => __('Es gibt ungespeicherte Änderungen.'),
    'linkOut' => __('Dieser Link führt aus der Vorschau – in der Vorschau öffnen sich nur Seiten dieser Website.'),
    'darkOff' => __('Dunkelmodus ist aus – Besucher sehen die Website immer hell. Die Zeile zeigt die Dunkel-Fassung trotzdem.'),
    'note' => __('Notiz zur Version (optional)'), 'versions' => __('Gespeicherte Versionen'), 'restore' => __('Laden'),
    'restored' => __('Version {v} geladen – „Speichern“ legt sie als neue Version ab.'), 'current' => __('aktuell'),
    'tokensExport' => __('Design-Tokens herunterladen (JSON, W3C-Format)'), 'tokensExportHelp' => __('Gespeicherter Stand im Format der W3C Design Tokens Community Group – lesbar z. B. von Tokens Studio für Figma und Style Dictionary.'),
    'tokensImport' => __('Design-Tokens importieren'), 'tokensFile' => __('JSON-Datei wählen'), 'tokensPaste' => __('oder JSON einfügen'),
    'tokensCheck' => __('Prüfen'), 'tokensApply' => __('Übernehmen'), 'tokensMapped' => __('{n} Tokens zugeordnet'), 'tokensIgnored' => __('{n} nicht zugeordnet'),
    'tokensApplied' => __('Tokens übernommen – helle Farben ohne Dunkel-Wert haben einen Vorschlag bekommen.'), 'tokensNone' => __('Keine passenden Tokens gefunden.'),
    'exportGate' => __('Export gesperrt, bis alle Farbpaare AA bestehen:'), 'exportReady' => __('Alle Farbpaare bestehen AA – der Export ist frei.'),
    'studio' => __('KLXM Studio'), 'studioHelp' => __('Kind-Kit von fluid als ZIP: unter Verwaltung → Kits hochladen oder als kits/{name}/ ins Projekt legen.'),
    'zip' => __('ZIP herunterladen'), 'install' => __('In dieser Installation installieren'),
    'installHelp' => __('Legt das Kit unter storage/kits/{name} an (wie ein ZIP-Upload). Danach in den Grundeinstellungen als „Aktives Kit“ wählen. Gedacht vor allem für lokale Installationen und Testsysteme.'),
    'installNo' => __('Installieren dürfen nur Personen, die die Installation verwalten – laden Sie das ZIP herunter.'),
    'installed' => __('Installiert.'), 'kitCheck' => __('kit:check'), 'kitCheckOk' => __('kit:check: keine Warnungen.'),
    'redaxo' => __('REDAXO fluid (REDAXO 6)'), 'redaxoHelp' => __('Kind-Kit im Format von bin/console fluid:kit:create: ZIP im Projekt entpacken (landet unter src/Kit/…), cache:clear, migrate, dann unter Website → Kit wählen.'),
    'namespace' => __('Namensraum des Projekts (PSR-4 für src/)'),
    'name' => __('Technischer Name'), 'nameHelp' => __('Ordner des Kits: Kleinbuchstaben, Ziffern, Bindestrich, 2–30 Zeichen.'), 'label' => __('Titel'),
    'description' => __('Beschreibung'), 'author' => __('Urheber (für LICENSE)'), 'saveFirst' => __('Vor dem Export wird gespeichert.'),
    'exists' => __('In dieser Installation gibt es schon ein Kit „{name}“ ({source}).'), 'existsOwn' => __('Schon in dieser Installation installiert – ein erneuter Export ersetzt das Kit.'),
];
?>
<?= $fontHead ?>
<div class="kd" data-kd>
  <a class="kd-skip" href="#kd-canvas"><?= e(__('Zu den Zeichenflächen springen')) ?></a>
  <div class="kd-bar">
    <a class="adm-btn adm-btn--ghost adm-btn--small" href="<?= e(url(K::ADMIN)) ?>"><?= icon('arrow-left') ?> <?= e(__('Entwürfe')) ?></a>
    <h1 class="kd-bar__title"><span class="kd-sr"><?= e(__('Kit-Designer')) ?>: </span><span data-kd-title><?= e($draft['label']) ?></span> <code class="kd-bar__name" data-kd-name><?= e($draft['name']) ?></code></h1>
    <label class="kd-bar__field"><span><?= e(__('Seite')) ?></span><select data-kd-page></select></label>
    <div class="kd-bar__group" role="group" aria-label="<?= e(__('Zoom')) ?>">
      <button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-kd-zoom="-1" aria-label="<?= e(__('Verkleinern')) ?>">−</button>
      <output class="kd-bar__zoom" data-kd-zoomval aria-live="polite">35 %</output>
      <button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-kd-zoom="1" aria-label="<?= e(__('Vergrößern')) ?>">+</button>
    </div>
    <div class="kd-bar__group" role="group" aria-label="<?= e(__('Zeichenflächen')) ?>" data-kd-show></div>
    <span class="kd-bar__status" role="status" aria-live="polite" data-kd-status></span>
    <button type="button" class="adm-btn adm-btn--primary" data-kd-save><?= icon('floppy-disk') ?> <?= e(__('Speichern')) ?></button>
  </div>
  <div class="kd-main">
    <aside class="kd-side" aria-label="<?= e(__('Eigenschaften')) ?>" data-tabs>
      <div class="adm-tabs kd-tabs" role="tablist" aria-label="<?= e(__('Bereiche')) ?>">
        <?php $tabs = ['farben' => __('Farben'), 'schrift' => __('Schrift'), 'form' => __('Raum & Form'), 'kopf' => __('Kopf & Fuß'), 'tokens' => __('Tokens'), 'export' => __('Export'), 'versionen' => __('Versionen')];
        $first = true; foreach ($tabs as $k => $l): ?>
        <button type="button" role="tab" id="kd-tab-<?= e($k) ?>" data-tab="kd-<?= e($k) ?>" aria-controls="kd-<?= e($k) ?>" aria-selected="<?= $first ? 'true' : 'false' ?>" tabindex="<?= $first ? '0' : '-1' ?>"><?= e($l) ?></button>
        <?php $first = false; endforeach; ?>
      </div>
      <?php $first = true; foreach ($tabs as $k => $l): ?>
      <section class="kd-panel" role="tabpanel" id="kd-<?= e($k) ?>" aria-labelledby="kd-tab-<?= e($k) ?>"<?= $first ? '' : ' hidden' ?> data-kd-panel="<?= e($k) ?>"></section>
      <?php $first = false; endforeach; ?>
    </aside>
    <div class="kd-canvas" id="kd-canvas" tabindex="0" role="region" aria-label="<?= e(__('Zeichenflächen mit Vorschau')) ?>" data-kd-canvas></div>
  </div>
</div>
<script type="application/json" id="kd-boot"><?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
