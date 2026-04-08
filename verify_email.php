<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use App\Core\Auth;
use App\Core\Flash;
use App\Repositories\UserRepository;
use function App\Core\redirect;
use function App\Core\t;

if (Auth::check()) {
    redirect('/houses.php');
}

$token = trim((string) ($_GET['token'] ?? ''));

if ($token === '') {
    Flash::set('error', t('auth.invalid_verification_token'));
    redirect('/login.php');
}

$repo = new UserRepository();
$user = $repo->findByVerificationToken($token);

if ($user === null || $user['email_verified_at'] !== null) {
    Flash::set('error', t('auth.invalid_verification_token'));
    redirect('/login.php');
}

$repo->markEmailVerified((int) $user['id']);
Flash::set('success', t('auth.email_verified'));
redirect('/login.php');
