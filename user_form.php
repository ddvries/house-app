<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\SecurityLogger;
use App\Repositories\UserRepository;
use function App\Core\e;
use function App\Core\isPost;
use function App\Core\redirect;
use function App\Core\t;

Auth::requireAuth();
if (!Auth::isAdmin()) {
    SecurityLogger::unauthorizedAccess('user_form', context: ['user_id' => Auth::userId()]);
    http_response_code(403);
    exit(t('error.access_denied'));
}

$repo = new UserRepository();
$currentUserId = (int) Auth::userId();
$userId = isset($_GET['id']) ? (int) $_GET['id'] : null;
$isEdit = $userId !== null;

$form = [
    'email' => '',
    'role' => 'gebruiker',
    'password' => '',
    'password_confirm' => '',
];

if ($isEdit) {
    $existing = $repo->findById((int) $userId);
    if ($existing === null) {
        Flash::set('error', t('error.user_not_found'));
        redirect('/admin_users.php');
    }

    $form['email'] = (string) $existing['email'];
    $form['role'] = (string) $existing['role'];
}

$errors = [];

if (isPost()) {
    try {
        Csrf::validate($_POST['_csrf'] ?? null);

        $form['email'] = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $form['role'] = trim((string) ($_POST['role'] ?? 'gebruiker'));
        $form['password'] = (string) ($_POST['password'] ?? '');
        $form['password_confirm'] = (string) ($_POST['password_confirm'] ?? '');

        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = t('error.invalid_email');
        }

        if (!in_array($form['role'], ['admin', 'gebruiker'], true)) {
            $errors['role'] = t('error.invalid_role');
        }

        $passwordHash = null;
        if ($isEdit) {
            if ($form['password'] !== '') {
                if (strlen($form['password']) < 8) {
                    $errors['password'] = t('error.password_min_8_new');
                }

                if (!hash_equals($form['password'], $form['password_confirm'])) {
                    $errors['password_confirm'] = t('error.passwords_do_not_match');
                }

                if ($errors === []) {
                    $passwordHash = password_hash($form['password'], PASSWORD_ARGON2ID);
                }
            }
        } else {
            if (strlen($form['password']) < 8) {
                $errors['password'] = t('error.password_min_8');
            }

            if (!hash_equals($form['password'], $form['password_confirm'])) {
                $errors['password_confirm'] = t('error.passwords_do_not_match');
            }

            if ($errors === []) {
                $passwordHash = password_hash($form['password'], PASSWORD_ARGON2ID);
            }
        }

        $existingByEmail = $repo->findByEmail($form['email']);
        if ($existingByEmail !== null && (!$isEdit || (int) $existingByEmail['id'] !== (int) $userId)) {
            $errors['email'] = t('error.email_in_use');
        }

        if ($isEdit && (int) $userId === $currentUserId && $form['role'] !== 'admin') {
            $errors['role'] = t('error.cannot_remove_own_admin_role');
        }

        if ($isEdit && $form['role'] !== 'admin') {
            $target = $repo->findById((int) $userId);
            if ($target !== null && (string) $target['role'] === 'admin' && $repo->countAdmins() <= 1) {
                $errors['role'] = t('error.last_admin_cannot_be_demoted');
            }
        }

        if ($errors === []) {
            if ($isEdit) {
                $repo->update((int) $userId, $form['email'], $form['role'], $passwordHash);
                Flash::set('success', t('flash.user_updated'));
            } else {
                if ($passwordHash === null) {
                    throw new RuntimeException(t('error.password_hash_failed'));
                }
                $repo->create($form['email'], $passwordHash, $form['role']);
                Flash::set('success', t('flash.user_created'));
            }

            redirect('/admin_users.php');
        }
    } catch (Throwable $e) {
        Flash::set('error', $e->getMessage());
    }
}

$title = $isEdit ? t('user_form.edit_title') : t('user_form.create_title');
$active = 'admin-users';
require __DIR__ . '/partials/layout_start.php';
?>
<section class="page-title">
  <div>
        <h2><?= e($isEdit ? t('user_form.edit_title') : t('user_form.create_title')) ?></h2>
        <p><?= e(t('admin_users.subtitle')) ?></p>
  </div>
    <a class="btn ghost" href="/admin_users.php"><?= e(t('user_form.back_to_users')) ?></a>
</section>

<section class="card">
  <form action="" method="post" novalidate>
    <?= Csrf::field() ?>

    <div class="field">
            <label for="email"><?= e(t('auth.email')) ?></label>
      <input id="email" name="email" type="email" required value="<?= e($form['email']) ?>" />
      <?php if (isset($errors['email'])): ?><p class="error-text"><?= e($errors['email']) ?></p><?php endif; ?>
    </div>

    <div class="field">
            <label for="role"><?= e(t('common.role')) ?></label>
      <select id="role" name="role">
        <option value="admin" <?= $form['role'] === 'admin' ? 'selected' : '' ?>><?= e(t('role.admin')) ?></option>
        <option value="gebruiker" <?= $form['role'] === 'gebruiker' ? 'selected' : '' ?>><?= e(t('role.user')) ?></option>
      </select>
      <?php if (isset($errors['role'])): ?><p class="error-text"><?= e($errors['role']) ?></p><?php endif; ?>
    </div>

    <div class="grid cols-2">
      <div class="field">
                <label for="password"><?= e($isEdit ? t('profile.new_password') : t('auth.password')) ?></label>
        <input id="password" name="password" type="password" <?= $isEdit ? '' : 'required' ?> />
        <?php if (isset($errors['password'])): ?><p class="error-text"><?= e($errors['password']) ?></p><?php endif; ?>
      </div>
      <div class="field">
                <label for="password_confirm"><?= e(t('user_form.confirm_password')) ?></label>
        <input id="password_confirm" name="password_confirm" type="password" <?= $isEdit ? '' : 'required' ?> />
        <?php if (isset($errors['password_confirm'])): ?><p class="error-text"><?= e($errors['password_confirm']) ?></p><?php endif; ?>
      </div>
    </div>

    <div style="display: flex; gap: 0.6rem; flex-wrap: wrap;">
            <button class="btn primary" type="submit"><?= e(t('common.save')) ?></button>
            <a class="btn ghost" href="/admin_users.php"><?= e(t('common.cancel')) ?></a>
    </div>
  </form>
</section>
<?php require __DIR__ . '/partials/layout_end.php';
