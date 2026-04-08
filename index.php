<?php

declare(strict_types=1);

require __DIR__ . '/bootstrap.php';

use function App\Core\redirect;

if (\App\Core\Auth::check()) {
    redirect('/houses.php');
}

redirect('/login.php');
