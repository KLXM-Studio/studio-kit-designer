<?php
/**
 * Arbeitsfläche eines Entwurfs: links Ebenen, Mitte Zeichenflächen (Seite: Telefon, Tablet, Desktop × hell, dunkel; Galerie aller
 * Blöcke; Vergleich zweier Stände), rechts Eigenschaften in Reitern (Element, Farben, Schrift, Raum & Form, Kopf & Fuß, Abschnitte,
 * Tokens, Export, Versionen). Aufbau und Verhalten: assets/js/kit-designer.js (liest #kd-boot).
 * @var array $draft  @var array $boot  @var string $fontHead
 */
use Klxm\KitDesigner\KitDesigner as K;

$boot['icons'] = ['reset' => icon('x'), 'cursor' => icon('cursor'), 'stack' => icon('stack'), 'copy' => icon('copy'), 'trash' => icon('trash'),
    'plus' => icon('plus'), 'eye' => icon('eye'), 'link' => icon('link'), 'caret' => icon('caret-right'), 'warning' => icon('warning'), 'check' => icon('check-circle')];
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
    // Stufe 2: Arbeitsfläche, Element, Galerie, Abschnitte, Vergleich, Teilen
    'layers' => __('Ebenen'), 'header' => __('Kopfbereich'), 'footer' => __('Fußbereich'), 'section' => __('Abschnitt'), 'noLayers' => __('Die Vorschau lädt noch …'), 'layersCompare' => __('Im Vergleich ohne Ebenen – die Unterschiede stehen unter den Zeichenflächen.'),
    'select' => __('Element wählen'), 'selectHelp' => __('Ein Element in der Vorschau anklicken oder unter „Ebenen“ wählen – oder hier einen Elementtyp:'),
    'scope' => __('Gilt für'), 'scopeAll' => __('Überall'), 'scopeBlock' => __('Alle Blöcke „{block}“'), 'scopeVariant' => __('Nur „{block}“ · {variant}'),
    'state' => __('Zustand'), 'stateShow' => __('Zustand in der Vorschau zeigen'), 'inBlock' => __('in „{block}“'),
    'reset' => __('Zurücksetzen'), 'resetAll' => __('Alle Angaben dieses Zustands zurücksetzen'), 'inherit' => __('wie im Kit'),
    'ownColor' => __('Eigene Farbe …'), 'darkOwn' => __('Dunkel'), 'tokenColor' => __('Farbe aus dem Design'),
    'groupPaint' => __('Farbe & Fläche'), 'groupType' => __('Schrift'), 'groupShape' => __('Form'), 'groupSpace' => __('Abstand & Bewegung'), 'groupFocus' => __('Fokus'),
    'measure' => __('Maße (Desktop, hell)'), 'mSize' => __('Größe'), 'mPadding' => __('Innenabstand'), 'mFont' => __('Schrift'), 'mRadius' => __('Rundung'),
    'mColor' => __('Farbe / Fläche'), 'mGap' => __('Abstand zu „{label}“: {px} px'),
    'contrastEl' => __('Kontrast dieses Elements'), 'contrastNone' => __('Keine eigenen Farben – es gelten die geprüften Farbpaare des Designs.'),
    'exempt' => __('ausgenommen (deaktiviert)'), 'what_text' => __('Schrift'), 'what_outline' => __('Fokusrahmen'), 'what_border' => __('Rahmen'), 'what_accent' => __('Akzent'),
    'stylesFail' => __('{n} Prüfungen der Stile (Zustände, Abschnitts-Stile) erreichen den Mindestkontrast nicht.'), 'stylesOk' => __('Alle {n} Prüfungen der Stile bestehen AA.'),
    'stylesHead' => __('Stile: Zustände und Abschnitte'), 'assignStyle' => __('Abschnitts-Stil in der Vorschau zeigen'), 'assignNone' => __('wie auf der Seite'),
    'source' => __('Inhalte'), 'galleryMissing' => __('Ohne Beispiel: {list}'), 'fromSite' => __('Inhalt dieser Website'), 'sectionStyle' => __('Abschnitts-Stil'),
    'sections' => __('Abschnitts-Stile'), 'sectionsHelp' => __('Wiederverwendbare Flächen, Verläufe und Bild-Overlays. Im exportierten Kit wählt die Redaktion sie unter Abschnitt → Hintergrund.'),
    'sectionAdd' => __('Abschnitts-Stil hinzufügen'), 'sectionRemove' => __('Entfernen'), 'sectionNew' => __('Neuer Stil'), 'kind' => __('Art'),
    'sBg' => __('Fläche'), 'sBg2' => __('Fläche 2 (Verlaufsende)'), 'sText' => __('Schrift'), 'sAccent' => __('Akzent (Links, Knöpfe) – optional'),
    'sAngle' => __('Winkel'), 'sOpacity' => __('Deckkraft über dem Bild (%)'), 'sNone' => __('Noch keine Abschnitts-Stile.'), 'sShow' => __('In der Galerie zeigen'),
    'undo' => __('Rückgängig'), 'redo' => __('Wiederholen'), 'undone' => __('Rückgängig gemacht.'), 'redone' => __('Wiederholt.'),
    'compareA' => __('Links'), 'compareB' => __('Rechts'), 'compareCurrent' => __('Aktueller Stand'), 'compareVersion' => __('Version {v}'),
    'compareDiff' => __('Unterschiede'), 'compareSame' => __('Beide Stände sind gleich.'), 'compareStyles' => __('Stile: {a} → {b} Angaben'), 'compareMode' => __('Farbschema'),
    'share' => __('Vorschau-Link teilen'), 'shareHelp' => __('Zeigt die zuletzt gespeicherte Version auf Seiten dieser Website – ohne Anmeldung, nur lesend, bis zum Ablauf. Der Link wird nur einmal angezeigt.'),
    'shareDays' => __('Gültig für'), 'shareDay' => __('{n} Tag'), 'shareDaysN' => __('{n} Tage'), 'shareNote' => __('Notiz (für Sie)'), 'shareCreate' => __('Link erstellen'),
    'shareCopy' => __('Kopieren'), 'shareCopied' => __('Link kopiert.'), 'shareList' => __('Vorhandene Links'), 'shareRevoke' => __('Widerrufen'),
    'shareActive' => __('gültig bis {date}'), 'shareInactive' => __('abgelaufen oder widerrufen'), 'shareHits' => __('{n} Aufrufe'), 'shareLink' => __('Vorschau-Link'),
];
?>
<?= $fontHead ?>
<div class="kd" data-kd>
  <a class="kd-skip" href="#kd-canvas"><?= e(__('Zu den Zeichenflächen springen')) ?></a>
  <div class="kd-bar">
    <a class="adm-btn adm-btn--ghost adm-btn--small" href="<?= e(url(K::ADMIN)) ?>"><?= icon('arrow-left') ?> <?= e(__('Entwürfe')) ?></a>
    <h1 class="kd-bar__title"><span class="kd-sr"><?= e(__('Kit-Designer')) ?>: </span><span data-kd-title><?= e($draft['label']) ?></span> <code class="kd-bar__name" data-kd-name><?= e($draft['name']) ?></code></h1>
    <div class="kd-seg" role="group" aria-label="<?= e(__('Ansicht')) ?>">
      <button type="button" class="kd-seg__b" data-kd-mode="page" aria-pressed="true"><?= e(__('Seite')) ?></button>
      <button type="button" class="kd-seg__b" data-kd-mode="gallery" aria-pressed="false"><?= e(__('Galerie')) ?></button>
      <button type="button" class="kd-seg__b" data-kd-mode="compare" aria-pressed="false"><?= e(__('Vergleich')) ?></button>
    </div>
    <label class="kd-bar__field" data-kd-for="page"><span><?= e(__('Seite')) ?></span><select data-kd-page></select></label>
    <label class="kd-bar__field" data-kd-for="gallery" hidden><span><?= e(__('Inhalte')) ?></span><select data-kd-source><option value="examples"><?= e(__('Beispiele')) ?></option><option value="site"><?= e(__('dieser Website')) ?></option></select></label>
    <div class="kd-bar__group" role="group" aria-label="<?= e(__('Zoom')) ?>">
      <button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-kd-zoom="-1" aria-label="<?= e(__('Verkleinern')) ?>">−</button>
      <output class="kd-bar__zoom" data-kd-zoomval aria-live="polite">35 %</output>
      <button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-kd-zoom="1" aria-label="<?= e(__('Vergrößern')) ?>">+</button>
    </div>
    <div class="kd-bar__group" role="group" aria-label="<?= e(__('Zeichenflächen')) ?>" data-kd-show></div>
    <div class="kd-bar__group" role="group" aria-label="<?= e(__('Rückgängig und Wiederholen')) ?>">
      <button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-kd-undo disabled aria-keyshortcuts="Control+Z Meta+Z" title="<?= e(__('Rückgängig')) ?>"><?= icon('arrow-u-up-left') ?><span class="kd-sr"><?= e(__('Rückgängig')) ?></span></button>
      <button type="button" class="adm-btn adm-btn--ghost adm-btn--small" data-kd-redo disabled aria-keyshortcuts="Control+Shift+Z Meta+Shift+Z Control+Y" title="<?= e(__('Wiederholen')) ?>"><?= icon('arrow-u-up-right') ?><span class="kd-sr"><?= e(__('Wiederholen')) ?></span></button>
    </div>
    <span class="kd-bar__status" role="status" aria-live="polite" data-kd-status></span>
    <button type="button" class="adm-btn adm-btn--primary" data-kd-save aria-keyshortcuts="Control+S Meta+S"><?= icon('floppy-disk') ?> <?= e(__('Speichern')) ?></button>
  </div>
  <div class="kd-main">
    <nav class="kd-layers" aria-labelledby="kd-layers-h">
      <h2 class="kd-group__h" id="kd-layers-h"><?= icon('stack') ?> <?= e(__('Ebenen')) ?></h2>
      <p class="f-help kd-layers__help" id="kd-layers-help"><?= e(__('Pfeiltasten wählen, Eingabe übernimmt.')) ?></p>
      <ul class="kd-tree" role="tree" aria-labelledby="kd-layers-h" aria-describedby="kd-layers-help" data-kd-tree></ul>
    </nav>
    <div class="kd-canvas" id="kd-canvas" tabindex="0" role="region" aria-label="<?= e(__('Zeichenflächen mit Vorschau')) ?>" data-kd-canvas></div>
    <aside class="kd-side" aria-label="<?= e(__('Eigenschaften')) ?>" data-tabs>
      <div class="adm-tabs kd-tabs" role="tablist" aria-label="<?= e(__('Bereiche')) ?>">
        <?php $tabs = ['element' => __('Element'), 'farben' => __('Farben'), 'schrift' => __('Schrift'), 'form' => __('Raum & Form'), 'kopf' => __('Kopf & Fuß'),
            'abschnitte' => __('Abschnitte'), 'tokens' => __('Tokens'), 'export' => __('Export'), 'versionen' => __('Versionen')];
        $first = true; foreach ($tabs as $k => $l): ?>
        <button type="button" role="tab" id="kd-tab-<?= e($k) ?>" data-tab="kd-<?= e($k) ?>" aria-controls="kd-<?= e($k) ?>" aria-selected="<?= $first ? 'true' : 'false' ?>" tabindex="<?= $first ? '0' : '-1' ?>"><?= e($l) ?></button>
        <?php $first = false; endforeach; ?>
      </div>
      <?php $first = true; foreach ($tabs as $k => $l): ?>
      <section class="kd-panel" role="tabpanel" id="kd-<?= e($k) ?>" aria-labelledby="kd-tab-<?= e($k) ?>"<?= $first ? '' : ' hidden' ?> data-kd-panel="<?= e($k) ?>"></section>
      <?php $first = false; endforeach; ?>
    </aside>
  </div>
</div>
<script type="application/json" id="kd-boot"><?= json_encode($boot, JSON_HEX_TAG | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?></script>
