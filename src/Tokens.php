<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Kit-Designer. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\KitDesigner;

/**
 * Design-Tokens im Format der W3C Design Tokens Community Group (DTCG, https://tr.designtokens.org/format/) – Import und Export.
 *
 * Export: Gruppen color, font, radius, size mit $type/$value; Dunkel-Werte und die Zuordnung zum Kit unter
 * $extensions["de.klxm.studio"] (DTCG kennt noch keine Modi). Auswahl-Werte des Kits (Buttons, Karten, Kopf …) stehen
 * im $extensions der Wurzel – andere Werkzeuge ignorieren sie, der Kit-Designer liest sie zurück.
 *
 * Import: eigene Exporte vollständig; fremde Dateien (Tokens Studio für Figma, Style Dictionary, Figma Variables als DTCG) über
 * Namen: semantische Tokens wie color.primary, color.text.muted, color.background, borderRadius.md, fontFamilies.heading,
 * typography.body. Auch das ältere Tokens-Studio-Format (value/type ohne $), Verweise {group.token} und Sätze „light“/„dark“
 * (Tokens Studio: ein Satz je Modus). Paletten-Stufen ohne Bedeutung (blue.500) ordnet der Import nicht zu – er nennt sie.
 */
final class Tokens
{
    public const EXT = 'de.klxm.studio';

    /** Namen fremder Tokens → Farb-Token des Kits (klein, ohne Trenner; zuerst zweiteilige Namen wie „text.muted“) */
    public const COLOR_ALIASES = [
        'accent' => ['accent', 'primary', 'brand', 'brandprimary', 'colorprimary', 'primarydefault', 'accentdefault', 'primarybase', 'interactive'],
        'accent_strong' => ['accentstrong', 'accenthover', 'primaryhover', 'primarystrong', 'primaryemphasis', 'branddark', 'primarydark', 'primaryactive'],
        'on_accent' => ['onaccent', 'onprimary', 'primaryforeground', 'primarycontrast', 'accentforeground', 'accentcontrast', 'textonprimary', 'textonaccent', 'onbrand', 'primarytext', 'primaryon'],
        'highlight' => ['highlight', 'marker', 'mark', 'accentsoft', 'primarysoft', 'primarysubtle', 'accentsubtle'],
        'secondary' => ['secondary', 'brandsecondary', 'colorsecondary', 'secondarydefault'],
        'on_secondary' => ['onsecondary', 'secondaryforeground', 'secondarycontrast', 'textonsecondary', 'secondarytext'],
        'ink' => ['ink', 'heading', 'headings', 'textheading', 'textstrong', 'texthigh', 'textemphasis', 'foregroundstrong', 'title', 'texttitle'],
        'text' => ['text', 'body', 'foreground', 'fg', 'textdefault', 'textbody', 'textprimary', 'bodytext', 'onbackground', 'onsurface', 'textbase'],
        'muted' => ['muted', 'textmuted', 'textsecondary', 'textsubtle', 'subtle', 'mutedforeground', 'textweak', 'textlow', 'foregroundmuted', 'textmeta'],
        'background' => ['background', 'bg', 'canvas', 'page', 'backgrounddefault', 'backgroundbase', 'backgroundpage', 'surfacepage', 'bgdefault', 'bgbase'],
        'surface' => ['surface', 'card', 'panel', 'backgroundsubtle', 'backgroundmuted', 'surfacesubtle', 'surfacedefault', 'bgsubtle', 'bgmuted', 'backgroundsecondary', 'surfacealt'],
        'line' => ['line', 'border', 'divider', 'stroke', 'borderdefault', 'outline', 'separator', 'bordersubtle'],
        'dark_section' => ['darksection', 'inverse', 'backgroundinverse', 'surfaceinverse', 'bginverse', 'backgroundstrong'],
        'color3' => ['color3', 'tertiary'],
        'color4' => ['color4', 'quaternary'],
        'color5' => ['color5'],
    ];
    /** Sätze, die für ein Farbschema stehen (Tokens Studio, Figma Variables) */
    public const DARK_SETS = ['dark', 'darkmode', 'modedark', 'themedark', 'night'];
    public const LIGHT_SETS = ['light', 'lightmode', 'modelight', 'themelight', 'global', 'core', 'base', 'default', 'semantic', 'brand', 'tokens'];

