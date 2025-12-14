<?php
require_once __DIR__ . '/config.php';

define('SF_UPLOAD_DIR', __DIR__ . '/uploads/images/');
define('SF_UPLOAD_URL', '/safetyflash-system/uploads/images/');

if (!isset($_FILES['image'])) {
    exit(json_encode(['error' => 'No file uploaded']));
}

$file = $_FILES['image'];

// ⚠️ KORJAUS: Tarkista OIKEA tiedostotyyppi finfo:lla
$finfo = new finfo(FILEINFO_MIME_TYPE);
$realMimeType = $finfo->file($file['tmp_name']);

$allowed = ['image/jpeg', 'image/png'];
if (!in_array($realMimeType, $allowed, true)) {
    exit(json_encode(['error' => 'Invalid file type: ' . $realMimeType]));
}

// ⚠️ KORJAUS: Tarkista myös tiedostopääte
$ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
$allowedExt = ['jpg', 'jpeg', 'png'];
if (!in_array($ext, $allowedExt, true)) {
    exit(json_encode(['error' => 'Invalid extension']));
}

// ⚠️ KORJAUS: Varmista että kuva on oikeasti kuva
$imageInfo = @getimagesize($file['tmp_name']);
if ($imageInfo === false) {
    exit(json_encode(['error' => 'File is not a valid image']));
}

// Luo uniikki nimi
$newName = uniqid('sf_', true) . '.' . $ext;
$target = SF_UPLOAD_DIR . $newName;

if (!is_dir(SF_UPLOAD_DIR)) {
    mkdir(SF_UPLOAD_DIR, 0755, true); // ⚠️ KORJAUS: 0755, ei 0775
}

if (!move_uploaded_file($file['tmp_name'], $target)) {
    exit(json_encode(['error' => 'Upload failed']));
}

echo json_encode([
    'success'  => true,
    'filename' => $newName,
    'url'      => SF_UPLOAD_URL . $newName,
]);