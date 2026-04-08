<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;
use App\Repositories\HouseRepository;
use App\Repositories\RoomRepository;
use function App\Core\e;
use function App\Core\floorOptions;
use function App\Core\isPost;
use function App\Core\redirect;
use function App\Core\t;

Auth::requireAuth();
$userId = (int) Auth::userId();

$roomRepo = new RoomRepository();
$houseRepo = new HouseRepository();

$roomId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit = $roomId !== null;

$houses = $houseRepo->allForUser($userId);
if ($houses === []) {
  Flash::set('error', t('error.create_house_first'));
    redirect('/house_form.php');
}

$room = [
    'house_id' => isset($_GET['house_id']) ? (int) $_GET['house_id'] : (int) $houses[0]['id'],
    'name' => '',
    'floor' => 'Begane Grond',
    'notes' => '',
];

if ($isEdit) {
    $existing = $roomRepo->findForUser((int) $roomId, $userId);
    if ($existing === null) {
    Flash::set('error', t('error.room_not_found'));
        redirect('/houses.php');
    }
    $room = $existing;
}

$errors = [];

if (isPost()) {
    try {
        Csrf::validate($_POST['_csrf'] ?? null);

        $houseId = (int) ($_POST['house_id'] ?? 0);
        $selectedHouse = $houseRepo->findForUser($houseId, $userId);
        if ($selectedHouse === null) {
          throw new RuntimeException(t('error.invalid_house_selection'));
        }

        $payload = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'floor' => trim((string) ($_POST['floor'] ?? '')),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ];

        $errors = Validator::room($payload);
        if ($errors === []) {
            if ($isEdit) {
                $roomRepo->update((int) $roomId, $payload);
            Flash::set('success', t('flash.room_updated'));
                redirect('/house.php?id=' . (int) $room['house_id']);
            }

            $newRoomId = $roomRepo->create($houseId, $payload);
          Flash::set('success', t('flash.room_created'));

            if (isset($_POST['save_and_material'])) {
                redirect('/material_form.php?room_id=' . $newRoomId);
            }

            redirect('/house.php?id=' . $houseId);
        }

        $room = array_merge($room, $payload, ['house_id' => $houseId]);
    } catch (Throwable $e) {
        Flash::set('error', $e->getMessage());
    }
}

$title = $isEdit ? t('room_form.edit_title') : t('room_form.create_title');
$active = 'houses';
require __DIR__ . '/partials/layout_start.php';
?>
<section class="page-title">
  <div>
    <h2><?= e($isEdit ? t('room_form.edit_title') : t('room_form.create_title')) ?></h2>
    <p><?= e(t('room_form.subtitle')) ?></p>
  </div>
  <a class="btn ghost" href="/house.php?id=<?= (int) $room['house_id'] ?>"><?= e(t('common.back_to_house')) ?></a>
</section>

<section class="card">
  <form action="" method="post" novalidate>
    <?= Csrf::field() ?>

    <div class="grid cols-2">
      <div class="field">
        <label for="name"><?= e(t('room_form.name')) ?></label>
        <input id="name" name="name" type="text" required value="<?= e((string) $room['name']) ?>" />
        <?php if (isset($errors['name'])): ?><p class="error-text"><?= e($errors['name']) ?></p><?php endif; ?>
      </div>

      <div class="field">
        <label for="house_id"><?= e(t('common.house')) ?></label>
        <select id="house_id" name="house_id" required>
          <?php foreach ($houses as $house): ?>
            <option value="<?= (int) $house['id'] ?>" <?= (int) $room['house_id'] === (int) $house['id'] ? 'selected' : '' ?>>
              <?= e((string) $house['name']) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>
    </div>

    <div class="grid">
      <div class="field">
        <label for="floor"><?= e(t('common.floor')) ?></label>
        <select id="floor" name="floor">
          <?php foreach (floorOptions() as $floorValue => $floorLabel): ?>
            <option value="<?= e($floorValue) ?>" <?= (string) $room['floor'] === $floorValue ? 'selected' : '' ?>><?= e($floorLabel) ?></option>
          <?php endforeach; ?>
        </select>
        <?php if (isset($errors['floor'])): ?><p class="error-text"><?= e($errors['floor']) ?></p><?php endif; ?>
      </div>

    </div>

    <div class="field">
      <label for="notes"><?= e(t('common.notes')) ?></label>
      <textarea id="notes" name="notes"><?= e((string) $room['notes']) ?></textarea>
    </div>

    <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
      <button class="btn primary" type="submit"><?= e(t('common.save')) ?></button>
      <?php if (!$isEdit): ?>
        <button class="btn warm" type="submit" name="save_and_material" value="1"><?= e(t('room_form.save_and_add_material')) ?></button>
      <?php endif; ?>
      <a class="btn ghost" href="/house.php?id=<?= (int) $room['house_id'] ?>"><?= e(t('common.cancel')) ?></a>
    </div>
  </form>
</section>
<?php require __DIR__ . '/partials/layout_end.php';
