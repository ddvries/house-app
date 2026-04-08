<?php

declare(strict_types=1);

use App\Core\Env;
use App\Core\Session;

require_once __DIR__ . '/app/Core/Utils.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'App\\';
    $baseDir = __DIR__ . '/app/';

    if (strncmp($prefix, $class, strlen($prefix)) !== 0) {
        return;
    }

    $relativeClass = substr($class, strlen($prefix));
    $file = $baseDir . str_replace('\\', '/', $relativeClass) . '.php';

    if (is_file($file)) {
        require_once $file;
    }
});

if (is_file(__DIR__ . '/vendor/autoload.php')) {
    require_once __DIR__ . '/vendor/autoload.php';
}

Env::load(__DIR__ . '/.env');

$debug = Env::get('APP_DEBUG', 'false') === 'true';
ini_set('display_errors', $debug ? '1' : '0');
ini_set('display_startup_errors', $debug ? '1' : '0');
error_reporting(E_ALL);

if (!headers_sent()) {
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: camera=(), microphone=(), geolocation=(), payment=()');
    header("Content-Security-Policy: default-src 'self'; script-src 'self'; style-src 'self' 'unsafe-inline'; img-src 'self' data: blob:; font-src 'self'; object-src 'none'; base-uri 'self'; form-action 'self'; frame-ancestors 'none'");
}

Session::start();

$basePath = \App\Core\appBasePath();
if ($basePath !== '') {
    ob_start(static function (string $buffer) use ($basePath): string {
        return (string) preg_replace(
            '/\b(href|src|action)=("|\')\/(?!\/)/i',
            '$1=$2' . $basePath . '/',
            $buffer
        );
    });
}

date_default_timezone_set('Europe/Amsterdam');

