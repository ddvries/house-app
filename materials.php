<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Repositories\MaterialRepository;
use App\Repositories\RoomRepository;
use function App\Core\e;
use function App\Core\materialTypeLabel;
use function App\Core\t;

Auth::requireAuth();
$userId = (int) Auth::userId();

$materialRepo = new MaterialRepository();
$roomRepo = new RoomRepository();
$materialRows = $materialRepo->allForUserDetailed($userId);
$allRooms = $roomRepo->allForUser($userId);

$categories = [];
foreach ($materialRows as $row) {
    $type = (string) $row['type'];
    $key = strtolower((string) $row['name']) . '|' . strtoupper((string) $row['color_hex']);

    if (!isset($categories[$type][$key])) {
        $categories[$type][$key] = [
            'name' => (string) $row['name'],
            'color_hex' => (string) $row['color_hex'],
            'description' => (string) $row['description'],
            'locations' => [],
        'location_keys' => [],
            'usage_count' => 0,
            'link_count' => 0,
            'attachment_count' => 0,
            'representative_id' => (int) $row['id'],
        ];
    }

    $locationKey = (int) $row['room_id'] . '|' . (int) $row['house_id'];
    if (!isset($categories[$type][$key]['location_keys'][$locationKey])) {
      $categories[$type][$key]['location_keys'][$locationKey] = true;
      $categories[$type][$key]['locations'][] = [
        'room_id' => (int) $row['room_id'],
        'room_name' => (string) $row['room_name'],
        'house_name' => (string) $row['house_name'],
      ];
    }

    $categories[$type][$key]['usage_count']++;
    $categories[$type][$key]['link_count'] += (int) $row['link_count'];
    $categories[$type][$key]['attachment_count'] += (int) $row['attachment_count'];

    if ($categories[$type][$key]['description'] === '' && (string) $row['description'] !== '') {
        $categories[$type][$key]['description'] = (string) $row['description'];
    }
}

foreach ($categories as $type => $items) {
  foreach ($items as $key => $item) {
    unset($categories[$type][$key]['location_keys']);
  }
}

$title = t('materials.title');
$active = 'materials';
require __DIR__ . '/partials/layout_start.php';
?>
<section class="page-title">
  <div>
    <h2><?= e(t('materials.heading')) ?></h2>
    <p><?= e(t('materials.subtitle')) ?></p>
  </div>
</section>

<section class="stack">
  <?php foreach ($categories as $type => $items): ?>
    <article class="card category-section">
      <div class="page-title" style="margin-bottom: 0;">
        <div>
          <h3 style="margin: 0;"><?= e(materialTypeLabel((string) $type)) ?></h3>
          <p><?= count($items) ?> <?= e(t('materials.unique_in_category')) ?></p>
        </div>
      </div>

      <div class="table-wrap">
      <table class="table">
        <thead>
          <tr>
            <th><?= e(t('common.material')) ?></th>
            <th><?= e(t('common.color')) ?></th>
            <th><?= e(t('materials.used_in')) ?></th>
            <th><?= e(t('materials.usage')) ?></th>
            <th><?= e(t('common.links')) ?></th>
            <th><?= e(t('common.attachments')) ?></th>
            <th><?= e(t('materials.assign_to_room')) ?></th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($items as $item): ?>
            <tr>
              <td>
                <strong><?= e((string) $item['name']) ?></strong>
                <?php if ((string) $item['description'] !== ''): ?>
                  <div class="muted" style="margin-top: 0.3rem;"><?= e((string) $item['description']) ?></div>
                <?php endif; ?>
              </td>
              <td>
                <?php if ((string) $item['color_hex'] !== ''): ?>
                  <span class="chip"><span class="color-dot" style="background: <?= e((string) $item['color_hex']) ?>;"></span><?= e((string) $item['color_hex']) ?></span>
                <?php else: ?>
                  -
                <?php endif; ?>
              </td>
              <td>
                <div class="location-list">
                  <?php foreach ($item['locations'] as $location): ?>
                    <a class="chip room-link" href="/room.php?id=<?= (int) $location['room_id'] ?>"><?= e((string) $location['house_name']) ?> / <?= e((string) $location['room_name']) ?></a>
                  <?php endforeach; ?>
                </div>
              </td>
              <td><?= (int) $item['usage_count'] ?> <?= e(t('materials.times')) ?></td>
              <td><?= (int) $item['link_count'] ?></td>
              <td><?= (int) $item['attachment_count'] ?></td>
              <td>
                <?php if ($allRooms !== []): ?>
                  <form method="post" action="/material_copy.php" class="copy-form">
                    <?= \App\Core\Csrf::field() ?>
                    <input type="hidden" name="material_id" value="<?= (int) $item['representative_id'] ?>" />
                    <select name="room_id" class="copy-select" required>
                      <option value=""><?= e(t('materials.choose_room')) ?></option>
                      <?php foreach ($allRooms as $room): ?>
                        <option value="<?= (int) $room['id'] ?>">
                          <?= e((string) $room['house_name']) ?> / <?= e((string) $room['name']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                    <button class="btn primary" type="submit" style="padding:0.35rem 0.65rem;font-size:0.85rem;"><?= e(t('materials.assign')) ?></button>
                  </form>
                <?php endif; ?>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      </div>
    </article>
  <?php endforeach; ?>

  <?php if ($categories === []): ?>
    <article class="card">
      <p class="muted" style="margin: 0;"><?= e(t('materials.empty')) ?></p>
    </article>
  <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/layout_end.php';