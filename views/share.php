<?php
/**
 * Öffentliche Vorschau eines Entwurfs (Vorschau-Link): nur lesend, ohne Skripte, nicht indexierbar. Seite, hell/dunkel und Gerät
 * wählt man über Links bzw. ein Formular mit GET. Die Seite selbst steht in einem Rahmen (srcdoc, sandbox ohne Skripte).
 * @var array $draft  @var array $share  @var list<array> $pages  @var ?array $page  @var bool $dark  @var string $dev  @var string $html  @var string $self
 */
$w = ['telefon' => 390, 'tablet' => 820, 'desktop' => 1280][$dev];
$base = ['seite' => (int) ($page['id'] ?? 0), 'modus' => $dark ? 'dunkel' : 'hell', 'geraet' => $dev];
$q = fn(array $o) => e(url($self) . '?' . http_build_query(array_replace($base, $o)));
$lang = \Core\Lang::current();
?><!doctype html>
<html lang="<?= e($lang) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title><?= e(__('Vorschau: {label}', ['label' => $draft['label']])) ?></title>
<style>
  :root { color-scheme: light dark; --bg: #F4F5F7; --card: #FFFFFF; --ink: #1B1F24; --t2: #4A5260; --line: #D9DDE3; --a: #1D4ED8; }
  @media (prefers-color-scheme: dark) { :root { --bg: #14171C; --card: #1E232A; --ink: #EEF1F5; --t2: #B4BCC8; --line: #343B45; --a: #8AB4FF; } }
  * { box-sizing: border-box; }
  body { margin: 0; background: var(--bg); color: var(--ink); font: 400 15px/1.45 Lato, ui-sans-serif, system-ui, -apple-system, "Segoe UI", sans-serif; }
  header { display: flex; flex-wrap: wrap; gap: 10px 18px; align-items: center; padding: 10px 16px; background: var(--card); border-bottom: 1px solid var(--line); }
  h1 { margin: 0; font-size: 16px; font-weight: 700; }
  .meta { color: var(--t2); font-size: 13px; }
  nav { display: flex; gap: 4px; flex-wrap: wrap; }
  nav a { color: var(--ink); text-decoration: none; padding: 4px 10px; border-radius: 6px; border: 1px solid var(--line); font-size: 13px; }
  nav a[aria-current] { background: var(--ink); color: var(--card); border-color: var(--ink); }
  nav a:focus-visible, select:focus-visible, button:focus-visible { outline: 2px solid var(--a); outline-offset: 2px; }
  form { display: flex; gap: 6px; align-items: center; margin: 0; font-size: 13px; }
  select, button { font: inherit; min-height: 30px; border-radius: 6px; border: 1px solid var(--line); background: var(--card); color: var(--ink); padding: 0 8px; }
  main { padding: 16px; display: grid; justify-items: center; }
  iframe { width: min(100%, <?= (int) $w ?>px); height: calc(100dvh - 120px); border: 0; border-radius: 8px; background: #fff; box-shadow: 0 0 0 1px var(--line), 0 12px 30px -14px rgb(0 0 0 / .4); }
  .note { color: var(--t2); font-size: 12px; margin: 8px 0 0; text-align: center; }
</style>
</head>
<body>
<header>
  <h1><?= e($draft['label']) ?> <span class="meta">· <?= e(__('Version {v}', ['v' => (int) $share['version']])) ?> · <?= e(__('nur lesend, gültig bis {date}', ['date' => fmt()->datetime((string) $share['expires_at'])])) ?></span></h1>
  <form method="get" action="<?= e(url($self)) ?>">
    <label for="kd-s-page"><?= e(__('Seite')) ?></label>
    <select id="kd-s-page" name="seite"><?php foreach ($pages as $p): ?><option value="<?= (int) $p['id'] ?>"<?= (int) $p['id'] === (int) ($page['id'] ?? 0) ? ' selected' : '' ?>><?= e((string) $p['title']) ?></option><?php endforeach; ?></select>
    <input type="hidden" name="modus" value="<?= $dark ? 'dunkel' : 'hell' ?>"><input type="hidden" name="geraet" value="<?= e($dev) ?>">
    <button type="submit"><?= e(__('Anzeigen')) ?></button>
  </form>
  <nav aria-label="<?= e(__('Farbschema')) ?>">
    <a href="<?= $q(['modus' => 'hell']) ?>"<?= !$dark ? ' aria-current="true"' : '' ?>><?= e(__('Hell')) ?></a>
    <a href="<?= $q(['modus' => 'dunkel']) ?>"<?= $dark ? ' aria-current="true"' : '' ?>><?= e(__('Dunkel')) ?></a>
  </nav>
  <nav aria-label="<?= e(__('Gerät')) ?>">
    <?php foreach (['telefon' => __('Telefon'), 'tablet' => __('Tablet'), 'desktop' => __('Desktop')] as $k => $l): ?>
    <a href="<?= $q(['geraet' => $k]) ?>"<?= $dev === $k ? ' aria-current="true"' : '' ?>><?= e($l) ?></a>
    <?php endforeach; ?>
  </nav>
</header>
<main>
  <?php if ($html !== ''): ?>
  <iframe title="<?= e(__('Vorschau der Seite „{title}“', ['title' => (string) ($page['title'] ?? '')])) ?>" sandbox="allow-same-origin" srcdoc="<?= e($html) ?>"></iframe>
  <p class="note"><?= e(__('Vorschau eines Kit-Entwurfs mit Kit-Designer – die Website selbst ist unverändert. Menüs und Schalter sind in der Vorschau ohne Funktion.')) ?></p>
  <?php else: ?>
  <p><?= e(__('Diese Website hat noch keine veröffentlichte Seite.')) ?></p>
  <?php endif; ?>
</main>
</body>
</html>
