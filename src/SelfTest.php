<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Kit-Designer. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\KitDesigner;

use Core\Contrast;
use Core\Design;
use Core\Kit;
use Core\KitCheck;
use Core\KitPackages;
use Core\Theme;

/**
 * php bin/console kit_designer:selftest [--site=…] [--no-install]
 *
 * Farbräume (OKLCH hin und zurück), Kontrastprüfung (AA, strengere Empfehlung des Kits), Beheben eines Paars, Dunkel-Vorschlag
 * (alle Paare AA, Flächen dunkel), Tokens im DTCG-Format (eigener Export ↔ Import verlustfrei; Tokens-Studio-Datei mit Sätzen
 * light/dark, Verweisen, Schriften, Radius, Zeilenhöhe), Entwürfe mit Versionen, Export-Sperre, Studio-Kit (theme.php gültig,
 * design_defaults nur Abweichungen, Zusammenführen mit fluid ergibt genau den Entwurf, KitPackages::check), Installation in
 * storage/kits mit kit:check und Entfernen (ohne --no-install), REDAXO-Kit (Aufbau wie fluid:kit:create, kit.php über die
 * Design-Definition von fluid gelegt). Legt einen Test-Entwurf und ein Test-Kit an und entfernt beide wieder.
 */
final class SelfTest
{
    private static int $ok = 0;
    private static int $fail = 0;

    private static function t(bool $cond, string $what, string $detail = ''): void
    {
        if ($cond) { self::$ok++; echo "  ✓ $what\n"; return; }
        self::$fail++;
        echo "  ✗ $what" . ($detail !== '' ? " – $detail" : '') . "\n";
    }

