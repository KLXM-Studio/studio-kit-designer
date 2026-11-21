<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Kit-Designer. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\KitDesigner;

use Core\Contrast;

/**
 * Farben des Entwurfs: Kontrastprüfung nach WCAG 2.2 AA, Vorschlag der Dunkel-Fassung, Beheben einzelner Paare.
 *
 * Rechnung im Farbraum OKLCH (Helligkeit L 0–1, Buntheit C, Farbton h): Helligkeit lässt sich darin verschieben, ohne dass der
 * Farbton kippt – „dasselbe Blau, nur heller“. Kontrast nach WCAG 2.x über Core\Contrast (wie der Style-Editor des Cores).
 *
 * Prüfpaare kommen aus dem Eltern-Kit (Token-Angabe 'contrast' => ['with' => …, 'min' => …]). Für den Export gilt AA:
 * Schrift mindestens 4,5 : 1, große Schrift, Bedienelemente und Grafik mindestens 3 : 1. Strengere Empfehlungen des Kits
 * (z. B. Überschriften 7 : 1 in fluid) erscheinen als Hinweis, sperren den Export aber nicht.
 */
final class Palette
{
    public const AA_TEXT = 4.5;
    public const AA_LARGE = 3.0;

    /**
     * Rollen der Farb-Tokens von fluid für den Dunkel-Vorschlag. Tokens ohne Rolle (Diagramm-Reihen …) schlägt er nicht vor.
     * bg/surface/line/band: Flächen · ink/text/muted: Schrift · brand: Marken- und Grafikfarben · on: Schrift auf Marke · tint: Hervorhebungsfläche
     */
    public const ROLES = [
        'background' => 'bg', 'surface' => 'surface', 'line' => 'line', 'dark_section' => 'band',
        'ink' => 'ink', 'text' => 'text', 'muted' => 'muted',
        'accent' => 'brand', 'accent_strong' => 'strong', 'secondary' => 'brand', 'color3' => 'brand', 'color4' => 'brand', 'color5' => 'brand',
        'on_accent' => 'on', 'on_secondary' => 'on', 'highlight' => 'tint',
    ];
    /** Partner der Schrift auf Markenfarben */
    public const ON = ['on_accent' => 'accent', 'on_secondary' => 'secondary', 'accent_strong' => 'accent'];

    // ------------------------------------------------------------------ Prüfung

    /**
     * Alle Prüfpaare für hell und dunkel.
     * @param array<string, mixed> $values Werte des Entwurfs (Token => Wert, Dunkel unter „token@dark“)
     * @param array<string, array> $tokens Token-Definitionen des Eltern-Kits (name => def)
     * @return list<array{token: string, label: string, mode: string, with: string, fg: string, bg: string, ratio: float, min: float, recommended: float, kind: string, ok: bool, strict: bool}>
     */
    public static function checks(array $values, array $tokens): array
    {
        $resolve = function (string $ref, bool $dark) use ($values, $tokens): ?string {
            if (isset($tokens[$ref])) return self::hex((string) ($dark ? ($values[$ref . '@dark'] ?? $values[$ref] ?? '') : ($values[$ref] ?? '')));
            return self::hex($ref);
        };
        $out = [];
        foreach ($tokens as $n => $t) {
            if (($t['type'] ?? '') !== 'color' || empty($t['contrast']['with'])) continue;
            $rec = (float) ($t['contrast']['min'] ?? self::AA_TEXT);
            $min = $rec >= self::AA_TEXT ? self::AA_TEXT : self::AA_LARGE;
            foreach (['light', 'dark'] as $mode) {
                $dark = $mode === 'dark';
                if ($dark && !isset($t['dark'])) continue;
                $with = $dark ? (string) ($t['contrast']['dark_with'] ?? $t['contrast']['with']) : (string) $t['contrast']['with'];
                $fg = $resolve((string) $n, $dark);
                $bg = $resolve($with, $dark);
                if ($fg === null || $bg === null) continue;
                $r = round(Contrast::ratio($fg, $bg), 2);
                $out[] = ['token' => (string) $n, 'label' => (string) ($t['label'] ?? $n), 'mode' => $mode, 'with' => $with,
                    'withLabel' => (string) ($tokens[$with]['label'] ?? $with), 'fg' => $fg, 'bg' => $bg, 'ratio' => $r, 'min' => $min,
                    'recommended' => $rec, 'kind' => $min >= self::AA_TEXT ? 'text' : 'ui', 'ok' => $r >= $min, 'strict' => $r >= $rec];
            }
        }
        return $out;
    }

    /** Nur die nicht bestandenen Paare (Sperre für den Export) */
    public static function failures(array $values, array $tokens): array
    {
        return array_values(array_filter(self::checks($values, $tokens), fn($c) => !$c['ok']));
    }

    // ------------------------------------------------------------------ Dunkel-Fassung

