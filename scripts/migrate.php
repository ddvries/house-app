<?php

declare(strict_types=1);

require dirname(__DIR__) . '/bootstrap.php';

use App\Core\Database;

$write = static function (string $message, bool $isError = false): void {
    if (defined('STDOUT') && defined('STDERR')) {
        fwrite($isError ? STDERR : STDOUT, $message);
        return;
    }

    // Fallback for SAPIs where STDOUT/STDERR are not defined.
    echo $message;
};

$schemaPath = dirname(__DIR__) . '/database/schema.sql';
if (!is_file($schemaPath)) {
    $write("Schema file not found.\n", true);
    exit(1);
}

$sql = file_get_contents($schemaPath);
if ($sql === false) {
    $write("Could not read schema file.\n", true);
    exit(1);
}

Database::connection()->exec($sql);
$write("Migration completed.\n");
