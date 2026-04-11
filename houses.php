<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Repositories\HouseRepository;
use function App\Core\e;
use function App\Core\t;

Auth::requireAuth();
$userId = Auth::userId();

$repo = new HouseRepository();
$houses = $repo->allForUser((int) $userId);
$stats = $repo->statsForUser((int) $userId);

$title = t('houses.title');
$active = 'houses';
require __DIR__ . '/partials/layout_start.php';
?>
<section class="page-title">
  <div>
    <h2><?= e(t('houses.heading')) ?></h2>
    <p><?= e(t('houses.subtitle')) ?></p>
  </div>
  <a class="btn primary" href="/house_form.php"><?= e(t('houses.create')) ?></a>
</section>

<section class="grid cols-3" style="margin-bottom: 1rem;">
  <article class="card kpi">
    <span class="muted"><?= e(t('houses.total_houses')) ?></span>
    <strong><?= (int) $stats['houses'] ?></strong>
  </article>
  <article class="card kpi">
    <span class="muted"><?= e(t('houses.total_rooms')) ?></span>
    <strong><?= (int) $stats['rooms'] ?></strong>
  </article>
  <article class="card kpi">
    <span class="muted"><?= e(t('houses.total_materials')) ?></span>
    <strong><?= (int) $stats['materials'] ?></strong>
  </article>
</section>

<section class="card">
  <div class="table-wrap">
  <table class="table">
    <thead>
      <tr>
        <th><?= e(t('common.house')) ?></th>
        <th class="hide-mobile"><?= e(t('common.location')) ?></th>
        <th><?= e(t('common.rooms')) ?></th>
        <th class="hide-mobile"><?= e(t('common.last_updated')) ?></th>
        <th><?= e(t('common.actions')) ?></th>
      </tr>
    </thead>
    <tbody>
    <?php foreach ($houses as $house): ?>
      <tr>
        <td><a href="/house.php?id=<?= (int) $house['id'] ?>"><?= e((string) $house['name']) ?></a></td>
        <td class="hide-mobile"><?= e((string) $house['city']) ?></td>
        <td><?= (int) $house['room_count'] ?></td>
        <td class="hide-mobile"><?= e((string) $house['updated_at']) ?></td>
        <td class="actions">
          <a class="btn ghost" href="/house_form.php?id=<?= (int) $house['id'] ?>" style="padding:0.3rem 0.7rem;font-size:0.82rem;"><?= e(t('common.edit')) ?></a>
          <form method="post" action="/house_delete.php" class="inline-form" onsubmit="return confirm('<?= e(t('confirm.delete_house')) ?>');">
            <?= \App\Core\Csrf::field() ?>
            <input type="hidden" name="house_id" value="<?= (int) $house['id'] ?>" />
            <button class="btn danger icon-btn" type="submit" title="<?= e(t('common.delete')) ?>" aria-label="<?= e(t('common.delete')) ?>">
              <svg class="trash-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z" fill="currentColor"></path>
              </svg>
            </button>
          </form>
        </td>
      </tr>
    <?php endforeach; ?>
    <?php if ($houses === []): ?>
      <tr>
        <td colspan="5"><?= e(t('houses.empty')) ?></td>
      </tr>
    <?php endif; ?>
    </tbody>
  </table>
  </div>
</section>
<?php require __DIR__ . '/partials/layout_end.php';
