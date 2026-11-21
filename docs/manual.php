<?php
/** Handbuch · Kapitel „Kits gestalten (Kit-Designer)“ – Erweiterung „kit_designer“ (Extension::docs('manual')). */
?>
  <p class="lead">Mit dem <b>Kit-Designer</b> gestalten Sie ein eigenes Kit wie in einem Design-Werkzeug: Farben, Schriften, Abstände und
    Formen festlegen, das Ergebnis auf echten Seiten Ihrer Website prüfen – auf Telefon, Tablet und Desktop, hell und dunkel nebeneinander –
    und als fertiges Kit ausgeben. Ihre Website ändert sich dabei nicht: Erst ein exportiertes Kit, das Sie bewusst wählen, wirkt auf die Website.</p>
  <p>Der Kit-Designer ist eine Erweiterung. Die Administration schaltet sie unter <b>Administration → Funktionen &amp; Erweiterungen</b> ein;
    arbeiten darf damit, wer das Recht <b>„Kits gestalten“</b> hat (Standard: nur die Administration). Jedes Kit aus dem Kit-Designer baut auf
    dem Kit <b>fluid</b> auf und übernimmt dessen Blöcke, Vorlagen und Funktionen – Sie bestimmen das Aussehen.</p>

  <h3 id="kit-designer-anlegen">Einen Entwurf anlegen</h3>
  <ol class="doc-steps">
    <li>Öffnen Sie <b>Kit-Designer</b> (unter den Werkzeugen) und geben Sie unter <b>Neuer Entwurf</b> einen Titel ein, z. B. „Kanzlei Nord“.
      Der technische Name (Ordner des Kits) entsteht daraus von selbst.</li>
    <li>Wählen Sie den Ausgangspunkt: eine <b>Vorlage von fluid</b>, das <b>aktuelle Design dieser Website</b> (wenn sie fluid nutzt) oder eine
      Datei mit <b>Design-Tokens</b>, z. B. aus Figma (siehe unten).</li>
    <li><b>Entwurf anlegen</b> öffnet die Arbeitsfläche. Sie können beliebig viele Entwürfe je Website anlegen.</li>
  </ol>

  <h3 id="kit-designer-arbeitsflaeche">Die Arbeitsfläche</h3>
  <p>Links stehen die Eigenschaften in Reitern, rechts die <b>Zeichenflächen</b>: dieselbe Seite Ihrer Website in drei Größen, oben hell, unten dunkel.
    Jede Änderung erscheint sofort. Oben wählen Sie die <b>Seite</b>, den <b>Zoom</b> und welche Zeichenflächen sichtbar sind. Ein Klick auf einen Link in
    einer Zeichenfläche öffnet diese Seite in allen Zeichenflächen.</p>
  <table class="doc-table">
    <tr><th>Reiter</th><th>Was Sie dort festlegen</th></tr>
    <tr><td>Farben</td><td>Vorlage (Palette) von fluid, Dunkelmodus an/aus, alle Farben hell und dunkel, Kontrastprüfung mit „Beheben“, Dunkel-Fassung vorschlagen</td></tr>
    <tr><td>Schrift</td><td>Schriften für Fließtext, Überschriften und Code (mit Probe), Grundschrift klein/groß, Verhältnis der Stufen (mit Tabelle der Schriftstufen), Zeilenhöhe, Stärke und Laufweite der Überschriften</td></tr>
    <tr><td>Raum &amp; Form</td><td>Eckenradius, Schatten, Buttons, Karten, Bilder, Dichte, Abstand der Abschnitte, Inhaltsbreite, Dachzeilen</td></tr>
    <tr><td>Kopf &amp; Fuß</td><td>Varianten des Kopf- und Fußbereichs, Hintergründe, Menüpunkte, Seitenhintergrund</td></tr>
    <tr><td>Tokens</td><td>Design-Tokens herunterladen oder importieren (siehe unten)</td></tr>
    <tr><td>Export</td><td>Titel, technischer Name, Beschreibung, Urheber; Ausgabe für KLXM Studio und REDAXO</td></tr>
    <tr><td>Versionen</td><td>Jede Speicherung ist eine Version – mit Notiz; ältere Versionen lassen sich wieder laden</td></tr>
  </table>
  <p><b>Speichern</b> (oder <kbd>Strg</kbd>/<kbd>⌘</kbd> + <kbd>S</kbd>) legt eine neue Version an. Alles lässt sich mit der Tastatur bedienen: Reiter mit den
    Pfeiltasten, Felder mit <kbd>Tab</kbd>; der Link „Zu den Zeichenflächen springen“ führt direkt zur Vorschau.</p>

  <h3 id="kit-designer-farben">Farben, Kontrast und Dunkel-Fassung</h3>
  <p>Jede Farbe hat einen hellen und – wo das Kit es vorsieht – einen dunklen Wert. Sie wählen sie mit dem Farbwähler oder tippen den Hex-Wert ein
    (z. B. <code>#0F5E63</code>, <kbd>Enter</kbd> übernimmt). Die <b>Kontrastprüfung</b> läuft dabei mit und folgt WCAG 2.2 AA: Schrift braucht mindestens
    <b>4,5 : 1</b>, große Schrift, Bedienelemente und Grafik mindestens <b>3 : 1</b>. Neben jeder Farbe zeigen kleine Abzeichen das Verhältnis hell und dunkel.</p>
  <ul>
    <li><b>Beheben</b> verschiebt die Helligkeit der Farbe so wenig wie nötig, bis das Paar besteht – der Farbton bleibt.</li>
    <li><b>Zur Farbe</b> springt zum Eingabefeld.</li>
    <li><b>Dunkel-Fassung vorschlagen</b> leitet alle dunklen Werte aus den hellen ab (dunkle Flächen im Farbton Ihres Hintergrunds, helle Schrift,
      etwas hellere Markenfarben) und prüft dabei jedes Paar. Danach folgen die dunklen Werte automatisch, wenn Sie eine helle Farbe ändern
      (Schalter <b>Auto</b> neben dem dunklen Wert). Ändern Sie einen dunklen Wert selbst, endet „Auto“ für diese Farbe.</li>
  </ul>
  <p>Für manche Paare empfiehlt das Kit mehr als AA (z. B. 7 : 1 für Überschriften) – das erscheint als Hinweis unter „Alle Paare anzeigen“, sperrt aber nichts.</p>

  <h3 id="kit-designer-tokens">Design-Tokens aus Figma &amp; Co.</h3>
  <p>Design-Tokens sind die Grundwerte eines Designs als Datei. Der Kit-Designer liest und schreibt das Format der <b>W3C Design Tokens Community Group</b>
    (JSON). So kommen Farben, Schriften und Radius z. B. aus <b>Tokens Studio für Figma</b> oder aus den Figma-Variablen herein:</p>
  <ol class="doc-steps">
    <li>In Figma bzw. Tokens Studio die Tokens als JSON exportieren (eine Datei, auch mit den Sätzen „light“ und „dark“).</li>
    <li>Im Reiter <b>Tokens</b> die Datei wählen (oder den Inhalt einfügen) und <b>Prüfen</b>. Sie sehen, welcher Token wohin gehört,
      was nicht zugeordnet wurde und welche Schriften fehlen.</li>
    <li><b>Übernehmen</b> setzt die Werte. Helle Farben ohne dunklen Wert bekommen einen Vorschlag. Gespeichert wird erst mit „Speichern“.</li>
  </ol>
  <p>Zugeordnet werden Tokens mit sprechenden Namen wie <code>color.primary</code>, <code>color.text.muted</code>, <code>color.background</code>,
    <code>borderRadius.md</code>, <code>fontFamilies.heading</code> oder <code>typography.body</code>. Reine Paletten-Stufen wie <code>blue.500</code>
    haben keine Bedeutung und werden nur aufgelistet. Schriften müssen im Schriften-Manager (Grundeinstellungen → Schriften) oder im Kit vorhanden sein.</p>

  <h3 id="kit-designer-export">Als Kit ausgeben</h3>
  <p>Der Export ist erst möglich, wenn <b>alle Farbpaare AA bestehen</b>. Fehlt noch etwas, nennt der Reiter <b>Export</b> die Paare mit einem Link zur Stelle.
    Ungespeicherte Änderungen werden vor dem Export gespeichert.</p>
  <ul>
    <li><b>KLXM Studio – ZIP herunterladen:</b> ein Kind-Kit von fluid. Hochladen unter <b>Administration → Kits</b> oder als Ordner ins Projekt legen.</li>
    <li><b>In dieser Installation installieren:</b> legt das Kit direkt an (wie ein Upload) – nur für Personen, die die Installation verwalten; gedacht vor
      allem für Testsysteme. Danach unter <b>Grundeinstellungen → Aktives Kit</b> wählen.</li>
    <li><b>REDAXO fluid – ZIP herunterladen:</b> dasselbe Kit für REDAXO 6 mit dem AddOn fluid. Die Datei README.md im ZIP beschreibt das Einspielen.</li>
  </ul>
  <p>Ein exportiertes Kit lässt sich jederzeit neu ausgeben – das installierte Kit aus dem Kit-Designer wird dabei ersetzt. Die Werte bleiben auch nach
    dem Export im Style-Editor (<b>Design</b>) änderbar; das Kit bestimmt nur die Standardwerte.</p>