    /**
     * Dunkel-Fassung aus den hellen Farben ableiten: Flächen dunkel im Farbton des Hintergrunds, Schrift hell, Markenfarben
     * heller und etwas weniger bunt, Schrift auf Marke dunkel. Danach jedes Prüfpaar auf AA bringen (Helligkeit verschieben).
     * @param list<string>|null $only nur diese Tokens zurückgeben (andere bleiben wie in $values)
     * @return array<string, string> token => #RRGGBB (Dunkel-Wert)
     */
    public static function suggestDark(array $values, array $tokens, ?array $only = null): array
    {
        $colors = array_filter($tokens, fn($t) => ($t['type'] ?? '') === 'color' && isset($t['dark']));
        $light = fn(string $n): string => self::hex((string) ($values[$n] ?? $tokens[$n]['default'] ?? '#808080')) ?? '#808080';
        $bg = self::oklch($light('background'));
        $acc = self::oklch($light('accent'));
        // Grundton der dunklen Flächen: Farbton des Hintergrunds, bei neutralem Weiß ein Hauch vom Akzent
        [$hue, $chroma] = $bg[1] > 0.006 ? [$bg[2], min(0.025, $bg[1] * 1.4)] : [$acc[2], 0.012];
        $out = [];
        $set = function (string $n, float $l, float $c, float $h) use (&$out): void { $out[$n] = self::fromOklch($l, $c, $h); };
        foreach ($colors as $n => $t) {
            // Nur Farben mit bekannter Rolle – andere (z. B. Reihenfarben der Diagramme) behalten ihren Dunkel-Wert
            if (!isset(self::ROLES[$n])) continue;
            $src = self::oklch($light($n));
            switch (self::ROLES[$n]) {
                case 'bg': $set($n, 0.165, $chroma, $hue); break;
                case 'surface': $set($n, 0.215, $chroma * 1.15, $hue); break;
                case 'line': $set($n, 0.31, $chroma * 1.2, $hue); break;
                case 'band': $set($n, 0.255, max($chroma, min(0.05, $acc[1] * 0.35)), $acc[2]); break;
                case 'ink': $set($n, 0.965, min(0.012, $src[1]), $hue); break;
                case 'text': $set($n, 0.875, min(0.02, $src[1] + 0.004), $hue); break;
                case 'muted': $set($n, 0.735, min(0.025, $src[1] + 0.006), $hue); break;
                case 'brand': $set($n, max(0.79, min(0.9, $src[0] + 0.3)), $src[1] * 0.72, $src[2]); break;
                case 'tint': $set($n, 0.34, min(0.09, $src[1] * 0.6), $src[2]); break;
                default: break;   // strong, on: nach ihren Partnern
            }
        }
        foreach (self::ON as $n => $partner) {
            if (!isset($colors[$n])) continue;
            $p = self::oklch($out[$partner] ?? $light($partner));
            if ($n === 'accent_strong') $set($n, min(0.95, $p[0] + 0.07), $p[1] * 0.85, $p[2]);
            else $set($n, 0.24, min(0.06, $p[1] * 0.5), $p[2]);
        }
        // Paare auf AA bringen – Reihenfolge: erst Flächen, dann Schrift, dann Marken
        $merged = $values;
        foreach ($out as $n => $hex) $merged[$n . '@dark'] = $hex;
        for ($round = 0; $round < 3; $round++) {
            $changed = false;
            foreach (self::checks($merged, $tokens) as $c) {
                if ($c['mode'] !== 'dark' || $c['ok'] || !isset($out[$c['token']])) continue;
                $fixed = self::fix($c['fg'], $c['bg'], $c['min']);
                if ($fixed !== $out[$c['token']]) {
                    $out[$c['token']] = $fixed;
                    $merged[$c['token'] . '@dark'] = $fixed;
                    $changed = true;
                }
            }
            if (!$changed) break;
        }
        return $only !== null ? array_intersect_key($out, array_flip($only)) : $out;
    }

    /**
     * Farbe $fg so wenig wie möglich verändern, bis sie auf $bg $min erreicht (+ kleiner Puffer gegen Rundung): Helligkeit in
     * OKLCH von der Fläche weg verschieben, Farbton bleibt. Erreicht das Ende (Schwarz/Weiß) den Wert nicht, die andere Richtung.
     */
    public static function fix(string $fg, string $bg, float $min): string
    {
        $fg = self::hex($fg) ?? '#000000';
        $bg = self::hex($bg) ?? '#FFFFFF';
        if (Contrast::ratio($fg, $bg) >= $min) return $fg;
        [$l, $c, $h] = self::oklch($fg);
        $down = Contrast::luminance($bg) > 0.18;
        foreach ([$down, !$down] as $dir) {
            for ($i = 1; $i <= 200; $i++) {
                $nl = $dir ? $l - $i * 0.005 : $l + $i * 0.005;
                if ($nl < 0 || $nl > 1) break;
                $cand = self::fromOklch($nl, $c, $h);
                if (Contrast::ratio($cand, $bg) >= $min + 0.05) return $cand;
            }
        }
        return Contrast::ratio('#000000', $bg) >= Contrast::ratio('#FFFFFF', $bg) ? '#000000' : '#FFFFFF';
    }

