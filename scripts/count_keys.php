<?php
require_once __DIR__ . '/../app/Core/Language.php';

$ref = new ReflectionClass(\App\Core\Language::class);
$method = $ref->getMethod('translations');
$method->setAccessible(true);
$all = $method->invoke(null);

foreach ($all as $lang => $keys) {
    echo $lang . ': ' . count($keys) . ' keys' . PHP_EOL;
}

// Find keys only in EN but missing in other languages
$enKeys = array_keys($all['en']);
foreach (['nl','de','fr','es'] as $lang) {
    $missing = array_diff($enKeys, array_keys($all[$lang] ?? []));
    if ($missing) {
        echo PHP_EOL . "Missing in $lang (" . count($missing) . "): " . implode(', ', array_slice($missing, 0, 20));
        if (count($missing) > 20) echo ' ...';
        echo PHP_EOL;
    } else {
        echo "$lang: fully complete" . PHP_EOL;
    }
}
