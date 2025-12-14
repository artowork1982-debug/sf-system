<?php
/**
 * app/api/get_library_images.php
 */
declare(strict_types=1);

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

// Lataa config (määrittelee $config-taulukon)
require __DIR__ . '/../../config.php';

// Tarkista kirjautuminen
if (empty($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Tietokantayhteys
try {
    $mysqli = new mysqli(
        $config['db']['host'],
        $config['db']['user'],
        $config['db']['pass'],
        $config['db']['name']
    );

    if ($mysqli->connect_error) {
        throw new Exception('Database connection failed');
    }

    $mysqli->set_charset($config['db']['charset'] ?? 'utf8mb4');

} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
    exit;
}

// Kategoriafiltteri
$category = $_GET['category'] ?? '';

$sql = "SELECT id, filename, title, category, description 
        FROM sf_image_library 
        WHERE is_active = 1";

if ($category !== '' && $category !== 'all') {
    $sql .= " AND category = '" . $mysqli->real_escape_string($category) . "'";
}

$sql .= " ORDER BY category ASC, sort_order ASC, title ASC";

$result = $mysqli->query($sql);

if (!$result) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed']);
    exit;
}

$basePath = __DIR__ . '/../../';

$images = [];
while ($row = $result->fetch_assoc()) {
    $filename = $row['filename'];

    // Tarkista fyysinen sijainti – tuki kahdelle hakemistolle
    if (file_exists($basePath . 'uploads/library/' . $filename)) {
        $url = '/uploads/library/' . $filename;
    } elseif (file_exists($basePath . 'uploads/images/' . $filename)) {
        $url = '/uploads/images/' . $filename;
    } else {
        // fallback – viittaa oletushakemistoon
        $url = '/uploads/library/' . $filename;
    }

    $images[] = [
        'id'          => (int) $row['id'],
        'filename'    => $filename,
        'title'       => $row['title'] ?? '',
        'category'    => $row['category'] ?? '',
        'description' => $row['description'] ?? '',
        'url'         => $url,
    ];
}

$mysqli->close();

echo json_encode([
    'success' => true,
    'images'  => $images,
    'count'   => count($images),
], JSON_UNESCAPED_UNICODE);