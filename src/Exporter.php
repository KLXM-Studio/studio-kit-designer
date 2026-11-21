<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Kit-Designer. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\KitDesigner;

use Core\Kit;
use Core\KitPackages;

/**
 * Export eines Entwurfs als Kind-Kit von fluid – für KLXM Studio und für REDAXO fluid (REDAXO 6).
 *
 * Studio (ZIP bzw. direkt installiert über Core\KitPackages nach storage/kits/{name}):
 *   theme.php             'extends' => 'fluid', 'design_defaults' => nur die Werte, die vom Eltern-Kit abweichen
 *   composer.json         Typ klxm-studio-kit (Paket für Composer oder ZIP-Upload)
 *   assets/css/site.css   nur bei eigenem CSS (Zeilenhöhe): @import des Eltern-CSS + abweichende Regeln (Quelle für den Build)
 *   public/css/site.css   dasselbe fertig für Pakete ohne Build (@import url(../../fluid/css/site.css))
 *   design-tokens.json    die Werte im DTCG-Format, README.md, LICENSE (MIT)
 *
 * REDAXO fluid (ZIP, Inhalt nach src/Kit/{Name}/ des Projekts – wie bin/console fluid:kit:create):
 *   {Name}Kit.php         #[AsKit('name', 'Titel', parent: 'fluid')] final class …Kit extends Kit {}
 *   kit.php               legt die Standardwerte über $parent['design'] (nur Tokens, die es dort gibt)
 *   assets/css/kit.css    abweichende Regeln (Zeilenhöhe), sonst nur Kommentar
 *
 * Export nur, wenn alle Kontrastpaare AA bestehen (Palette::failures).
 */
final class Exporter
{
    /** Namen der Tokens in REDAXO fluid, wo sie abweichen */
    public const REDAXO_RENAME = ['transitions' => 'transition'];

    /**
     * Werte, die vom Eltern-Kit abweichen, im Format von 'design_defaults' (Farben: ['default' => …, 'dark' => …]).
     * @return array<string, mixed>
     */
    public static function designDefaults(array $values): array
    {
        $values = KitDesigner::normalize($values);
        $def = KitDesigner::defaults();
        $out = [];
        foreach (KitDesigner::tokens() as $n => $t) {
            if (!array_key_exists($n, $values)) continue;
            if (($t['type'] ?? '') === 'color') {
                $light = $values[$n];
                $dark = $values[$n . '@dark'] ?? null;
                if ($light === ($def[$n] ?? null) && $dark === ($def[$n . '@dark'] ?? null)) continue;
                $out[$n] = $dark !== null ? ['default' => $light, 'dark' => $dark] : $light;
                continue;
            }
            if ($values[$n] == ($def[$n] ?? null)) continue;
            $out[$n] = $values[$n];
        }
        return $out;
    }

    /** Sperre: nicht bestandene Kontrastpaare der Farben und der Stile (Zustände, Abschnitts-Stile) – leer = Export erlaubt */
    public static function blockers(array $draft): array
    {
        $values = KitDesigner::normalize((array) ($draft['data']['values'] ?? []));
        return [...Palette::failures($values, KitDesigner::tokens()), ...Styles::failures((array) ($draft['data']['styles'] ?? []), $values)];
    }

    // ------------------------------------------------------------------ Studio

