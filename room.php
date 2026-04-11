<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Flash;
use App\Repositories\MaterialRepository;
use App\Repositories\RoomRepository;
use function App\Core\e;
use function App\Core\floorLabel;
use function App\Core\materialTypeLabel;
use function App\Core\redirect;
use function App\Core\t;

Auth::requireAuth();
$userId = (int) Auth::userId();
$roomId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$roomRepo = new RoomRepository();
$materialRepo = new MaterialRepository();

$room = $roomRepo->findForUser($roomId, $userId);
if ($room === null) {
  Flash::set('error', t('error.room_not_found'));
    redirect('/houses.php');
}

$materials = $materialRepo->allForRoom($roomId, $userId);

$title = t('room.title');
$active = 'houses';
require __DIR__ . '/partials/layout_start.php';
?>
<section class="page-title">
  <div>
    <h2><?= e((string) $room['name']) ?></h2>
    <p><?= e(t('room.subtitle')) ?></p>
  </div>
  <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
    <a class="btn primary" href="/material_form.php?room_id=<?= (int) $room['id'] ?>"><?= e(t('room.add_material')) ?></a>
    <a class="btn ghost" href="/house.php?id=<?= (int) $room['house_id'] ?>"><?= e(t('common.back_to_house')) ?></a>
  </div>
</section>

<section class="split">
  <article class="card">
    <h3 style="margin-top: 0;"><?= e(t('common.materials')) ?></h3>
    <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th><?= e(t('common.name')) ?></th>
          <th><?= e(t('common.type')) ?></th>
          <th class="hide-mobile"><?= e(t('common.color')) ?></th>
          <th><?= e(t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($materials as $material): ?>
          <tr>
            <td><?= e((string) $material['name']) ?></td>
            <td><?= e(materialTypeLabel((string) $material['type'])) ?></td>
            <td class="hide-mobile">
              <?php if ((string) $material['color_hex'] !== ''): ?>
                <span
                  class="chip"
                  style="background: <?= e((string) $material['color_hex']) ?>; border-color: <?= e((string) $material['color_hex']) ?>; width: 2.4rem; min-height: 1.4rem; padding: 0;"
                  title="<?= e((string) $material['color_hex']) ?>"
                  aria-label="<?= e(t('common.color')) ?> <?= e((string) $material['color_hex']) ?>"
                ></span>
              <?php else: ?>
                -
              <?php endif; ?>
            </td>
            <td class="actions">
              <a class="chip" href="/material_form.php?id=<?= (int) $material['id'] ?>"><?= e(t('common.edit')) ?></a>
              <form method="post" action="/material_delete.php" class="inline-form" onsubmit="return confirm('<?= e(t('confirm.delete_material')) ?>');">
                <?= \App\Core\Csrf::field() ?>
                <input type="hidden" name="material_id" value="<?= (int) $material['id'] ?>" />
                <input type="hidden" name="house_id" value="<?= (int) $room['house_id'] ?>" />
                <input type="hidden" name="room_id" value="<?= (int) $room['id'] ?>" />
                <button class="btn danger icon-btn" type="submit" title="<?= e(t('common.delete')) ?>" aria-label="<?= e(t('common.delete')) ?>">
                  <svg class="trash-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                    <path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z" fill="currentColor"></path>
                  </svg>
                </button>
              </form>
            </td>
          </tr>
        <?php endforeach; ?>
        <?php if ($materials === []): ?>
          <tr><td colspan="4"><?= e(t('room.no_materials')) ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
  </article>

  <aside class="card">
    <h3 style="margin-top: 0;"><?= e(t('room.details')) ?></h3>
    <div class="stack" style="margin-top: 0.8rem;">
      <div class="chip"><?= e(t('common.house')) ?>: <?= e((string) $room['house_name']) ?></div>
      <div class="chip"><?= e(t('common.location')) ?>: <?= e((string) $room['house_city']) ?></div>
      <div class="chip"><?= e(t('common.floor')) ?>: <?= e(floorLabel((string) $room['floor'])) ?></div>
    </div>
    <?php if (trim((string) $room['notes']) !== ''): ?>
      <div style="margin-top: 1rem;">
        <h4 style="margin-bottom: 0.5rem;"><?= e(t('common.notes')) ?></h4>
        <p class="muted" style="margin: 0;"><?= nl2br(e((string) $room['notes'])) ?></p>
      </div>
    <?php endif; ?>
  </aside>
</section>
<?php require __DIR__ . '/partials/layout_end.php';