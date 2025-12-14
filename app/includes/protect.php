<?php
// app/includes/protect.php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/csrf.php';

// Estetään suojaus loginissa & API:ssa
$current = $_SERVER['REQUEST_URI'] ?? '';

$publicPaths = [
    '/app/pages/login.php',
    '/app/api/login.php',
    '/app/pages/logout.php',
];

foreach ($publicPaths as $pub) {
    if (str_ends_with($current, $pub)) {
        return; // ei vaadita loginia näille
    }
}

sf_require_login();