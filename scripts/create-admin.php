<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Repositories\UserRepository;

$write = static function (string $message, bool $isError = false): void {
    if (defined('STDOUT') && defined('STDERR')) {
        fwrite($isError ? STDERR : STDOUT, $message);
        return;
    }

    echo $message;
};

$options = getopt('', ['email:', 'password:']);
$email = $options['email'] ?? null;
$password = $options['password'] ?? null;

if (!is_string($email) || !is_string($password) || $email === '' || $password === '') {
    $write("Usage: php scripts/create-admin.php --email=admin@example.com --password=StrongPassword\n", true);
    exit(1);
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $write("Invalid email address.\n", true);
    exit(1);
}

if (strlen($password) < 8) {
    $write("Password must be at least 8 characters.\n", true);
    exit(1);
}

$repo = new UserRepository();
$existing = $repo->findByEmail($email);
if ($existing !== null) {
    $write("User already exists.\n", true);
    exit(1);
}

$hash = password_hash($password, PASSWORD_ARGON2ID);
$repo->create($email, $hash, 'admin');

$write("Admin user created.\n");
