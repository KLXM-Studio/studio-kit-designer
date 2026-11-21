<?php
// SPDX-License-Identifier: MIT
// KLXM Studio – Kit-Designer. Copyright (C) 2026 KLXM and contributors (see LICENSE)
/**
 * Erweiterung „kit_designer“: Kits visuell gestalten – Farben mit Kontrastprüfung und Dunkel-Vorschlag, Schriften, Skala,
 * Abstände, Formen; Vorschau mit echten Seiten auf Telefon, Tablet und Desktop, hell und dunkel; Design-Tokens im W3C-Format
 * (DTCG) tauschen; Export als Kind-Kit von fluid für KLXM Studio und für REDAXO fluid.
 *
 * Stufe 1: Eltern-Kit fluid, Design-Grundlagen. Stufe 2: Elemente in der Vorschau anklicken und je Zustand gestalten (überall, je Blocktyp,
 * je Variante), Ebenen, Komponenten-Galerie, Abschnitts-Stile, Rückgängig/Wiederholen, Vergleich, Vorschau-Links (öffentliche Route
 * /kit-designer/vorschau/{token}, nur lesend, befristet), Import exportierter Kits. Die Website ändert sich nie – erst ein exportiertes und
 * gewähltes Kit wirkt.
 * Einschalten je Website: Administration → Funktionen & Erweiterungen oder 'extensions' => ['kit_designer'].
 * Recht: „Kits gestalten“ (kit_designer.design, Standard nur Administration); direkt installieren nur mit Verwaltung der
 * Installation (Core\KitPackages::canManage). Doku: README.md, Handbuch und Entwicklerhandbuch → Kit-Designer.
 */
declare(strict_types=1);

spl_autoload_register(function (string $class): void {
    if (str_starts_with($class, 'Klxm\\KitDesigner\\')) {
        $file = __DIR__ . '/src/' . substr($class, strlen('Klxm\\KitDesigner\\')) . '.php';
        if (is_file($file)) require $file;
    }
});

use Klxm\KitDesigner\Controller;
use Klxm\KitDesigner\Drafts;
use Klxm\KitDesigner\KitDesigner as K;

return [
    'name' => 'kit_designer',
    'label' => 'Kit-Designer – Kits visuell gestalten',
    'version' => '0.2.0',
    'requires' => '>=1.0.0',
    'description' => 'Kits wie in einem Design-Werkzeug gestalten: Farben mit Kontrastprüfung, Schriften, Formen, Elemente in der Vorschau anklicken und gestalten (mit Zuständen), Komponenten-Galerie aller Blöcke, Abschnitts-Stile, Vergleich und Vorschau-Link, Export als Kind-Kit von fluid für KLXM Studio und REDAXO.',
    'author' => 'KLXM Crossmedia GmbH and contributors',
    'license' => 'MIT',
    'homepage' => 'https://studio.klxm.de',
    'provides' => ['Seite „Kit-Designer“ unter Werkzeuge', 'Recht „Kits gestalten“', 'Export Kind-Kit für KLXM Studio und REDAXO fluid', 'Vorschau-Links (nur lesend, befristet)', 'Befehl kit_designer:selftest'],
    'commands' => ['kit_designer:selftest'],
    'usage' => function (): ?string {
        try {
            $n = Drafts::count();
        } catch (\Throwable) {
            return null;
        }
        return $n ? __('{n} Entwürfe', ['n' => $n]) : null;
    },
    'boot' => function (Core\Extension $x): void {
        $x->permissions('Kit-Designer', [K::PERM => 'Kits gestalten (Kit-Designer)']);
        foreach ([...Drafts::tables(), ...Klxm\KitDesigner\Shares::table()] as $name => $define) $x->table($name, $define);

        $x->adminPage(['href' => K::ADMIN, 'label' => 'Kit-Designer', 'kind' => 'tool', 'icon' => 'palette', 'perm' => K::PERM,
            'description' => 'Kits gestalten: Farben, Schriften, Formen, Vorschau, Export für Studio und REDAXO']);
        $x->adminAssets(fn(string $view): array => str_starts_with($view, K::NAME . '/')
            ? ['css/kit-designer.css', ...($view === K::NAME . '/edit' ? ['js/kit-designer.js'] : [])] : []);

        $x->routes(function (Core\Http\Router $r): void {
            $c = Controller::class;
            $p = K::PERM;
            $r->get(K::ADMIN, [$c, 'index'], $p);
            $r->post(K::ADMIN . '/neu', [$c, 'create'], $p);
            $r->post(K::ADMIN . '/api/vorschau', [$c, 'preview'], $p);
            $r->post(K::ADMIN . '/api/farben', [$c, 'palette'], $p);
            $r->get(K::ADMIN . '/{id}', [$c, 'edit'], $p);
            $r->post(K::ADMIN . '/{id}/speichern', [$c, 'save'], $p);
            $r->get(K::ADMIN . '/{id}/version/{v}', [$c, 'version'], $p);
            $r->post(K::ADMIN . '/{id}/loeschen', [$c, 'delete'], $p);
            $r->get(K::ADMIN . '/{id}/tokens.json', [$c, 'tokensExport'], $p);
            $r->post(K::ADMIN . '/{id}/tokens', [$c, 'tokensImport'], $p);
            $r->get(K::ADMIN . '/{id}/export/{target}', [$c, 'exportZip'], $p);
            $r->post(K::ADMIN . '/{id}/installieren', [$c, 'install'], $p);
            $r->post(K::ADMIN . '/{id}/teilen', [$c, 'shareCreate'], $p);
            $r->post(K::ADMIN . '/{id}/teilen/{sid}/widerrufen', [$c, 'shareRevoke'], $p);
            // Vorschau-Link: ohne Anmeldung, nur lesend, Token mit Ablauf (Klxm\KitDesigner\Shares)
            $r->get(Klxm\KitDesigner\Shares::PATH . '/{token}', [$c, 'shareView']);
        });

        $x->docs('manual', ['kit_designer' => ['id' => 'kit-designer', 'title' => 'Kits gestalten (Kit-Designer)', 'after' => 'baukasten', 'file' => K::dir() . '/docs/manual.php']]);
        $x->docs('technical', ['kit_designer' => ['title' => 'Kit-Designer (Erweiterung)', 'part' => 2, 'after' => 'kits', 'file' => K::dir() . '/docs/technical.php']]);

        $x->health(fn(): array => K::parentAvailable() ? [] : [__('Kit-Designer: Das Kit fluid fehlt – ohne Eltern-Kit lassen sich keine Entwürfe gestalten.') => null]);

        $x->command('kit_designer:selftest', 'Kit-Designer: Token-Import/-Export (DTCG), Dunkel-Vorschlag, Kontrastprüfung, Kit-Export (Studio + REDAXO), kit:check',
            fn(array $args): int => Klxm\KitDesigner\SelfTest::run($args));
    },
];
