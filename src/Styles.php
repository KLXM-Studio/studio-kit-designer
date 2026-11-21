<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Kit-Designer. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\KitDesigner;

use Core\Contrast;

/**
 * Stile einzelner Elemente, Blöcke und Abschnitte eines Entwurfs (Stufe 2) – die „Figma-artige“ Ebene über den Design-Tokens.
 *
 * Daten (Entwurf → data.styles):
 *   'scopes'   => [Geltung => [Element => [Zustand => [Eigenschaft => Wert]]]]
 *                 Geltung: '*' (überall), '{typ}' (alle Blöcke dieses Typs, z. B. 'cards'), '{typ}.{variante}' ('cards.overlay')
 *                 Element: Schlüssel aus ELEMENTS ('button', 'h2', 'card', 'section' = der Abschnitt selbst …)
 *                 Zustand: base | hover | focus | active | disabled | current (welche, sagt ELEMENTS)
 *   'sections' => [['key' => 'ozean', 'label' => 'Ozean', 'kind' => flat|gradient|overlay, 'bg', 'bg2', 'angle', 'text',
 *                   'accent', 'opacity', '{farbe}@dark' …], …] – Abschnitts-Stile, die die Redaktion später als Hintergrund wählt
 *
 * Farben sind token-gebunden ('token:accent' → var(--f-a), folgt hell/dunkel und dem Style-Editor) oder feste Werte ('#0F766E',
 * mit optionalem Dunkel-Wert unter 'color@dark'). Alles andere sind Stufen aus PROPS. Ausgabe als CSS für das Kind-Kit (gleiche
 * Regeln in Vorschau und Export): global (css/kit.css) und je Blocktyp (css/kd-{typ}.css, lädt nur auf Seiten mit dem Block).
 * Kontrastprüfung je Zustand und je Abschnitts-Stil (WCAG 2.2 AA) sperrt wie die Farbpaare den Export.
 */
final class Styles
{
    public const STATES = ['base', 'hover', 'focus', 'active', 'disabled', 'current'];

    /** Zustände → CSS (Platzhalter & = Selektor des Elements) */
    public const STATE_CSS = [
        'base' => '&', 'hover' => '&:hover', 'focus' => '&:focus-visible', 'active' => '&:active',
        'disabled' => '&:is(:disabled,[aria-disabled="true"])', 'current' => '&[aria-current="page"]',
    ];

    /** Eigenschaften: Typ, Grenzen, CSS */
    public const PROPS = [
        'color' => ['type' => 'color', 'label' => 'Schriftfarbe', 'css' => 'color'],
        'background' => ['type' => 'color', 'label' => 'Fläche', 'css' => 'background-color'],
        'border_color' => ['type' => 'color', 'label' => 'Rahmenfarbe', 'css' => 'border-color'],
        'border_width' => ['type' => 'range', 'label' => 'Rahmenstärke', 'unit' => 'px', 'min' => 0, 'max' => 8, 'step' => 0.5, 'css' => 'border-width'],
        'radius' => ['type' => 'range', 'label' => 'Rundung', 'unit' => 'px', 'min' => 0, 'max' => 48, 'step' => 1, 'css' => 'border-radius'],
        'shadow' => ['type' => 'choice', 'label' => 'Schatten', 'css' => 'box-shadow', 'options' => [
            'none' => 'Keiner', 'kit' => 'Wie im Design (Schatten-Token)', 'soft' => 'Weich', 'crisp' => 'Klar', 'layered' => 'Geschichtet', 'glow' => 'Leuchten in Akzentfarbe']],
        'font' => ['type' => 'choice', 'label' => 'Schrift', 'css' => 'font-family', 'options' => ['body' => 'Fließtext', 'head' => 'Überschriften', 'mono' => 'Monospace']],
        'size' => ['type' => 'choice', 'label' => 'Größe (Schriftstufe)', 'css' => 'font-size', 'options' => [
            's' => 'Klein', 't0' => 'Stufe 0 (Fließtext)', 't1' => 'Stufe 1', 't2' => 'Stufe 2', 't3' => 'Stufe 3', 't4' => 'Stufe 4', 't5' => 'Stufe 5', 't6' => 'Stufe 6']],
        'weight' => ['type' => 'range', 'label' => 'Stärke', 'unit' => '', 'min' => 300, 'max' => 900, 'step' => 50, 'css' => 'font-weight'],
        'tracking' => ['type' => 'range', 'label' => 'Laufweite', 'unit' => 'em', 'min' => -0.06, 'max' => 0.2, 'step' => 0.005, 'css' => 'letter-spacing'],
        'line_height' => ['type' => 'range', 'label' => 'Zeilenhöhe', 'unit' => '', 'min' => 0.9, 'max' => 2.2, 'step' => 0.02, 'css' => 'line-height'],
        'case' => ['type' => 'choice', 'label' => 'Schreibweise', 'css' => 'text-transform', 'options' => ['none' => 'Normal', 'uppercase' => 'VERSALIEN', 'lowercase' => 'klein', 'capitalize' => 'Wortanfänge groß']],
        'underline' => ['type' => 'choice', 'label' => 'Unterstreichung', 'css' => 'text-decoration', 'options' => ['none' => 'Keine', 'solid' => 'Linie', 'thick' => 'Kräftige Linie', 'dotted' => 'Gepunktet']],
        'padding_y' => ['type' => 'range', 'label' => 'Innenabstand oben/unten', 'unit' => 'rem', 'min' => 0, 'max' => 8, 'step' => 0.05, 'css' => 'padding-block'],
        'padding_x' => ['type' => 'range', 'label' => 'Innenabstand links/rechts', 'unit' => 'rem', 'min' => 0, 'max' => 6, 'step' => 0.05, 'css' => 'padding-inline'],
        'lift' => ['type' => 'range', 'label' => 'Versatz nach oben', 'unit' => 'px', 'min' => -8, 'max' => 8, 'step' => 1, 'css' => 'translate'],
        'opacity' => ['type' => 'range', 'label' => 'Deckkraft', 'unit' => '', 'min' => 0.2, 'max' => 1, 'step' => 0.05, 'css' => 'opacity'],
        'outline_color' => ['type' => 'color', 'label' => 'Fokusrahmen', 'css' => 'outline-color'],
        'outline_width' => ['type' => 'range', 'label' => 'Fokusrahmen-Stärke', 'unit' => 'px', 'min' => 0, 'max' => 6, 'step' => 0.5, 'css' => 'outline-width'],
    ];

