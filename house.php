<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Flash;
use App\Repositories\HouseRepository;
use App\Repositories\RoomRepository;
use function App\Core\e;
use function App\Core\floorLabel;
use function App\Core\redirect;
use function App\Core\t;

Auth::requireAuth();
$userId = (int) Auth::userId();
$houseId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

$houseRepo = new HouseRepository();
$roomRepo = new RoomRepository();

$house = $houseRepo->findForUser($houseId, $userId);
if ($house === null) {
  Flash::set('error', t('error.house_not_found'));
    redirect('/houses.php');
}

$rooms = $roomRepo->allForHouse($houseId, $userId);
$roomCount = count($rooms);
$materialCount = array_sum(array_map(static fn(array $room): int => (int) ($room['material_count'] ?? 0), $rooms));

$floorOrder = ['Begane Grond', 'Eerste Verdieping', 'Tweede Verdieping', 'Zolder', 'Kelder'];
$roomsByFloor = [];
foreach ($rooms as $room) {
  $floor = (string) ($room['floor'] ?? 'Onbekend');
  $roomsByFloor[$floor][] = $room;
}

$title = t('house.title');
$active = 'houses';
require __DIR__ . '/partials/layout_start.php';
?>
<section class="page-title">
  <div>
    <h2><?= e((string) $house['name']) ?></h2>
    <p><?= e(t('house.subtitle')) ?></p>
  </div>
  <div style="display:flex;gap:.6rem;flex-wrap:wrap;">
    <a class="btn primary" href="/room_form.php?house_id=<?= (int) $house['id'] ?>"><?= e(t('house.create_room')) ?></a>
    <a class="btn primary" href="/material_form.php"><?= e(t('house.add_material')) ?></a>
    <a class="btn warm" href="/export_house.php?house_id=<?= (int) $house['id'] ?>"><?= e(t('house.pdf_export')) ?></a>
  </div>
</section>

<section class="split">
  <article class="card">
    <h3 style="margin-top: 0;"><?= e(t('common.rooms')) ?></h3>
    <div class="table-wrap">
    <table class="table">
      <thead>
        <tr>
          <th><?= e(t('common.room')) ?></th>
          <th class="hide-mobile"><?= e(t('common.floor')) ?></th>
          <th><?= e(t('common.materials')) ?></th>
          <th><?= e(t('common.actions')) ?></th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($floorOrder as $floor): ?>
          <?php if (!isset($roomsByFloor[$floor])): continue; endif; ?>
          <tr>
            <td colspan="4"><strong><?= e(floorLabel($floor)) ?></strong></td>
          </tr>
          <?php foreach ($roomsByFloor[$floor] as $room): ?>
            <tr>
              <td><a href="/room.php?id=<?= (int) $room['id'] ?>"><?= e((string) $room['name']) ?></a></td>
              <td class="hide-mobile"><?= e(floorLabel((string) $room['floor'])) ?></td>
              <td><?= (int) $room['material_count'] ?></td>
              <td class="actions">
                <a class="chip" href="/room_form.php?id=<?= (int) $room['id'] ?>"><?= e(t('common.edit')) ?></a>
                <form method="post" action="/room_delete.php" class="inline-form" onsubmit="return confirm('<?= e(t('confirm.delete_room')) ?>');">
                  <?= \App\Core\Csrf::field() ?>
                  <input type="hidden" name="room_id" value="<?= (int) $room['id'] ?>" />
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
        <?php endforeach; ?>
        <?php if ($rooms === []): ?>
          <tr><td colspan="4"><?= e(t('house.no_rooms')) ?></td></tr>
        <?php endif; ?>
      </tbody>
    </table>
    </div>
  </article>

  <aside class="card">
    <h3 style="margin-top: 0;"><?= e(t('house.details')) ?></h3>
    <div class="grid" style="margin-top: 0.8rem;">
      <div class="chip"><?= e(t('common.location')) ?>: <?= e((string) $house['city']) ?></div>
      <div class="chip"><?= e(t('common.rooms')) ?>: <?= $roomCount ?></div>
      <div class="chip"><?= e(t('common.materials')) ?>: <?= $materialCount ?></div>
      <div class="chip"><?= e(t('common.last_updated')) ?>: <?= e((string) $house['updated_at']) ?></div>
    </div>
  </aside>
</section>
<?php require __DIR__ . '/partials/layout_end.php';