    /** „Akzent kräftig“ (Hover) hell aus dem Akzent: etwas dunkler, gleicher Farbton */
    public static function strongFrom(string $accent): string
    {
        [$l, $c, $h] = self::oklch($accent);
        return self::fromOklch(max(0.05, $l - 0.08), $c * 1.02, $h);
    }

    // ------------------------------------------------------------------ Farbräume

    public static function hex(string $v): ?string
    {
        $s = strtoupper(trim($v));
        if (preg_match('~^#?([0-9A-F])([0-9A-F])([0-9A-F])$~', $s, $m)) $s = '#' . $m[1] . $m[1] . $m[2] . $m[2] . $m[3] . $m[3];
        if ($s !== '' && $s[0] !== '#') $s = '#' . $s;
        if (preg_match('~^#[0-9A-F]{8}$~', $s)) $s = substr($s, 0, 7);   // Alpha (Figma: #RRGGBBAA) fällt weg
        return preg_match('~^#[0-9A-F]{6}$~', $s) ? $s : null;
    }

    /** @return array{0: float, 1: float, 2: float} L (0–1), C, h (Grad) */
    public static function oklch(string $hex): array
    {
        [$r, $g, $b] = array_map(fn($x) => self::lin((float) $x), Contrast::rgb(self::hex($hex) ?? '#000000'));
        $l = 0.4122214708 * $r + 0.5363325363 * $g + 0.0514459929 * $b;
        $m = 0.2119034982 * $r + 0.6806995451 * $g + 0.1073969566 * $b;
        $s = 0.0883024619 * $r + 0.2817188376 * $g + 0.6299787005 * $b;
        [$l, $m, $s] = [self::cbrt($l), self::cbrt($m), self::cbrt($s)];
        $L = 0.2104542553 * $l + 0.7936177850 * $m - 0.0040720468 * $s;
        $A = 1.9779984951 * $l - 2.4285922050 * $m + 0.4505937099 * $s;
        $B = 0.0259040371 * $l + 0.7827717662 * $m - 0.8086757660 * $s;
        $C = sqrt($A * $A + $B * $B);
        $h = $C < 1e-6 ? 0.0 : fmod(rad2deg(atan2($B, $A)) + 360, 360);
        return [$L, $C, $h];
    }

    /** OKLCH → #RRGGBB; außerhalb von sRGB wird die Buntheit verringert (Farbton und Helligkeit bleiben) */
    public static function fromOklch(float $L, float $C, float $h): string
    {
        $L = max(0.0, min(1.0, $L));
        $C = max(0.0, $C);
        $rgb = self::oklabToRgb($L, $C, $h);
        if (!self::inGamut($rgb)) {
            [$lo, $hi] = [0.0, $C];
            for ($i = 0; $i < 24; $i++) {
                $mid = ($lo + $hi) / 2;
                if (self::inGamut(self::oklabToRgb($L, $mid, $h))) $lo = $mid; else $hi = $mid;
            }
            $rgb = self::oklabToRgb($L, $lo, $h);
        }
        return sprintf('#%02X%02X%02X', ...array_map(fn($x) => (int) round(max(0, min(1, self::gam($x))) * 255), $rgb));
    }

    /** @return array{0: float, 1: float, 2: float} lineares sRGB */
    private static function oklabToRgb(float $L, float $C, float $h): array
    {
        $a = $C * cos(deg2rad($h));
        $b = $C * sin(deg2rad($h));
        $l = ($L + 0.3963377774 * $a + 0.2158037573 * $b) ** 3;
        $m = ($L - 0.1055613458 * $a - 0.0638541728 * $b) ** 3;
        $s = ($L - 0.0894841775 * $a - 1.2914855480 * $b) ** 3;
        return [
            4.0767416621 * $l - 3.3077115913 * $m + 0.2309699292 * $s,
            -1.2684380046 * $l + 2.6097574011 * $m - 0.3413193965 * $s,
            -0.0041960863 * $l - 0.7034186147 * $m + 1.7076147010 * $s,
        ];
    }

    private static function inGamut(array $rgb): bool
    {
        foreach ($rgb as $x) if ($x < -0.0005 || $x > 1.0005) return false;
        return true;
    }

    private static function lin(float $c): float
    {
        return $c <= 0.04045 ? $c / 12.92 : (($c + 0.055) / 1.055) ** 2.4;
    }

    private static function gam(float $c): float
    {
        return $c <= 0.0031308 ? 12.92 * $c : 1.055 * ($c ** (1 / 2.4)) - 0.055;
    }

    private static function cbrt(float $x): float
    {
        return $x < 0 ? -((-$x) ** (1 / 3)) : $x ** (1 / 3);
    }
}