    public const SHADOWS = [
        'none' => 'none', 'kit' => 'var(--f-shadow)',
        'soft' => '0 1px 2px rgb(10 12 20/.06),0 10px 28px -10px rgb(10 12 20/.22)',
        'crisp' => '0 1px 0 rgb(10 12 20/.08),0 2px 6px rgb(10 12 20/.12)',
        'layered' => '0 1px 1px rgb(10 12 20/.05),0 4px 8px -2px rgb(10 12 20/.08),0 18px 40px -14px rgb(10 12 20/.26)',
        'glow' => '0 0 0 1px color-mix(in srgb,var(--f-a) 30%,transparent),0 10px 34px -8px color-mix(in srgb,var(--f-a) 55%,transparent)',
    ];

    private const TEXT = ['color', 'font', 'size', 'weight', 'tracking', 'line_height', 'case'];
    private const BOX = ['color', 'background', 'border_color', 'border_width', 'radius', 'shadow', 'padding_y', 'padding_x', 'lift', 'opacity', 'outline_color', 'outline_width'];

    /**
     * Elementtypen der Vorschau (Markup von fluid – gleich in KLXM Studio und REDAXO fluid). sel: Selektor der CSS-Regel, pick:
     * Selektor zum Anklicken (closest()), map: Eigenschaft → CSS-Eigenschaften/Variablen (sonst PROPS.css), fg/bg: Farben, gegen die
     * geprüft wird, wenn die Person nur eine der beiden setzt, min: Mindestkontrast der Schrift.
     */
    public const ELEMENTS = [
        'button' => ['label' => 'Knopf (primär)', 'sel' => '.btn--primary', 'pick' => '.btn--primary', 'props' => [...self::BOX, 'font', 'weight', 'tracking', 'case'],
            'states' => ['base', 'hover', 'focus', 'active', 'disabled'], 'fg' => 'token:background', 'bg' => 'token:ink', 'min' => 4.5],
        'button2' => ['label' => 'Knopf (sekundär)', 'sel' => '.btn--secondary', 'pick' => '.btn--secondary', 'props' => [...self::BOX, 'font', 'weight', 'tracking', 'case'],
            'states' => ['base', 'hover', 'focus', 'active', 'disabled'], 'fg' => 'token:ink', 'bg' => 'token:background', 'min' => 4.5],
        'link' => ['label' => 'Link im Text', 'sel' => '.prose a', 'pick' => '.prose a, .lead a', 'props' => ['color', 'weight', 'underline', 'outline_color', 'outline_width'],
            'states' => ['base', 'hover', 'focus', 'active'], 'fg' => 'token:accent', 'bg' => 'token:background', 'min' => 4.5],
        'nav' => ['label' => 'Menüpunkt', 'sel' => ':is(.hnav__link,.snav__link,.mnav__link)', 'pick' => '.hnav__link, .snav__link, .mnav__link',
            'props' => ['color', 'background', 'font', 'size', 'weight', 'tracking', 'case', 'radius', 'underline', 'outline_color', 'outline_width'],
            'states' => ['base', 'hover', 'focus', 'current'], 'fg' => 'token:ink', 'bg' => 'token:background', 'min' => 4.5],
        'h1' => ['label' => 'Überschrift 1', 'sel' => '.h1', 'pick' => 'h1, .h1', 'props' => self::TEXT, 'states' => ['base'], 'fg' => 'token:ink', 'bg' => 'token:background', 'min' => 3.0],
        'h2' => ['label' => 'Überschrift 2', 'sel' => '.h2', 'pick' => 'h2.h2, .h2', 'props' => self::TEXT, 'states' => ['base'], 'fg' => 'token:ink', 'bg' => 'token:background', 'min' => 3.0],
        'h3' => ['label' => 'Überschrift 3', 'sel' => '.h3', 'pick' => 'h3, .h3', 'props' => self::TEXT, 'states' => ['base'], 'fg' => 'token:ink', 'bg' => 'token:background', 'min' => 4.5],
        'eyebrow' => ['label' => 'Dachzeile', 'sel' => '.eyebrow', 'pick' => '.eyebrow', 'props' => self::TEXT, 'states' => ['base'], 'fg' => 'token:accent', 'bg' => 'token:background', 'min' => 4.5],
        'lead' => ['label' => 'Einleitung', 'sel' => '.lead', 'pick' => '.lead', 'props' => self::TEXT, 'states' => ['base'], 'fg' => 'token:muted', 'bg' => 'token:background', 'min' => 4.5],
        'text' => ['label' => 'Fließtext', 'sel' => ':is(.prose p,.prose li)', 'pick' => '.prose p, .prose li', 'props' => self::TEXT, 'states' => ['base'], 'fg' => 'token:text', 'bg' => 'token:background', 'min' => 4.5],
        'card' => ['label' => 'Karte', 'sel' => ':is(.card,.tcard)', 'pick' => '.card, .tcard', 'props' => ['color', 'background', 'border_color', 'border_width', 'radius', 'shadow', 'lift', 'outline_color', 'outline_width'],
            'states' => ['base', 'hover', 'focus'], 'fg' => 'token:text', 'bg' => 'token:background', 'min' => 4.5,
            'map' => ['background' => ['background-color', '--f-card'], 'border_color' => ['border-color', '--f-card-bd'], 'shadow' => ['box-shadow', '--f-card-sh'], 'radius' => ['border-radius']],
            'state_css' => ['focus' => '&:focus-within']],
        'badge' => ['label' => 'Etikett', 'sel' => '.badge', 'pick' => '.badge', 'props' => ['color', 'background', 'border_color', 'border_width', 'radius', 'font', 'weight', 'tracking', 'case'],
            'states' => ['base'], 'fg' => 'token:ink', 'bg' => 'token:surface', 'min' => 4.5],
        'header' => ['label' => 'Kopfbereich', 'sel' => '.hdr', 'pick' => '.hdr', 'props' => ['color', 'background', 'border_color', 'border_width', 'shadow', 'padding_y'],
            'states' => ['base'], 'fg' => 'token:ink', 'bg' => 'token:background', 'min' => 4.5, 'global' => true,
            'map' => ['color' => ['color', '--f-ink', '--f-fg'], 'border_width' => ['border-block-end-width'], 'border_color' => ['border-block-end-color'], 'padding_y' => ['padding-block']]],
        'footer' => ['label' => 'Fußbereich', 'sel' => '.ftr', 'pick' => '.ftr', 'props' => ['color', 'background', 'border_color', 'border_width', 'padding_y'],
            'states' => ['base'], 'fg' => 'token:text', 'bg' => 'token:surface', 'min' => 4.5, 'global' => true,
            'map' => ['color' => ['color', '--f-text', '--f-ink', '--f-fg'], 'border_width' => ['border-block-start-width'], 'border_color' => ['border-block-start-color']]],
        'section' => ['label' => 'Abschnitt', 'sel' => '.sec', 'pick' => 'main section.sec, main .sec', 'props' => ['color', 'background', 'padding_y'],
            'states' => ['base'], 'fg' => 'token:text', 'bg' => 'token:background', 'min' => 4.5,
            'map' => ['color' => ['color', '--f-text', '--f-ink', '--f-fg'], 'background' => ['--f-sec-bg']]],
    ];

