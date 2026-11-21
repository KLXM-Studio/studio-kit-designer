<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Kit-Designer. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\KitDesigner;

use Core\Pages;

/**
 * Komponenten-Galerie: alle Blöcke des Eltern-Kits in jeder Variante auf einer Seite, dazu je Abschnitts-Stil ein Beispiel.
 *
 * Inhalte: 'examples' (Standard) = frei erfundene Beispiele aus den Startinhalten von fluid (seed.php) plus eigenen Beispielen
 * dieses Pakets für Blöcke, die dort fehlen; Blöcke ohne Beispiel nimmt die Galerie von veröffentlichten Seiten der Website.
 * 'site' = zuerst Blöcke der Website (echte Inhalte, wie kit:test). Jeder Abschnitt trägt die id „kdg-{typ}-{variante}“
 * (Abschnitts-Stile: „kdg-stil-{schlüssel}“) – die Arbeitsfläche beschriftet und gruppiert danach.
 */
final class Gallery
{
    /** Typen ohne eigene Darstellung als Abschnitt (wie Core\KitTest) bzw. ohne sinnvolles Beispiel */
    private const SKIP = ['entry_nav', 'entry_blocks', 'data_fields', 'not_found', 'job_facts', 'job_apply', 'push_subscribe', 'live_gallery', 'live_text', 'layout'];

    /** Eigene Beispiele (frei erfunden) für Blöcke von fluid, die in den Startinhalten fehlen */
    public static function ownSamples(): array
    {
        $items = [
            ['eyebrow' => 'Werkstatt', 'title' => 'Ein Tag im Atelier', 'text' => 'Wie aus Skizzen fertige Entwürfe werden.', 'link_label' => 'Weiterlesen', 'link' => '#', 'badge' => 'Neu'],
            ['eyebrow' => 'Projekt', 'title' => 'Leitsystem Musterstadt', 'text' => 'Orientierung für ein Rathaus mit drei Eingängen.', 'link_label' => 'Weiterlesen', 'link' => '#'],
            ['eyebrow' => 'Ratgeber', 'title' => 'Farben, die tragen', 'text' => 'Kontraste prüfen, bevor gedruckt wird.', 'link_label' => 'Weiterlesen', 'link' => '#'],
        ];
        return [
            'cards' => ['eyebrow' => 'Beispiele', 'title' => 'Aus dem Journal', 'intro' => 'Drei erfundene Beiträge zur Ansicht.', 'size' => 'm', 'ratio' => '3:2', 'items' => $items],
            'tabs' => ['eyebrow' => 'Leistungen', 'title' => 'Was wir anbieten', 'intro' => '', 'items' => [
                ['label' => 'Beratung', 'text' => '<p>Wir hören zu, ordnen und schlagen einen Weg vor.</p>'],
                ['label' => 'Gestaltung', 'text' => '<p>Ein Erscheinungsbild für alle Geräte.</p>'],
                ['label' => 'Betreuung', 'text' => '<p>Pflege, Schulung und kleine Verbesserungen.</p>'],
            ]],
            'cta' => ['eyebrow' => 'Kontakt', 'title' => 'Sprechen wir über Ihr Vorhaben.', 'text' => 'Ein kurzes Gespräch genügt – unverbindlich.',
                'button_label' => 'Kontakt aufnehmen', 'button_link' => '#', 'button2_label' => 'E-Mail schreiben', 'button2_link' => '#'],
        ];
    }

    /** Erstes Beispiel je Blocktyp aus den Startinhalten des Eltern-Kits (seed.php: Seiten → Blöcke) */
    public static function seedSamples(): array
    {
        $file = KitDesigner::parentTheme()->path . '/seed.php';
        if (!is_file($file)) return [];
        try {
            $seed = (array) (static fn() => require $file)();
        } catch (\Throwable) {
            return [];
        }
        $out = [];
        $walk = function (array $pages) use (&$walk, &$out): void {
            foreach ($pages as $p) {
                foreach ((array) ($p['blocks'] ?? []) as $b) {
                    $t = (string) ($b['type'] ?? '');
                    if ($t !== '' && !isset($out[$t]) && is_array($b['data'] ?? null)) $out[$t] = (array) $b['data'];
                }
                if (!empty($p['children'])) $walk((array) $p['children']);
            }
        };
        $walk((array) ($seed['pages'] ?? []));
        return $out;
    }

