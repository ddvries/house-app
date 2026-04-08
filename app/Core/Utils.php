<?php

declare(strict_types=1);

namespace App\Core;

function lang(): string
{
    return Language::current();
}

function t(string $key): string
{
    return Language::translate($key);
}

/** @return array<string,string> */
function floorOptions(): array
{
    return [
        'Kelder' => t('floor.basement'),
        'Begane Grond' => t('floor.ground'),
        'Eerste Verdieping' => t('floor.first'),
        'Tweede Verdieping' => t('floor.second'),
        'Zolder' => t('floor.attic'),
    ];
}

function floorLabel(string $value): string
{
    $options = floorOptions();
    return $options[$value] ?? $value;
}

/** @return array<string,string> */
function materialTypeOptions(): array
{
    return [
        'Verf' => t('material_type.paint'),
        'Tegel' => t('material_type.tile'),
        'Hout' => t('material_type.wood'),
        'Behang' => t('material_type.wallpaper'),
        'Overig' => t('material_type.other'),
    ];
}

function materialTypeLabel(string $value): string
{
    $options = materialTypeOptions();
    return $options[$value] ?? $value;
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function appBasePath(): string
{
    static $basePath = null;
    if ($basePath !== null) {
        return $basePath;
    }

    $detectedBasePath = '';
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    if (is_string($scriptName) && str_starts_with($scriptName, '/')) {
        $dir = str_replace('\\', '/', dirname($scriptName));
        $dir = rtrim($dir, '/');
        if ($dir !== '' && $dir !== '.' && $dir !== '/') {
            $detectedBasePath = $dir;
        }
    }

    $appUrl = (string) Env::get('APP_URL', '');
    if ($appUrl !== '') {
        $path = (string) parse_url($appUrl, PHP_URL_PATH);
        $path = '/' . trim($path, '/');
        $configuredBasePath = $path === '/' ? '' : $path;

        // Prefer configured path only when it matches the current script path.
        // This keeps deployments working when APP_URL is stale.
        if ($configuredBasePath !== '' && str_starts_with($scriptName, $configuredBasePath . '/')) {
            $basePath = $configuredBasePath;
            return $basePath;
        }
    }

    $basePath = $detectedBasePath;

    return $basePath;
}

function appUrl(string $path = '/'): string
{
    $basePath = appBasePath();
    $normalizedPath = '/' . ltrim($path, '/');

    if ($normalizedPath === '/') {
        return $basePath !== '' ? $basePath . '/' : '/';
    }

    if ($basePath !== '' && str_starts_with($normalizedPath, $basePath . '/')) {
        return $normalizedPath;
    }

    return ($basePath !== '' ? $basePath : '') . $normalizedPath;
}

function redirect(string $path): never
{
    // Guard against open redirect via protocol-relative or absolute external URLs
    if (!str_starts_with($path, '/') || str_starts_with($path, '//')) {
        $path = '/';
    }
    header('Location: ' . appUrl($path));
    exit;
}

function isPost(): bool
{
    return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST';
}

/**
 * @return list<string>
 */
function parseLinks(string $linksText): array
{
    $lines = preg_split('/\R/', $linksText) ?: [];
    $result = [];

    foreach ($lines as $line) {
        $url = trim($line);
        if ($url === '') {
            continue;
        }

        if (filter_var($url, FILTER_VALIDATE_URL)) {
            $scheme = strtolower((string) parse_url($url, PHP_URL_SCHEME));
            if (in_array($scheme, ['http', 'https'], true)) {
                $result[] = $url;
            }
        }
    }

    return array_values(array_unique($result));
}