    /** Abschnitts-Stile: Arten */
    public const SECTION_KINDS = ['flat' => 'Fläche', 'gradient' => 'Verlauf', 'overlay' => 'Bild-Overlay (für Abschnitte mit Hintergrundbild)'];
    public const SECTION_COLORS = ['bg', 'bg2', 'text', 'accent'];

    // ------------------------------------------------------------------ Bereinigen

    /** Geltung prüfen: '*', 'typ' oder 'typ.variante' (Kleinbuchstaben, Ziffern, _ und -) */
    public static function validScope(string $s): bool
    {
        return $s === '*' || (bool) preg_match('~^[a-z][a-z0-9_]{1,40}(\.[a-z0-9][a-z0-9_-]{0,40})?$~', $s);
    }

    /** Farbangabe: 'token:{name}' (Farb-Token des Eltern-Kits) oder #RRGGBB; sonst null */
    public static function color(mixed $v, array $tokens): ?string
    {
        $v = trim((string) $v);
        if (str_starts_with($v, 'token:')) {
            $n = substr($v, 6);
            return ($tokens[$n]['type'] ?? '') === 'color' ? 'token:' . $n : null;
        }
        return Palette::hex($v);
    }

    /** @return array{scopes: array, sections: list<array>} */
    public static function normalize(array $in, ?array $tokens = null): array
    {
        $tokens ??= KitDesigner::tokens();
        $scopes = [];
        foreach ((array) ($in['scopes'] ?? []) as $scope => $els) {
            $scope = (string) $scope;
            if (!self::validScope($scope) || !is_array($els)) continue;
            foreach ($els as $el => $states) {
                $def = self::ELEMENTS[$el] ?? null;
                if (!$def || !is_array($states)) continue;
                if ($scope !== '*' && !empty($def['global'])) continue;
                foreach ($states as $st => $props) {
                    if (!in_array($st, $def['states'], true) || !is_array($props)) continue;
                    $clean = self::props($props, $def['props'], $tokens);
                    if ($clean) $scopes[$scope][$el][$st] = $clean;
                }
            }
        }
        ksort($scopes);
        $sections = [];
        $seen = [];
        foreach (array_slice(array_values((array) ($in['sections'] ?? [])), 0, 24) as $s) {
            if (!is_array($s)) continue;
            $c = self::section($s, $tokens);
            if ($c === null || isset($seen[$c['key']])) continue;
            $seen[$c['key']] = true;
            $sections[] = $c;
        }
        return ['scopes' => $scopes, 'sections' => $sections];
    }

