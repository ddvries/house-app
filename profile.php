<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use App\Core\Language;
use App\Repositories\UserRepository;
use function App\Core\e;
use function App\Core\isPost;
use function App\Core\redirect;
use function App\Core\t;

Auth::requireAuth();

$repo = new UserRepository();
$userId = (int) Auth::userId();
$user = $repo->findById($userId);

if ($user === null) {
    Flash::set('error', t('error.user_not_found'));
    redirect('/houses.php');
}

$form = [
    'email' => (string) $user['email'],
  'preferred_language' => Language::normalize((string) ($user['preferred_language'] ?? 'en')),
    'password' => '',
    'password_confirm' => '',
    'current_password' => '',
];

$errors = [];

if (isPost()) {
    try {
        Csrf::validate($_POST['_csrf'] ?? null);

        $form['email'] = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $form['preferred_language'] = Language::normalize((string) ($_POST['preferred_language'] ?? 'en'));
        $form['password'] = (string) ($_POST['password'] ?? '');
        $form['password_confirm'] = (string) ($_POST['password_confirm'] ?? '');
        $form['current_password'] = (string) ($_POST['current_password'] ?? '');

        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = t('error.invalid_email');
        }

        $existingByEmail = $repo->findByEmail($form['email']);
        if ($existingByEmail !== null && (int) $existingByEmail['id'] !== $userId) {
            $errors['email'] = t('error.email_in_use');
        }

        $passwordHash = null;
        if ($form['password'] !== '') {
            if (strlen($form['password']) < 8) {
                $errors['password'] = t('error.password_min_8_new');
            }

            if (!hash_equals($form['password'], $form['password_confirm'])) {
                $errors['password_confirm'] = t('error.passwords_do_not_match');
            }

            if ($errors === [] || !isset($errors['password']) && !isset($errors['password_confirm'])) {
                $passwordHash = password_hash($form['password'], PASSWORD_ARGON2ID);
            }
        }

        if ($form['email'] !== (string) $user['email'] || $form['password'] !== '') {
            if ($form['current_password'] === '') {
                $errors['current_password'] = t('error.current_password_required');
            } else {
                $userForAuth = $repo->findByEmail((string) $user['email']);
                if ($userForAuth === null || !password_verify($form['current_password'], (string) $userForAuth['password_hash'])) {
                    $errors['current_password'] = t('error.current_password_incorrect');
                }
            }
        }

        if ($errors === []) {
            $repo->update($userId, $form['email'], (string) $user['role'], $passwordHash, $form['preferred_language']);
            Language::setCurrent($form['preferred_language']);
            Flash::set('success', t('flash.profile_updated'));
            
            // Refresh current user data
            $user = $repo->findById($userId);
            $form['email'] = (string) $user['email'];
            $form['preferred_language'] = Language::normalize((string) ($user['preferred_language'] ?? 'en'));
            $form['password'] = '';
            $form['password_confirm'] = '';
            $form['current_password'] = '';
        }
    } catch (Throwable $e) {
        Flash::set('error', $e->getMessage());
    }
}

$title = t('profile.title');
$active = 'profile';
require __DIR__ . '/partials/layout_start.php';

$languageLabels = Language::labels();
?>
<section class="page-title">
  <div>
    <h2><?= e(t('profile.title')) ?></h2>
    <p><?= e(t('profile.subtitle')) ?></p>
  </div>
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
      <label for="preferred_language"><?= e(t('profile.language')) ?></label>
      <select id="preferred_language" name="preferred_language">
        <?php foreach ($languageLabels as $languageCode => $languageLabel): ?>
          <option value="<?= e($languageCode) ?>" <?= $form['preferred_language'] === $languageCode ? 'selected' : '' ?>><?= e($languageLabel) ?></option>
        <?php endforeach; ?>
      </select>
    </div>

    <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--border-color);" />

    <div class="field">
      <label for="password"><?= e(t('profile.new_password')) ?></label>
      <input id="password" name="password" type="password" value="<?= e($form['password']) ?>" placeholder="<?= e(t('profile.password_placeholder')) ?>" />
      <?php if (isset($errors['password'])): ?><p class="error-text"><?= e($errors['password']) ?></p><?php endif; ?>
    </div>

    <div class="field">
      <label for="password_confirm"><?= e(t('profile.new_password_confirm')) ?></label>
      <input id="password_confirm" name="password_confirm" type="password" value="<?= e($form['password_confirm']) ?>" placeholder="<?= e(t('profile.new_password_confirm')) ?>" />
      <?php if (isset($errors['password_confirm'])): ?><p class="error-text"><?= e($errors['password_confirm']) ?></p><?php endif; ?>
    </div>

    <hr style="margin: 1.5rem 0; border: none; border-top: 1px solid var(--border-color);" />

    <div class="field">
      <label for="current_password"><?= e(t('profile.current_password')) ?></label>
      <input id="current_password" name="current_password" type="password" value="<?= e($form['current_password']) ?>" placeholder="<?= e(t('profile.current_password')) ?>" />
      <small style="color: var(--muted-color); display: block; margin-top: 0.25rem;"><?= e(t('profile.current_password_help')) ?></small>
      <?php if (isset($errors['current_password'])): ?><p class="error-text"><?= e($errors['current_password']) ?></p><?php endif; ?>
    </div>

    <div style="margin-top: 2rem; display: flex; gap: 1rem;">
      <button class="btn primary" type="submit"><?= e(t('profile.save')) ?></button>
      <a class="btn ghost" href="/houses.php"><?= e(t('profile.back')) ?></a>
    </div>
  </form>
</section>

<?php require __DIR__ . '/partials/layout_end.php';
