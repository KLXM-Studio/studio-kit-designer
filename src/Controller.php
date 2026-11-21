<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Kit-Designer. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\KitDesigner;

use Core\Design;
use Core\Http\Controllers\Admin\DesignController;
use Core\Http\Controllers\SiteController;
use Core\Http\HttpException;
use Core\Http\Request;
use Core\Http\Response;
use Core\KitPackages;
use Core\Pages;

/**
 * Verwaltung → Kit-Designer (Recht kit_designer.design; Anmeldung, Recht und CSRF prüft der Router):
 * Entwürfe anlegen, gestalten (Arbeitsfläche mit Vorschau), speichern (Versionen), Tokens im DTCG-Format tauschen und als
 * Kind-Kit von fluid für KLXM Studio bzw. REDAXO fluid ausgeben.
 */
final class Controller extends \Core\Http\Controllers\Admin\AdminController
{
    private function guard(): void
    {
        if (!KitDesigner::on()) throw new HttpException(404);
        if (!KitDesigner::parentAvailable()) throw new HttpException(503, __('Das Kit fluid fehlt in dieser Installation – der Kit-Designer braucht es als Eltern-Kit.'));
    }

    private function draftOr404(string $id): array
    {
        $this->guard();
        $d = ctype_digit($id) ? Drafts::find((int) $id) : null;
        if (!$d) throw new HttpException(404);
        return $d;
    }

    private function to(string $path, string $type, string $msg): Response
    {
        app()->session->flash($type, $msg);
        return Response::redirect(url($path), 303);
    }

    // ------------------------------------------------------------------ Seiten

    public function index(Request $r): Response
    {
        $this->guard();
        return KitDesigner::page('index', ['drafts' => Drafts::all(), 'presets' => KitDesigner::presets(), 'siteFluid' => self::siteUsesParent(),
            'siteKit' => app()->theme->label()]);
    }

    public function create(Request $r): Response
    {
        $this->guard();
        $label = (string) ($r->post['label'] ?? '');
        $start = (string) ($r->post['start'] ?? 'preset');
        if ($start === 'preset') $start = (string) ($r->post['preset'] ?? 'preset:standard');
        $values = KitDesigner::defaults();
        $auto = [];
        $note = '';
        if (str_starts_with($start, 'preset:')) {
            $p = KitDesigner::presets()[substr($start, 7)] ?? null;
            if ($p) { $values = array_replace($values, $p['values']); $note = __('Vorlage „{label}“', ['label' => __($p['label'])]); }
        } elseif ($start === 'site' && self::siteUsesParent()) {
            $values = array_replace($values, Design::values());
            $note = __('Design dieser Website');
        } elseif ($start === 'import') {
            $json = json_decode(self::uploaded($r) ?? (string) ($r->post['json'] ?? ''), true);
            if (!is_array($json)) return $this->to(KitDesigner::ADMIN, 'error', __('Die Datei enthält kein gültiges JSON.'));
            $imp = self::applyImport($values, [], $json);
            if (!$imp['mapped']) return $this->to(KitDesigner::ADMIN, 'error', __('In der Datei wurden keine passenden Design-Tokens gefunden.'));
            [$values, $auto, $extra] = [$imp['values'], $imp['auto'], $imp['extra']];
            $note = __('Import: {n} Tokens übernommen', ['n' => count($imp['mapped'])]);
            $label = $label !== '' ? $label : (string) ($json['$extensions'][Tokens::EXT]['label'] ?? '');
        }
        $id = Drafts::create($label, (string) ($r->post['name'] ?? ''), ['values' => $values, 'extra' => $extra ?? [], 'auto' => $auto], $note);
        return $this->to(KitDesigner::ADMIN . '/' . $id, 'success', __('Entwurf angelegt. Die Website bleibt unverändert, bis Sie das Kit exportieren und wählen.'));
    }

    public function edit(Request $r, string $id): Response
    {
        $d = $this->draftOr404($id);
        return KitDesigner::page('edit', ['draft' => $d, 'boot' => self::boot($d), 'fontHead' => self::fontHead()]);
    }

