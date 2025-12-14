<?php
// safetyflash-system/config.php

// Lataa ympäristömuuttujat .env-tiedostosta jos olemassa
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            list($key, $value) = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value, " \t\n\r\0\x0B\"'");
            putenv("$key=$value");
            $_ENV[$key] = $value;
        }
    }
}

$config = [
    // Järjestelmän perus-URL
    'base_url' => getenv('APP_BASE_URL') ?: 'https://tapojarvi.online/safetyflash-system',

    // Tietokanta-asetukset
    'db' => [
        'host'    => getenv('DB_HOST') ?: 'localhost',
        'name'    => getenv('DB_NAME') ?: '',
        'user'    => getenv('DB_USER') ?: '',
        'pass'    => getenv('DB_PASS') ?: '',
        'charset' => 'utf8mb4',
    ],

    // Tiedostopolut (näitä käyttää mm. upload.php)
    'storage' => [
        'images_dir' => __DIR__ . '/uploads/images',
        'images_url' => '/safetyflash-system/uploads/images',
    ],

    // Debug-tila (false tuotannossa)
    'debug' => getenv('APP_DEBUG') === 'true',
];

// Alusta keskitetty tietokantapalvelu
require_once __DIR__ . '/app/lib/Database.php';
Database::setConfig($config['db']);