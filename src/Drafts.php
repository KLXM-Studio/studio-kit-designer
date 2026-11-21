<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Kit-Designer. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\KitDesigner;

use Core\Db\Table;

/**
 * Entwürfe je Website (Tabellen in der Datenbank der Website) mit Versionen.
 *
 *   kit_designer_drafts    ein Entwurf: technischer Name (= Ordner des Kits), Titel, Daten (JSON), laufende Versionsnummer
 *   kit_designer_versions  jeder gespeicherte Stand (Daten, Notiz, Person, Zeitpunkt) – Wiederherstellen legt eine neue Version an
 *
 * Daten eines Entwurfs: ['parent' => 'fluid', 'values' => Design-Werte des Eltern-Kits (normalisiert, „token@dark“ für dunkel),
 *   'extra' => ['line_height' => …], 'auto' => [token => true] (Dunkel-Wert folgt dem Vorschlag), 'meta' => [description, author],
 *   'styles' => Stile für Elemente, Blöcke und Abschnitts-Stile (Klxm\KitDesigner\Styles)]
 */
final class Drafts
{
    public const DRAFTS = 'kit_designer_drafts';
    public const VERSIONS = 'kit_designer_versions';
    /** Höchstzahl gespeicherter Versionen je Entwurf (älteste fallen weg) */
    public const KEEP = 40;

    public static function tables(): array
    {
        return [
            self::DRAFTS => fn(Table $t) => $t->id()
                ->column('name', 'string', ['length' => 40, 'null' => false, 'default' => ''])
                ->column('label', 'string', ['length' => 120, 'null' => false, 'default' => ''])
                ->column('data', 'json')
                ->column('version', 'int', ['default' => 0])
                ->column('created_by', 'string', ['length' => 191, 'default' => ''])
                ->column('created_at', 'datetime')->column('updated_at', 'datetime'),
            self::VERSIONS => fn(Table $t) => $t->id()
                ->column('draft_id', 'int', ['null' => false])
                ->column('version', 'int', ['default' => 0])
                ->column('label', 'string', ['length' => 120, 'default' => ''])
                ->column('data', 'json')
                ->column('note', 'string', ['length' => 191, 'default' => ''])
                ->column('created_by', 'string', ['length' => 191, 'default' => ''])
                ->column('created_at', 'datetime')
                ->index(['draft_id', 'version']),
        ];
    }

    /** @return list<array> neueste zuerst */
    public static function all(): array
    {
        return array_map([self::class, 'decode'], app()->db->fetchAll('SELECT * FROM ' . self::DRAFTS . ' ORDER BY updated_at DESC, id DESC'));
    }

    public static function find(int $id): ?array
    {
        $row = app()->db->fetch('SELECT * FROM ' . self::DRAFTS . ' WHERE id = ?', [$id]);
        return $row ? self::decode($row) : null;
    }

    /** Daten bereinigen: Werte des Eltern-Kits, eigene Angaben, Auto-Markierungen, Metadaten */
    public static function clean(array $in): array
    {
        $values = KitDesigner::normalize((array) ($in['values'] ?? []));
        $colors = array_keys(array_filter(KitDesigner::tokens(), fn($t) => ($t['type'] ?? '') === 'color' && isset($t['dark'])));
        $auto = [];
        foreach ((array) ($in['auto'] ?? []) as $k => $v) if (in_array($k, $colors, true) && $v) $auto[$k] = true;
        $meta = (array) ($in['meta'] ?? []);
        $extra = KitDesigner::normalizeExtra((array) ($in['extra'] ?? []));
        // fluid mit Token line_height: die eigene Angabe aus Stufe 1 wandert in das Token (und bleibt gleich)
        if (KitDesigner::lineHeightToken()) {
            if (abs($extra['line_height'] - KitDesigner::PARENT_LINE_HEIGHT) > 0.0001 && abs((float) $values['line_height'] - KitDesigner::PARENT_LINE_HEIGHT) < 0.0001) {
                $values = KitDesigner::normalize(['line_height' => $extra['line_height']] + $values);
            }
            $extra['line_height'] = (float) $values['line_height'];
        }
        return [
            'parent' => KitDesigner::PARENT,
            'values' => $values,
            'extra' => $extra,
            'styles' => Styles::normalize((array) ($in['styles'] ?? [])),
            'auto' => $auto,
            'meta' => [
                'description' => mb_substr(trim(strip_tags((string) ($meta['description'] ?? ''))), 0, 300),
                'author' => mb_substr(trim(strip_tags((string) ($meta['author'] ?? ''))), 0, 120),
            ],
        ];
    }

