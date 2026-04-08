<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Validator;
use App\Repositories\HouseRepository;
use function App\Core\e;
use function App\Core\isPost;
use function App\Core\redirect;
use function App\Core\t;

Auth::requireAuth();
$userId = (int) Auth::userId();

$repo = new HouseRepository();
$id = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit = $id !== null;

$house = [
    'name' => '',
    'city' => '',
    'notes' => '',
];

if ($isEdit) {
    $existing = $repo->findForUser((int) $id, $userId);
    if ($existing === null) {
    Flash::set('error', t('error.house_not_found'));
        redirect('/houses.php');
    }
    $house = $existing;
}

$errors = [];

if (isPost()) {
    try {
        Csrf::validate($_POST['_csrf'] ?? null);

        $payload = [
            'name' => trim((string) ($_POST['name'] ?? '')),
            'city' => trim((string) ($_POST['city'] ?? '')),
            'notes' => trim((string) ($_POST['notes'] ?? '')),
        ];

        $errors = Validator::house($payload);
        if ($errors === []) {
            if ($isEdit) {
                $repo->update((int) $id, $userId, $payload);
            Flash::set('success', t('flash.house_updated'));
            } else {
                $id = $repo->create($userId, $payload);
            Flash::set('success', t('flash.house_created'));
            }

            redirect('/house.php?id=' . (int) $id);
        }

        $house = array_merge($house, $payload);
    } catch (Throwable $e) {
        Flash::set('error', $e->getMessage());
    }
}

$title = $isEdit ? t('house_form.edit_title') : t('house_form.create_title');
$active = 'houses';
require __DIR__ . '/partials/layout_start.php';
?>
<section class="page-title">
  <div>
    <h2><?= e($isEdit ? t('house_form.edit_title') : t('house_form.create_title')) ?></h2>
    <p><?= e(t('house_form.subtitle')) ?></p>
  </div>
  <a class="btn ghost" href="/houses.php"><?= e(t('common.back_to_houses')) ?></a>
</section>

<section class="card">
  <form action="" method="post" novalidate>
    <?= Csrf::field() ?>

    <div class="grid cols-2">
      <div class="field">
        <label for="name"><?= e(t('house_form.name')) ?></label>
        <input id="name" name="name" type="text" required value="<?= e((string) $house['name']) ?>" placeholder="Hoofdwoning" />
        <?php if (isset($errors['name'])): ?><p class="error-text"><?= e($errors['name']) ?></p><?php endif; ?>
      </div>

      <div class="field">
        <label for="city"><?= e(t('common.location')) ?></label>
        <input id="city" name="city" type="text" required value="<?= e((string) $house['city']) ?>" placeholder="Utrecht" />
        <?php if (isset($errors['city'])): ?><p class="error-text"><?= e($errors['city']) ?></p><?php endif; ?>
      </div>
    </div>

    <div class="field">
      <label for="notes"><?= e(t('common.notes')) ?></label>
      <textarea id="notes" name="notes" placeholder="<?= e(t('house_form.notes_placeholder')) ?>"><?= e((string) $house['notes']) ?></textarea>
    </div>

    <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
      <button class="btn primary" type="submit"><?= e(t('common.save')) ?></button>
      <a class="btn ghost" href="/houses.php"><?= e(t('common.cancel')) ?></a>
    </div>
  </form>
</section>
<?php require __DIR__ . '/partials/layout_end.php';