    public function delete(Request $r, string $id): Response
    {
        $d = $this->draftOr404($id);
        Drafts::delete($d['id']);
        return $this->to(KitDesigner::ADMIN, 'success', __('Entwurf „{label}“ gelöscht.', ['label' => $d['label']]));
    }

    // ------------------------------------------------------------------ Speichern, Versionen

    public function save(Request $r, string $id): Response
    {
        $d = $this->draftOr404($id);
        try {
            $v = Drafts::save($d['id'], (string) ($r->post['label'] ?? $d['label']), isset($r->post['name']) ? (string) $r->post['name'] : null,
                ['values' => (array) ($r->post['values'] ?? []), 'extra' => (array) ($r->post['extra'] ?? []), 'auto' => (array) ($r->post['auto'] ?? []),
                    'meta' => (array) ($r->post['meta'] ?? [])], (string) ($r->post['note'] ?? ''));
        } catch (\Throwable $e) {
            return Response::fail($e->getMessage());
        }
        $n = Drafts::find($d['id']);
        return Response::json(['ok' => true, 'version' => $v, 'name' => $n['name'] ?? '', 'label' => $n['label'] ?? '', 'versions' => Drafts::versions($d['id']),
            'message' => __('Gespeichert als Version {v}.', ['v' => $v])]);
    }

    public function version(Request $r, string $id, string $v): Response
    {
        $d = $this->draftOr404($id);
        $ver = ctype_digit($v) ? Drafts::version($d['id'], (int) $v) : null;
        if (!$ver) return Response::fail(__('Version nicht gefunden.'), 404);
        return Response::json(['ok' => true] + $ver);
    }

    // ------------------------------------------------------------------ Vorschau, Farben

    /**
     * Vorschau: mode=full → HTML der Seite hell und dunkel (mit dem Entwurf, nur im Speicher); mode=live → nur Variablen, Klassen,
     * Stylesheets zum Austauschen in den Zeichenflächen (schnell, ohne Neuladen). Die Website ändert sich nie.
     */
    public function preview(Request $r): Response
    {
        $this->guard();
        $in = (array) ($r->post['values'] ?? []);
        $extra = KitDesigner::normalizeExtra((array) ($r->post['extra'] ?? []));
        $pageId = (string) ($r->post['page'] ?? '');
        $mode = (string) ($r->post['mode'] ?? 'full');
        return KitDesigner::withParent(function () use ($in, $extra, $pageId, $mode): Response {
            $values = Design::normalize($in);
            $extraCss = KitDesigner::extraCss($extra);
            $force = trim((string) (Design::def()['dark']['force'] ?? ''));
            // Klassen des Hell/Dunkel-Schalters des Kits (fluid: scheme-light/scheme-dark) – Zeichenflächen „hell“ bleiben so auch
            // bei dunkel eingestelltem Gerät hell, „dunkel“ zeigt die dunklen Werte samt abgeleiteter Farben wie die Website
            $manual = (array) (Design::def()['dark']['manual'] ?? []);
            $scheme = ['light' => (string) ($manual['light'] ?? ''), 'dark' => (string) ($manual['dark'] ?? '')];
            $darkStyle = '';
            if (preg_match('~<style data-design-dark>(.*?)</style>~s', DesignController::forceDark('<html><head></head></html>', $values), $m)) $darkStyle = $m[1];
            if ($mode === 'live') {
                $live = Design::live($values);
                return Response::json(['ok' => true, 'css' => $live['css'], 'classes' => $live['classes'], 'fonts' => $live['fonts'], 'opt' => $live['opt'],
                    'darkCss' => $darkStyle, 'force' => $force, 'scheme' => $scheme, 'extraCss' => $extraCss]);
            }
            Design::override($values);
            $page = ctype_digit($pageId) ? Pages::find((int) $pageId) : null;
            $page ??= Pages::home();
            if (!$page) return Response::fail(__('Diese Website hat noch keine Startseite – legen Sie zuerst Inhalte an.'), 404);
            $html = (new SiteController())->previewHtml($page);
            $html = self::inject($html, $extraCss);
            return Response::json(['ok' => true, 'light' => $html, 'dark' => DesignController::forceDark($html, $values), 'page' => (int) $page['id'],
                'darkCss' => $darkStyle, 'force' => $force, 'scheme' => $scheme]);
        });
    }