    /** Neuen Entwurf anlegen. @return int id */
    public static function create(string $label, string $name, array $data, string $note = ''): int
    {
        [$label, $name] = self::names($label, $name);
        $data = self::clean($data);
        $id = (int) app()->db->insert(self::DRAFTS, ['name' => self::uniqueName($name), 'label' => $label, 'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'version' => 0, 'created_by' => self::who(), 'created_at' => now(), 'updated_at' => now()]);
        self::save($id, $label, null, $data, $note !== '' ? $note : __('Angelegt'));
        return $id;
    }

    /**
     * Stand speichern: Entwurf aktualisieren und als neue Version ablegen. $name = null lässt den Namen unverändert.
     * @return int neue Versionsnummer
     */
    public static function save(int $id, string $label, ?string $name, array $data, string $note = ''): int
    {
        $d = self::find($id);
        if (!$d) throw new \RuntimeException(__('Entwurf nicht gefunden.'));
        [$label, $clean] = self::names($label, $name ?? $d['name']);
        $data = self::clean($data);
        $json = json_encode($data, JSON_UNESCAPED_UNICODE);
        $v = (int) $d['version'] + 1;
        $row = ['label' => $label, 'data' => $json, 'version' => $v, 'updated_at' => now()];
        if ($name !== null && $clean !== $d['name']) $row['name'] = self::uniqueName($clean, $id);
        app()->db->transaction(function () use ($id, $row, $v, $label, $json, $note): void {
            app()->db->update(self::DRAFTS, $row, 'id = ?', [$id]);
            app()->db->insert(self::VERSIONS, ['draft_id' => $id, 'version' => $v, 'label' => $label, 'data' => $json,
                'note' => mb_substr(trim(strip_tags($note)), 0, 191), 'created_by' => self::who(), 'created_at' => now()]);
            $all = app()->db->fetchAll('SELECT id FROM ' . self::VERSIONS . ' WHERE draft_id = ? ORDER BY version DESC', [$id]);
            foreach (array_slice($all, self::KEEP) as $o) app()->db->query('DELETE FROM ' . self::VERSIONS . ' WHERE id = ?', [(int) $o['id']]);
        });
        return $v;
    }

    /** @return list<array{version: int, label: string, note: string, created_by: string, created_at: string}> neueste zuerst */
    public static function versions(int $id): array
    {
        return array_map(fn($r) => ['version' => (int) $r['version'], 'label' => (string) $r['label'], 'note' => (string) $r['note'],
            'created_by' => (string) $r['created_by'], 'created_at' => (string) $r['created_at']],
            app()->db->fetchAll('SELECT version, label, note, created_by, created_at FROM ' . self::VERSIONS . ' WHERE draft_id = ? ORDER BY version DESC', [$id]));
    }

    /** Daten einer Version (oder null) */
    public static function version(int $id, int $version): ?array
    {
        $r = app()->db->fetch('SELECT * FROM ' . self::VERSIONS . ' WHERE draft_id = ? AND version = ?', [$id, $version]);
        if (!$r) return null;
        return ['version' => (int) $r['version'], 'label' => (string) $r['label'], 'data' => self::clean((array) (json_decode((string) $r['data'], true) ?: []))];
    }

    public static function delete(int $id): void
    {
        app()->db->transaction(function () use ($id): void {
            app()->db->query('DELETE FROM ' . self::VERSIONS . ' WHERE draft_id = ?', [$id]);
            Shares::deleteFor($id);
            app()->db->query('DELETE FROM ' . self::DRAFTS . ' WHERE id = ?', [$id]);
        });
    }

    public static function count(): int
    {
        return (int) app()->db->fetchValue('SELECT COUNT(*) FROM ' . self::DRAFTS);
    }

    // ------------------------------------------------------------------ intern

    private static function decode(array $row): array
    {
        $data = (array) (json_decode((string) ($row['data'] ?? ''), true) ?: []);
        return ['id' => (int) $row['id'], 'name' => (string) $row['name'], 'label' => (string) $row['label'], 'version' => (int) $row['version'],
            'created_by' => (string) $row['created_by'], 'created_at' => (string) $row['created_at'], 'updated_at' => (string) $row['updated_at'],
            'data' => $data + ['values' => [], 'extra' => [], 'auto' => [], 'meta' => [], 'styles' => ['scopes' => [], 'sections' => []]]];
    }

    /** @return array{0: string, 1: string} Titel, technischer Name */
    private static function names(string $label, string $name): array
    {
        $label = mb_substr(trim(strip_tags($label)), 0, 120);
        if ($label === '') $label = __('Mein Kit');
        $name = strtolower(trim($name));
        if (!KitDesigner::validName($name)) $name = KitDesigner::slug($name !== '' ? $name : $label);
        if (!KitDesigner::validName($name)) $name = 'mein-kit';
        return [$label, $name];
    }

    /** Name eindeutig unter den Entwürfen dieser Website (Kits der Installation prüft der Export) */
    private static function uniqueName(string $name, int $except = 0): string
    {
        $try = $name;
        for ($i = 2; $i < 100; $i++) {
            if (!app()->db->fetchValue('SELECT id FROM ' . self::DRAFTS . ' WHERE name = ? AND id != ?', [$try, $except])) return $try;
            $try = substr($name, 0, 26) . '-' . $i;
        }
        return $name . '-' . bin2hex(random_bytes(2));
    }

    private static function who(): string
    {
        $u = isset(app()->auth) ? app()->auth->user() : null;
        return $u ? (string) (($u['name'] ?? '') ?: ($u['email'] ?? '')) : 'cli';
    }
}
