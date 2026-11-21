<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Kit-Designer. Copyright (C) 2026 KLXM and contributors (see LICENSE)
declare(strict_types=1);

namespace Klxm\KitDesigner;

use Core\Design;
use Core\Http\Controllers\Admin\AdminController;
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
            'siteKit' => app()->theme->label(), 'kits' => Importer::kits()]);
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
        } elseif ($start === 'kit' || $start === 'zip') {
            $src = $start === 'kit' ? Importer::fromKit((string) ($r->post['kit'] ?? '')) : Importer::fromZip(self::uploaded($r, 'zip', 20));
            if (!$src['ok']) return $this->to(KitDesigner::ADMIN, 'error', $src['message']);
            $label = $label !== '' ? $label : $src['label'];
            $id = Drafts::create($label, (string) ($r->post['name'] ?? '') ?: $src['name'], $src['data'], $src['note']);
            return $this->to(KitDesigner::ADMIN . '/' . $id, 'success', $src['message']);
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
                    'meta' => (array) ($r->post['meta'] ?? []), 'styles' => (array) ($r->post['styles'] ?? [])], (string) ($r->post['note'] ?? ''));
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
        $styles = Styles::normalize((array) ($r->post['styles'] ?? []));
        $pageId = (string) ($r->post['page'] ?? '');
        $mode = (string) ($r->post['mode'] ?? 'full');
        $source = (string) ($r->post['source'] ?? 'examples');
        return KitDesigner::withParent(function () use ($in, $extra, $styles, $pageId, $mode, $source): Response {
            $values = Design::normalize($in);
            $extraCss = trim(KitDesigner::extraCss($extra) . "\n" . Styles::css($styles, null, true));
            $checks = Styles::checks($styles, $values);
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
                    'darkCss' => $darkStyle, 'force' => $force, 'scheme' => $scheme, 'extraCss' => $extraCss, 'checks' => $checks]);
            }
            Design::override($values);
            $items = $missing = [];
            if ($mode === 'gallery') {
                $g = Gallery::build($source, $styles['sections']);
                [$items, $missing] = [$g['items'], $g['missing']];
                try {
                    $html = Gallery::page($g['blocks']);
                } catch (\RuntimeException $e) {
                    return Response::fail($e->getMessage(), 404);
                }
                $page = ['id' => 0];
            } else {
                $page = ctype_digit($pageId) ? Pages::find((int) $pageId) : null;
                $page ??= Pages::home();
                if (!$page) return Response::fail(__('Diese Website hat noch keine Startseite – legen Sie zuerst Inhalte an.'), 404);
                $html = (new SiteController())->previewHtml($page);
            }
            $html = self::inject($html, $extraCss);
            return Response::json(['ok' => true, 'light' => $html, 'dark' => DesignController::forceDark($html, $values), 'page' => (int) $page['id'],
                'darkCss' => $darkStyle, 'force' => $force, 'scheme' => $scheme, 'checks' => $checks, 'items' => $items, 'missing' => $missing]);
        });
    }

    /**
     * Eigenes CSS des Entwurfs dort einsetzen, wo das exportierte Kit es lädt: nach Grund-, Options- und Block-CSS, vor der
     * Design-Datei der Website (wie 'css' => ['css/kit.css']); ohne Design-Datei vor </head>.
     */
    public static function inject(string $html, string $extraCss): string
    {
        $style = '<style data-kd-extra>' . str_ireplace('</style', '<\/style', $extraCss) . '</style>';
        $n = 0;
        $out = preg_replace('~(<style data-design-preview>|<link rel="stylesheet" href="[^"]*/design/design-[^"]*">)~', $style . '$1', $html, 1, $n);
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
        if ($action === 'fixpair') {
            $fg = Palette::hex((string) ($r->post['fg'] ?? ''));
            $bg = Palette::hex((string) ($r->post['bg'] ?? ''));
            if ($fg === null || $bg === null) return Response::fail(__('Dieses Farbpaar gibt es nicht.'));
            $min = max(Palette::AA_LARGE, min(7.0, (float) ($r->post['min'] ?? Palette::AA_TEXT)));
            return Response::json(['ok' => true, 'value' => Palette::fix($fg, $bg, $min)]);
        }
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

    // ------------------------------------------------------------------ Vorschau-Links (teilen)

    /** Vorschau-Link für die zuletzt gespeicherte Version anlegen – Token nur in dieser Antwort */
    public function shareCreate(Request $r, string $id): Response
    {
        $d = $this->draftOr404($id);
        $u = app()->auth->user();
        $res = Shares::create($d['id'], (int) $d['version'], (int) ($r->post['days'] ?? 7), (string) ($r->post['note'] ?? ''), (string) (($u['name'] ?? '') ?: ($u['email'] ?? '')));
        return Response::json(['ok' => true, 'url' => absolute_url(Shares::PATH . '/' . $res['token']), 'expires_at' => $res['expires_at'], 'version' => (int) $d['version'],
            'shares' => Shares::list($d['id']), 'message' => __('Vorschau-Link angelegt – er wird nur jetzt angezeigt.')]);
    }

    public function shareRevoke(Request $r, string $id, string $sid): Response
    {
        $d = $this->draftOr404($id);
        if (!ctype_digit($sid)) throw new HttpException(404);
        Shares::revoke($d['id'], (int) $sid);
        return Response::json(['ok' => true, 'shares' => Shares::list($d['id']), 'message' => __('Vorschau-Link widerrufen.')]);
    }

    /**
     * Öffentliche Vorschau (ohne Anmeldung, nur lesend): Seite der Website mit dem Stand der freigegebenen Version, hell oder
     * dunkel, Telefon/Tablet/Desktop – in einem Rahmen ohne Skripte. Ungültige Tokens: 404, mit Begrenzung je IP.
     */
    public function shareView(Request $r, string $token): Response
    {
        if (!KitDesigner::on() || !KitDesigner::parentAvailable()) throw new HttpException(404);
        $limiter = new \Core\RateLimiter(app()->db);
        $key = 'kd-share:' . $r->ip();
        if ($limiter->tooMany($key, 30, 900)) return AdminController::secure(new Response(e(__('Zu viele Versuche. Bitte später erneut.')), 429, ['Content-Type' => 'text/plain; charset=utf-8']));
        $share = Shares::find($token);
        $draft = $share ? Drafts::find((int) $share['draft_id']) : null;
        $ver = $draft ? Drafts::version($draft['id'], (int) $share['version']) : null;
        if (!$share || !$draft || !$ver) {
            $limiter->hit($key);
            return AdminController::secure(new Response(e(__('Dieser Vorschau-Link ist ungültig oder abgelaufen.')), 404, ['Content-Type' => 'text/plain; charset=utf-8']))
                ->header('Referrer-Policy', 'no-referrer');
        }
        Shares::touch((int) $share['id']);
        $data = $ver['data'];
        $pages = array_values(array_filter(Pages::published(), fn($p) => ($p['type'] ?? 'page') === 'page'));
        $pageId = (int) ($r->query['seite'] ?? 0);
        $page = null;
        foreach ($pages as $p) if ((int) $p['id'] === $pageId) $page = $p;
        $page ??= Pages::home() ?? ($pages[0] ?? null);
        $dark = ($r->query['modus'] ?? '') === 'dunkel';
        $dev = in_array($r->query['geraet'] ?? '', ['telefon', 'tablet', 'desktop'], true) ? (string) $r->query['geraet'] : 'desktop';
        $html = '';
        if ($page) {
            $html = KitDesigner::withParent(function () use ($data, $page, $dark): string {
                $values = Design::normalize((array) $data['values']);
                Design::override($values);
                $h = self::inject((new SiteController())->previewHtml($page), trim(KitDesigner::extraCss((array) $data['extra']) . "\n" . Styles::css((array) ($data['styles'] ?? []))));
                $manual = (array) (Design::def()['dark']['manual'] ?? []);
                $cls = (string) ($manual[$dark ? 'dark' : 'light'] ?? '');
                if ($dark) $h = DesignController::forceDark($h, $values);
                if ($cls !== '') $h = (string) preg_replace('~<html\b([^>]*)\bclass="([^"]*)"~i', '<html$1class="$2 ' . $cls . '"', $h, 1);
                // nur lesend: ohne Skripte (der Rahmen erlaubt sie ohnehin nicht)
                return (string) preg_replace('~<script\b[^>]*>.*?</script>~is', '', $h);
            });
        }
        $view = \Core\Theme::capture(KitDesigner::dir() . '/views/share.php', ['draft' => $draft, 'share' => $share, 'pages' => $pages, 'page' => $page, 'dark' => $dark,
            'dev' => $dev, 'html' => $html, 'self' => Shares::PATH . '/' . $token]);
        return AdminController::secure(new Response($view))->header('Referrer-Policy', 'no-referrer');
    }

    // ------------------------------------------------------------------ Daten für die Arbeitsfläche

    /** Nutzt die Website fluid oder ein Kind-Kit davon? (dann passt „Design dieser Website“ als Start) */
    private static function siteUsesParent(): bool
    {
        return isset(app()->theme->chain[KitDesigner::PARENT]);
    }

    private static function uploaded(Request $r, string $field = 'file', int $mb = 2): ?string
    {
        $f = $r->files[$field] ?? null;
        if (!is_array($f) || ($f['error'] ?? 1) !== UPLOAD_ERR_OK || (int) ($f['size'] ?? 0) > $mb * 1024 * 1024) return null;
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
                'contrast' => $t['contrast'] ?? null, 'markup' => !empty($t['markup']) ? true : null, 'group' => $t['group'] ?? null, 'var' => $t['var'] ?? null,
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
        $blocks = [];
        foreach ((array) (KitDesigner::parentTheme()->def['blocks'] ?? []) as $type => $b) {
            if (!is_array($b)) continue;
            $blocks[(string) $type] = ['label' => $tr((string) ($b['label'] ?? $type)), 'variants' => array_map(fn($l) => $tr((string) $l), (array) ($b['variants'] ?? []))];
        }
        return [
            'draft' => ['id' => $d['id'], 'name' => $d['name'], 'label' => $d['label'], 'version' => $d['version'],
                'values' => KitDesigner::normalize((array) $d['data']['values']), 'extra' => KitDesigner::normalizeExtra((array) $d['data']['extra']),
                'auto' => (object) $d['data']['auto'], 'meta' => (object) $d['data']['meta'], 'styles' => Styles::normalize((array) ($d['data']['styles'] ?? []))],
            'catalog' => Styles::catalog(), 'blocks' => $blocks, 'lineHeightToken' => KitDesigner::lineHeightToken(),
            'backgrounds' => array_map($tr, (array) (KitDesigner::parentTheme()->def['backgrounds'] ?? [])), 'shares' => Shares::list($d['id']), 'shareDays' => Shares::DAYS,
            'tokens' => $tokens, 'defaults' => KitDesigner::defaults(), 'fonts' => $fonts, 'presets' => $presets, 'pages' => $pages,
            'markup' => KitDesigner::markupTokens(), 'extra' => KitDesigner::EXTRA, 'versions' => Drafts::versions($d['id']),
            'siteKit' => app()->theme->name, 'siteUsesParent' => self::siteUsesParent(),
            'canInstall' => KitPackages::canManage(), 'kitExists' => \Core\Kit::source($d['name']), 'kitOwn' => Exporter::ownUpload($d['name']),
            'aa' => ['text' => Palette::AA_TEXT, 'ui' => Palette::AA_LARGE],
            'urls' => ['preview' => url(KitDesigner::ADMIN . '/api/vorschau'), 'palette' => url(KitDesigner::ADMIN . '/api/farben'),
                'save' => $base . '/speichern', 'version' => $base . '/version/', 'tokensExport' => $base . '/tokens.json', 'tokensImport' => $base . '/tokens',
                'zipStudio' => $base . '/export/studio', 'zipRedaxo' => $base . '/export/redaxo', 'install' => $base . '/installieren', 'kits' => url('/admin/system/kits'),
                'share' => $base . '/teilen', 'shareRevoke' => $base . '/teilen/'],
        ];
    }
}
