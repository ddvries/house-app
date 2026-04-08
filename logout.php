<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Csrf;
use App\Core\Flash;
use function App\Core\redirect;
use function App\Core\t;

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    try {
        Csrf::validate($_POST['_csrf'] ?? null);
        Auth::logout();
        \App\Core\Session::start();
        Flash::set('success', t('flash.logged_out'));
    } catch (Throwable $e) {
        Auth::logout();
        \App\Core\Session::start();
        Flash::set('error', t('error.logout_failed'));
    }
}

redirect('/login.php');
