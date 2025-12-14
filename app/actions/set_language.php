<?php
require_once __DIR__ . '/../../config.php';
session_start();

$lang = $_POST['lang'] ?? 'fi';
$allowed = ['fi', 'en'];

if (!in_array($lang, $allowed, true)) {
    $lang = 'fi';
}

$_SESSION['ui_lang'] = $lang;

// Palataan takaisin edelliselle sivulle
$redirect = $_SERVER['HTTP_REFERER'] ?? ($config['base_url'] . '/index.php?page=list');
header('Location: ' . $redirect);
exit;