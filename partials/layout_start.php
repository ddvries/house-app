<?php

declare(strict_types=1);

use App\Core\Auth;
use App\Core\Flash;
use function App\Core\e;
use function App\Core\lang;
use function App\Core\t;

$title = $title ?? t('app.title');
$showNav = $showNav ?? true;
$active = $active ?? '';
$flashMessages = Flash::pull();
?>
<!DOCTYPE html>
<html lang="<?= e(lang()) ?>">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= e($title) ?></title>
    <link rel="stylesheet" href="/assets/css/styles.css" />
  </head>
  <body>
    <div class="app-shell">
      <?php if ($showNav): ?>
      <header class="site-header">
        <div class="brand">
          <div class="brand-mark"></div>
          <h1><?= e(t('app.title')) ?></h1>
        </div>

        <button
          class="hamburger-btn"
          type="button"
          aria-expanded="false"
          aria-controls="site-nav"
          aria-label="<?= e(t('nav.menu_open')) ?>"
          data-label-open="<?= e(t('nav.menu_open')) ?>"
          data-label-close="<?= e(t('nav.menu_close')) ?>"
        >
          <span></span>
          <span></span>
          <span></span>
        </button>

        <nav id="site-nav" class="nav-links">
          <a href="/houses.php"<?= $active === 'houses' ? ' aria-current="page"' : '' ?>><?= e(t('nav.houses')) ?></a>
          <a href="/materials.php"<?= $active === 'materials' ? ' aria-current="page"' : '' ?>><?= e(t('nav.materials')) ?></a>
          <?php if (Auth::isAdmin()): ?>
            <a href="/admin_users.php"<?= $active === 'admin-users' ? ' aria-current="page"' : '' ?>><?= e(t('nav.users')) ?></a>
            <a href="/admin_logs.php"<?= $active === 'admin-logs' ? ' aria-current="page"' : '' ?>><?= e(t('nav.logs')) ?></a>
          <?php endif; ?>
          <?php if (Auth::check()): ?>
            <span class="chip"><?= e(t('nav.role')) ?>: <?= e(t((string) Auth::role() === 'admin' ? 'role.admin' : 'role.user')) ?></span>
           <a href="/profile.php"<?= $active === 'profile' ? ' aria-current="page"' : '' ?>><?= e(t('nav.profile')) ?></a>
            <form method="post" action="/logout.php" class="inline-form">
              <?= \App\Core\Csrf::field() ?>
              <button type="submit" class="btn ghost"><?= e(t('nav.logout')) ?></button>
            </form>
          <?php endif; ?>
        </nav>
      </header>
      <?php endif; ?>

      <?php foreach ($flashMessages as $flash): ?>
        <div class="alert <?= e((string) $flash['type']) ?>"><?= e((string) $flash['message']) ?></div>
      <?php endforeach; ?>