    // ------------------------------------------------------------------ Export

    /**
     * @param array $draft Entwurf (Drafts::find)
     * @return array JSON-taugliche DTCG-Datei
     */
    public static function export(array $draft): array
    {
        $values = KitDesigner::normalize((array) ($draft['data']['values'] ?? []));
        $extra = KitDesigner::normalizeExtra((array) ($draft['data']['extra'] ?? []));
        $tokens = KitDesigner::tokens();
        $fonts = KitDesigner::fonts();
        $ext = fn(array $x) => ['$extensions' => [self::EXT => $x]];
        $color = [];
        foreach ($tokens as $n => $t) {
            if (($t['type'] ?? '') !== 'color') continue;
            $color[str_replace('_', '-', (string) $n)] = ['$type' => 'color', '$value' => $values[$n], '$description' => (string) ($t['label'] ?? $n)]
                + $ext(['token' => $n] + (isset($values[$n . '@dark']) ? ['dark' => $values[$n . '@dark']] : []));
        }
        $family = [];
        foreach (['font_body' => 'body', 'font_head' => 'heading', 'font_mono' => 'mono'] as $n => $key) {
            if (!isset($tokens[$n])) continue;
            $stack = (string) ($fonts[$values[$n]]['stack'] ?? 'system-ui');
            $family[$key] = ['$type' => 'fontFamily', '$value' => self::stackList($stack), '$description' => (string) ($fonts[$values[$n]]['label'] ?? $values[$n])]
                + $ext(['token' => $n, 'font' => $values[$n]]);
        }
        $dim = fn(float $v, string $unit) => ['value' => $v == floor($v) ? (int) $v : $v, 'unit' => $unit];
        $out = [
            '$description' => sprintf('Design-Tokens „%s“ – Kit-Designer (KLXM Studio), Eltern-Kit %s', (string) $draft['label'], KitDesigner::PARENT),
            'color' => $color,
            'font' => [
                'family' => $family,
                'weight' => ['heading' => ['$type' => 'fontWeight', '$value' => (int) $values['heading_weight']] + $ext(['token' => 'heading_weight'])],
                'size' => [
                    'base-min' => ['$type' => 'dimension', '$value' => $dim((float) $values['fs_min'], 'px'), '$description' => 'Grundschrift auf kleinen Bildschirmen'] + $ext(['token' => 'fs_min']),
                    'base-max' => ['$type' => 'dimension', '$value' => $dim((float) $values['fs_max'], 'px'), '$description' => 'Grundschrift auf großen Bildschirmen'] + $ext(['token' => 'fs_max']),
                ],
                'scale' => ['$type' => 'number', '$value' => (float) $values['ratio'], '$description' => 'Verhältnis der Schriftstufen'] + $ext(['token' => 'ratio']),
                'line-height' => ['$type' => 'number', '$value' => $extra['line_height'], '$description' => 'Zeilenhöhe des Fließtexts'] + $ext(['extra' => 'line_height']),
                'tracking-heading' => ['$type' => 'dimension', '$value' => $dim(round((float) $values['heading_tracking'] / 100, 4), 'em')] + $ext(['token' => 'heading_tracking']),
            ],
            'radius' => ['base' => ['$type' => 'dimension', '$value' => $dim((float) $values['radius'], 'px')] + $ext(['token' => 'radius'])],
            'size' => ['content-width' => ['$type' => 'dimension', '$value' => $dim((float) $values['wrap'], 'rem'), '$description' => 'Maximale Inhaltsbreite'] + $ext(['token' => 'wrap'])],
        ];
        // Übrige Werte des Kits (Auswahl, Schalter, Zahlen ohne eigenes Token oben)
        $done = ['heading_weight', 'fs_min', 'fs_max', 'ratio', 'heading_tracking', 'radius', 'wrap', 'font_body', 'font_head', 'font_mono'];
        $rest = [];
        foreach ($tokens as $n => $t) {
            if (($t['type'] ?? '') === 'color' || in_array($n, $done, true)) continue;
            $rest[$n] = $values[$n] ?? null;
        }
        $out['$extensions'] = [self::EXT => ['format' => 1, 'kit' => (string) $draft['name'], 'label' => (string) $draft['label'], 'parent' => KitDesigner::PARENT,
            'version' => (int) $draft['version'], 'values' => $rest]];
        return $out;
    }