    public static function run(array $args = []): int
    {
        self::$ok = self::$fail = 0;
        if (!KitDesigner::parentAvailable()) {
            echo "Kit fluid fehlt – Selbsttest übersprungen.\n";
            return 1;
        }
        \Core\Extensions::active()[KitDesigner::NAME]?->ensureTables();
        $tokens = KitDesigner::tokens();
        $def = KitDesigner::defaults();

        echo "Farbräume\n";
        $round = true;
        foreach (['#4338CA', '#FFFFFF', '#000000', '#0F766E', '#FDE68A', '#B45309', '#7F7F7F'] as $hex) {
            [$l, $c, $h] = Palette::oklch($hex);
            if (Palette::fromOklch($l, $c, $h) !== $hex) $round = false;
        }
        self::t($round, 'OKLCH hin und zurück verlustfrei (7 Farben)');
        self::t(Palette::hex('#abc') === '#AABBCC' && Palette::hex('0f766eff') === '#0F766E' && Palette::hex('rot') === null, 'Hex-Werte: Kurzform, Alpha, Unsinn');
        $out = Palette::fromOklch(0.7, 0.4, 145);
        self::t(Palette::hex($out) !== null, 'außerhalb von sRGB: Buntheit begrenzt (' . $out . ')');

        echo "Kontrastprüfung\n";
        $checks = Palette::checks($def, $tokens);
        self::t(count($checks) >= 20, 'Prüfpaare aus fluid hell und dunkel', (string) count($checks));
        self::t(!Palette::failures($def, $tokens), 'Standard von fluid besteht AA', json_encode(Palette::failures($def, $tokens)) ?: '');
        $ink = array_values(array_filter($checks, fn($c) => $c['token'] === 'ink' && $c['mode'] === 'light'))[0] ?? null;
        self::t($ink !== null && $ink['min'] === 4.5 && $ink['recommended'] === 7.0, 'Überschriften: AA 4,5 verlangt, Empfehlung des Kits 7 bleibt Hinweis');
        $sec = array_values(array_filter($checks, fn($c) => $c['token'] === 'secondary'))[0] ?? null;
        self::t($sec !== null && $sec['min'] === 3.0 && $sec['kind'] === 'ui', 'Zweite Markenfarbe: Grafik/Bedienelement 3 : 1');
        $bad = ['accent' => '#9DA8F5'] + $def;
        $f = Palette::failures($bad, $tokens);
        self::t(in_array('accent', array_column($f, 'token'), true), 'zu heller Akzent fällt durch');
        $fixed = Palette::fix('#9DA8F5', '#FFFFFF', 4.5);
        [$h1] = [Palette::oklch('#9DA8F5')[2]];
        self::t(Contrast::ratio($fixed, '#FFFFFF') >= 4.5 && abs(Palette::oklch($fixed)[2] - $h1) < 8, 'Beheben: ' . $fixed . ' erreicht 4,5 : 1, Farbton bleibt');
        self::t(Palette::fix('#111111', '#FFFFFF', 4.5) === '#111111', 'Beheben ändert bestandene Farben nicht');

        echo "Dunkel-Vorschlag\n";
        $palettes = ['fluid' => $def, 'eigene' => ['accent' => '#B4232C', 'accent_strong' => '#8E1B22', 'secondary' => '#1F6F50', 'highlight' => '#FFE3A3',
            'background' => '#FFFDF8', 'surface' => '#F6F1E7', 'line' => '#E5DDCD', 'ink' => '#1E1A16', 'text' => '#3A342D', 'muted' => '#625A50', 'dark_section' => '#2B1D16'] + $def];
        foreach ($palettes as $label => $vals) {
            $dark = Palette::suggestDark($vals, $tokens);
            $merged = $vals;
            foreach ($dark as $k => $v) $merged[$k . '@dark'] = $v;
            $fails = array_filter(Palette::failures($merged, $tokens), fn($c) => $c['mode'] === 'dark');
            self::t(!$fails, "„{$label}“: alle Dunkel-Paare AA", implode(', ', array_map(fn($c) => $c['token'] . ' ' . $c['ratio'], $fails)));
            self::t(Contrast::luminance($dark['background'] ?? '#FFFFFF') < 0.03 && Contrast::luminance($dark['ink'] ?? '#000000') > 0.8, "„{$label}“: Grund dunkel, Überschriften hell");
            $accHue = Palette::oklch($vals['accent'])[2];
            self::t(abs(Palette::oklch($dark['accent'])[2] - $accHue) < 12, "„{$label}“: Akzent behält den Farbton");
        }
        $charts = array_filter(array_keys(Palette::suggestDark($def, $tokens)), fn($k) => !isset(Palette::ROLES[$k]));
        self::t(!$charts, 'Farben ohne Rolle (Diagramm-Reihen) behalten ihren Dunkel-Wert', implode(', ', $charts));
        $only = Palette::suggestDark($def, $tokens, ['accent']);
        self::t(array_keys($only) === ['accent'], 'Vorschlag nur für gewählte Farben');

        echo "Tokens (W3C DTCG)\n";
        $values = KitDesigner::normalize(['accent' => '#0F5E63', 'accent@dark' => '#7FD3CF', 'font_head' => 'fraunces', 'radius' => 6, 'buttons' => 'pill', 'header' => 'centered', 'wrap' => 70] + $def);
        $draft = ['id' => 0, 'name' => 'kd-test', 'label' => 'Test', 'version' => 3, 'data' => ['values' => $values, 'extra' => ['line_height' => 1.55], 'meta' => []]];
        $json = Tokens::export($draft);
        self::t(($json['color']['accent']['$type'] ?? '') === 'color' && ($json['color']['accent']['$value'] ?? '') === '#0F5E63', 'Export: color.accent mit $type/$value');
        self::t(($json['radius']['base']['$value'] ?? null) === ['value' => 6, 'unit' => 'px'], 'Export: Maß als {value, unit}');
        self::t(($json['font']['family']['heading']['$value'][0] ?? '') === 'Fraunces', 'Export: fontFamily als Liste');
        $back = Tokens::import(json_decode((string) json_encode($json), true));
        $re = KitDesigner::normalize(array_replace($def, $back['values']));
        $diff = array_keys(array_filter($values, fn($v, $k) => ($re[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH));
        self::t($back['own'] && !$diff, 'Import des eigenen Exports verlustfrei (alle Werte, hell + dunkel)', implode(', ', $diff));
        self::t(($back['extra']['line_height'] ?? null) == 1.55, 'Zeilenhöhe zurück');
        $file = KitDesigner::dir() . '/examples/tokens-studio-beispiel.json';
        $ts = Tokens::import((array) json_decode((string) file_get_contents($file), true));
        $v = $ts['values'];
        self::t(($v['accent'] ?? '') === '#0F5E63' && ($v['accent@dark'] ?? '') === '#7FD3CF', 'Tokens Studio: primary (Verweis) hell + Satz „dark“');
        self::t(($v['on_accent'] ?? '') === '#FFFFFF' && ($v['muted'] ?? '') === '#5B6166' && ($v['text'] ?? '') === '#2A2F33' && ($v['ink'] ?? '') === '#111518', 'Tokens Studio: on-primary, text.muted, text.default, heading');
        self::t(($v['surface'] ?? '') === '#FBF6EE' && ($v['line'] ?? '') === '#F1E8DA', 'Tokens Studio: surface, border');
        self::t(($v['font_head'] ?? '') === 'fraunces' && ($v['font_body'] ?? '') === 'source-sans', 'Tokens Studio: Schriften Fraunces, Source Sans 3');
        self::t(($v['heading_weight'] ?? 0) === 600 && (float) ($v['radius'] ?? 0) === 6.0 && (float) ($v['fs_max'] ?? 0) === 18.0, 'Tokens Studio: SemiBold, Radius, Grundschrift');
        self::t(($ts['extra']['line_height'] ?? null) == 1.55, 'Tokens Studio: Zeilenhöhe 155 %');
        self::t(in_array('global.colors.teal.700', $ts['ignored'], true), 'Paletten-Stufen ohne Bedeutung werden genannt, nicht zugeordnet');
        $unknown = Tokens::import(['font' => ['body' => ['$type' => 'fontFamily', '$value' => 'Gibtsnicht Sans']]]);
        self::t(count($unknown['warnings']) === 1, 'unbekannte Schrift: Hinweis');

        echo "Entwürfe und Versionen\n";
        $id = 0;
        $kitName = 'kdtest-' . bin2hex(random_bytes(3));
        try {
            $id = Drafts::create('Selbsttest Kit', $kitName, ['values' => $values, 'extra' => ['line_height' => 1.55], 'auto' => ['accent' => true, 'gibtsnicht' => true]]);
            $d = Drafts::find($id);
            self::t($d !== null && $d['name'] === $kitName && $d['version'] === 1, 'Anlegen: Name, Version 1');
            self::t(($d['data']['auto'] ?? []) === ['accent' => true], 'Auto-Markierungen nur für Farben mit Dunkel-Wert');
            $v2 = Drafts::save($id, 'Selbsttest Kit', null, ['values' => ['radius' => 14] + $values, 'extra' => ['line_height' => 1.55]], 'Radius');
            self::t($v2 === 2 && count(Drafts::versions($id)) === 2, 'Speichern legt Version 2 an');
            self::t((float) (Drafts::version($id, 1)['data']['values']['radius'] ?? 0) === 6.0, 'Version 1 bleibt abrufbar');
            $d = (array) Drafts::find($id);

            echo "Export-Sperre\n";
            $blocked = ['data' => ['values' => ['on_accent' => '#7F7FFF'] + $values]] + $d;
            self::t((bool) Exporter::blockers($blocked), 'Kontrastmangel sperrt den Export');
            self::t(!Exporter::blockers($d), 'Entwurf ohne Mängel ist frei');
            $strong = Palette::strongFrom('#0F5E63');
            self::t(Contrast::ratio($strong, '#FFFFFF') > Contrast::ratio('#0F5E63', '#FFFFFF') && abs(Palette::oklch($strong)[2] - Palette::oklch('#0F5E63')[2]) < 6, '„Akzent kräftig“ aus dem Akzent: dunkler, gleicher Farbton');

            echo "Studio-Kit\n";
            $files = Exporter::studioFiles($d);
            self::t(isset($files['theme.php'], $files['composer.json'], $files['README.md'], $files['LICENSE'], $files['design-tokens.json'], $files['templates/LIESMICH.txt']), 'Dateien: theme.php, composer.json, README, LICENSE, design-tokens.json, templates/');
            self::t(isset($files['assets/css/site.css'], $files['public/css/site.css']) && str_contains($files['public/css/site.css'], 'line-height:1.55'), 'abweichende Zeilenhöhe als CSS (Quelle + fertig)');
            $dir = Exporter::writeTemp($files, $kitName);
            try {
                [$code] = \Core\Ffmpeg::exec([PHP_BINARY, '-l', $dir . '/theme.php'], 15);
                self::t($code === 0, 'theme.php: Syntax gültig');
                $child = (array) require $dir . '/theme.php';
                self::t(($child['extends'] ?? '') === 'fluid' && isset($child['design_defaults']['accent']['dark']), "extends fluid, design_defaults mit hell + dunkel");
                self::t(!isset($child['design_defaults']['ink']) && count($child['design_defaults']) < 12, 'nur abweichende Werte (' . count($child['design_defaults']) . ')');
                $merged = Theme::mergeDefinition(KitDesigner::parentTheme()->def, $child);
                $got = [];
                foreach ($merged['design']['groups'] as $g) foreach ($g['tokens'] as $t) if (!empty($t['name'])) {
                    $got[$t['name']] = $t['default'] ?? null;
                    if (($t['type'] ?? '') === 'color' && isset($t['dark'])) $got[$t['name'] . '@dark'] = $t['dark'];
                }
                $want = KitDesigner::normalize((array) $d['data']['values']);
                $miss = array_keys(array_filter($want, fn($v, $k) => array_key_exists($k, $got) && $got[$k] != $v, ARRAY_FILTER_USE_BOTH));
                self::t(!$miss, 'zusammengeführt mit fluid = Werte des Entwurfs', implode(', ', $miss));
                $chk = KitPackages::check($dir);
                self::t(!$chk['errors'] && !$chk['warnings'], 'KitPackages::check ohne Fehler und Warnungen', implode(' ', array_merge($chk['errors'], $chk['warnings'])));
            } finally {
                Exporter::rmdir(dirname($dir));
            }
            $zip = Exporter::zip($files, $kitName);
            self::t(str_starts_with($zip, "PK\x03\x04"), 'ZIP erzeugt (' . strlen($zip) . ' Bytes)');

            if (!in_array('--no-install', $args, true)) {
                echo "Installation (storage/kits) + kit:check\n";
                $res = Exporter::installStudio($d, 'selftest');
                self::t($res['ok'], 'installiert', $res['message']);
                if ($res['ok']) {
                    try {
                        Kit::reset();
                        $theme = new Theme($kitName);
                        self::t(array_keys($theme->chain) === [$kitName, 'fluid'], 'Kette: Kit → fluid');
                        $warn = array_filter(KitCheck::run($theme), fn($c) => $c['level'] === 'warn');
                        self::t(!$warn, 'kit:check ohne Warnung', implode(' | ', array_map(fn($c) => $c['text'], $warn)));
                        $app = app();
                        $saved = $app->theme;
                        $app->theme = $theme;
                        try {
                            $dv = Design::defaults();
                        } finally {
                            Design::override(null);
                            $app->theme = $saved;
                        }
                        $miss = array_keys(array_filter($want, fn($v, $k) => ($dv[$k] ?? null) != $v, ARRAY_FILTER_USE_BOTH));
                        self::t(!$miss, 'Standardwerte des installierten Kits = Entwurf', implode(', ', $miss));
                        self::t(is_file(Kit::publicDir($kitName) . '/css/site.css'), 'fertiges CSS veröffentlicht');
                        $again = Exporter::installStudio($d, 'selftest');
                        self::t($again['ok'], 'erneut exportieren ersetzt das eigene Kit', $again['message']);
                    } finally {
                        $rm = KitPackages::remove($kitName);
                        self::t($rm['ok'], 'Test-Kit entfernt', $rm['message']);
                    }
                }
            }

            echo "REDAXO fluid\n";
            $rx = Exporter::redaxoFiles($d, 'App');
            $cls = Exporter::className($kitName);
            $base = 'src/Kit/' . $cls . '/';
            self::t(isset($rx[$base . $cls . 'Kit.php'], $rx[$base . 'kit.php'], $rx[$base . 'assets/css/kit.css']), "Aufbau src/Kit/$cls/ wie fluid:kit:create");
            self::t(str_contains($rx[$base . $cls . 'Kit.php'], "namespace App\\Kit\\$cls;") && str_contains($rx[$base . $cls . 'Kit.php'], "#[AsKit('$kitName', 'Selbsttest Kit', parent: 'fluid')]"), 'Kit-Klasse mit #[AsKit] und Namensraum');
            self::t(Exporter::namespace('app\\x;') === 'Project' && Exporter::namespace('Acme\\Web') === 'Acme\\Web', 'Namensraum geprüft');
            $rdir = Exporter::writeTemp($rx, 'rx');
            try {
                $lint = true;
                foreach ([$base . $cls . 'Kit.php', $base . 'kit.php'] as $p) { [$code] = \Core\Ffmpeg::exec([PHP_BINARY, '-l', $rdir . '/' . $p], 15); $lint = $lint && $code === 0; }
                self::t($lint, 'PHP-Dateien: Syntax gültig');
                $parent = ['design' => KitDesigner::parentTheme()->def['design']];
                $kit = (static fn(array $parent, string $kitFile): array => (array) require $kitFile)($parent, $rdir . '/' . $base . 'kit.php');
                $acc = null;
                foreach ($kit['design']['groups'] as $g) foreach ($g['tokens'] as $t) if (($t['name'] ?? '') === 'accent') $acc = $t;
                self::t(($acc['default'] ?? '') === '#0F5E63' && ($acc['dark'] ?? '') === '#7FD3CF' && ($kit['css'] ?? []) === ['css/kit.css'], 'kit.php legt Standardwerte über $parent[\'design\']');
                self::t(str_contains($rx[$base . 'assets/css/kit.css'], 'line-height:1.55'), 'kit.css mit abweichender Zeilenhöhe');
            } finally {
                Exporter::rmdir(dirname($rdir));
            }
        } catch (\Throwable $e) {
            self::t(false, 'Ablauf', $e->getMessage() . ' @ ' . basename($e->getFile()) . ':' . $e->getLine());
        } finally {
            if ($id) Drafts::delete($id);
            self::t(!$id || Drafts::find($id) === null, 'Test-Entwurf entfernt');
        }

        echo "\n" . self::$ok . ' bestanden, ' . self::$fail . " fehlgeschlagen\n";
        return self::$fail ? 1 : 0;
    }
}