    /** Eigenes CSS des Entwurfs direkt nach dem Grund-CSS des Kits einsetzen (gleiche Stelle wie im exportierten Kit) */
    public static function inject(string $html, string $extraCss): string
    {
        $style = '<style data-kd-extra>' . $extraCss . '</style>';
        $n = 0;
        $out = preg_replace('~(<link rel="stylesheet" href="[^"]*/css/site\.css[^"]*">)~', '$1' . $style, $html, 1, $n);
        if (!$n) $out = preg_replace('~</head>~i', $style . '</head>', $html, 1);
        return (string) $out;
    }

    /** Farben: action=dark → Dunkel-Vorschlag (alle oder only), action=fix → ein Paar auf AA bringen */
    public function palette(Request $r): Response
    {
        $this->guard();
        $values = KitDesigner::normalize((array) ($r->post['values'] ?? []));
        $tokens = KitDesigner::tokens();
        $action = (string) ($r->post['action'] ?? 'dark');
        if ($action === 'fix') {
            $token = (string) ($r->post['token'] ?? '');
            $mode = (string) ($r->post['mode'] ?? 'light');
            foreach (Palette::checks($values, $tokens) as $c) {
                if ($c['token'] !== $token || $c['mode'] !== $mode) continue;
                $fixed = Palette::fix($c['fg'], $c['bg'], $c['min']);
                return Response::json(['ok' => true, 'key' => $token . ($mode === 'dark' ? '@dark' : ''), 'value' => $fixed]);
            }
            return Response::fail(__('Dieses Farbpaar gibt es nicht.'));
        }
        $only = isset($r->post['only']) && is_array($r->post['only']) ? array_values(array_map('strval', $r->post['only'])) : null;
        return Response::json(['ok' => true, 'dark' => Palette::suggestDark($values, $tokens, $only)]);
    }

    // ------------------------------------------------------------------ Tokens (DTCG)