    private static function props(array $in, array $allowed, array $tokens): array
    {
        $out = [];
        foreach ($allowed as $p) {
            $d = self::PROPS[$p];
            if (!array_key_exists($p, $in) || $in[$p] === '' || $in[$p] === null) continue;
            $v = $in[$p];
            if ($d['type'] === 'color') {
                $c = self::color($v, $tokens);
                if ($c === null) continue;
                $out[$p] = $c;
                if (!str_starts_with($c, 'token:') && ($dk = Palette::hex((string) ($in[$p . '@dark'] ?? ''))) !== null && $dk !== $c) $out[$p . '@dark'] = $dk;
            } elseif ($d['type'] === 'range') {
                if (!is_numeric($v)) continue;
                $out[$p] = round(max((float) $d['min'], min((float) $d['max'], (float) $v)), 3);
            } elseif (isset($d['options'][(string) $v])) {
                $out[$p] = (string) $v;
            }
        }
        return $out;
    }

    /** Technischer Name eines Abschnitts-Stils: nicht wie ein Hintergrund des Eltern-Kits */
    public static function sectionKey(string $key, string $label = ''): string
    {
        $k = KitDesigner::slug($key !== '' ? $key : $label);
        $k = substr($k, 0, 24);
        $parent = array_keys((array) (KitDesigner::parentTheme()->def['backgrounds'] ?? []));
        if (in_array($k, $parent, true) || !preg_match('~^[a-z][a-z0-9-]{1,23}$~', $k)) $k = 'stil-' . substr($k !== '' ? $k : 'neu', 0, 18);
        return $k;
    }