    /** Erstes Vorkommen je Blocktyp auf veröffentlichten Seiten der Website (Daten des Blocks) */
    public static function siteSamples(): array
    {
        $out = [];
        try {
            foreach (Pages::published() as $p) {
                foreach (Pages::blocks($p) as $b) {
                    $t = (string) ($b['type'] ?? '');
                    if ($t !== '' && !isset($out[$t]) && is_array($b['data'] ?? null)) $out[$t] = (array) $b['data'];
                }
            }
        } catch (\Throwable) {
        }
        return $out;
    }

    /**
     * Blöcke der Galerie (für eine Seite) und Liste für die Ebenen-Leiste.
     * @return array{blocks: list<array>, items: list<array{id: string, type: string, variant: string, label: string, variantLabel: string, source: string}>, missing: list<string>}
     */
    public static function build(string $source = 'examples', array $sections = []): array
    {
        $kit = KitDesigner::parentTheme();
        $own = self::ownSamples();
        $seed = self::seedSamples();
        $site = self::siteSamples();
        $order = $source === 'site' ? [['site', $site], ['examples', $seed + $own]] : [['examples', $seed + $own], ['site', $site]];
        $blocks = $items = $missing = [];
        foreach ((array) ($kit->def['blocks'] ?? []) as $type => $def) {
            $type = (string) $type;
            if (in_array($type, self::SKIP, true) || !is_array($def)) continue;
            $data = null;
            $from = '';
            foreach ($order as [$name, $list]) {
                if (isset($list[$type])) { $data = $list[$type]; $from = $name; break; }
            }
            if ($data === null) { $missing[] = (string) __((string) ($def['label'] ?? $type)); continue; }
            $variants = (array) ($def['variants'] ?? []);
            if (!$variants) $variants = ['' => ''];
            foreach ($variants as $v => $vl) {
                $v = (string) $v;
                $id = 'kdg-' . self::slug($type) . ($v !== '' ? '-' . self::slug($v) : '');
                $d = $data;
                if ($v !== '') $d['variant'] = $v;
                $blocks[] = ['id' => $id, 'type' => $type, 'data' => $d, 'tunes' => ['section' => ['background' => 'white', 'anchor' => $id, 'visible' => true]]];
                $items[] = ['id' => $id, 'type' => $type, 'variant' => $v, 'label' => (string) __((string) ($def['label'] ?? $type)),
                    'variantLabel' => $v !== '' ? (string) __((string) $vl) : '', 'source' => $from];
            }
        }
        // je Abschnitts-Stil ein Beispiel (Aufruf zum Handeln) – die Arbeitsfläche setzt die Klasse bg-{schlüssel}
        $sample = $own['cta'];
        foreach ($sections as $s) {
            $key = (string) ($s['key'] ?? '');
            if ($key === '') continue;
            $id = 'kdg-stil-' . $key;
            $blocks[] = ['id' => $id, 'type' => 'cta', 'data' => ['variant' => 'band'] + $sample + ['title' => (string) ($s['label'] ?? $key)],
                'tunes' => ['section' => ['background' => 'white', 'anchor' => $id, 'visible' => true]]];
            $items[] = ['id' => $id, 'type' => '@section', 'variant' => $key, 'label' => (string) ($s['label'] ?? $key), 'variantLabel' => '', 'source' => 'style'];
        }
        return ['blocks' => $blocks, 'items' => $items, 'missing' => $missing];
    }

    /** HTML der Galerie-Seite (Layout des aktiven Kits – im Kit-Designer das Eltern-Kit) */
    public static function page(array $blocks): string
    {
        $page = Pages::home() ?? (Pages::published()[0] ?? null);
        if (!$page) throw new \RuntimeException(__('Diese Website hat noch keine Startseite – legen Sie zuerst Inhalte an.'));
        $page['content_published'] = json_encode(['blocks' => $blocks], JSON_UNESCAPED_UNICODE);
        $page['title'] = __('Komponenten-Galerie');
        return (new \Core\Http\Controllers\SiteController())->previewHtml($page);
    }

    private static function slug(string $s): string
    {
        return trim((string) preg_replace('~[^a-z0-9]+~', '-', strtolower($s)), '-') ?: 'x';
    }
}