    /** Schrift-Stapel „Inter, ui-sans-serif, …“ → ['Inter', 'ui-sans-serif', …] */
    public static function stackList(string $stack): array
    {
        $out = [];
        foreach (str_getcsv($stack, ',', '"', '\\') as $p) {
            $p = trim(trim((string) $p), "'\" ");
            if ($p !== '') $out[] = $p;
        }
        return $out;
    }

    // ------------------------------------------------------------------ Import

    /**
     * DTCG-/Tokens-Studio-JSON lesen und auf die Werte des Kits abbilden.
     * @return array{values: array, extra: array, mapped: list<array{path: string, token: string, value: string}>, ignored: list<string>, warnings: list<string>, own: bool}
     */
    public static function import(array $json): array
    {
        $tokens = KitDesigner::tokens();
        $fonts = KitDesigner::fonts();
        $flat = [];
        self::flatten($json, [], null, $flat);
        $resolved = [];
        foreach ($flat as $path => $t) $resolved[$path] = ['type' => $t['type'], 'value' => self::resolve($t['value'], $flat), 'ext' => $t['ext']];

        $values = $extra = $mapped = $warnings = [];
        $ignored = [];
        $own = isset($json['$extensions'][self::EXT]);
        $put = function (string $token, mixed $v, string $path) use (&$values, &$mapped): void {
            if (array_key_exists($token, $values)) return;   // erster Treffer gewinnt
            $values[$token] = $v;
            $mapped[] = ['path' => $path, 'token' => $token, 'value' => is_scalar($v) ? (string) $v : json_encode($v)];
        };

        // 1. Eigener Export: Zuordnung über $extensions
        foreach ($resolved as $path => $t) {
            $x = $t['ext'];
            if (!$x) continue;
            if (!empty($x['token']) && isset($tokens[$x['token']])) {
                $n = (string) $x['token'];
                $v = match ($tokens[$n]['type'] ?? '') {
                    'color' => Palette::hex((string) self::scalar($t['value'])),
                    'font' => (string) ($x['font'] ?? '') !== '' && isset($fonts[$x['font']]) ? (string) $x['font'] : self::fontKey($t['value'], $fonts),
                    default => self::number($t['value'], $n === 'heading_tracking' ? 'em100' : ($n === 'wrap' ? 'rem' : 'px')),
                };
                if ($v !== null) $put($n, $v, $path);
                if (!empty($x['dark']) && ($d = Palette::hex((string) $x['dark']))) $values[$n . '@dark'] = $d;
            } elseif (($x['extra'] ?? '') === 'line_height' && ($v = self::number($t['value'], 'ratio')) !== null) {
                $extra['line_height'] = $v;
                $mapped[] = ['path' => $path, 'token' => 'line_height', 'value' => (string) $v];
            }
        }
        if ($own) foreach ((array) ($json['$extensions'][self::EXT]['values'] ?? []) as $n => $v) {
            if (isset($tokens[$n]) && !array_key_exists($n, $values) && is_scalar($v)) $put((string) $n, $v, '$extensions.values.' . $n);
        }

        // 2. Fremde Dateien: nach Namen
        foreach ($resolved as $path => $t) {
            if ($t['ext'] && !empty($t['ext']['token'])) continue;
            $seg = array_values(array_filter(explode('.', $path), fn($s) => $s !== ''));
            $mode = 'light';
            $first = self::norm($seg[0] ?? '');
            if (in_array($first, self::DARK_SETS, true)) { $mode = 'dark'; array_shift($seg); }
            elseif (in_array($first, self::LIGHT_SETS, true) && count($seg) > 1) array_shift($seg);
            $last = self::norm((string) end($seg));
            if (in_array($last, ['dark', 'light', 'default'], true) && count($seg) > 1) {
                if ($last === 'dark') $mode = 'dark';
                array_pop($seg);
            }
            $type = strtolower((string) $t['type']);
            $v = $t['value'];
            $hit = false;
            if ($type === 'color' || (is_string($v) && Palette::hex($v) !== null && $type === '')) {
                $hex = Palette::hex((string) self::scalar($v));
                $tok = $hex !== null ? self::colorToken($seg) : null;
                if ($tok !== null && isset($tokens[$tok])) {
                    if ($mode === 'dark') {
                        if (!isset($values[$tok . '@dark'])) { $values[$tok . '@dark'] = $hex; $mapped[] = ['path' => $path, 'token' => $tok . '@dark', 'value' => $hex]; }
                    } else {
                        $put($tok, $hex, $path);
                    }
                    $hit = true;
                }
            } elseif ($type === 'typography' && is_array($v)) {
                $role = self::fontRole($seg);
                if ($role !== null) {
                    $hit = true;
                    if (isset($v['fontFamily']) && ($k = self::fontKey($v['fontFamily'], $fonts)) !== null) $put($role === 'head' ? 'font_head' : ($role === 'mono' ? 'font_mono' : 'font_body'), $k, $path);
                    elseif (isset($v['fontFamily'])) $warnings[] = __('Schrift „{font}“ ({path}) gibt es hier nicht – bitte im Schriften-Manager installieren oder eine andere wählen.', ['font' => self::firstFamily($v['fontFamily']), 'path' => $path]);
                    if ($role === 'head' && isset($v['fontWeight']) && ($w = self::weight($v['fontWeight'])) !== null) $put('heading_weight', $w, $path);
                    if ($role === 'body' && isset($v['fontSize']) && ($px = self::number($v['fontSize'], 'px')) !== null) self::baseSize($px, $put, $path);
                    if ($role === 'body' && isset($v['lineHeight']) && ($lh = self::number($v['lineHeight'], 'ratio')) !== null && !isset($extra['line_height'])) {
                        $extra['line_height'] = $lh;
                        $mapped[] = ['path' => $path, 'token' => 'line_height', 'value' => (string) $lh];
                    }
                }
            } else {
                $joined = self::norm(implode('', $seg));
                if ($type === 'fontfamily' || $type === 'fontfamilies' || str_contains($joined, 'fontfamil')) {
                    $role = self::fontRole($seg);
                    if ($role !== null) {
                        $hit = true;
                        $k = self::fontKey($v, $fonts);
                        if ($k !== null) $put($role === 'head' ? 'font_head' : ($role === 'mono' ? 'font_mono' : 'font_body'), $k, $path);
                        else $warnings[] = __('Schrift „{font}“ ({path}) gibt es hier nicht – bitte im Schriften-Manager installieren oder eine andere wählen.', ['font' => self::firstFamily($v), 'path' => $path]);
                    }
                } elseif ($type === 'fontweight' || $type === 'fontweights' || str_contains($joined, 'fontweight')) {
                    if (self::fontRole($seg) === 'head' && ($w = self::weight($v)) !== null) { $put('heading_weight', $w, $path); $hit = true; }
                } elseif (preg_match('~(borderradius|radius|rounded|corner)~', $joined)) {
                    if (preg_match('~(base|default|md|medium|m$|radius$|rounded$|card|control)~', $joined) && ($px = self::number($v, 'px')) !== null) { $put('radius', $px, $path); $hit = true; }
                } elseif (preg_match('~(lineheight|leading)~', $joined)) {
                    if (preg_match('~(body|base|default|text|normal|md)~', $joined) && ($lh = self::number($v, 'ratio')) !== null && !isset($extra['line_height'])) {
                        $extra['line_height'] = $lh;
                        $mapped[] = ['path' => $path, 'token' => 'line_height', 'value' => (string) $lh];
                        $hit = true;
                    }
                } elseif (preg_match('~(fontsize|fontsizes|textsize)~', $joined) || $type === 'fontsizes') {
                    if (preg_match('~(body|base|default|md|medium|text|normal)$~', $joined) && ($px = self::number($v, 'px')) !== null) { self::baseSize($px, $put, $path); $hit = true; }
                } elseif (preg_match('~(scale|ratio)~', $joined) && is_numeric(self::scalar($v)) && (float) self::scalar($v) > 1 && (float) self::scalar($v) < 2) {
                    $put('ratio', (float) self::scalar($v), $path);
                    $hit = true;
                } elseif (preg_match('~(contentwidth|container|maxwidth|wrap|layoutwidth)~', $joined) && ($rem = self::number($v, 'rem')) !== null) {
                    $put('wrap', $rem, $path);
                    $hit = true;
                }
            }
            if (!$hit) $ignored[] = $path;
        }
        return ['values' => $values, 'extra' => $extra, 'mapped' => $mapped, 'ignored' => $ignored, 'warnings' => array_values(array_unique($warnings)), 'own' => $own];
    }

