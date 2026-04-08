<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\EmailNotVerifiedException;
use App\Core\Flash;
use App\Core\RateLimiter;
use App\Core\SecurityLogger;
use function App\Core\e;
use function App\Core\isPost;
use function App\Core\redirect;
use function App\Core\t;

if (Auth::check()) {
    redirect('/houses.php');
}

$error       = null;
$notVerified = false;
$email       = '';

// Flash messages from registration / email verification redirect
if (isset($_GET['registered'])) {
    Flash::set('info', t('auth.check_email'));
}
if (isset($_GET['verified'])) {
    Flash::set('success', t('auth.email_verified'));
}

if (isPost()) {
    try {
        Csrf::validate($_POST['_csrf'] ?? null);

        $rateLimitKey = 'login:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
        if (RateLimiter::tooManyAttempts($rateLimitKey)) {
            $wait = RateLimiter::secondsUntilUnlocked($rateLimitKey);
            SecurityLogger::rateLimitLockout('login', $wait);
            $error = t('auth.too_many_attempts') . ($wait > 0 ? ' ' . sprintf(t('auth.try_again_in'), (int) ceil($wait / 60)) : '');
        } else {
            $email    = trim((string) ($_POST['email'] ?? ''));
            $password = (string) ($_POST['password'] ?? '');

            if (Auth::attempt($email, $password)) {
                RateLimiter::clear($rateLimitKey);
                Flash::set('success', t('flash.welcome_back'));
                redirect('/houses.php');
            }

            RateLimiter::hit($rateLimitKey);
            $state = RateLimiter::state($rateLimitKey);
            SecurityLogger::loginFailure($email, (int) $state['attempts'], RateLimiter::MAX_ATTEMPTS);
            $error = t('auth.invalid_credentials');
        }
    } catch (EmailNotVerifiedException) {
        $notVerified = true;
    } catch (Throwable $e) {
        $error = $e->getMessage();
    }
}

$title = t('auth.login');
$showNav = false;
require __DIR__ . '/partials/layout_start.php';
?>
<main class="login-wrap">
  <section class="card login-card">
    <div class="brand" style="margin-bottom: 1rem;">
      <div class="brand-mark"></div>
      <div>
        <h1><?= e(t('app.title')) ?></h1>
        <p class="muted" style="margin: 0.2rem 0 0;"><?= e(t('auth.welcome')) ?></p>
      </div>
    </div>

    <?php if ($error !== null): ?>
      <div class="alert error"><?= e($error) ?></div>
    <?php endif; ?>

    <?php if ($notVerified): ?>
      <div class="alert error">
        <?= e(t('auth.not_verified')) ?>
        <br>
        <a href="/resend_verification.php?email=<?= urlencode($email) ?>" style="font-size:0.9rem">
          <?= e(t('auth.resend_verification')) ?>
        </a>
      </div>
    <?php endif; ?>

    <form action="/login.php" method="post" autocomplete="on">
      <?= Csrf::field() ?>
      <div class="field">
        <label for="email"><?= e(t('auth.email')) ?></label>
        <input id="email" name="email" type="email" required value="<?= e($email) ?>" placeholder="jij@voorbeeld.nl" />
      </div>

      <div class="field">
        <label for="password"><?= e(t('auth.password')) ?></label>
        <input id="password" name="password" type="password" required placeholder="<?= e(t('auth.password_placeholder')) ?>" />
      </div>

      <button class="btn primary" type="submit"><?= e(t('auth.login')) ?></button>
    </form>

    <p style="margin-top: 1rem; text-align: center; font-size: 0.9rem;">
      <?= e(t('auth.no_account')) ?>
      <a href="/register.php"><?= e(t('auth.register')) ?></a>
    </p>
  </section>
</main>
<?php require __DIR__ . '/partials/layout_end.php';

