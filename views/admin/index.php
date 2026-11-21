<?php
/**
 * Übersicht der Entwürfe + neuer Entwurf.
 * @var list<array> $drafts  @var array $presets  @var bool $siteFluid  @var string $siteKit
 */
use Klxm\KitDesigner\KitDesigner as K;

echo \Core\Theme::capture(__DIR__ . '/_head.php', ['h1' => __('Kit-Designer'),
    'lead' => __('Kits gestalten wie in einem Design-Werkzeug: Farben, Schriften und Formen festlegen, auf echten Seiten prüfen und als Kind-Kit von fluid für KLXM Studio oder REDAXO ausgeben. Die Website ändert sich dabei nicht.')]);
?>
<?php if ($drafts): ?>
<section class="adm-card" aria-labelledby="kd-list-h">
  <h2 id="kd-list-h"><?= e(__('Entwürfe')) ?></h2>
  <table class="adm-table kd-list">
    <thead><tr><th scope="col"><?= e(__('Titel')) ?></th><th scope="col"><?= e(__('Technischer Name')) ?></th><th scope="col"><?= e(__('Version')) ?></th><th scope="col"><?= e(__('Geändert')) ?></th><th scope="col"><span class="kd-sr"><?= e(__('Aktionen')) ?></span></th></tr></thead>
    <tbody>
    <?php foreach ($drafts as $d): $v = (array) ($d['data']['values'] ?? []); ?>
      <tr>
        <td><a class="kd-list__link" href="<?= e(url(K::ADMIN . '/' . $d['id'])) ?>">
          <span class="kd-swatches" aria-hidden="true"><?php foreach (['accent', 'secondary', 'highlight', 'ink', 'background'] as $c): if (!empty($v[$c])): ?><span style="background:<?= e((string) $v[$c]) ?>"></span><?php endif; endforeach; ?></span>
          <?= e($d['label']) ?></a></td>
        <td><code><?= e($d['name']) ?></code><?php if (\Core\Kit::source($d['name']) !== null): ?> <span class="adm-badge"><?= e(__('installiert')) ?></span><?php endif; ?></td>
        <td><?= (int) $d['version'] ?></td>
        <td><?= e(fmt()->datetime($d['updated_at'])) ?></td>
        <td class="kd-list__act">
          <form method="post" action="<?= e(url(K::ADMIN . '/' . $d['id'] . '/loeschen')) ?>" data-confirm="<?= e(__('Entwurf „{label}“ mit allen Versionen löschen? Bereits exportierte Kits bleiben erhalten.', ['label' => $d['label']])) ?>">
            <?= csrf_field() ?>
            <button class="adm-btn adm-btn--ghost adm-btn--small"><?= icon('trash') ?> <?= e(__('Löschen')) ?><span class="kd-sr"> – <?= e($d['label']) ?></span></button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    </tbody>
  </table>
</section>
<?php else: ?>
<p class="adm-muted"><?= e(__('Noch keine Entwürfe. Legen Sie den ersten unten an.')) ?></p>
<?php endif; ?>

<section class="adm-card" id="kd-neu" aria-labelledby="kd-new-h">
  <h2 id="kd-new-h"><?= e(__('Neuer Entwurf')) ?></h2>
  <form method="post" action="<?= e(url(K::ADMIN . '/neu')) ?>" enctype="multipart/form-data" class="kd-form">
    <?= csrf_field() ?>
    <div class="kd-form__row">
      <div class="f"><label for="kd-n-label"><?= e(__('Titel des Kits')) ?></label><input id="kd-n-label" name="label" maxlength="120" required placeholder="<?= e(__('z. B. Kanzlei Nord')) ?>"></div>
      <div class="f"><label for="kd-n-name"><?= e(__('Technischer Name (optional)')) ?></label><input id="kd-n-name" name="name" maxlength="30" pattern="[a-z][a-z0-9\-]{1,29}" aria-describedby="kd-n-name-h" placeholder="kanzlei-nord">
        <p class="f-help" id="kd-n-name-h"><?= e(__('Ordnername des Kits: Kleinbuchstaben, Ziffern, Bindestrich. Leer = aus dem Titel.')) ?></p></div>
    </div>
    <fieldset class="f kd-start"><legend><?= e(__('Ausgangspunkt')) ?></legend>
      <label class="kd-start__opt"><input type="radio" name="start" value="preset" checked data-kd-start> <?= e(__('Vorlage von fluid')) ?></label>
      <div class="kd-start__sub"><label for="kd-n-preset" class="kd-sr"><?= e(__('Vorlage')) ?></label>
        <select id="kd-n-preset" name="preset"><?php foreach ($presets as $k => $p): ?><option value="preset:<?= e($k) ?>"><?= e(__($p['label'])) ?></option><?php endforeach; ?></select></div>
      <?php if ($siteFluid): ?><label class="kd-start__opt"><input type="radio" name="start" value="site"> <?= e(__('Aktuelles Design dieser Website ({kit})', ['kit' => $siteKit])) ?></label><?php endif; ?>
      <label class="kd-start__opt"><input type="radio" name="start" value="import"> <?= e(__('Design-Tokens importieren (JSON, z. B. aus Figma/Tokens Studio)')) ?></label>
      <div class="kd-start__sub"><label for="kd-n-file"><?= e(__('Datei')) ?></label> <input type="file" id="kd-n-file" name="file" accept=".json,application/json"></div>
    </fieldset>
    <button class="adm-btn adm-btn--primary"><?= icon('plus') ?> <?= e(__('Entwurf anlegen')) ?></button>
  </form>
</section>

<section class="adm-card kd-about" aria-labelledby="kd-about-h">
  <h2 id="kd-about-h"><?= e(__('So entsteht ein Kit')) ?></h2>
  <ol>
    <li><?= e(__('Farben, Schriften, Abstände und Formen festlegen – die Kontrastprüfung läuft mit, die Dunkel-Fassung entsteht auf Wunsch automatisch.')) ?></li>
    <li><?= e(__('Auf echten Seiten dieser Website prüfen: Telefon, Tablet und Desktop, hell und dunkel nebeneinander.')) ?></li>
    <li><?= e(__('Speichern (jede Speicherung ist eine Version) und exportieren: als Kind-Kit von fluid für KLXM Studio oder für REDAXO fluid.')) ?></li>
  </ol>
</section>
