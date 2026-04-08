<?php

declare(strict_types=1);

require_once __DIR__ . '/../app/Core/Utils.php';
require_once __DIR__ . '/../app/Core/Env.php';
App\Core\Env::load(__DIR__ . '/../.env');

require_once __DIR__ . '/../app/Core/Database.php';
$pdo = App\Core\Database::connection();

$pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verified_at DATETIME NULL AFTER password_hash');
echo "Column email_verified_at: OK\n";

$pdo->exec('ALTER TABLE users ADD COLUMN IF NOT EXISTS email_verification_token VARCHAR(64) NULL AFTER email_verified_at');
echo "Column email_verification_token: OK\n";

$count = $pdo->exec('UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL AND email_verification_token IS NULL');
echo "Existing users marked as verified: {$count} row(s) updated\n";

$total = $pdo->query('SELECT COUNT(*) FROM users')->fetchColumn();
echo "Total users in DB: {$total}\n";

echo "Migration complete.\n";