    /** @return array<string, string> Pfad im Kit => Inhalt */
    public static function studioFiles(array $draft): array
    {
        $name = (string) $draft['name'];
        $label = (string) $draft['label'];
        $meta = (array) ($draft['data']['meta'] ?? []);
        $values = (array) ($draft['data']['values'] ?? []);
        $extraCss = KitDesigner::extraCss((array) ($draft['data']['extra'] ?? []));
        $styles = (array) ($draft['data']['styles'] ?? []);
        $parts = Styles::cssParts($styles);
        $global = trim(implode("\n", array_filter([$extraCss, $parts[''] ?? ''])));
        unset($parts['']);
        $bgs = Styles::backgrounds($styles, KitDesigner::normalize($values));
        $ownCss = KitDesigner::coreKitCss();   // Core lädt 'css' zusätzlich – sonst css/site.css mit @import von fluid
        $dd = self::designDefaults($values);
        $desc = (string) ($meta['description'] ?? '') !== '' ? (string) $meta['description'] : 'Kind-Kit von fluid, gestaltet mit dem Kit-Designer.';
        $date = date('Y-m-d');
        $php = "<?php\n/*\n * Kit „" . self::comment($label) . "“ – Kind-Kit von fluid (Vererbung über 'extends', Core\\Kit::chain).\n"
            . " * Erstellt mit dem Kit-Designer (KLXM Studio) am $date, Entwurf Version " . (int) $draft['version'] . ".\n *\n"
            . " * Alles, was hier fehlt – Blöcke, Vorlagen, Kopf und Fuß, CSS, Style-Editor –, kommt von fluid; Korrekturen an fluid kommen\n"
            . " * so automatisch an. 'design_defaults' setzt nur die Standardwerte, die vom Eltern-Kit abweichen (Farben hell und dunkel).\n"
            . " * Redakteure ändern sie weiter unter Verwaltung → Design.\n"
            . ($global !== '' ? ($ownCss ? " * Eigenes CSS: css/kit.css ('css' – lädt zusätzlich zum CSS von fluid, nach dem CSS der Blöcke).\n"
                : " * Eigenes CSS: assets/css/site.css (Quelle) bzw. public/css/site.css (fertig) – lädt das CSS von fluid und ergänzt es.\n") : '')
            . ($parts ? " * CSS je Block: css/kd-{typ}.css ('conditional_css' – nur auf Seiten mit dem Block).\n" : '')
            . ($bgs['list'] ? " * Abschnitts-Stile: 'backgrounds' – die Redaktion wählt sie unter Abschnitt → Hintergrund.\n" : '')
            . " */\n\nreturn " . self::php(array_filter([
                'extends' => KitDesigner::PARENT,
                'label' => $label,
                'description' => $desc,
                'category' => 'general',
                'version' => '0.' . (self::stage($draft) >= 2 ? '2' : '1') . '.' . max(0, (int) $draft['version'] - 1),
                'requires' => '>=1.0.0',
                'css' => $global !== '' && $ownCss ? ['css/kit.css'] : null,
                'conditional_css' => $parts ? array_combine(array_map(fn($t) => 'css/kd-' . $t . '.css', array_keys($parts)), array_map(fn($t) => [(string) $t], array_keys($parts))) : null,
                'backgrounds' => $bgs['list'] ?: null,
                'dark_backgrounds' => $bgs['dark'] ? array_values(array_unique([...(array) (KitDesigner::parentTheme()->def['dark_backgrounds'] ?? []), ...$bgs['dark']])) : null,
                'design_defaults' => $dd,
            ], fn($v) => $v !== null)) . ";\n";
        $files = [
            'theme.php' => $php,
            'composer.json' => json_encode([
                'name' => 'kits/' . $name,
                'description' => $label . ' – Kit für KLXM Studio (Kind-Kit von fluid)',
                'type' => Kit::PACKAGE_TYPE,
                'license' => 'MIT',
                'extra' => ['klxm-studio' => ['kit' => $name, 'public' => 'public', 'requires-core' => '>=1.0.0']],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
            'design-tokens.json' => json_encode(Tokens::export($draft), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
            'kit-designer.json' => self::sourceJson($draft),
            'LICENSE' => self::license((string) ($meta['author'] ?? '')),
            'README.md' => self::readme($draft, 'studio', $dd),
            // Ordner public/ (fertige Assets) – ohne eigenes CSS nur dieser Hinweis; alles Übrige kommt von fluid
            'public/LIESMICH.txt' => "Fertige Assets des Kits „{$label}“. Fehlende Dateien kommen vom Eltern-Kit fluid.\n",
        ];
        // Vorlagen kommen von fluid; ältere Cores verlangen den Ordner templates/ auch bei Kind-Kits (KitPackages::check)
        if (!$ownCss) $files['templates/LIESMICH.txt'] = "Vorlagen des Kits „{$label}“: leer – alle Vorlagen kommen von fluid. Eine Datei hier ersetzt die gleichnamige von fluid.\n";
        if ($global !== '' && $ownCss) {
            $css = "/* Kit „" . self::comment($label) . "“ – eigenes CSS (Kit-Designer), lädt zusätzlich zum CSS von fluid. */\n" . $global . "\n";
            $files['assets/css/kit.css'] = $css;
            $files['public/css/kit.css'] = $css;
        } elseif ($global !== '') {
            $head = "/* Kit „" . self::comment($label) . "“ – CSS von fluid plus abweichende Regeln (Kit-Designer) */\n";
            $files['assets/css/site.css'] = $head . "@import \"../../../fluid/assets/css/site.css\";\n\n" . $global . "\n";
            $files['public/css/site.css'] = $head . "@import url(\"../../fluid/css/site.css\");\n\n" . $global . "\n";
        }
        foreach ($parts as $type => $css) {
            $css = "/* Kit „" . self::comment($label) . "“ – Stile für den Block „{$type}“ (Kit-Designer), nur auf Seiten mit diesem Block. */\n" . $css . "\n";
            $files["assets/css/kd-{$type}.css"] = $css;
            $files["public/css/kd-{$type}.css"] = $css;
        }
        return $files;
    }

    /**
     * In dieser Installation installieren (storage/kits/{name}, Core\KitPackages – wie ein ZIP-Upload unter Verwaltung → Kits).
     * @return array{ok: bool, name: string, message: string, warnings: list<string>}
     */
    public static function installStudio(array $draft, string $by = ''): array
    {
        $name = (string) $draft['name'];
        $src = Kit::source($name);
        if ($src !== null && $src !== 'upload') {
            return ['ok' => false, 'name' => $name, 'message' => __('Es gibt bereits ein Kit „{name}“ in dieser Installation ({source}) – bitte den technischen Namen des Entwurfs ändern.', ['name' => $name, 'source' => $src]), 'warnings' => []];
        }
        if ($src === 'upload' && !self::ownUpload($name)) {
            return ['ok' => false, 'name' => $name, 'message' => __('Das hochgeladene Kit „{name}“ stammt nicht aus dem Kit-Designer – es wird nicht ersetzt. Bitte den technischen Namen ändern.', ['name' => $name]), 'warnings' => []];
        }
        $dir = self::writeTemp(self::studioFiles($draft), $name);
        try {
            return KitPackages::install($dir, $src === 'upload', $by, $name . '.zip');
        } finally {
            self::rmdir(dirname($dir));
        }
    }

    /** Hochgeladenes Kit aus dem Kit-Designer? (Kennzeichen in theme.php) */
    public static function ownUpload(string $name): bool
    {
        $f = KitPackages::uploadDir($name) . '/theme.php';
        return is_file($f) && str_contains((string) file_get_contents($f), 'Erstellt mit dem Kit-Designer');
    }

    // ------------------------------------------------------------------ REDAXO fluid

    /** Klassenname aus dem technischen Namen: „kanzlei-nord“ → „KanzleiNord“ */
    public static function className(string $name): string
    {
        $c = str_replace(' ', '', ucwords(str_replace(['-', '_'], ' ', $name)));
        return preg_match('~^[A-Z][A-Za-z0-9]*$~', $c) ? $c : 'Mein' . preg_replace('~[^A-Za-z0-9]~', '', $c);
    }

    /** Namensraum des Projekts prüfen (z. B. „Project“ oder „App“); ungültig → „Project“ */
    public static function namespace(string $ns): string
    {
        $ns = trim(str_replace('/', '\\', $ns), '\\ ');
        return preg_match('~^[A-Z][A-Za-z0-9_]*(\\\\[A-Z][A-Za-z0-9_]*)*$~', $ns) ? $ns : 'Project';
    }

    /** @return array<string, string> Pfad relativ zum Projekt (src/Kit/{Name}/…) => Inhalt */
    public static function redaxoFiles(array $draft, string $namespace = 'Project'): array
    {
        $name = (string) $draft['name'];
        $label = (string) $draft['label'];
        $class = self::className($name);
        $ns = self::namespace($namespace) . '\\Kit\\' . $class;
        $base = 'src/Kit/' . $class . '/';
        $dd = self::designDefaults((array) ($draft['data']['values'] ?? []));
        $defaults = [];
        foreach ($dd as $n => $v) {
            $n = self::REDAXO_RENAME[$n] ?? $n;
            // installierte Schriften des Studio-Schriften-Managers („installed:{id}“) heißen in fluid wie die Fontsource-Kennung
            if (is_string($v) && str_starts_with($v, 'installed:')) $v = substr($v, 10);
            $defaults[$n] = is_array($v) ? [$v['default'], $v['dark']] : $v;
        }
        $extraCss = KitDesigner::extraCss((array) ($draft['data']['extra'] ?? []));
        // REDAXO fluid kennt (noch) kein Token für die Zeilenhöhe: abweichende Werte als CSS
        $lh = (float) (KitDesigner::normalize((array) ($draft['data']['values'] ?? []))['line_height'] ?? KitDesigner::PARENT_LINE_HEIGHT);
        if ($extraCss === '' && KitDesigner::lineHeightToken() && abs($lh - KitDesigner::PARENT_LINE_HEIGHT) > 0.0001) {
            $extraCss = 'body{line-height:' . rtrim(rtrim(number_format($lh, 3, '.', ''), '0'), '.') . '}';
        }
        unset($defaults['line_height']);
        $styles = (array) ($draft['data']['styles'] ?? []);
        $parts = Styles::cssParts($styles);
        $global = trim(implode("\n", array_filter([$extraCss, $parts[''] ?? ''])));
        unset($parts['']);
        $bgs = Styles::backgrounds($styles, KitDesigner::normalize((array) ($draft['data']['values'] ?? [])));
        $q = fn(string $s) => str_replace(['\\', "'"], ['\\\\', "\\'"], $s);
        $date = date('Y-m-d');
        $kitClass = "<?php\n\nnamespace {$ns};\n\nuse Klxm\\Fluid\\AsKit;\nuse Klxm\\Fluid\\Kit;\n\n/**\n"
            . " * Kit „" . self::comment($label) . "“ – erbt von „Fluid“. Erstellt mit dem Kit-Designer (KLXM Studio) am $date.\n"
            . " * Dateien in diesem Ordner ersetzen die des Eltern-Kits: kit.php (Definition), views/{typ}.php, template.view.php,\n"
            . " * functions.php, assets/.\n */\n"
            . "#[AsKit('{$name}', '{$q($label)}', parent: '" . KitDesigner::PARENT . "')]\nfinal class {$class}Kit extends Kit {}\n";
        $kitPhp = "<?php\n\n/*\n * Kit „" . self::comment($label) . "“: Definition. \$parent ist die Definition des Eltern-Kits „fluid“ – alles, was hier\n"
            . " * fehlt, gilt von dort. Erstellt mit dem Kit-Designer (KLXM Studio) am $date, Entwurf Version " . (int) $draft['version'] . ".\n *\n"
            . " * \$defaults: Standardwerte des Style-Editors, die von fluid abweichen – Farben als [hell, dunkel]. Sie werden über die\n"
            . " * Tokens von \$parent['design'] gelegt; Tokens oder Schriften, die es in dieser Fassung von fluid nicht gibt, bleiben unberührt.\n */\n\n"
            . '$defaults = ' . self::php($defaults) . ";\n\n"
            . "\$design = \$parent['design'];\n"
            . "foreach (\$design['groups'] as &\$group) {\n"
            . "    foreach (\$group['tokens'] as &\$token) {\n"
            . "        \$name = (string) (\$token['name'] ?? '');\n"
            . "        if (!array_key_exists(\$name, \$defaults)) {\n            continue;\n        }\n"
            . "        \$value = \$defaults[\$name];\n"
            . "        if ('font' === (\$token['type'] ?? '') && !isset(\$design['fonts'][\$value])) {\n            continue;\n        }\n"
            . "        if (is_array(\$value)) {\n            \$token['default'] = \$value[0];\n            if ('color' === (\$token['type'] ?? '')) {\n                \$token['dark'] = \$value[1];\n            }\n"
            . "        } else {\n            \$token['default'] = \$value;\n        }\n"
            . "    }\n    unset(\$token);\n}\nunset(\$group);\n\n"
            . "return [\n    'label' => '{$q($label)}',\n    // Stylesheets für jede Seite – nach dem Grund- und Block-CSS des Eltern-Kits\n    'css' => ['css/kit.css'],\n"
            . ($parts ? "    // CSS je Blocktyp (Kit-Designer) – nur auf Seiten mit dem Block\n    'assets' => " . self::php(array_combine(array_map(fn($t) => 'css/kd-' . $t . '.css', array_keys($parts)), array_map(fn($t) => [(string) $t], array_keys($parts))), 1) . ",\n" : '')
            . ($bgs['list'] ? "    // Abschnitts-Stile (Kit-Designer): wählbar als Hintergrund eines Abschnitts\n    'backgrounds' => " . self::php($bgs['list'], 1) . ",\n" : '')
            . ($bgs['dark'] ? "    'dark_backgrounds' => [...(\$parent['dark_backgrounds'] ?? []), ..." . self::php($bgs['dark']) . "],\n" : '')
            . "    'design' => \$design,\n];\n";
        $css = "/*\n * Kit „" . self::comment($label) . "“ – eigenes CSS (Kit-Designer). Wird nach dem CSS von „Fluid“ (auch dem der Blöcke) geladen.\n"
            . " * Farben, Schriften und Formen stehen als Standardwerte in kit.php und bleiben im Style-Editor (Design) änderbar.\n */\n"
            . ($global !== '' ? "\n" . $global . "\n" : '');
        $files = [
            $base . $class . 'Kit.php' => $kitClass,
            $base . 'kit.php' => $kitPhp,
            $base . 'assets/css/kit.css' => $css,
            $base . 'design-tokens.json' => json_encode(Tokens::export($draft), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n",
            $base . 'README.md' => self::readme($draft, 'redaxo', $dd, $ns . '\\' . $class . 'Kit'),
            $base . 'kit-designer.json' => self::sourceJson($draft),
            $base . 'LICENSE' => self::license((string) ($draft['data']['meta']['author'] ?? '')),
        ];
        foreach ($parts as $type => $css) {
            $files[$base . "assets/css/kd-{$type}.css"] = "/* Kit „" . self::comment($label) . "“ – Stile für den Block „{$type}“ (Kit-Designer). */\n" . $css . "\n";
        }
        return $files;
    }

    /** Stufe des Entwurfs: 2, sobald Stile für Elemente, Blöcke oder Abschnitte da sind */
    public static function stage(array $draft): int
    {
        $st = Styles::normalize((array) ($draft['data']['styles'] ?? []));
        return $st['scopes'] || $st['sections'] ? 2 : 1;
    }

    /** Quelle des Entwurfs für den Import zum Weiterbearbeiten (kit-designer.json) */
    public static function sourceJson(array $draft): string
    {
        $d = Drafts::clean((array) ($draft['data'] ?? []));
        return json_encode(['format' => 'klxm-kit-designer', 'version' => 2, 'name' => (string) $draft['name'], 'label' => (string) $draft['label'],
            'draft_version' => (int) $draft['version'], 'data' => $d], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
    }

    // ------------------------------------------------------------------ ZIP

    /** ZIP aus Dateien; $root = Ordner im ZIP ('' = Wurzel). @return string Inhalt der ZIP-Datei */
    public static function zip(array $files, string $root = ''): string
    {
        if (!class_exists(\ZipArchive::class)) throw new \RuntimeException(__('PHP-Erweiterung zip fehlt.'));
        $tmp = tempnam(sys_get_temp_dir(), 'kd');
        if ($tmp === false) throw new \RuntimeException('tempnam');
        $zip = new \ZipArchive();
        $zip->open($tmp, \ZipArchive::OVERWRITE);
        $prefix = $root !== '' ? rtrim($root, '/') . '/' : '';
        foreach ($files as $path => $content) $zip->addFromString($prefix . $path, $content);
        $zip->close();
        $bytes = (string) file_get_contents($tmp);
        @unlink($tmp);
        return $bytes;
    }

    // ------------------------------------------------------------------ intern

    /** Dateien in einen frischen Ordner schreiben. @return string Ordner des Kits ({tmp}/{name}) */
    public static function writeTemp(array $files, string $name): string
    {
        $base = ROOT . '/storage/tmp/kit-designer-' . bin2hex(random_bytes(5));
        $dir = $base . '/' . $name;
        foreach ($files as $path => $content) {
            @mkdir(dirname($dir . '/' . $path), 0775, true);
            file_put_contents($dir . '/' . $path, $content);
        }
        return $dir;
    }

    public static function rmdir(string $dir): void
    {
        if (!is_dir($dir) || !str_contains($dir, '/storage/tmp/kit-designer-')) return;
        $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS), \RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $f) $f->isDir() ? @rmdir($f->getPathname()) : @unlink($f->getPathname());
        @rmdir($dir);
    }

    /** PHP-Quelltext eines Werts (kurze Array-Schreibweise, 4 Leerzeichen) */
    public static function php(mixed $v, int $depth = 0): string
    {
        if (is_array($v)) {
            if ($v === []) return '[]';
            $list = array_is_list($v);
            $pad = str_repeat('    ', $depth + 1);
            $inline = $list && count($v) <= 4 && !array_filter($v, 'is_array');
            if ($inline) return '[' . implode(', ', array_map(fn($x) => self::php($x), $v)) . ']';
            $lines = [];
            foreach ($v as $k => $x) $lines[] = $pad . ($list ? '' : var_export($k, true) . ' => ') . self::php($x, $depth + 1) . ',';
            return "[\n" . implode("\n", $lines) . "\n" . str_repeat('    ', $depth) . ']';
        }
        if (is_float($v)) return rtrim(rtrim(number_format($v, 4, '.', ''), '0'), '.') ?: '0';
        return var_export($v, true);
    }

    private static function comment(string $s): string
    {
        return str_replace(['*/', "\n", "\r"], ['* /', ' ', ' '], $s);
    }

    private static function license(string $author): string
    {
        $holder = $author !== '' ? $author : 'the authors';
        return "MIT License\n\nCopyright (c) " . date('Y') . " {$holder}\n\n"
            . "Permission is hereby granted, free of charge, to any person obtaining a copy\nof this software and associated documentation files (the \"Software\"), to deal\n"
            . "in the Software without restriction, including without limitation the rights\nto use, copy, modify, merge, publish, distribute, sublicense, and/or sell\n"
            . "copies of the Software, and to permit persons to whom the Software is\nfurnished to do so, subject to the following conditions:\n\n"
            . "The above copyright notice and this permission notice shall be included in all\ncopies or substantial portions of the Software.\n\n"
            . "THE SOFTWARE IS PROVIDED \"AS IS\", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR\nIMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,\n"
            . "FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE\nAUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER\n"
            . "LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,\nOUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE\nSOFTWARE.\n";
    }

    private static function readme(array $draft, string $target, array $dd, string $class = ''): string
    {
        $name = (string) $draft['name'];
        $label = (string) $draft['label'];
        $fonts = KitDesigner::fonts();
        $rows = [];
        foreach ($dd as $n => $v) {
            $t = KitDesigner::tokens()[$n] ?? [];
            $val = is_array($v) ? $v['default'] . ' / dunkel ' . $v['dark'] : (($t['type'] ?? '') === 'font' ? (string) ($fonts[$v]['label'] ?? $v) : (is_bool($v) ? ($v ? 'an' : 'aus') : (string) $v));
            $rows[] = '| ' . ($t['label'] ?? $n) . ' | `' . $n . '` | ' . str_replace('|', '\\|', $val) . ' |';
        }
        $installed = array_filter($dd, fn($v) => is_string($v) && str_starts_with($v, 'installed:'));
        $md = "# Kit „{$label}“ (`{$name}`)\n\nKind-Kit von **fluid**, gestaltet mit dem Kit-Designer von KLXM Studio (Entwurf Version " . (int) $draft['version'] . ", " . date('d.m.Y') . ").\n"
            . "Es erbt Blöcke, Vorlagen, Kopf und Fuß, CSS und den Style-Editor von fluid und setzt nur eigene Standardwerte.\n"
            . "Alle Farbpaare erfüllen WCAG 2.2 AA (Schrift mindestens 4,5 : 1, Bedienelemente und Grafik mindestens 3 : 1) – hell und dunkel.\n\n";
        if ($target === 'studio') {
            $md .= "## Einspielen in KLXM Studio\n\n"
                . "- **Verwaltung → Kits → Kit hochladen** (ZIP) oder `php bin/console kit:install {$name}.zip` – landet in `storage/kits/{$name}/`.\n"
                . "- Oder den Ordner als `kits/{$name}/` ins Projekt legen und `cd tools && pnpm run build` ausführen.\n"
                . "- Danach unter **Verwaltung → Design → Kit** wählen. Prüfen: `php bin/console kit:check --kit={$name}`.\n\n";
        } else {
            $md .= "## Einspielen in REDAXO fluid (REDAXO 6)\n\n"
                . "1. Den Inhalt des ZIP in das Projekt entpacken – der Ordner landet unter `src/Kit/" . self::className($name) . "/`\n"
                . "   (wie `bin/console fluid:kit:create`). Der Namensraum `{$class}` muss zum PSR-4-Eintrag für `src/` in der\n"
                . "   `composer.json` des Projekts passen (sonst `namespace` in der Kit-Klasse anpassen).\n"
                . "2. `bin/console cache:clear` (Klassen neu einlesen), dann `bin/console migrate` (veröffentlicht `assets/` nach `assets/fluid-kits/{$name}/`).\n"
                . "3. **Website-Einstellungen → Reiter „Kit“** → „{$label}“ wählen.\n"
                . "4. Hat die Website schon eigene Design-Werte im Kit fluid, übernimmt das neue Kit diese zunächst: unter **Design** auf\n"
                . "   **Standard** gehen, damit die Werte dieses Kits gelten.\n\n";
        }
        $md .= "## Abweichende Standardwerte\n\n| Einstellung | Token | Wert |\n|---|---|---|\n" . ($rows ? implode("\n", $rows) : '| – | – | wie fluid |') . "\n\n";
        $extra = KitDesigner::extraCss((array) ($draft['data']['extra'] ?? []));
        if ($extra !== '') $md .= "Eigenes CSS: `" . $extra . "` (" . ($target === 'studio' ? 'assets/css/site.css' : 'assets/css/kit.css') . ").\n\n";
        if ($installed) {
            $md .= "Schriften aus dem Schriften-Manager (" . implode(', ', array_map(fn($v) => substr((string) $v, 10), $installed)) . "): in der Zielinstallation"
                . ($target === 'studio' ? " unter Grundeinstellungen → Schriften installieren, sonst gilt die Ersatzschrift.\n\n" : " gibt es sie nur, wenn fluid sie kennt – sonst bleibt die Schrift von fluid.\n\n");
        }
        $md .= "`design-tokens.json` enthält alle Werte im Format der W3C Design Tokens Community Group (z. B. für Figma/Tokens Studio).\n\nLizenz: MIT (siehe LICENSE).\n";
        return $md;
    }
}
