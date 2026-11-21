<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Kit-Designer. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\KitDesigner;

use Core\Design;
use Core\Kit;

/**
 * Bestehende Kind-Kits zum Weiterbearbeiten einlesen.
 *
 *  - fromKit($name): ein installiertes Kit der Installation, das von fluid erbt. Mit kit-designer.json (Export ab 0.2.0) kommt der
 *    ganze Entwurf zurück (Werte, Stile, Abschnitts-Stile); sonst die Standardwerte seines Designs (design_defaults über fluid).
 *  - fromZip($bytes): ZIP eines Exports (KLXM Studio oder REDAXO fluid) – gelesen werden nur kit-designer.json bzw.
 *    design-tokens.json, nie PHP (kein Code aus dem Archiv wird ausgeführt).
 * @phpstan-type Result array{ok: bool, message: string, label: string, name: string, data: array, note: string}
 */
final class Importer
{
    /** Kits der Installation, die von fluid erben (außer fluid selbst): name => Titel */
    public static function kits(): array
    {
        $out = [];
        foreach (array_keys(Kit::all()) as $k) {
            $k = (string) $k;
            if ($k === KitDesigner::PARENT || !isset(Kit::chain($k)[KitDesigner::PARENT])) continue;
            $out[$k] = Kit::meta(Kit::dir($k))['label'] ?: $k;
        }
        return $out;
    }

    /** @return Result */
    public static function fromKit(string $name): array
    {
        $name = Kit::clean($name);
        if (!isset(self::kits()[$name])) return self::fail(__('Dieses Kit erbt nicht von fluid oder ist nicht installiert.'));
        $json = Kit::dir($name) . '/kit-designer.json';
        if (is_file($json)) {
            $r = self::fromSource((string) file_get_contents($json));
            if ($r['ok']) return ['name' => $name, 'note' => __('Aus Kit „{name}“ (kit-designer.json)', ['name' => $name])] + $r;
        }
        try {
            $values = KitDesigner::withKit($name, fn() => Design::defaults());
        } catch (\Throwable $e) {
            return self::fail($e->getMessage());
        }
        $label = (string) (Kit::meta(Kit::dir($name))['label'] ?: $name);
        return ['ok' => true, 'message' => __('Kit „{label}“ eingelesen: Standardwerte des Designs. Eigenes CSS des Kits bleibt im Kit und wird nicht übernommen.', ['label' => $label]),
            'label' => $label, 'name' => $name, 'note' => __('Aus Kit „{name}“', ['name' => $name]),
            'data' => ['values' => KitDesigner::normalize($values), 'extra' => [], 'auto' => [], 'styles' => [], 'meta' => []]];
    }

    /** @return Result */
    public static function fromZip(?string $bytes): array
    {
        if ($bytes === null || $bytes === '') return self::fail(__('Bitte eine ZIP-Datei wählen (höchstens 20 MB).'));
        if (!class_exists(\ZipArchive::class)) return self::fail(__('PHP-Erweiterung zip fehlt.'));
        $tmp = tempnam(sys_get_temp_dir(), 'kdi');
        if ($tmp === false) return self::fail('tempnam');
        file_put_contents($tmp, $bytes);
        $zip = new \ZipArchive();
        $open = false;
        try {
            if ($zip->open($tmp, \ZipArchive::RDONLY) !== true) return self::fail(__('Keine gültige ZIP-Datei.'));
            $open = true;
            $source = $tokens = null;
            for ($i = 0; $i < min($zip->numFiles, 2000); $i++) {
                $n = (string) $zip->getNameIndex($i);
                $size = (int) ($zip->statIndex($i)['size'] ?? 0);
                if ($size > 2 * 1024 * 1024) continue;
                if (basename($n) === 'kit-designer.json' && $source === null) $source = (string) $zip->getFromIndex($i);
                if (basename($n) === 'design-tokens.json' && $tokens === null) $tokens = (string) $zip->getFromIndex($i);
            }
        } finally {
            if ($open) $zip->close();
            @unlink($tmp);
        }
        if ($source !== null && ($r = self::fromSource($source))['ok']) return $r;
        if ($tokens !== null) {
            $json = json_decode($tokens, true);
            if (is_array($json)) {
                $imp = Tokens::import($json);
                if ($imp['mapped']) {
                    $label = (string) ($json['$extensions'][Tokens::EXT]['label'] ?? '');
                    return ['ok' => true, 'message' => __('Import: {n} Tokens übernommen (ohne Stile für Elemente und Abschnitte).', ['n' => count($imp['mapped'])]),
                        'label' => $label, 'name' => (string) ($json['$extensions'][Tokens::EXT]['name'] ?? ''), 'note' => __('Import aus ZIP (Design-Tokens)'),
                        'data' => ['values' => KitDesigner::normalize(array_replace(KitDesigner::defaults(), $imp['values'])), 'extra' => $imp['extra'], 'auto' => [], 'styles' => [], 'meta' => []]];
                }
            }
        }
        return self::fail(__('Im ZIP fehlen kit-designer.json und design-tokens.json – es stammt nicht aus dem Kit-Designer.'));
    }

    /** kit-designer.json (Exporter::sourceJson) lesen. @return Result */
    public static function fromSource(string $json): array
    {
        $j = json_decode($json, true);
        if (!is_array($j) || ($j['format'] ?? '') !== 'klxm-kit-designer' || !is_array($j['data'] ?? null)) return self::fail(__('Die Datei kit-designer.json ist unvollständig.'));
        $data = Drafts::clean((array) $j['data']);
        $label = mb_substr(trim(strip_tags((string) ($j['label'] ?? ''))), 0, 120);
        return ['ok' => true, 'message' => __('Entwurf „{label}“ aus dem Export eingelesen – mit Stilen und Abschnitts-Stilen.', ['label' => $label]),
            'label' => $label, 'name' => (string) ($j['name'] ?? ''), 'note' => __('Import aus Export (Version {v})', ['v' => (int) ($j['draft_version'] ?? 0)]), 'data' => $data];
    }

    /** @return Result */
    private static function fail(string $msg): array
    {
        return ['ok' => false, 'message' => $msg, 'label' => '', 'name' => '', 'data' => [], 'note' => ''];
    }
}
