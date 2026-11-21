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
    <li>Wählen Sie den Ausgangspunkt: eine <b>Vorlage von fluid</b>, das <b>aktuelle Design dieser Website</b> (wenn sie fluid nutzt), ein
      <b>installiertes Kind-Kit</b> von fluid, einen <b>Export des Kit-Designers</b> (ZIP für KLXM Studio oder REDAXO fluid) oder eine Datei mit
      <b>Design-Tokens</b>, z. B. aus Figma (siehe unten). Kits und ZIPs aus dem Kit-Designer kommen vollständig zurück – mit allen Stilen für Elemente
      und Abschnitte; bei anderen Kind-Kits übernimmt der Kit-Designer die Standardwerte des Designs.</li>
    <li><b>Entwurf anlegen</b> öffnet die Arbeitsfläche. Sie können beliebig viele Entwürfe je Website anlegen.</li>
  </ol>

  <h3 id="kit-designer-arbeitsflaeche">Die Arbeitsfläche</h3>
  <p>Links stehen die <b>Ebenen</b> der Seite (Kopfbereich, Abschnitte mit ihren Elementen, Fußbereich), in der Mitte die <b>Zeichenflächen</b>: dieselbe
    Seite Ihrer Website in drei Größen, oben hell, unten dunkel. Rechts stehen die Eigenschaften in Reitern. Jede Änderung erscheint sofort. Oben wählen Sie
    die <b>Ansicht</b> (Seite, Galerie, Vergleich), die <b>Seite</b>, den <b>Zoom</b> und welche Zeichenflächen sichtbar sind. Ein <b>Doppelklick</b> auf einen
    Link in einer Zeichenfläche öffnet diese Seite in allen Zeichenflächen.</p>
  <table class="doc-table">
    <tr><th>Reiter</th><th>Was Sie dort festlegen</th></tr>
    <tr><td>Element</td><td>Das gewählte Element (Knopf, Überschrift, Karte, Kopfbereich …): Geltung, Zustand, Farben, Schrift, Form, Abstände, Kontrast, Maße</td></tr>
    <tr><td>Farben</td><td>Vorlage (Palette) von fluid, Dunkelmodus an/aus, alle Farben hell und dunkel, Kontrastprüfung mit „Beheben“, Dunkel-Fassung vorschlagen</td></tr>
    <tr><td>Schrift</td><td>Schriften für Fließtext, Überschriften und Code (mit Probe), Grundschrift klein/groß, Verhältnis der Stufen (mit Tabelle der Schriftstufen), Zeilenhöhe, Stärke und Laufweite der Überschriften</td></tr>
    <tr><td>Raum &amp; Form</td><td>Eckenradius, Schatten, Buttons, Karten, Bilder, Dichte, Abstand der Abschnitte, Inhaltsbreite, Dachzeilen</td></tr>
    <tr><td>Kopf &amp; Fuß</td><td>Varianten des Kopf- und Fußbereichs, Hintergründe, Menüpunkte, Seitenhintergrund</td></tr>
    <tr><td>Abschnitte</td><td>Abschnitts-Stile: Flächen, Verläufe und Bild-Overlays, die die Redaktion später als Hintergrund wählt</td></tr>
    <tr><td>Tokens</td><td>Design-Tokens herunterladen oder importieren (siehe unten)</td></tr>
    <tr><td>Export</td><td>Titel, technischer Name, Beschreibung, Urheber; Ausgabe für KLXM Studio und REDAXO</td></tr>
    <tr><td>Versionen</td><td>Jede Speicherung ist eine Version – mit Notiz; ältere Versionen lassen sich wieder laden. Vorschau-Links zum Teilen</td></tr>
  </table>
  <p><b>Speichern</b> (oder <kbd>Strg</kbd>/<kbd>⌘</kbd> + <kbd>S</kbd>) legt eine neue Version an. <b>Rückgängig</b> und <b>Wiederholen</b> stehen oben
    (<kbd>Strg</kbd>/<kbd>⌘</kbd> + <kbd>Z</kbd>, mit <kbd>Umschalt</kbd> wiederholen) – auch über Speicherungen hinweg, solange die Seite offen ist.
    Alles lässt sich mit der Tastatur bedienen: Reiter mit den Pfeiltasten, Felder mit <kbd>Tab</kbd>, die Ebenen mit den Pfeiltasten und <kbd>Enter</kbd>;
    der Link „Zu den Zeichenflächen springen“ führt direkt zur Vorschau.</p>

  <h3 id="kit-designer-elemente">Elemente gestalten – direkt in der Vorschau</h3>
  <p>Fahren Sie mit der Maus über die Vorschau: Elemente bekommen einen blauen Umriss mit Name und Größe. Ein <b>Klick</b> wählt das Element – der Reiter
    <b>Element</b> zeigt dann seine Eigenschaften. Die gelben Flächen zeigen den Innenabstand; zeigen Sie bei gewähltem Element auf ein anderes, erscheint
    der <b>Abstand</b> zwischen beiden in Pixeln (wie in Figma). Ohne Maus wählen Sie unter <b>Ebenen</b> oder über die Liste der Elementtypen im Reiter
    Element. <kbd>Alt</kbd> + <kbd>↑</kbd> wählt das übergeordnete Element (z. B. vom Titel zur Karte), <kbd>Esc</kbd> hebt die Auswahl auf.</p>
  <ul>
    <li><b>Gilt für:</b> <em>Überall</em>, für <em>alle Blöcke dieses Typs</em> (z. B. alle „Karten“) oder <em>nur für eine Variante</em> (z. B. „Karten · Text auf dem Bild“).
      Ein Häkchen zeigt, wo es schon Angaben gibt.</li>
    <li><b>Zustand:</b> Normal, Zeigen (hover), Fokus, Gedrückt, Deaktiviert bzw. „Aktuelle Seite“ bei Menüpunkten. Mit <b>„Zustand in der Vorschau zeigen“</b>
      sehen Sie z. B. den Hover-Zustand, ohne mit der Maus darüber zu fahren.</li>
    <li><b>Eigenschaften:</b> Farben kommen aus dem Design (z. B. „Akzentfarbe“ – sie folgen hell/dunkel und späteren Änderungen im Style-Editor) oder als
      eigene Farbe mit optionalem Dunkel-Wert. Dazu Schrift, Schriftstufe, Stärke, Laufweite, Schreibweise, Rundung, Rahmen, Schatten, Innenabstände,
      Versatz beim Zeigen und der Fokusrahmen. „wie im Kit“ bzw. das Kreuz setzt eine Angabe zurück.</li>
    <li><b>Kontrast dieses Elements:</b> je Zustand hell und dunkel, mit „Beheben“. „Deaktiviert“ ist nach WCAG ausgenommen und wird nur angezeigt.
      Fokusrahmen brauchen 3 : 1 zur Fläche.</li>
    <li><b>Maße:</b> Größe, Innenabstand, Schrift, Rundung und Farben des Elements in der Desktop-Ansicht.</li>
  </ul>

  <h3 id="kit-designer-galerie">Komponenten-Galerie</h3>
  <p>Die Ansicht <b>Galerie</b> zeigt alle Blöcke von fluid in jeder Variante untereinander – mit frei erfundenen Beispielinhalten (oder mit Inhalten
    dieser Website, Auswahl „Inhalte“). So gestalten Sie Block für Block: Ein Klick auf ein Element in der Galerie stellt „Gilt für“ auf genau diese
    Variante. Am Ende der Galerie steht je Abschnitts-Stil ein Beispiel.</p>

  <h3 id="kit-designer-abschnitte">Abschnitts-Stile</h3>
  <p>Im Reiter <b>Abschnitte</b> legen Sie wiederverwendbare Hintergründe an: eine <b>Fläche</b>, einen <b>Verlauf</b> (zwei Farben, Winkel) oder ein
    <b>Bild-Overlay</b> für Abschnitte mit Hintergrundbild (Farbe und Deckkraft über dem Bild). Dazu die Schriftfarbe und optional eine Akzentfarbe für
    Links und Knöpfe. Der Kontrast wird geprüft – beim Verlauf an beiden Enden, beim Bild-Overlay über hellem und dunklem Bild. Im exportierten Kit wählt
    die Redaktion die Stile wie die anderen Hintergründe unter <b>Abschnitt → Hintergrund</b>. In der Seitenansicht können Sie einen Stil einem gewählten
    Abschnitt zur Probe geben (nur Vorschau).</p>

  <h3 id="kit-designer-vergleich">Zwei Stände vergleichen und teilen</h3>
  <ul>
    <li><b>Vergleich:</b> zwei gespeicherte Versionen oder eine Version und der aktuelle Stand nebeneinander, hell oder dunkel, mit gekoppeltem Bildlauf und
      einer Liste der Unterschiede.</li>
    <li><b>Vorschau-Link</b> (Reiter Versionen): zeigt die zuletzt gespeicherte Version auf Seiten Ihrer Website – ohne Anmeldung, nur lesend, 1, 7 oder
      30 Tage lang. Der Link erscheint nur einmal; Sie können ihn jederzeit widerrufen. Die Liste zeigt, wie oft er geöffnet wurde. Schriften, die nur im
      Kit-Designer zur Probe geladen werden (nicht im Schriften-Manager installiert), erscheinen im Vorschau-Link als Ersatzschrift.</li>
  </ul>

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
  <p>Der Export ist erst möglich, wenn <b>alle Farbpaare und alle Stile AA bestehen</b> (auch Zustände und Abschnitts-Stile). Fehlt noch etwas, nennt der
    Reiter <b>Export</b> die Stellen mit einem Link dorthin.
    Ungespeicherte Änderungen werden vor dem Export gespeichert.</p>
  <ul>
    <li><b>KLXM Studio – ZIP herunterladen:</b> ein Kind-Kit von fluid. Hochladen unter <b>Administration → Kits</b> oder als Ordner ins Projekt legen.</li>
    <li><b>In dieser Installation installieren:</b> legt das Kit direkt an (wie ein Upload) – nur für Personen, die die Installation verwalten; gedacht vor
      allem für Testsysteme. Danach unter <b>Grundeinstellungen → Aktives Kit</b> wählen.</li>
    <li><b>REDAXO fluid – ZIP herunterladen:</b> dasselbe Kit für REDAXO 6 mit dem AddOn fluid. Die Datei README.md im ZIP beschreibt das Einspielen.</li>
  </ul>
  <p>Ein exportiertes Kit lässt sich jederzeit neu ausgeben – das installierte Kit aus dem Kit-Designer wird dabei ersetzt. Die Werte bleiben auch nach
    dem Export im Style-Editor (<b>Design</b>) änderbar; das Kit bestimmt nur die Standardwerte. Stile für Elemente und Abschnitte stehen im Kit als CSS
    (CSS je Block lädt nur auf Seiten mit diesem Block). Zum Weiterbearbeiten legen Sie einen neuen Entwurf aus dem installierten Kit oder dem ZIP an.</p>
