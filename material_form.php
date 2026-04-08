<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;
use App\Repositories\AttachmentRepository;
use App\Repositories\MaterialRepository;
use App\Repositories\RoomRepository;
use App\Services\AttachmentService;
use function App\Core\e;
use function App\Core\isPost;
use function App\Core\materialTypeOptions;
use function App\Core\parseLinks;
use function App\Core\redirect;
use function App\Core\t;

Auth::requireAuth();
$userId = (int) Auth::userId();

$materialRepo = new MaterialRepository();
$roomRepo = new RoomRepository();
$attachmentRepo = new AttachmentRepository();

$materialId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit = $materialId !== null;

$rooms = $roomRepo->allForUser($userId);
if ($rooms === []) {
  Flash::set('error', t('error.create_room_first'));
    redirect('/houses.php');
}

$material = [
    'room_id' => isset($_GET['room_id']) ? (int) $_GET['room_id'] : (int) $rooms[0]['id'],
    'type' => 'Verf',
    'name' => '',
    'color_hex' => '',
    'description' => '',
    'store_links' => '',
];

$attachments = [];
$currentHouseId = 0;
$currentRoomId = (int) $material['room_id'];

if ($isEdit) {
    $existing = $materialRepo->findForUser((int) $materialId, $userId);
    if ($existing === null) {
    Flash::set('error', t('error.material_not_found'));
        redirect('/houses.php');
    }

    $material = $existing;
    $links = $materialRepo->linksForMaterial((int) $materialId);
    $material['store_links'] = implode("\n", $links);
    $attachments = $attachmentRepo->listForMaterial((int) $materialId);
}

$selectedRoom = $roomRepo->findForUser((int) $material['room_id'], $userId);
if ($selectedRoom !== null) {
    $currentHouseId = (int) $selectedRoom['house_id'];
  $currentRoomId = (int) $selectedRoom['id'];
}

$errors = [];

if (isPost()) {
    try {
        Csrf::validate($_POST['_csrf'] ?? null);

        $roomId = (int) ($_POST['room_id'] ?? 0);
        $room = $roomRepo->findForUser($roomId, $userId);
        if ($room === null) {
          throw new RuntimeException(t('error.invalid_room_selection'));
        }

        $payload = [
            'room_id' => $roomId,
            'type' => trim((string) ($_POST['type'] ?? '')),
            'name' => trim((string) ($_POST['name'] ?? '')),
            'color_hex' => strtoupper(trim((string) ($_POST['color_hex'] ?? ''))),
            'description' => trim((string) ($_POST['description'] ?? '')),
            'store_links' => trim((string) ($_POST['store_links'] ?? '')),
        ];

        $errors = Validator::material($payload);
        if ($errors === []) {
            if ($isEdit) {
                $materialRepo->update((int) $materialId, $payload);
                $targetMaterialId = (int) $materialId;
            Flash::set('success', t('flash.material_updated'));
            } else {
                $targetMaterialId = $materialRepo->create($roomId, $payload);
            Flash::set('success', t('flash.material_created'));
            }

            $urls = parseLinks((string) $payload['store_links']);
            $materialRepo->replaceLinks($targetMaterialId, $urls);

            if (isset($_FILES['attachments'])) {
                (new AttachmentService())->handleUploads($targetMaterialId, $_FILES['attachments']);
            }

            redirect('/room.php?id=' . (int) $roomId);
        }

        $material = array_merge($material, $payload);
        $currentHouseId = (int) $room['house_id'];
          $currentRoomId = $roomId;
    } catch (Throwable $e) {
        Flash::set('error', $e->getMessage());
    }
}

$title = $isEdit ? t('material_form.edit_title') : t('material_form.create_title');
$active = 'houses';
require __DIR__ . '/partials/layout_start.php';
?>
<section class="page-title">
  <div>
    <h2><?= e($isEdit ? t('material_form.edit_title') : t('material_form.create_title')) ?></h2>
    <p><?= e(t('material_form.subtitle')) ?></p>
  </div>
  <?php if ($currentRoomId > 0): ?>
    <a class="btn ghost" href="/room.php?id=<?= $currentRoomId ?>"><?= e(t('common.back_to_room')) ?></a>
  <?php elseif ($currentHouseId > 0): ?>
    <a class="btn ghost" href="/house.php?id=<?= $currentHouseId ?>"><?= e(t('common.back_to_house')) ?></a>
  <?php endif; ?>
</section>