    /** Baum → [pfad => [type, value, ext]]; $type erbt von Gruppen ($type bzw. type) */
    private static function flatten(array $node, array $path, ?string $type, array &$out): void
    {
        $type = isset($node['$type']) && is_string($node['$type']) ? $node['$type'] : $type;
        // Token: DTCG „$value“ oder älteres Tokens-Studio-Format „value“ (mit „type“ oder einfachem Wert)
        $legacy = array_key_exists('value', $node) && ((isset($node['type']) && is_string($node['type'])) || !is_array($node['value']));
        if (array_key_exists('$value', $node) || $legacy) {
            $out[implode('.', $path)] = ['type' => (string) ($node['$type'] ?? $node['type'] ?? $type), 'value' => $node['$value'] ?? $node['value'],
                'ext' => (array) ($node['$extensions'][self::EXT] ?? [])];
            return;
        }
        foreach ($node as $k => $v) {
            if (!is_array($v) || str_starts_with((string) $k, '$')) continue;
            self::flatten($v, [...$path, (string) $k], $type, $out);
        }
    }

    /** Verweise {a.b.c} auflösen (höchstens 8 Ebenen) */
    private static function resolve(mixed $v, array $flat, int $depth = 0): mixed
    {
        if (is_string($v) && preg_match('~^\{([^}]+)\}$~', trim($v), $m) && $depth < 8) {
            $ref = $flat[$m[1]] ?? null;
            if ($ref === null) {
                // Tokens Studio: Verweis ohne Satz-Namen → in allen Sätzen suchen
                foreach ($flat as $p => $t) if (str_ends_with($p, '.' . $m[1])) { $ref = $t; break; }
            }
            return $ref !== null ? self::resolve($ref['value'], $flat, $depth + 1) : null;
        }
        if (is_array($v)) return array_map(fn($x) => self::resolve($x, $flat, $depth + 1), $v);
        return $v;
    }