    private static function section(array $s, array $tokens): ?array
    {
        $label = mb_substr(trim(strip_tags((string) ($s['label'] ?? ''))), 0, 60);
        if ($label === '') return null;
        $out = ['key' => self::sectionKey((string) ($s['key'] ?? ''), $label), 'label' => $label,
            'kind' => isset(self::SECTION_KINDS[(string) ($s['kind'] ?? '')]) ? (string) $s['kind'] : 'flat',
            'angle' => is_numeric($s['angle'] ?? null) ? (int) max(0, min(360, (int) $s['angle'])) : 135,
            'opacity' => is_numeric($s['opacity'] ?? null) ? (int) max(10, min(95, (int) $s['opacity'])) : 70];
        foreach (self::SECTION_COLORS as $c) {
            $v = self::color($s[$c] ?? '', $tokens);
            if ($v === null) continue;
            $out[$c] = $v;
            if (!str_starts_with($v, 'token:') && ($dk = Palette::hex((string) ($s[$c . '@dark'] ?? ''))) !== null && $dk !== $v) $out[$c . '@dark'] = $dk;
        }
        $out['bg'] ??= 'token:dark_section';
        $out['text'] ??= 'token:background';
        if ($out['kind'] !== 'flat') $out['bg2'] ??= $out['bg'];
        return $out;
    }

    // ------------------------------------------------------------------ CSS

    /** CSS-Wert einer Farbe (Token → Variable des Eltern-Kits) – $dark: Dunkel-Wert fester Farben */
    public static function colorCss(string $v, array $tokens, ?string $dark = null): string
    {
        if (str_starts_with($v, 'token:')) {
            $t = $tokens[substr($v, 6)] ?? null;
            return $t && !empty($t['var']) ? 'var(' . $t['var'] . ')' : 'inherit';
        }
        return $dark ?? $v;
    }

    private static function value(string $p, mixed $v, array $tokens, bool $dark, array $props): ?string
    {
        $d = self::PROPS[$p];
        return match (true) {
            $d['type'] === 'color' => self::colorCss((string) $v, $tokens, $dark ? ($props[$p . '@dark'] ?? null) : null),
            $p === 'shadow' => self::SHADOWS[$v] ?? null,
            $p === 'font' => ['body' => 'var(--f-font)', 'head' => 'var(--f-font-head)', 'mono' => 'var(--f-font-mono)'][$v] ?? null,
            $p === 'size' => $v === 's' ? 'calc(var(--f-t0)*.875)' : 'var(--f-' . $v . ')',
            $p === 'underline' => ['none' => 'none', 'solid' => 'underline 1px', 'thick' => 'underline .14em', 'dotted' => 'underline dotted 1.5px'][$v] ?? null,
            $p === 'lift' => '0 ' . self::n(-(float) $v) . 'px',
            $p === 'case', $p === 'weight', $p === 'line_height', $p === 'opacity' => self::n($v),
            default => self::n($v) . ($d['unit'] ?? ''),
        };
    }

    private static function n(mixed $v): string
    {
        if (!is_numeric($v)) return (string) $v;
        $s = rtrim(rtrim(number_format((float) $v, 3, '.', ''), '0'), '.');
        return $s === '-0' ? '0' : ($s === '' ? '0' : $s);
    }

    /** Deklarationen eines Zustands. $dark: nur Farben mit eigenem Dunkel-Wert */
    private static function decls(string $el, array $props, array $tokens, bool $dark = false): string
    {
        $def = self::ELEMENTS[$el];
        $out = [];
        foreach ($props as $p => $v) {
            if (str_contains((string) $p, '@') || !isset(self::PROPS[$p])) continue;
            if ($dark && !isset($props[$p . '@dark'])) continue;
            $val = self::value((string) $p, $v, $tokens, $dark, $props);
            if ($val === null) continue;
            foreach ((array) ($def['map'][$p] ?? [self::PROPS[$p]['css']]) as $css) $out[] = $css . ':' . $val;
            if (!$dark && $p === 'border_width') $out[] = (isset($def['map']['border_width']) ? str_replace('-width', '-style', (string) $def['map']['border_width'][0]) : 'border-style') . ':solid';
            if (!$dark && $p === 'outline_width') { $out[] = 'outline-style:solid'; $out[] = 'outline-offset:2px'; }
            if (!$dark && $p === 'underline' && $v !== 'none') $out[] = 'text-underline-offset:.18em';
        }
        return implode(';', $out);
    }

