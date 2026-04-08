<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Mailer;
use App\Repositories\UserRepository;
use function App\Core\e;
use function App\Core\isPost;
use function App\Core\redirect;
use function App\Core\t;

if (Auth::check()) {
    redirect('/houses.php');
}

$errors = [];
$form   = ['email' => '', 'password' => '', 'password_confirm' => ''];

if (isPost()) {
    try {
        Csrf::validate($_POST['_csrf'] ?? null);

        $form['email']            = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $form['password']         = (string) ($_POST['password'] ?? '');
        $form['password_confirm'] = (string) ($_POST['password_confirm'] ?? '');

        if (!filter_var($form['email'], FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = t('error.invalid_email');
        }
        if (strlen($form['password']) < 8) {
            $errors['password'] = t('error.password_min_8');
        }
        if ($form['password'] !== $form['password_confirm']) {
            $errors['password_confirm'] = t('error.passwords_do_not_match');
        }

        if ($errors === []) {
            $repo     = new UserRepository();
            $existing = $repo->findByEmail($form['email']);

            if ($existing !== null && $existing['email_verified_at'] !== null) {
                // A verified account already exists for this email
                $errors['email'] = t('error.email_in_use');
            } else {
                $passwordHash = password_hash($form['password'], PASSWORD_ARGON2ID);
                if ($passwordHash === false) {
                    throw new \RuntimeException(t('error.password_hash_failed'));
                }

                $token = bin2hex(random_bytes(32));

                if ($existing !== null) {
                    // Unverified account already exists — update token and password
                    $repo->updateVerificationToken((int) $existing['id'], $token, $passwordHash);
                } else {
                    $repo->create($form['email'], $passwordHash, 'gebruiker', 'en', verified: false, verificationToken: $token);
                }

                Mailer::sendVerification($form['email'], $token);
                redirect('/login.php?registered=1');
            }
        }
    } catch (\Throwable $e) {
        $errors['general'] = $e->getMessage();
    }
}

$title   = t('auth.register');
$showNav = false;
require __DIR__ . '/partials/layout_start.php';
?>
<main class="login-wrap">
  <section class="card login-card">
    <div class="brand" style="margin-bottom: 1rem;">
      <div class="brand-mark"></div>
      <div>
        <h1><?= e(t('app.title')) ?></h1>
        <p class="muted" style="margin: 0.2rem 0 0;"><?= e(t('auth.register_subtitle')) ?></p>
      </div>
    </div>

    <?php if (isset($errors['general'])): ?>
      <div class="alert error"><?= e($errors['general']) ?></div>
    <?php endif; ?>

    <form action="/register.php" method="post" autocomplete="on">
      <?= Csrf::field() ?>

      <div class="field">
        <label for="email"><?= e(t('auth.email')) ?></label>
        <input id="email" name="email" type="email" required value="<?= e($form['email']) ?>" placeholder="jij@voorbeeld.nl" />
        <?php if (isset($errors['email'])): ?>
          <span class="field-error"><?= e($errors['email']) ?></span>
        <?php endif; ?>
      </div>

      <div class="field">
        <label for="password"><?= e(t('auth.password')) ?></label>
        <input id="password" name="password" type="password" required minlength="8" placeholder="<?= e(t('auth.password_placeholder')) ?>" />
        <?php if (isset($errors['password'])): ?>
          <span class="field-error"><?= e($errors['password']) ?></span>
        <?php endif; ?>
      </div>

      <div class="field">
        <label for="password_confirm"><?= e(t('auth.confirm_password')) ?></label>
        <input id="password_confirm" name="password_confirm" type="password" required minlength="8" placeholder="<?= e(t('auth.confirm_password_placeholder')) ?>" />
        <?php if (isset($errors['password_confirm'])): ?>
          <span class="field-error"><?= e($errors['password_confirm']) ?></span>
        <?php endif; ?>
      </div>

      <button class="btn primary" type="submit"><?= e(t('auth.create_account')) ?></button>
    </form>

    <p style="margin-top: 1rem; text-align: center; font-size: 0.9rem;">
      <?= e(t('auth.already_have_account')) ?>
      <a href="/login.php"><?= e(t('auth.login')) ?></a>
    </p>
  </section>
</main>
<?php require __DIR__ . '/partials/layout_end.php'; ?>