    private static function norm(string $s): string
    {
        return (string) preg_replace('~[^a-z0-9]~', '', strtolower($s));
    }

    /** Farb-Token zu einem Pfad (zweiteilig vor einteilig, Gruppen wie „color“ überspringen) */
    private static function colorToken(array $seg): ?string
    {
        $seg = array_values(array_filter(array_map([self::class, 'norm'], $seg), fn($s) => !in_array($s, ['color', 'colors', 'colour', 'semantic', 'sys', 'ref', 'theme', 'palette', 'brandcolors'], true) && $s !== ''));
        if (!$seg) return null;
        $cands = [];
        if (count($seg) >= 2) $cands[] = $seg[count($seg) - 2] . $seg[count($seg) - 1];
        $cands[] = $seg[count($seg) - 1];
        foreach ($cands as $c) foreach (self::COLOR_ALIASES as $tok => $names) if (in_array($c, $names, true)) return $tok;
        return null;
    }

    private static function fontRole(array $seg): ?string
    {
        $s = self::norm(implode('.', $seg));
        if (preg_match('~(mono|code)~', $s)) return 'mono';
        if (preg_match('~(heading|head|display|title|headline)~', $s)) return 'head';
        if (preg_match('~(body|text|base|sans|default|paragraph|copy|primary)~', $s)) return 'body';
        return null;
    }