<section class="split">
  <article class="card">
    <form action="" method="post" enctype="multipart/form-data" novalidate>
      <?= Csrf::field() ?>

      <div class="grid cols-2">
        <div class="field">
          <label for="room_id"><?= e(t('common.room')) ?></label>
          <select id="room_id" name="room_id" required>
            <?php foreach ($rooms as $room): ?>
              <option value="<?= (int) $room['id'] ?>" <?= (int) $material['room_id'] === (int) $room['id'] ? 'selected' : '' ?>>
                <?= e((string) $room['house_name']) ?> - <?= e((string) $room['name']) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="field">
          <label for="type"><?= e(t('common.type')) ?></label>
          <select id="type" name="type">
            <?php foreach (materialTypeOptions() as $typeValue => $typeLabel): ?>
              <option value="<?= e($typeValue) ?>" <?= (string) $material['type'] === $typeValue ? 'selected' : '' ?>><?= e($typeLabel) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (isset($errors['type'])): ?><p class="error-text"><?= e($errors['type']) ?></p><?php endif; ?>
        </div>
      </div>

      <div class="field">
        <label for="name"><?= e(t('material_form.name')) ?></label>
        <input id="name" name="name" type="text" required value="<?= e((string) $material['name']) ?>" />
        <?php if (isset($errors['name'])): ?><p class="error-text"><?= e($errors['name']) ?></p><?php endif; ?>
      </div>

      <div class="grid cols-2">
        <div class="field">
          <label for="color_hex"><?= e(t('material_form.color_hex')) ?></label>
          <input id="color_hex" name="color_hex" type="text" value="<?= e((string) $material['color_hex']) ?>" placeholder="#E7DCC5" />
          <?php if (isset($errors['color_hex'])): ?><p class="error-text"><?= e($errors['color_hex']) ?></p><?php endif; ?>
        </div>
        <div class="field">
          <label for="color_picker"><?= e(t('material_form.color_picker')) ?></label>
          <input id="color_picker" type="color" value="<?= e((string) ($material['color_hex'] !== '' ? $material['color_hex'] : '#e7dcc5')) ?>" oninput="document.getElementById('color_hex').value=this.value.toUpperCase();" />
        </div>
      </div>

      <div class="field">
        <label for="description"><?= e(t('common.description')) ?></label>
        <textarea id="description" name="description"><?= e((string) $material['description']) ?></textarea>
      </div>

      <div class="field">
        <label for="store_links"><?= e(t('material_form.store_links')) ?></label>
        <textarea id="store_links" name="store_links"><?= e((string) $material['store_links']) ?></textarea>
      </div>

      <div class="field">
        <label for="attachments"><?= e(t('material_form.upload_attachments')) ?></label>
        <input id="attachments" name="attachments[]" type="file" accept=".pdf,image/*" multiple />
      </div>

      <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
        <button class="btn primary" type="submit"><?= e(t('common.save')) ?></button>
        <?php if ($isEdit): ?>
          <form method="post" action="/material_delete.php" class="inline-form" onsubmit="return confirm('<?= e(t('confirm.delete_material')) ?>');">
            <?= Csrf::field() ?>
            <input type="hidden" name="material_id" value="<?= (int) $materialId ?>" />
            <input type="hidden" name="house_id" value="<?= $currentHouseId ?>" />
            <input type="hidden" name="room_id" value="<?= $currentRoomId ?>" />
            <button class="btn danger icon-btn" type="submit" title="<?= e(t('common.delete')) ?>" aria-label="<?= e(t('common.delete')) ?>">
              <svg class="trash-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">
                <path d="M9 3h6l1 2h4v2H4V5h4l1-2zm1 6h2v9h-2V9zm4 0h2v9h-2V9zM7 9h2v9H7V9z" fill="currentColor"></path>
              </svg>
            </button>
          </form>
        <?php endif; ?>
        <?php if ($currentRoomId > 0): ?>
          <a class="btn ghost" href="/room.php?id=<?= $currentRoomId ?>"><?= e(t('common.cancel')) ?></a>
        <?php elseif ($currentHouseId > 0): ?>
          <a class="btn ghost" href="/house.php?id=<?= $currentHouseId ?>"><?= e(t('common.cancel')) ?></a>
        <?php endif; ?>
      </div>
    </form>
  </article>

  <aside class="card">
    <h3 style="margin-top: 0;"><?= e(t('material_form.existing_attachments')) ?></h3>
    <div class="attachments" style="margin-top: 0.8rem;">
      <?php foreach ($attachments as $attachment): ?>
        <div class="attachment">
          <p style="margin: 0; word-break: break-word;"><?= e((string) $attachment['original_name']) ?></p>
          <p class="muted" style="margin: .35rem 0; font-size: .85rem;"><?= e((string) $attachment['mime_type']) ?></p>
          <a href="/attachment.php?id=<?= (int) $attachment['id'] ?>"><?= e(t('common.download')) ?></a>
        </div>
      <?php endforeach; ?>
      <?php if ($attachments === []): ?>
        <p class="muted"><?= e(t('material_form.no_attachments')) ?></p>
      <?php endif; ?>
    </div>
  </aside>
</section>
<?php require __DIR__ . '/partials/layout_end.php';
