<?php
// app/actions/image_library_save.php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../includes/auth.php';

// Vain admin
$user = sf_current_user();
if (!$user || (int)$user['role_id'] !== 1) {
    http_response_code(403);
    exit('Ei oikeuksia');
}

$baseUrl = rtrim($config['base_url'] ?? '', '/');
$action  = $_POST['action'] ?? '';

$mysqli = sf_db();

// Luo uploads/library kansio jos ei ole
$uploadDir = __DIR__ . '/../../uploads/library/';
if (!is_dir($uploadDir)) {
    mkdir($uploadDir, 0755, true);
}

switch ($action) {
    case 'add':
        // Validoi
        $title       = trim($_POST['title'] ?? '');
        $category    = trim($_POST['category'] ?? 'body');
        $description = trim($_POST['description'] ?? '');

        $allowedCategories = ['body', 'warning', 'equipment', 'template'];
        if (!in_array($category, $allowedCategories, true)) {
            $category = 'body';
        }

        if ($title === '') {
            header("Location: {$baseUrl}/index.php?page=settings&tab=image_library&error=missing_title");
            exit;
        }

        // Tarkista tiedosto
        if (!isset($_FILES['image']) || $_FILES['image']['error'] !== UPLOAD_ERR_OK) {
            header("Location: {$baseUrl}/index.php?page=settings&tab=image_library&error=upload_failed");
            exit;
        }

        $file         = $_FILES['image'];
        $originalName = $file['name'];
        $tmpPath      = $file['tmp_name'];

        // Tarkista tyyppi
        $allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp', 'image/svg+xml'];
        $finfo        = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType     = finfo_file($finfo, $tmpPath);
        finfo_close($finfo);

        if (!in_array($mimeType, $allowedTypes, true)) {
            header("Location: {$baseUrl}/index.php?page=settings&tab=image_library&error=invalid_type");
            exit;
        }

        // Luo uniikki tiedostonimi
        $ext      = pathinfo($originalName, PATHINFO_EXTENSION);
        $filename = 'lib_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;

        // Siirrä tiedosto
        $destPath = $uploadDir . $filename;
        if (!move_uploaded_file($tmpPath, $destPath)) {
            header("Location: {$baseUrl}/index.php?page=settings&tab=image_library&error=move_failed");
            exit;
        }

        // Tallenna kantaan
        $stmt = $mysqli->prepare("
            INSERT INTO sf_image_library 
            (filename, original_name, category, title, description, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, NOW())
        ");
        $userId = (int) $user['id'];
        $stmt->bind_param('sssssi', $filename, $originalName, $category, $title, $description, $userId);
        $stmt->execute();

        header("Location: {$baseUrl}/index.php?page=settings&tab=image_library&notice=image_added");
        exit;

    case 'toggle':
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            $mysqli->query("UPDATE sf_image_library SET is_active = NOT is_active WHERE id = {$id}");
        }
        header("Location: {$baseUrl}/index.php?page=settings&tab=image_library");
        exit;

    case 'delete':
        $id = (int) ($_POST['id'] ?? 0);
        if ($id > 0) {
            // Hae tiedostonimi
            $res = $mysqli->query("SELECT filename FROM sf_image_library WHERE id = {$id}");
            $row = $res->fetch_assoc();

            if ($row) {
                // Poista tiedosto
                $filePath = $uploadDir . $row['filename'];
                if (file_exists($filePath)) {
                    unlink($filePath);
                }

                // Poista kannasta
                $mysqli->query("DELETE FROM sf_image_library WHERE id = {$id}");
            }
        }
        header("Location: {$baseUrl}/index.php?page=settings&tab=image_library&notice=image_deleted");
        exit;

    default:
        header("Location: {$baseUrl}/index.php?page=settings&tab=image_library");
        exit;
}