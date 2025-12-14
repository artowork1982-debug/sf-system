<?php
// app/api/users_delete.php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/protect.php';

header('Content-Type: application/json; charset=utf-8');

sf_require_role([1]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['ok' => false, 'error' => 'Invalid method']);
    exit;
}

$mysqli = sf_db();
$id = (int)($_POST['id'] ?? 0);

if ($id <= 0) {
    echo json_encode(['ok' => false, 'error' => 'Virheellinen ID']);
    exit;
}

// Pehmeä poisto
$stmt = $mysqli->prepare('UPDATE sf_users SET is_active = 0 WHERE id = ?');
$stmt->bind_param('i', $id);

if (!$stmt->execute()) {
    echo json_encode(['ok' => false, 'error' => 'DB-virhe poistossa']);
    exit;
}

echo json_encode(['ok' => true]);