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

$success      = false;
$error        = null;
$prefillEmail = mb_strtolower(trim((string) ($_GET['email'] ?? '')));

if (isPost()) {
    try {
        Csrf::validate($_POST['_csrf'] ?? null);

        $email = mb_strtolower(trim((string) ($_POST['email'] ?? '')));
        $repo  = new UserRepository();
        $user  = $repo->findByEmail($email);

        // Always show success message to prevent email enumeration
        if ($user !== null && $user['email_verified_at'] === null) {
            $token = bin2hex(random_bytes(32));
            $repo->updateVerificationToken((int) $user['id'], $token);
            Mailer::sendVerification($email, $token);
        }

        $success = true;
    } catch (\Throwable $e) {
        $error = $e->getMessage();
    }
}

$title   = t('auth.resend_verification');
$showNav = false;
require __DIR__ . '/partials/layout_start.php';
?>
<main class="login-wrap">
  <section class="card login-card">
    <div class="brand" style="margin-bottom: 1rem;">
      <div class="brand-mark"></div>
      <div>
        <h1><?= e(t('app.title')) ?></h1>
        <p class="muted" style="margin: 0.2rem 0 0;"><?= e(t('auth.resend_verification')) ?></p>
      </div>
    </div>

    <?php if ($success): ?>
      <div class="alert success"><?= e(t('auth.verification_resent')) ?></div>
      <p style="text-align: center; margin-top: 1rem; font-size: 0.9rem;">
        <a href="/login.php"><?= e(t('auth.login')) ?></a>
      </p>
    <?php else: ?>
      <?php if ($error !== null): ?>
        <div class="alert error"><?= e($error) ?></div>
      <?php endif; ?>

      <form action="/resend_verification.php" method="post" autocomplete="on">
        <?= Csrf::field() ?>
        <div class="field">
          <label for="email"><?= e(t('auth.email')) ?></label>
          <input id="email" name="email" type="email" required value="<?= e($prefillEmail) ?>" placeholder="jij@voorbeeld.nl" />
        </div>
        <button class="btn primary" type="submit"><?= e(t('auth.resend_button')) ?></button>
      </form>

      <p style="margin-top: 1rem; text-align: center; font-size: 0.9rem;">
        <a href="/login.php"><?= e(t('auth.login')) ?></a>
      </p>
    <?php endif; ?>
  </section>
</main>
<?php require __DIR__ . '/partials/layout_end.php'; ?>
