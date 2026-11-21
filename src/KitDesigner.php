<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Kit-Designer. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\KitDesigner;

use Core\Design;
use Core\Http\Response;
use Core\Theme;

/**
 * Erweiterung „kit_designer“: Namen, Recht, Eltern-Kit und die Seiten der Verwaltung im Layout des Cores.
 *
 * Ein Entwurf ist ein Satz Design-Werte des Eltern-Kits (Stufe 1: fluid) plus wenige eigene Angaben (Zeilenhöhe). Gerechnet und
 * gerendert wird mit dem Style-Editor des Cores (Core\Design) – dafür setzt withParent() das Eltern-Kit für die Dauer eines Aufrufs
 * als aktives Kit ein (app()->theme) und stellt danach alles wieder her. Die Website selbst ändert sich dabei nie.
 */
final class KitDesigner
{
    public const NAME = 'kit_designer';
    public const PERM = 'kit_designer.design';
    public const ADMIN = '/admin/kit-designer';
    /** Eltern-Kit in Stufe 1 */
    public const PARENT = 'fluid';
    /** Zeilenhöhe des Fließtexts im Kit fluid (assets/css/_base.css, body) – abweichende Werte schreibt der Export als CSS */
    public const PARENT_LINE_HEIGHT = 1.62;

    /** Ordner des Pakets */
    public static function dir(): string
    {
        return dirname(__DIR__);
    }

    /** Öffentliche Adresse einer gebauten Datei (public/assets/ext/kit_designer/…) */
    public static function asset(string $path): string
    {
        $x = \Core\Extensions::active()[self::NAME] ?? null;
        return $x ? $x->asset($path) : '';
    }

    public static function on(): bool
    {
        return \Core\Extensions::isActive(self::NAME);
    }

    /** Gibt es das Eltern-Kit in dieser Installation? */
    public static function parentAvailable(): bool
    {
        return \Core\Kit::exists(self::PARENT);
    }

    // ------------------------------------------------------------------ Eltern-Kit

    private static ?Theme $parent = null;

    public static function parentTheme(): Theme
    {
        return self::$parent ??= new Theme(self::PARENT);
    }

    /**
     * $fn mit dem Eltern-Kit als aktivem Kit ausführen (Design-Rechnung, Schriften, Vorschau). Design::override() wird danach
     * zurückgesetzt, das aktive Kit der Website wiederhergestellt – auch bei Fehlern.
     * @template T
     * @param callable(): T $fn
     * @return T
     */
    public static function withParent(callable $fn): mixed
    {
        $app = app();
        $saved = $app->theme;
        $page = $app->currentPage ?? null;
        $app->theme = self::parentTheme();
        try {
            return $fn();
        } finally {
            Design::override(null);
            $app->theme = $saved;
            $app->currentPage = $page;
        }
    }

    /** Token-Definitionen des Eltern-Kits: name => def (+ group) */
    public static function tokens(): array
    {
        static $memo = null;
        return $memo ??= self::withParent(fn() => Design::tokens());
    }

    /** Werte auf erlaubte Werte des Eltern-Kits begrenzen (Unbekanntes fällt weg) */
    public static function normalize(array $values): array
    {
        return self::withParent(fn() => Design::normalize($values));
    }

    /** Standardwerte des Eltern-Kits (normalisiert) */
    public static function defaults(): array
    {
        static $memo = null;
        return $memo ??= self::withParent(fn() => Design::defaults());
    }

    /** Schriften für die Auswahl (Kit-Schriften des Eltern-Kits + installierte Schriften des Schriften-Managers) */
    public static function fonts(): array
    {
        return self::withParent(fn() => Design::fonts());
    }

    /** Vorlagen („Paletten“) des Eltern-Kits: key => [label, description, values] */
    public static function presets(): array
    {
        $out = [];
        foreach ((array) (self::parentTheme()->def['design']['presets'] ?? []) as $k => $p) {
            $out[(string) $k] = ['label' => (string) ($p['label'] ?? $k), 'description' => (string) ($p['description'] ?? ''), 'values' => (array) ($p['values'] ?? [])];
        }
        return $out;
    }

    /** Tokens, deren Wert das Markup ändert (Kopf-/Fußvariante …) – Vorschau rendert dann neu */
    public static function markupTokens(): array
    {
        return array_keys(array_filter(self::tokens(), fn($t) => !empty($t['markup'])));
    }

    // ------------------------------------------------------------------ Eigene Angaben (nicht im Eltern-Kit)

    /** Eigene Angaben mit Grenzen: Zeilenhöhe des Fließtexts */
    public const EXTRA = ['line_height' => ['min' => 1.2, 'max' => 2.0, 'step' => 0.02, 'default' => self::PARENT_LINE_HEIGHT]];

    public static function normalizeExtra(array $in): array
    {
        $out = [];
        foreach (self::EXTRA as $k => $d) {
            $v = $in[$k] ?? null;
            $out[$k] = is_numeric($v) ? round(max($d['min'], min($d['max'], (float) $v)), 3) : $d['default'];
        }
        return $out;
    }

    /** CSS der eigenen Angaben, die vom Eltern-Kit abweichen ('' = keins) – gleiche Regel in Vorschau und Export */
    public static function extraCss(array $extra): string
    {
        $extra = self::normalizeExtra($extra);
        $rules = [];
        if (abs($extra['line_height'] - self::PARENT_LINE_HEIGHT) > 0.0001) {
            $rules[] = 'body{line-height:' . rtrim(rtrim(number_format($extra['line_height'], 3, '.', ''), '0'), '.') . '}';
        }
        return implode("\n", $rules);
    }

    // ------------------------------------------------------------------ Namen

    /** Technischer Name eines Kits aus einem Titel: a–z, 0–9, Bindestrich, beginnt mit Buchstabe (für Studio und REDAXO gültig) */
    public static function slug(string $label): string
    {
        $s = strtolower(strtr(trim($label), ['ä' => 'ae', 'ö' => 'oe', 'ü' => 'ue', 'Ä' => 'ae', 'Ö' => 'oe', 'Ü' => 'ue', 'ß' => 'ss']));
        $s = trim((string) preg_replace('~[^a-z0-9]+~', '-', $s), '-');
        $s = (string) preg_replace('~^[^a-z]+~', '', $s);
        return substr($s !== '' ? $s : 'mein-kit', 0, 30);
    }

    public static function validName(string $name): bool
    {
        return (bool) preg_match('~^[a-z][a-z0-9-]{1,29}$~', $name);
    }

    // ------------------------------------------------------------------ Verwaltung

    /** Seite der Verwaltung: Ansicht aus views/admin im Layout des Cores */
    public static function page(string $view, array $vars = [], int $status = 200): Response
    {
        if (!array_key_exists('user', $vars)) $vars['user'] = app()->auth->user();
        $vars['flash'] = app()->session->takeFlash();
        $content = Theme::capture(self::dir() . '/views/admin/' . $view . '.php', $vars);
        if (!isset($vars['title']) && preg_match('~<h1[^>]*>(.*?)</h1>~s', $content, $m)) {
            $vars['title'] = trim((string) preg_replace('~\s+~', ' ', html_entity_decode(strip_tags($m[1]))));
        }
        $html = Theme::capture(ROOT . '/app/Admin/views/layout.php', $vars + ['content' => $content, 'view' => self::NAME . '/' . $view]);
        return \Core\Http\Controllers\Admin\AdminController::secure(new Response($html, $status));
    }
}