    public function tokensExport(Request $r, string $id): Response
    {
        $d = $this->draftOr404($id);
        $json = json_encode(Tokens::export($d), JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . "\n";
        return Response::download($json, $d['name'] . '.tokens.json', 'application/json');
    }

    /** Import prüfen (nicht speichern): Werte des Entwurfs + Treffer aus der Datei, Bericht */
    public function tokensImport(Request $r, string $id): Response
    {
        $this->draftOr404($id);
        $json = json_decode((string) ($r->post['json'] ?? ''), true);
        if (!is_array($json)) return Response::fail(__('Die Datei enthält kein gültiges JSON.'));
        $imp = self::applyImport((array) ($r->post['values'] ?? []), (array) ($r->post['auto'] ?? []), $json, (array) ($r->post['extra'] ?? []));
        if (!$imp['mapped']) return Response::fail(__('In der Datei wurden keine passenden Design-Tokens gefunden.'), 422, ['ignored' => array_slice($imp['ignored'], 0, 40)]);
        return Response::json(['ok' => true] + $imp);
    }

    /**
     * Import auf Werte anwenden: Treffer übernehmen, normalisieren; helle Farben ohne Dunkel-Wert aus der Datei bekommen den
     * Vorschlag (und folgen ihm weiter: auto).
     * @return array{values: array, extra: array, auto: array, mapped: list<array>, ignored: list<string>, warnings: list<string>}
     */
    private static function applyImport(array $values, array $auto, array $json, array $extra = []): array
    {
        $imp = Tokens::import($json);
        $tokens = KitDesigner::tokens();
        $in = $imp['values'];
        // Akzent ohne „Akzent kräftig“ (Hover) in der Datei: passend ableiten statt den alten Farbton zu behalten
        if (isset($in['accent']) && !isset($in['accent_strong']) && isset($tokens['accent_strong'])) {
            $in['accent_strong'] = Palette::strongFrom((string) $in['accent']);
            $imp['values'] = $in;
        }
        $merged = KitDesigner::normalize(array_replace(KitDesigner::normalize($values), $in));
        $needDark = [];
        foreach ($imp['values'] as $k => $v) {
            if (!str_contains((string) $k, '@') && ($tokens[$k]['type'] ?? '') === 'color' && isset($tokens[$k]['dark']) && !isset($imp['values'][$k . '@dark'])) $needDark[] = (string) $k;
        }
        if ($needDark) {
            foreach (Palette::suggestDark($merged, $tokens, $needDark) as $k => $hex) { $merged[$k . '@dark'] = $hex; $auto[$k] = true; }
        }
        return ['values' => $merged, 'extra' => KitDesigner::normalizeExtra(array_replace($extra, $imp['extra'])), 'auto' => $auto,
            'mapped' => $imp['mapped'], 'ignored' => array_slice($imp['ignored'], 0, 200), 'warnings' => $imp['warnings'], 'own' => $imp['own']];
    }

    // ------------------------------------------------------------------ Export

    public function exportZip(Request $r, string $id, string $target): Response
    {
        $d = $this->draftOr404($id);
        if (!in_array($target, ['studio', 'redaxo'], true)) throw new HttpException(404);
        if ($fails = Exporter::blockers($d)) {
            return new Response(e(__('Export gesperrt: {n} Farbpaare erreichen den Mindestkontrast nicht. Bitte im Kit-Designer beheben und speichern.', ['n' => count($fails)])), 409,
                ['Content-Type' => 'text/plain; charset=utf-8']);
        }
        $files = $target === 'studio' ? Exporter::studioFiles($d) : Exporter::redaxoFiles($d, (string) ($r->query['ns'] ?? 'Project'));
        $zip = Exporter::zip($files, $target === 'studio' ? $d['name'] : '');
        return Response::download($zip, $d['name'] . ($target === 'redaxo' ? '-redaxo-fluid' : '-studio') . '.zip', 'application/zip');
    }

    /** Direkt in dieser Installation installieren (storage/kits) – nur für die Verwaltung der Installation */
    public function install(Request $r, string $id): Response
    {
        $d = $this->draftOr404($id);
        if (!KitPackages::canManage()) return Response::fail(__('Kits installieren dürfen nur Personen, die die Installation verwalten.'), 403);
        if ($fails = Exporter::blockers($d)) return Response::fail(__('Export gesperrt: {n} Farbpaare erreichen den Mindestkontrast nicht.', ['n' => count($fails)]), 409, ['checks' => $fails]);
        $u = app()->auth->user();
        $res = Exporter::installStudio($d, (string) (($u['email'] ?? '') ?: 'kit-designer'));
        $check = [];
        if ($res['ok']) {
            \Core\Kit::reset();
            try { $check = \Core\KitCheck::run(new \Core\Theme($res['name'])); } catch (\Throwable $e) { $check = [['level' => 'warn', 'file' => '', 'text' => $e->getMessage()]]; }
        }
        return Response::json($res + ['check' => $check, 'kitsUrl' => url('/admin/system/kits')], $res['ok'] ? 200 : 422);
    }

    // ------------------------------------------------------------------ Daten für die Arbeitsfläche

    /** Nutzt die Website fluid oder ein Kind-Kit davon? (dann passt „Design dieser Website“ als Start) */
    private static function siteUsesParent(): bool
    {
        return isset(app()->theme->chain[KitDesigner::PARENT]);
    }

    private static function uploaded(Request $r): ?string
    {
        $f = $r->files['file'] ?? null;
        if (!is_array($f) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || (int) ($f['size'] ?? 0) > 2 * 1024 * 1024) return null;
        $s = file_get_contents((string) $f['tmp_name']);
        return $s === false ? null : $s;
    }

    /** Schrift-Stylesheets und Vorschau-Schnitte für die Schriftproben der Arbeitsfläche (wie Verwaltung → Design) */
    private static function fontHead(): string
    {
        $h = '';
        $faces = [];
        foreach (KitDesigner::fonts() as $f) {
            if (!empty($f['dup'])) continue;
            if (!empty($f['href'])) $h .= '<link rel="stylesheet" href="' . e((string) $f['href']) . '">';
            elseif (!empty($f['fontsource']) && ($face = \Core\Fonts::previewFace($f)) !== '') $faces[] = $face;
        }
        return $h . ($faces ? '<style>' . implode('', $faces) . '</style>' : '');
    }

    /** Startdaten der Arbeitsfläche (JSON im Seitenquelltext) */
    private static function boot(array $d): array
    {
        $tr = fn($s) => is_string($s) && $s !== '' ? __($s) : $s;
        $tokens = [];
        foreach (KitDesigner::tokens() as $n => $t) {
            $tokens[$n] = array_filter([
                'type' => $t['type'] ?? 'color', 'label' => $tr($t['label'] ?? $n), 'help' => $tr($t['help'] ?? ''),
                'min' => $t['min'] ?? null, 'max' => $t['max'] ?? null, 'step' => $t['step'] ?? null, 'unit' => $t['unit'] ?? null,
                'options' => isset($t['options']) ? array_map($tr, (array) $t['options']) : null, 'dark' => isset($t['dark']) ? true : null,
                'contrast' => $t['contrast'] ?? null, 'markup' => !empty($t['markup']) ? true : null, 'group' => $t['group'] ?? null,
            ], fn($v) => $v !== null && $v !== '');
        }
        $fonts = [];
        foreach (KitDesigner::fonts() as $k => $f) {
            if (!empty($f['dup'])) continue;
            $fonts[] = ['key' => (string) $k, 'label' => $tr((string) ($f['label'] ?? $k)), 'stack' => (string) ($f['stack'] ?? ''), 'installed' => !empty($f['installed'])];
        }
        $pages = [];
        foreach (Pages::flat() as $p) {
            if (($p['type'] ?? 'page') !== 'page') continue;
            $pages[] = ['id' => (int) $p['id'], 'title' => (string) $p['title'], 'depth' => (int) ($p['depth'] ?? 0), 'home' => !empty($p['is_home']), 'url' => Pages::url($p)];
            if (count($pages) >= 80) break;
        }
        $presets = [];
        foreach (KitDesigner::presets() as $k => $p) $presets[] = ['key' => $k, 'label' => $tr($p['label']), 'description' => $tr($p['description']), 'values' => KitDesigner::normalize($p['values'])];
        $base = url(KitDesigner::ADMIN . '/' . $d['id']);
        return [
            'draft' => ['id' => $d['id'], 'name' => $d['name'], 'label' => $d['label'], 'version' => $d['version'],
                'values' => KitDesigner::normalize((array) $d['data']['values']), 'extra' => KitDesigner::normalizeExtra((array) $d['data']['extra']),
                'auto' => (object) $d['data']['auto'], 'meta' => (object) $d['data']['meta']],
            'tokens' => $tokens, 'defaults' => KitDesigner::defaults(), 'fonts' => $fonts, 'presets' => $presets, 'pages' => $pages,
            'markup' => KitDesigner::markupTokens(), 'extra' => KitDesigner::EXTRA, 'versions' => Drafts::versions($d['id']),
            'siteKit' => app()->theme->name, 'siteUsesParent' => self::siteUsesParent(),
            'canInstall' => KitPackages::canManage(), 'kitExists' => \Core\Kit::source($d['name']), 'kitOwn' => Exporter::ownUpload($d['name']),
            'aa' => ['text' => Palette::AA_TEXT, 'ui' => Palette::AA_LARGE],
            'urls' => ['preview' => url(KitDesigner::ADMIN . '/api/vorschau'), 'palette' => url(KitDesigner::ADMIN . '/api/farben'),
                'save' => $base . '/speichern', 'version' => $base . '/version/', 'tokensExport' => $base . '/tokens.json', 'tokensImport' => $base . '/tokens',
                'zipStudio' => $base . '/export/studio', 'zipRedaxo' => $base . '/export/redaxo', 'install' => $base . '/installieren', 'kits' => url('/admin/system/kits')],
        ];
    }
}
