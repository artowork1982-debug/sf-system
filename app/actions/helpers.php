<?php
// app/actions/helpers.php

declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

function sf_get_pdo(): PDO {
    global $config;

    return new PDO(
        'mysql:host=' . $config['db']['host'] .
        ';dbname=' . $config['db']['name'] .
        ';charset=' . $config['db']['charset'],
        $config['db']['user'],
        $config['db']['pass'],
        [ PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION ]
    );
}

function sf_redirect(string $url): never {
    header("Location: $url");
    exit;
}

function sf_validate_id(): int {
    $id = (int)($_GET['id'] ?? 0);
    if ($id <= 0) {
        die("Virheellinen ID");
    }
    return $id;
}