    /** Selektor eines Elements in einer Geltung und einem Zustand */
    public static function selector(string $scope, string $el, string $state = 'base'): string
    {
        $def = self::ELEMENTS[$el];
        $base = $scope === '*' ? '' : self::scopeSelector($scope);
        $sel = $el === 'section' && $base !== '' ? $base : trim($base . ' ' . $def['sel']);
        $tpl = $def['state_css'][$state] ?? self::STATE_CSS[$state];
        return ':root ' . str_replace('&', $sel, $tpl);
    }

    /** '.sec--cards' bzw. '.sec--cards.v-overlay' */
    public static function scopeSelector(string $scope): string
    {
        [$type, $variant] = array_pad(explode('.', $scope, 2), 2, '');
        return '.sec--' . str_replace('_', '-', $type) . ($variant !== '' ? '.v-' . $variant : '');
    }

    /** Selektoren für Dunkel (wie die Design-Datei: Gerät dunkel, Schalter „dunkel“, erzwungen in Vorschau/Editor) */
    private static function darkWrap(string $sel, string $decl): string
    {
        $in = substr($sel, strlen(':root '));
        return '@media (prefers-color-scheme: dark){html.has-dark:not(.scheme-light) ' . $in . '{' . $decl . '}}'
            . 'html.has-dark.scheme-dark ' . $in . ',html.is-dark ' . $in . '{' . $decl . '}';
    }

    /**
     * CSS je Datei: '' = global (css/kit.css: Elemente überall, Kopf, Fuß, Abschnitts-Stile), '{typ}' = nur für diesen Blocktyp
     * (css/kd-{typ}.css, auch seine Varianten). Leere Teile fallen weg.
     * @return array<string, string>
     */
    public static function cssParts(array $styles, ?array $tokens = null, bool $preview = false): array
    {
        $tokens ??= KitDesigner::tokens();
        $styles = self::normalize($styles, $tokens);
        $parts = [];
        foreach ($styles['scopes'] as $scope => $els) {
            $file = $scope === '*' ? '' : explode('.', (string) $scope, 2)[0];
            foreach ($els as $el => $states) {
                foreach (self::STATES as $st) {
                    if (!isset($states[$st])) continue;
                    $sel = self::selector((string) $scope, (string) $el, $st);
                    // Vorschau: Zustand erzwingen (Arbeitsfläche setzt data-kd-state="hover" … am gewählten Element)
                    if ($preview && $st !== 'base') $sel .= ',' . self::selector((string) $scope, (string) $el, 'base') . '[data-kd-state="' . $st . '"]';
                    $d = self::decls((string) $el, $states[$st], $tokens);
                    if ($d !== '') $parts[$file][] = $sel . '{' . $d . '}';
                    $dd = self::decls((string) $el, $states[$st], $tokens, true);
                    if ($dd !== '') foreach (explode(',:root ', $sel) as $i => $one) $parts[$file][] = self::darkWrap($i ? ':root ' . $one : $one, $dd);
                }
            }
        }
        foreach ($styles['sections'] as $s) {
            foreach (self::sectionCss($s, $tokens) as $rule) $parts[''][] = $rule;
        }
        $out = [];
        foreach ($parts as $f => $rules) $out[(string) $f] = implode("\n", $rules);
        ksort($out);
        return $out;
    }

    /** Ein CSS für die Vorschau (alle Teile) */
    public static function css(array $styles, ?array $tokens = null, bool $preview = false): string
    {
        return implode("\n", self::cssParts($styles, $tokens, $preview));
    }

    /** @return list<string> Regeln eines Abschnitts-Stils */
    public static function sectionCss(array $s, array $tokens): array
    {
        $rules = [];
        foreach ([false, true] as $dark) {
            $c = fn(string $k) => isset($s[$k]) ? self::colorCss((string) $s[$k], $tokens, $dark ? ($s[$k . '@dark'] ?? null) : null) : null;
            if ($dark && !array_filter(self::SECTION_COLORS, fn($k) => isset($s[$k . '@dark']))) break;
            $bg = (string) $c('bg');
            $tx = (string) $c('text');
            $vars = ["--f-sec-bg:$bg", "--f-fg:$tx", "--f-text:$tx", "--f-ink:$tx", "--f-muted:color-mix(in srgb,$tx 80%,$bg)",
                "--f-line:color-mix(in srgb,$tx 20%,$bg)", "--f-card:color-mix(in srgb,$tx 6%,$bg)", "--f-card-bd:color-mix(in srgb,$tx 16%,$bg)", "--f-btn-ink:$bg"];
            if (($a = $c('accent')) !== null) array_push($vars, "--f-a:$a", "--f-link:$a", "--f-ico:$a");
            $sel = ':root .bg-' . $s['key'];
            $set = [$sel => implode(';', $vars)];
            $angle = (int) $s['angle'] . 'deg';
            $bg2 = (string) ($c('bg2') ?? $bg);
            if ($s['kind'] === 'gradient') $set[':root .sec.bg-' . $s['key']] = "background:linear-gradient($angle,$bg,$bg2)";
            if ($s['kind'] === 'overlay') {
                $o = (int) $s['opacity'];
                $set[$sel . ' .sec__bg::after'] = "background:linear-gradient($angle,color-mix(in srgb,$bg $o%,transparent),color-mix(in srgb,$bg2 $o%,transparent))";
                $set[':root .sec--has-bg.bg-' . $s['key']] = '--f-sec-bg:transparent';
            }
            foreach ($set as $k => $v) $rules[] = $dark ? self::darkWrap($k, $v) : $k . '{' . $v . '}';
        }
        return $rules;
    }

