# Changelog – Kit-Designer (`klxm/studio-kit-designer`)

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
