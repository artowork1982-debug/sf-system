<?php
// app/api/users_reset_password.php
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

// Generoi uusi salasana
function sf_random_password(int $length = 10): string {
    $chars = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';
    $out = '';
    $max = strlen($chars) - 1;
    for ($i = 0; $i < $length; $i++) {
        $out .= $chars[random_int(0, $max)];
    }
    return $out;
}

$newPass = sf_random_password(10);
$hash    = password_hash($newPass, PASSWORD_DEFAULT);

$stmt = $mysqli->prepare('UPDATE sf_users SET password_hash = ? WHERE id = ?');
$stmt->bind_param('si', $hash, $id);

if (!$stmt->execute()) {
    echo json_encode(['ok' => false, 'error' => 'DB-virhe resetissä']);
    exit;
}

echo json_encode(['ok' => true, 'password' => $newPass]);