    /** Hintergründe für die Kit-Definition (Schlüssel => Titel) und die dunklen darunter */
    public static function backgrounds(array $styles, array $values, ?array $tokens = null): array
    {
        $tokens ??= KitDesigner::tokens();
        $out = ['list' => [], 'dark' => []];
        foreach (self::normalize($styles, $tokens)['sections'] as $s) {
            $out['list'][$s['key']] = $s['label'];
            $bg = self::resolve((string) $s['bg'], $values, $tokens, false);
            if ($bg !== null && Contrast::luminance($bg) < 0.18) $out['dark'][] = $s['key'];
        }
        return $out;
    }

    // ------------------------------------------------------------------ Kontrast

    /** Farbe auflösen (Token → Wert des Entwurfs hell/dunkel, fester Wert → ggf. Dunkel-Wert) */
    public static function resolve(string $v, array $values, array $tokens, bool $dark, ?string $darkHex = null): ?string
    {
        if (str_starts_with($v, 'token:')) {
            $n = substr($v, 6);
            if (!isset($tokens[$n])) return null;
            return Palette::hex((string) ($dark ? ($values[$n . '@dark'] ?? $values[$n] ?? '') : ($values[$n] ?? $tokens[$n]['default'] ?? '')));
        }
        return Palette::hex($dark && $darkHex ? $darkHex : $v);
    }

