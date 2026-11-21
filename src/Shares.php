<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Kit-Designer. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\KitDesigner;

use Core\Db\Table;

/**
 * Vorschau-Links zum Teilen: ein gespeicherter Stand (Version) eines Entwurfs, nur lesend, zeitlich begrenzt.
 *
 * Tabelle kit_designer_shares: Entwurf, Version, SHA-256 des Tokens (Klartext nur einmal bei der Erstellung), Ablauf,
 * angelegt von/am, zuletzt geöffnet, Zahl der Aufrufe, widerrufen. Die Adresse /kit-designer/vorschau/{token} braucht keine
 * Anmeldung; sie zeigt Seiten der Website mit dem Stand der Version (ohne Skripte, nicht indexierbar) und ändert nichts.
 */
final class Shares
{
    public const TABLE = 'kit_designer_shares';
    public const PATH = '/kit-designer/vorschau';
    /** Erlaubte Laufzeiten in Tagen */
    public const DAYS = [1, 7, 30];

    public static function table(): array
    {
        return [self::TABLE => fn(Table $t) => $t->id()
            ->column('draft_id', 'int', ['null' => false])
            ->column('version', 'int', ['default' => 0])
            ->column('token_hash', 'string', ['length' => 64, 'null' => false, 'default' => ''])
            ->column('note', 'string', ['length' => 120, 'default' => ''])
            ->column('expires_at', 'datetime')
            ->column('created_by', 'string', ['length' => 191, 'default' => ''])
            ->column('created_at', 'datetime')
            ->column('last_used_at', 'datetime', ['null' => true])
            ->column('hits', 'int', ['default' => 0])
            ->column('revoked', 'int', ['default' => 0])
            ->index(['token_hash'])->index(['draft_id'])];
    }

    /** @return array{token: string, id: int, expires_at: string} – Token im Klartext nur hier */
    public static function create(int $draftId, int $version, int $days, string $note = '', string $by = ''): array
    {
        $days = in_array($days, self::DAYS, true) ? $days : 7;
        $token = bin2hex(random_bytes(24));
        $exp = date('Y-m-d H:i:s', time() + $days * 86400);
        $id = (int) app()->db->insert(self::TABLE, ['draft_id' => $draftId, 'version' => $version, 'token_hash' => hash('sha256', $token),
            'note' => mb_substr(trim(strip_tags($note)), 0, 120), 'expires_at' => $exp, 'created_by' => $by, 'created_at' => now(), 'hits' => 0, 'revoked' => 0]);
        return ['token' => $token, 'id' => $id, 'expires_at' => $exp];
    }

    /** Gültige Freigabe zum Token (oder null): nicht widerrufen, nicht abgelaufen, Entwurf und Version vorhanden */
    public static function find(string $token): ?array
    {
        if (!preg_match('~^[a-f0-9]{48}$~', $token)) return null;
        $row = app()->db->fetch('SELECT * FROM ' . self::TABLE . ' WHERE token_hash = ? AND revoked = 0', [hash('sha256', $token)]);
        if (!$row || strtotime((string) $row['expires_at']) < time()) return null;
        return $row;
    }

    public static function touch(int $id): void
    {
        app()->db->query('UPDATE ' . self::TABLE . ' SET hits = hits + 1, last_used_at = ? WHERE id = ?', [now(), $id]);
    }

    /** @return list<array> Freigaben eines Entwurfs, neueste zuerst (ohne Token) */
    public static function list(int $draftId): array
    {
        return array_map(fn($r) => ['id' => (int) $r['id'], 'version' => (int) $r['version'], 'note' => (string) $r['note'], 'expires_at' => (string) $r['expires_at'],
            'created_by' => (string) $r['created_by'], 'created_at' => (string) $r['created_at'], 'last_used_at' => (string) ($r['last_used_at'] ?? ''),
            'hits' => (int) $r['hits'], 'active' => !(int) $r['revoked'] && strtotime((string) $r['expires_at']) >= time()],
            app()->db->fetchAll('SELECT * FROM ' . self::TABLE . ' WHERE draft_id = ? ORDER BY id DESC LIMIT 50', [$draftId]));
    }

    public static function revoke(int $draftId, int $id): bool
    {
        return (bool) app()->db->query('UPDATE ' . self::TABLE . ' SET revoked = 1 WHERE id = ? AND draft_id = ?', [$id, $draftId]);
    }

    public static function deleteFor(int $draftId): void
    {
        app()->db->query('DELETE FROM ' . self::TABLE . ' WHERE draft_id = ?', [$draftId]);
    }
}
