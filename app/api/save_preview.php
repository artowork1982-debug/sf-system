<?php
// app/api/save_preview.php
// New full-featured version: saves 1920x1080 JPG for displays,
// uses Imagick if available (higher quality), fallback to GD.
// Handles transparency, unique filenames, and moves to `uploads/previews`.
//
// Expects POST data: 'id' (flashId), 'image' (dataURL from html2canvas)
declare(strict_types=1);

ini_set('display_errors', '1');
error_reporting(E_ALL);

header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../../config.php';

// Constants for image processing (adjust as needed)
define('PREVIEW_TARGET_WIDTH', 1920);
define('PREVIEW_TARGET_HEIGHT', 1080);
define('PREVIEW_JPG_QUALITY', 88);
define('UPLOADS_PREVIEWS_DIR', __DIR__ . '/../../uploads/previews/');

// Ensure upload dir exists
if (!is_dir(UPLOADS_PREVIEWS_DIR)) {
    if (!@mkdir(UPLOADS_PREVIEWS_DIR, 0755, true)) {
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Cannot create preview directory']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$db = $config['db'] ?? null;
if (!$db) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'No DB config']);
    exit;
}

$mysqli = @new mysqli($db['host'], $db['user'], $db['pass'], $db['name']);
if ($mysqli->connect_error) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'DB connection error']);
    exit;
}
$mysqli->set_charset($db['charset'] ?? 'utf8mb4');

// Helpers: safe filename & unique name creation
function sf_slug(string $str): string {
    $str = mb_strtolower($str, 'UTF-8');
    $str = iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $str) ?: $str;
    $str = preg_replace('/[^a-z0-9\-]+/', '-', $str);
    $str = trim($str, '-');
    return $str ?: 'na';
}
function sf_unique_filename(string $dir, string $basename, string $ext): string {
    $i = 0;
    do {
        $suffix = $i === 0 ? '' : "-$i";
        $name = $basename . $suffix . '.' . $ext;
        $i++;
    } while (file_exists($dir . $name) && $i < 500);
    return $name;
}

// Inputs
$flashId = isset($_POST['id']) ? (int)$_POST['id'] : 0;
$image   = $_POST['image'] ?? ''; // dataURL

if ($flashId <= 0 || !$image) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing id or image']);
    exit;
}

// Fetch flash record for filename context and old file cleanup
$stmt = $mysqli->prepare("SELECT occurred_at, site, title_short, title, preview_filename FROM sf_flashes WHERE id = ? LIMIT 1");
$stmt->bind_param('i', $flashId);
$stmt->execute();
$res = $stmt->get_result();
$flash = $res->fetch_assoc();
$stmt->close();

if (!$flash) {
    http_response_code(404);
    echo json_encode(['success' => false, 'error' => 'Flash not found']);
    exit;
}

// Delete old preview file if it exists
if (!empty($flash['preview_filename'])) {
    $oldPath1 = UPLOADS_PREVIEWS_DIR . $flash['preview_filename'];
    $oldPath2 = __DIR__ . '/../../img/' . $flash['preview_filename']; // legacy dir
    if (is_file($oldPath1)) @unlink($oldPath1);
    if (is_file($oldPath2)) @unlink($oldPath2);
}

// Decode base64 dataURL
if (!preg_match('#^data:image/[^;]+;base64,(.+)$#', $image, $m)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid dataURL format']);
    exit;
}
$binary = base64_decode($m[1]);
if ($binary === false) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid base64 data']);
    exit;
}
if (strlen($binary) > 15 * 1024 * 1024) { // 15MB decoded data limit
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Image data too large']);
    exit;
}

// Generate new unique filename
$datePart = !empty($flash['occurred_at']) ? (new DateTime($flash['occurred_at']))->format('Ymd') : date('Ymd');
$siteSlug  = sf_slug($flash['site'] ?? 'site');
$titleSlug = sf_slug($flash['title_short'] ?: ($flash['title'] ?? 'flash'));
$baseName = sprintf('SF_%d_%s_%s_%s', $flashId, $datePart, $siteSlug, mb_substr($titleSlug, 0, 50));
$filename = sf_unique_filename(UPLOADS_PREVIEWS_DIR, $baseName, 'jpg');
$targetPath = UPLOADS_PREVIEWS_DIR . $filename;
$saved = false;

// Process with Imagick (preferred) or GD
if (extension_loaded('imagick')) {
    try {
        $im = new Imagick();
        $im->readImageBlob($binary);
        if ($im->getImageAlphaChannel()) { // Flatten transparency
            $im->setImageBackgroundColor('white');
            $im = $im->mergeImageLayers(Imagick::LAYERMETHOD_FLATTEN);
        }
        $im->cropThumbnailImage(PREVIEW_TARGET_WIDTH, PREVIEW_TARGET_HEIGHT);
        $im->setImageFormat('jpeg');
        $im->setImageCompression(Imagick::COMPRESSION_JPEG);
        $im->setImageCompressionQuality(PREVIEW_JPG_QUALITY);
        $im->stripImage(); // Remove metadata
        $saved = $im->writeImage($targetPath);
        $im->destroy();
    } catch (ImagickException $e) {
        error_log('save_preview Imagick error: ' . $e->getMessage());
        // GD will be attempted as fallback
    }
}

if (!$saved) { // Fallback to GD if Imagick failed or not available
    try {
        $src = @imagecreatefromstring($binary);
        if ($src === false) throw new Exception('imagecreatefromstring failed');
        
        $srcW = imagesx($src); $srcH = imagesy($src);
        $targetW = PREVIEW_TARGET_WIDTH; $targetH = PREVIEW_TARGET_HEIGHT;

        $scale = max($targetW / $srcW, $targetH / $srcH);
        $scaledW = (int)ceil($srcW * $scale);
        $scaledH = (int)ceil($srcH * $scale);

        $tmp = imagecreatetruecolor($scaledW, $scaledH);
        $white = imagecolorallocate($tmp, 255, 255, 255);
        imagefill($tmp, 0, 0, $white);
        imagecopyresampled($tmp, $src, 0, 0, 0, 0, $scaledW, $scaledH, $srcW, $srcH);
        imagedestroy($src);
        
        $dst = imagecreatetruecolor($targetW, $targetH);
        imagefill($dst, 0, 0, $white);
        $srcX = (int)floor(($scaledW - $targetW) / 2);
        $srcY = (int)floor(($scaledH - $targetH) / 2);
        imagecopy($dst, $tmp, 0, 0, $srcX, $srcY, $targetW, $targetH);
        imagedestroy($tmp);

        $saved = imagejpeg($dst, $targetPath, PREVIEW_JPG_QUALITY);
        imagedestroy($dst);
    } catch (Exception $e) {
        error_log('save_preview GD error: ' . $e->getMessage());
    }
}

if (!$saved) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save image']);
    exit;
}

// Update DB with the new preview filename
$stmt = $mysqli->prepare("UPDATE sf_flashes SET preview_filename = ?, image_main = ? WHERE id = ?");
$stmt->bind_param('ssi', $filename, $filename, $flashId);
$stmt->execute();
$stmt->close();
$mysqli->close();

echo json_encode([
    'success'   => true,
    'filename'  => $filename,
    'image_url' => $config['base_url'] . '/uploads/previews/' . $filename,
]);