    /**
     * Prüfpaare der Stile: je Geltung, Element und Zustand (mit eigener Schrift- oder Flächenfarbe; „deaktiviert“ ist nach WCAG
     * ausgenommen und wird nur gezeigt), Fokusrahmen gegen die Fläche (3 : 1), Rahmen ohne Fläche gegen den Abschnitt (3 : 1)
     * und je Abschnitts-Stil Schrift und Akzent auf der Fläche (bei Verlauf beide Enden, bei Bild-Overlay über hellem und
     * dunklem Bild) – hell und dunkel.
     * @return list<array{id: string, scope: string, element: string, state: string, mode: string, what: string, fg: string, bg: string, ratio: float, min: float, ok: bool, exempt: bool}>
     */
    public static function checks(array $styles, array $values, ?array $tokens = null): array
    {
        $tokens ??= KitDesigner::tokens();
        $styles = self::normalize($styles, $tokens);
        $out = [];
        $add = function (array $base, string $fg, string $bg, float $min, string $what) use (&$out): void {
            $r = round(Contrast::ratio($fg, $bg), 2);
            $out[] = $base + ['what' => $what, 'fg' => $fg, 'bg' => $bg, 'ratio' => $r, 'min' => $min, 'ok' => $r >= $min || !empty($base['exempt'])];
        };
        foreach ($styles['scopes'] as $scope => $els) {
            foreach ($els as $el => $states) {
                $def = self::ELEMENTS[$el];
                $base = (array) ($states['base'] ?? []);
                foreach ($states as $st => $p) {
                    $merged = $st === 'base' ? $p : array_replace($base, $p);
                    foreach (['light', 'dark'] as $mode) {
                        $dark = $mode === 'dark';
                        $get = fn(string $k, string $fallback) => isset($merged[$k])
                            ? self::resolve((string) $merged[$k], $values, $tokens, $dark, $merged[$k . '@dark'] ?? null)
                            : self::resolve($fallback, $values, $tokens, $dark);
                        $id = "$scope|$el|$st|$mode";
                        $meta = ['id' => $id, 'scope' => (string) $scope, 'element' => (string) $el, 'state' => (string) $st, 'mode' => $mode, 'exempt' => $st === 'disabled'];
                        $bg = $get('background', $def['bg']);
                        if (isset($p['color']) || isset($p['background']) || ($st !== 'base' && (isset($base['color']) || isset($base['background'])))) {
                            $fg = $get('color', $def['fg']);
                            if ($fg !== null && $bg !== null) $add($meta, $fg, $bg, (float) $def['min'], 'text');
                        }
                        if (isset($merged['outline_color']) && ($st === 'focus' || $st === 'base')) {
                            $oc = $get('outline_color', 'token:accent');
                            $page = self::resolve('token:background', $values, $tokens, $dark);
                            if ($oc !== null && $page !== null) $add($meta + ['id' => $id . '|outline'], $oc, $page, Palette::AA_LARGE, 'outline');
                        }
                        if (isset($p['border_color']) && !isset($merged['background']) && in_array($el, ['button2', 'card'], true)) {
                            $bc = $get('border_color', 'token:line');
                            $page = self::resolve('token:background', $values, $tokens, $dark);
                            if ($bc !== null && $page !== null && $el === 'button2') $add($meta + ['id' => $id . '|border'], $bc, $page, Palette::AA_LARGE, 'border');
                        }
                    }
                }
            }
        }
        foreach ($styles['sections'] as $s) {
            foreach (['light', 'dark'] as $mode) {
                $dark = $mode === 'dark';
                $r = fn(string $k) => isset($s[$k]) ? self::resolve((string) $s[$k], $values, $tokens, $dark, $s[$k . '@dark'] ?? null) : null;
                $bgs = array_values(array_filter([$r('bg'), $s['kind'] !== 'flat' ? $r('bg2') : null]));
                if ($s['kind'] === 'overlay') {
                    $o = $s['opacity'] / 100;
                    $mixed = [];
                    foreach ($bgs as $b) foreach (['#FFFFFF', '#000000'] as $img) $mixed[] = self::mix($b, $img, $o);
                    $bgs = $mixed;
                }
                foreach (['text' => Palette::AA_TEXT, 'accent' => Palette::AA_TEXT] as $k => $min) {
                    $fg = $r($k);
                    if ($fg === null || !$bgs) continue;
                    $worst = null;
                    foreach ($bgs as $b) if ($worst === null || Contrast::ratio($fg, $b) < Contrast::ratio($fg, $worst)) $worst = $b;
                    $add(['id' => "section|{$s['key']}|$k|$mode", 'scope' => 'section', 'element' => $s['key'], 'state' => $k, 'mode' => $mode, 'exempt' => false], $fg, (string) $worst, $min, $k);
                }
            }
        }
        return $out;
    }

    /** Nicht bestandene Paare (ohne Ausnahmen) */
    public static function failures(array $styles, array $values, ?array $tokens = null): array
    {
        return array_values(array_filter(self::checks($styles, $values, $tokens), fn($c) => !$c['ok']));
    }

    /** Farbe $top mit Deckkraft $a über $under (sRGB) */
    public static function mix(string $top, string $under, float $a): string
    {
        $t = sscanf($top, '#%02x%02x%02x');
        $u = sscanf($under, '#%02x%02x%02x');
        $c = [];
        for ($i = 0; $i < 3; $i++) $c[] = (int) round($t[$i] * $a + $u[$i] * (1 - $a));
        return sprintf('#%02X%02X%02X', ...$c);
    }

    /** Katalog für die Arbeitsfläche (übersetzte Titel) */
    public static function catalog(): array
    {
        $tr = fn($s) => __($s);
        $props = [];
        foreach (self::PROPS as $k => $p) {
            $props[$k] = ['type' => $p['type'], 'label' => $tr($p['label'])] + array_intersect_key($p, ['min' => 1, 'max' => 1, 'step' => 1, 'unit' => 1])
                + (isset($p['options']) ? ['options' => array_map($tr, $p['options'])] : []);
        }
        $els = [];
        foreach (self::ELEMENTS as $k => $e) {
            $els[$k] = ['label' => $tr($e['label']), 'pick' => $e['pick'], 'props' => $e['props'], 'states' => $e['states'], 'global' => !empty($e['global']), 'min' => $e['min']];
        }
        return ['elements' => $els, 'props' => $props, 'kinds' => array_map($tr, self::SECTION_KINDS),
            'states' => ['base' => __('Normal'), 'hover' => __('Zeigen (hover)'), 'focus' => __('Fokus'), 'active' => __('Gedrückt'), 'disabled' => __('Deaktiviert'), 'current' => __('Aktuelle Seite')]];
    }
}