    private static function firstFamily(mixed $v): string
    {
        $list = is_array($v) ? array_values($v) : self::stackList((string) $v);
        return trim((string) ($list[0] ?? ''), "'\" ");
    }

    /** Schrift-Schlüssel des Kits zur ersten Familie eines Stapels (Kit-Schriften und installierte) */
    public static function fontKey(mixed $v, array $fonts): ?string
    {
        $want = self::norm(self::firstFamily($v));
        if ($want === '') return null;
        foreach ($fonts as $k => $f) {
            if (!empty($f['dup'])) continue;
            $fam = self::norm(self::firstFamily((string) ($f['stack'] ?? '')));
            if ($fam === $want || self::norm((string) $k) === $want) return (string) $k;
        }
        if (in_array($want, ['systemui', 'uisansserif', 'sansserif', 'applesystem'], true) && isset($fonts['system'])) return 'system';
        return null;
    }

    private static function weight(mixed $v): ?int
    {
        $v = self::scalar($v);
        if (is_numeric($v)) return (int) $v;
        $map = ['thin' => 100, 'extralight' => 200, 'light' => 300, 'regular' => 400, 'normal' => 400, 'medium' => 500, 'semibold' => 600, 'bold' => 700, 'extrabold' => 800, 'black' => 900];
        return $map[self::norm((string) $v)] ?? null;
    }

    private static function scalar(mixed $v): mixed
    {
        if (is_array($v) && array_key_exists('value', $v)) return $v['value'];
        return $v;
    }

    /**
     * Zahl aus DTCG-Maß ({value, unit}), String („16px“, „1rem“, „150%“) oder Zahl. $want: px | rem | ratio | em100 (1/100 em).
     */
    private static function number(mixed $v, string $want): ?float
    {
        $unit = '';
        if (is_array($v) && isset($v['value'])) { $unit = (string) ($v['unit'] ?? ''); $v = $v['value']; }
        if (is_string($v) && preg_match('~^\s*(-?[\d.]+)\s*(px|rem|em|%)?\s*$~', $v, $m)) { $v = (float) $m[1]; $unit = $m[2] ?? $unit; }
        if (!is_numeric($v)) return null;
        $n = (float) $v;
        return match ($want) {
            'px' => $unit === 'rem' || $unit === 'em' ? $n * 16 : $n,
            'rem' => $unit === 'px' || ($unit === '' && $n > 200) ? $n / 16 : $n,
            'ratio' => $unit === '%' ? $n / 100 : ($unit === 'px' || $n > 4 ? null : $n),
            'em100' => $unit === 'em' || ($unit === '' && abs($n) < 0.2) ? $n * 100 : $n,
            default => $n,
        };
    }

    /** Grundschrift: großer Bildschirm = Wert, kleiner = 90 % (auf 0,5 px), höchstens wie der große */
    private static function baseSize(float $px, callable $put, string $path): void
    {
        $put('fs_max', $px, $path);
        $put('fs_min', min($px, max(14, round($px * 0.9 * 2) / 2)), $path);
    }
}
