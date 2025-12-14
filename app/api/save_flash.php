<?php
// app/api/save_flash.php
// FULL VERSION - WITH ANNOTATIONS SUPPORT + INVESTIGATION REPORT (GREEN) SUPPORT

declare(strict_types=1);

// Load config + session protections
require_once __DIR__ . '/../../config.php';

// Debug-asetukset konfiguraation mukaan
if ($config['debug'] ??  false) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
}

require_once __DIR__ . '/../includes/protect.php';
require_once __DIR__ . '/../includes/statuses.php';
require_once __DIR__ . '/../includes/csrf.php';
// Lokitus
require_once __DIR__ . '/../includes/audit_log.php';

// Tietokantaan tallentava lokifunktio (sf_log_event)
if (file_exists(__DIR__ . '/../includes/log.php')) {
    require_once __DIR__ . '/../includes/log.php';
}

// Debug-lokitus tiedostoon (sf_app_log) - valinnainen
if (file_exists(__DIR__ . '/../includes/log_app.php')) {
    require_once __DIR__ . '/../includes/log_app.php';
}

// sähköpostipalvelut (turvatiimille, viestintään jne.)
require_once __DIR__ . '/../services/email_services.php';


/**
 * Tallentaa dataURL-muotoisen kuvan JPG: ksi
 * Käyttää Imagick:kia (parempi laatu) tai GD:tä (fallback)
 * @param string $dataurl Base64 dataURL (esim. "data:image/jpeg;base64,...")
 * @param string $uploadDir Kohdekansiopolku
 * @param string $prefix Tiedostonimen etuliite
 * @return string|false Tiedoston nimi tai false
 */
function sf_save_dataurl_preview_v2($dataurl, $uploadDir, $prefix = 'preview') {
    if (empty($dataurl) || strpos($dataurl, 'data:image') !== 0) {
        error_log('sf_save_dataurl_preview_v2: Invalid dataurl');
        return false;
    }

    $parts = explode(',', $dataurl);
    if (count($parts) !== 2) {
        error_log('sf_save_dataurl_preview_v2: Could not parse dataurl');
        return false;
    }

    $imageData = base64_decode($parts[1], true);
    if ($imageData === false) {
        error_log('sf_save_dataurl_preview_v2: base64_decode failed');
        return false;
    }

    // Luo ainutlaatuinen tiedostonimi
    $filename = $prefix . '_' . time() . '_' . uniqid() . '.jpg';
    $targetPath = $uploadDir . $filename;

    $saved = false;

    try {
        if (extension_loaded('imagick')) {
            error_log('sf_save_dataurl_preview_v2: Using Imagick');
            $im = new Imagick();
            $im->readImageBlob($imageData);
            
            // Poista alpha-kanava jos olemassa
            if ($im->getImageAlphaChannel()) {
                $im->setImageBackgroundColor('white');
                $im = $im->mergeImageLayers(Imagick:: LAYERMETHOD_FLATTEN);
            }
            
            $im->cropThumbnailImage(1920, 1080);
            $im->setImageFormat('jpeg');
            $im->setImageCompression(Imagick::COMPRESSION_JPEG);
            $im->setImageCompressionQuality(88);
            $im->writeImage($targetPath);
            $saved = true;
            error_log('sf_save_dataurl_preview_v2:  Saved with Imagick: ' . $filename);
        } else if (extension_loaded('gd')) {
            error_log('sf_save_dataurl_preview_v2: Using GD');
            $image = @imagecreatefromstring($imageData);
            if ($image !== false) {
                $src_width = imagesx($image);
                $src_height = imagesy($image);
                
                $dst_width = 1920;
                $dst_height = 1080;
                $dst = imagecreatetruecolor($dst_width, $dst_height);
                
                // Täytä valkoinen tausta
                $white = imagecolorallocate($dst, 255, 255, 255);
                imagefill($dst, 0, 0, $white);
                
                // Kopioi kuva skaalattuna
                imagecopyresampled(
                    $dst, $image,
                    0, 0, 0, 0,
                    $dst_width, $dst_height,
                    $src_width, $src_height
                );
                
                imagejpeg($dst, $targetPath, 88);
                imagedestroy($image);
                imagedestroy($dst);
                $saved = true;
                error_log('sf_save_dataurl_preview_v2: Saved with GD: ' .  $filename);
            }
        }
    } catch (Exception $e) {
        error_log('sf_save_dataurl_preview_v2: Exception: ' . $e->getMessage());
    }

    if (!$saved) {
        error_log('sf_save_dataurl_preview_v2: Failed to save image');
        return false;
    }

    return $filename;
}


$base = rtrim($config['base_url'] ?? '', '/');

// Upload configuration
define('UPLOADS_IMAGES_DIR', __DIR__ . '/../../uploads/images/');
define('UPLOADS_PREVIEWS_DIR', __DIR__ . '/../../uploads/previews/');
define('ALLOWED_IMAGE_MIME', ['image/jpeg', 'image/png', 'image/webp']);
define('MAX_IMAGE_BYTES', 5 * 1024 * 1024); // 5 MB

// Ensure upload dirs exist
if (!is_dir(UPLOADS_IMAGES_DIR)) {
    @mkdir(UPLOADS_IMAGES_DIR, 0755, true);
}
if (!is_dir(UPLOADS_PREVIEWS_DIR)) {
    @mkdir(UPLOADS_PREVIEWS_DIR, 0755, true);
}

// --- HELPER FUNCTIONS ---

function sf_safe_filename(string $name): string
{
    $name = preg_replace('/[^\w\-. ]+/u', '_', $name);
    $name = preg_replace('/_+/', '_', $name);
    $name = trim($name, '._');
    if ($name === '') {
        $name = bin2hex(random_bytes(4));
    }
    return mb_substr($name, 0, 200);
}

function sf_unique_filename(string $dir, string $basename, string $ext): string
{
    $i = 0;
    do {
        $suffix = $i === 0 ? '' : "-$i";
        $name = $basename . $suffix . '.' . $ext;
        $i++;
    } while (file_exists($dir . $name) && $i < 1000);
    return $name;
}

function sf_save_dataurl_preview(string $dataUrl, ?string $destDir = null, string $prefix = 'preview'): ?string
{
    if (!$dataUrl) {
        return null;
    }

    $destDir = $destDir ?: UPLOADS_PREVIEWS_DIR;

    if (preg_match('#^data:(image/[^;]+);base64,(.+)$#', $dataUrl, $m)) {
        $mime = $m[1];
        $b64  = $m[2];

        $extMap = [
            'image/png'  => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        $ext  = $extMap[$mime] ?? 'png';
        $data = base64_decode($b64);

        if ($data === false) {
            return null;
        }

        if (strlen($data) > (10 * 1024 * 1024)) {
            return null;
        }

        $base     = sf_safe_filename($prefix . '_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)));
        $filename = sf_unique_filename($destDir, $base, $ext);
        $path     = $destDir . $filename;

        if (@file_put_contents($path, $data) !== false) {
            return $filename;
        }
    }

    return null;
}

function sf_handle_uploaded_image(array $file, ?string $destDir = null): ?string
{
    if (!isset($file['tmp_name']) || $file['error'] !== UPLOAD_ERR_OK) {
        if (function_exists('sf_app_log')) {
            sf_app_log("sf_handle_uploaded_image: Skipped - error=" . ($file['error'] ?? 'N/A'));
        }
        return null;
    }

    $destDir      = $destDir ?: UPLOADS_IMAGES_DIR;
    $tmp          = $file['tmp_name'];
    $originalSize = filesize($tmp);

    // Salli suuremmat tiedostot uploadissa, kompressoidaan myöhemmin
    $maxUploadSize = 20 * 1024 * 1024; // 20 MB upload-raja
    if ($originalSize > $maxUploadSize) {
        if (function_exists('sf_app_log')) {
            sf_app_log("sf_handle_uploaded_image: REJECTED - size={$originalSize} exceeds {$maxUploadSize}");
        }
        return null;
    }

    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime  = $finfo->file($tmp);
    if (!in_array($mime, ALLOWED_IMAGE_MIME, true)) {
        if (function_exists('sf_app_log')) {
            sf_app_log("sf_handle_uploaded_image: REJECTED - invalid mime: {$mime}");
        }
        return null;
    }

    $origName = basename($file['name'] ?? ('img_' . time()));
    $base     = sf_safe_filename(pathinfo($origName, PATHINFO_FILENAME));

    // Aina tallennetaan JPEG-muodossa (paras kompressio valokuville)
    $filename = sf_unique_filename($destDir, $base, 'jpg');
    $dest     = $destDir . $filename;

    // Kompressoi ja skaalaa kuva
    $compressed = sf_compress_image($tmp, $dest, $mime);

    if ($compressed) {
        @chmod($dest, 0644);
        if (function_exists('sf_app_log')) {
            $newSize   = filesize($dest);
            $reduction = round((1 - $newSize / $originalSize) * 100);
            sf_app_log("sf_handle_uploaded_image: OK - {$origName} compressed {$originalSize} -> {$newSize} bytes ({$reduction}% reduction)");
        }
        return $filename;
    }

    // Fallback: yritä siirtää alkuperäinen
    if (@move_uploaded_file($tmp, $dest)) {
        @chmod($dest, 0644);
        return $filename;
    }

    return null;
}

/**
 * Kompressoi ja skaalaa kuva sopivaan kokoon
 * Maksimileveys 1920px, JPEG-laatu 85%
 */
function sf_compress_image(string $source, string $dest, string $mime): bool
{
    $maxWidth    = 1920;
    $maxHeight   = 1920;
    $jpegQuality = 85;

    // Luo kuva lähdetiedostosta
    switch ($mime) {
        case 'image/jpeg':
            $srcImage = @imagecreatefromjpeg($source);
            break;
        case 'image/png':
            $srcImage = @imagecreatefrompng($source);
            break;
        case 'image/webp':
            $srcImage = @imagecreatefromwebp($source);
            break;
        default:
            return false;
    }

    if (!$srcImage) {
        if (function_exists('sf_app_log')) {
            sf_app_log("sf_compress_image: Failed to create image from {$mime}");
        }
        return false;
    }

    // Alkuperäiset mitat
    $origWidth  = imagesx($srcImage);
    $origHeight = imagesy($srcImage);

    // Laske uudet mitat säilyttäen kuvasuhde
    $ratio      = min($maxWidth / $origWidth, $maxHeight / $origHeight, 1.0);
    $newWidth   = (int) round($origWidth * $ratio);
    $newHeight  = (int) round($origHeight * $ratio);

    // Luo uusi kuva
    $newImage = imagecreatetruecolor($newWidth, $newHeight);

    if (!$newImage) {
        imagedestroy($srcImage);
        return false;
    }

    // Tausta valkoiseksi
    $white = imagecolorallocate($newImage, 255, 255, 255);
    imagefill($newImage, 0, 0, $white);

    // Skaalaa kuva
    $resized = imagecopyresampled(
        $newImage,
        $srcImage,
        0,
        0,
        0,
        0,
        $newWidth,
        $newHeight,
        $origWidth,
        $origHeight
    );

    if (!$resized) {
        imagedestroy($srcImage);
        imagedestroy($newImage);
        return false;
    }

    // Tallenna JPEG-muodossa
    $saved = imagejpeg($newImage, $dest, $jpegQuality);

    // Siivoa muisti
    imagedestroy($srcImage);
    imagedestroy($newImage);

    if (function_exists('sf_app_log') && $saved) {
        sf_app_log("sf_compress_image: Resized {$origWidth}x{$origHeight} -> {$newWidth}x{$newHeight}");
    }

    return $saved;
}

function sf_unlink_if_exists(?string $path): void
{
    if (!$path) {
        return;
    }
    if (is_file($path)) {
        @unlink($path);
    }
}

// --- MAIN LOGIC ---

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: ' . $base . '/index.php?page=list');
    exit;
}

// CSRF-tarkistus
sf_csrf_check();

$post = $_POST + [];

$id = isset($post['id']) ? (int) $post['id'] : 0;

// Tutkintatiedotteen pohja-ID
$related_flash_id = isset($post['related_flash_id']) ? (int) $post['related_flash_id'] : 0;

// nappi: draft / review
$submissionType = trim((string) ($post['submission_type'] ?? 'review'));

$title       = trim((string) ($post['title'] ?? ''));
$title_short = trim((string) ($post['title_short'] ?? ($post['short_text'] ?? ($post['title_short'] ?? ''))));
$summary     = trim((string) ($post['summary'] ?? $title_short));
$description = trim((string) ($post['description'] ?? ''));
$type        = trim((string) ($post['type'] ?? $post['sf_type'] ?? ''));

// Työmaa – huomioi tutkintatiedotteen eri kentät
$site = trim((string) (
    $post['site']
    ?? $post['worksite']
    ?? $post['worksite_investigation']
    ?? ''
));

$site_detail = trim((string) (
    $post['site_detail']
    ?? $post['site_detail_investigation']
    ?? ''
));

// Päivämäärä – huomioi tutkintatiedotteen eri kentät
$occurred_at = trim((string) (
    $post['occurred_at']
    ?? $post['event_date']
    ?? $post['event_date_investigation']
    ?? ''
));

$lang          = trim((string) ($post['lang'] ?? $_SESSION['lang'] ?? 'fi'));
$grid_style    = trim((string) ($post['grid_style'] ?? 'auto'));
$preview_dataurl = trim((string) ($post['preview_image_data'] ?? ''));

// UUSI: Toisen kortin preview (tutkintatiedote)
$preview_dataurl_2 = trim((string) ($post['preview_image_data_2'] ?? ''));

// DEBUG: Lokita preview-datan tila
if (function_exists('sf_app_log')) {
    sf_app_log('=== PREVIEW DATA DEBUG ===');
    sf_app_log('preview_image_data length: ' . strlen($preview_dataurl));
    sf_app_log('preview_image_data_2 length: ' . strlen($preview_dataurl_2));

    if ($preview_dataurl) {
        sf_app_log('preview_image_data starts with: ' . substr($preview_dataurl, 0, 50));
    } else {
        sf_app_log('preview_image_data is EMPTY!');
    }

    // Tarkista onko POST-data mukana
    sf_app_log('POST keys: ' . implode(', ', array_keys($post)));
    sf_app_log('=== END PREVIEW DEBUG ===');
}

// UUSI: Tutkintatiedotteen lisäkentät (juurisyyt ja toimenpiteet)
$root_causes = trim((string) ($post['root_causes'] ?? ''));
$actions     = trim((string) ($post['actions'] ?? ''));

// Kuvien transform-tiedot (asemointi)
$image1_transform = trim((string) ($post['image1_transform'] ?? ''));
$image2_transform = trim((string) ($post['image2_transform'] ?? ''));
$image3_transform = trim((string) ($post['image3_transform'] ?? ''));

// Merkintöjen data (annotations) – huomioi molemmat kentät
$annotations_data = trim((string) (
    $post['annotations_data']
    ?? $post['annotations_data_green']
    ?? ''
));

// Jos tyhjä → käytä tyhjää taulukkoa
if ($annotations_data === '') {
    $annotations_data = '[]';
}

// Varmista JSON-validius
$decoded = json_decode($annotations_data, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($decoded)) {
    $annotations_data = '[]';
}

$files = $_FILES ?? [];

// === DEBUG - käytä sovelluksen omaa lokitusta ===
if (function_exists('sf_app_log')) {
    sf_app_log('=== save_flash.php DEBUG ===');
    sf_app_log('$_FILES count: ' . count($files));

    foreach ($files as $key => $file) {
        sf_app_log(
            "File '$key': " .
            "name=" . ($file['name'] ?? 'N/A') .
            ", error=" . ($file['error'] ?? 'N/A') .
            ", size=" . ($file['size'] ?? 0) .
            ", tmp_name=" . ($file['tmp_name'] ?? 'N/A')
        );
    }

    sf_app_log('$_POST keys: ' . implode(', ', array_keys($_POST)));
    sf_app_log('type: ' . ($post['type'] ?? 'N/A'));
    sf_app_log('=== END DEBUG ===');
}

// Create PDO
try {
    $pdo = Database::getInstance();
} catch (Throwable $e) {
    error_log('save_flash.php PDO error: ' . $e->getMessage());
    die('Database error. Please check logs.');
}

$occurred_at_db = $occurred_at ? date('Y-m-d H:i:s', strtotime($occurred_at)) : null;

$newPreviewFilename  = null;
$newPreviewFilename2 = null;
$uploadedFiles       = [];
$orig                = null;

// =====================================================
// TUTKINTATIEDOTE-LOGIIKKA
// Jos type=green JA related_flash_id on annettu,
// päivitetään alkuperäinen tietue eikä luoda uutta
// =====================================================
$isInvestigationUpdate = ($type === 'green' && $related_flash_id > 0 && $id === 0);

if ($isInvestigationUpdate) {
    // Tutkintatiedote päivittää alkuperäisen safetyflashin
    $id = $related_flash_id;
}

try {
    if (!$pdo->inTransaction()) {
        $pdo->beginTransaction();
    }

    if ($id > 0) {
        $s = $pdo->prepare('SELECT * FROM sf_flashes WHERE id = :id LIMIT 1');
        $s->execute([':id' => $id]);
        $orig = $s->fetch() ?: null;

        if (!$orig) {
            if ($pdo->inTransaction()) {
                $pdo->rollBack();
            }
            header('Location: ' . $base . '/index.php?page=list&notice=notfound');
            exit;
        }
    }

    // Tallenna ensimmäinen preview-kuva
    if ($preview_dataurl) {
        $saved = sf_save_dataurl_preview_v2($preview_dataurl, UPLOADS_PREVIEWS_DIR, 'preview');
        if ($saved) {
            $newPreviewFilename = $saved;
        }
    }

    // Tallenna toinen preview-kuva (tutkintatiedote kortti 2)
    if ($preview_dataurl_2 && $type === 'green') {
        $saved2 = sf_save_dataurl_preview_v2($preview_dataurl_2, UPLOADS_PREVIEWS_DIR, 'preview2');
        if ($saved2) {
            $newPreviewFilename2 = $saved2;
        }
    }

    // 1. Käsittele ladatut kuvat TAI kuvapankin kuvat
    foreach (['image1' => 'image_main', 'image2' => 'image_2', 'image3' => 'image_3'] as $field => $dbcol) {
        $libraryField = 'library_image_' . substr($field, -1);

        // Tarkista onko ladattu uusi kuva
        if (!empty($files[$field]) && is_array($files[$field]) && ($files[$field]['error'] === UPLOAD_ERR_OK)) {
            $saved = sf_handle_uploaded_image($files[$field], UPLOADS_IMAGES_DIR);
            if ($saved) {
                $uploadedFiles[$dbcol] = $saved;
            }
        }
        // TAI onko valittu kuvapankista
        elseif (!empty($post[$libraryField])) {
            $libraryFilename    = trim($post[$libraryField]);
            $librarySourcePath  = __DIR__ . '/../../uploads/library/' . $libraryFilename;

            if (file_exists($librarySourcePath)) {
                $ext         = pathinfo($libraryFilename, PATHINFO_EXTENSION);
                $newFilename = 'lib_copy_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $ext;
                $destPath    = UPLOADS_IMAGES_DIR . $newFilename;

                if (copy($librarySourcePath, $destPath)) {
                    $uploadedFiles[$dbcol] = $newFilename;
                    if (function_exists('sf_app_log')) {
                        sf_app_log("Copied library image: {$libraryFilename} -> {$newFilename}");
                    }
                }
            }
        }
    }

    // 2. Käytä existing_image_* kenttiä (related-flash.js:stä)
    foreach (['1' => 'image_main', '2' => 'image_2', '3' => 'image_3'] as $slot => $dbcol) {
        if (empty($uploadedFiles[$dbcol])) {
            $existingField = "existing_image_{$slot}";
            if (!empty($post[$existingField])) {
                $uploadedFiles[$dbcol] = trim($post[$existingField]);
            }
        }
    }

    // 3. TUTKINTATIEDOTE: Kopioi alkuperäisen kuvat jos mitään ei valittu
    if ($isInvestigationUpdate && $orig) {
        foreach (['image_main', 'image_2', 'image_3'] as $imgCol) {
            if (empty($uploadedFiles[$imgCol]) && !empty($orig[$imgCol])) {
                $uploadedFiles[$imgCol] = $orig[$imgCol];
            }
        }

        // Kopioi transform-tiedot jos tyhjiä
        if (empty($image1_transform) && !empty($orig['image1_transform'])) {
            $image1_transform = $orig['image1_transform'];
        }
        if (empty($image2_transform) && !empty($orig['image2_transform'])) {
            $image2_transform = $orig['image2_transform'];
        }
        if (empty($image3_transform) && !empty($orig['image3_transform'])) {
            $image3_transform = $orig['image3_transform'];
        }
    }

    // --- TILALOGIIKKA ---

    if ($id > 0) {
        $origState = $orig['state'] ?? 'draft';
        if ($submissionType === 'review') {
            $newState = 'pending_review';
        } elseif ($submissionType === 'draft') {
            $newState = 'draft';
        } else {
            $newState = $origState;
        }
    } else {
        $newState = ($submissionType === 'draft') ? 'draft' : 'pending_review';
    }

    if ($id > 0) {
        // UPDATE
        $fields = [
            'title'            => $title,
            'title_short'      => $title_short,
            'summary'          => $summary,
            'description'      => $description,
            'type'             => $type,
            'site'             => $site,
            'site_detail'      => $site_detail,
            'occurred_at'      => $occurred_at_db,
            'lang'             => $lang,
            'state'            => $newState,
            'root_causes'      => $root_causes, // UUSI
            'actions'          => $actions,     // UUSI
            'image1_transform' => $image1_transform,
            'image2_transform' => $image2_transform,
            'image3_transform' => $image3_transform,
            'annotations_data' => $annotations_data,
            'updated_at'       => date('Y-m-d H:i:s'),
        ];

        if ($newPreviewFilename !== null) {
            $fields['preview_filename'] = $newPreviewFilename;
        }

        // UUSI: Toisen preview-kuvan tallennus
        if ($newPreviewFilename2 !== null) {
            $fields['preview_filename_2'] = $newPreviewFilename2;
        }

        foreach ($uploadedFiles as $col => $fname) {
            $fields[$col] = $fname;
        }

        $setParts = [];
        $params   = [];
        foreach ($fields as $k => $v) {
            $setParts[]      = "`$k` = :$k";
            $params[":$k"]   = $v;
        }
        $params[':id'] = $id;

        $sql = 'UPDATE sf_flashes SET ' . implode(', ', $setParts) . ' WHERE id = :id';
        $upd = $pdo->prepare($sql);
        $upd->execute($params);

        // Poista vanhat preview-kuvat jos uudet ladattiin
        if ($newPreviewFilename && !empty($orig['preview_filename'])) {
            sf_unlink_if_exists(UPLOADS_PREVIEWS_DIR . $orig['preview_filename']);
            sf_unlink_if_exists(__DIR__ . '/../../img/' . $orig['preview_filename']);
        }

        // UUSI: Poista vanha toinen preview-kuva
        if ($newPreviewFilename2 && !empty($orig['preview_filename_2'])) {
            sf_unlink_if_exists(UPLOADS_PREVIEWS_DIR . $orig['preview_filename_2']);
            sf_unlink_if_exists(__DIR__ . '/../../img/' . $orig['preview_filename_2']);
        }

        foreach ($uploadedFiles as $col => $fname) {
            if (!empty($orig[$col])) {
                sf_unlink_if_exists(UPLOADS_IMAGES_DIR . $orig[$col]);
                sf_unlink_if_exists(__DIR__ . '/../../img/' . $orig[$col]);
            }
        }

        // === LOKITUS TIETOKANTAAN ===
        $origType = $orig['type'] ?? 'yellow';

        if (function_exists('sf_log_event')) {
            // Erillinen lokitus tutkintatiedotteelle
            if ($isInvestigationUpdate) {
                sf_log_event($id, 'investigation_added', "Tutkintatiedote lisätty. Tyyppi muutettu: {$origType} → green");
            } else {
                sf_log_event($id, 'edited', 'Tiedote muokattu');
            }

            if (($orig['state'] ?? '') !== $newState) {
                sf_log_event($id, 'status_changed', "Tila: {$newState}");
            }

            if ($origType !== $type) {
                sf_log_event($id, 'type_changed', "Tyyppi muutettu: {$origType} → {$type}");
            }
        } else {
            try {
                $logStmt = $pdo->prepare(
                    'INSERT INTO safetyflash_logs (flash_id, user_id, event_type, description, created_at)
                     VALUES (?, ?, ?, ?, NOW())'
                );

                if ($isInvestigationUpdate) {
                    $logStmt->execute([
                        $id,
                        $_SESSION['user_id'] ?? null,
                        'investigation_added',
                        "Tutkintatiedote lisätty. Tyyppi: {$origType} → green",
                    ]);
                } else {
                    $logStmt->execute([
                        $id,
                        $_SESSION['user_id'] ?? null,
                        'edited',
                        'Tiedote muokattu',
                    ]);
                }

                if (($orig['state'] ?? '') !== $newState) {
                    $logStmt->execute([
                        $id,
                        $_SESSION['user_id'] ?? null,
                        'status_changed',
                        "Tila: {$newState}",
                    ]);
                }
            } catch (Exception $e) {
                error_log('save_flash.php log fallback error: ' . $e->getMessage());
            }
        }

        $targetId = $id;
    } else {
        // INSERT
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;

        $sql = "INSERT INTO sf_flashes
            (title, title_short, summary, description, type, site, site_detail, occurred_at, lang, state, created_by,
             preview_filename, preview_filename_2, image_main, image_2, image_3,
             root_causes, actions,
             image1_transform, image2_transform, image3_transform, annotations_data, created_at, updated_at)
            VALUES
            (:title, :title_short, :summary, :description, :type, :site, :site_detail, :occurred_at, :lang, :state, :created_by,
             :preview_filename, :preview_filename_2, :image_main, :image_2, :image_3,
             :root_causes, :actions,
             :image1_transform, :image2_transform, :image3_transform, :annotations_data, NOW(), NOW())
        ";

        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':title'            => $title,
            ':title_short'      => $title_short,
            ':summary'          => $summary,
            ':description'      => $description,
            ':type'             => $type,
            ':site'             => $site,
            ':site_detail'      => $site_detail,
            ':occurred_at'      => $occurred_at_db,
            ':lang'             => $lang,
            ':state'            => $newState,
            ':created_by'       => $userId,
            ':preview_filename' => $newPreviewFilename,
            ':preview_filename_2' => $newPreviewFilename2, // UUSI
            ':image_main'       => $uploadedFiles['image_main'] ?? null,
            ':image_2'          => $uploadedFiles['image_2'] ?? null,
            ':image_3'          => $uploadedFiles['image_3'] ?? null,
            ':root_causes'      => $root_causes,           // UUSI
            ':actions'          => $actions,               // UUSI
            ':image1_transform' => $image1_transform,
            ':image2_transform' => $image2_transform,
            ':image3_transform' => $image3_transform,
            ':annotations_data' => $annotations_data,
        ]);

        $newId = (int) $pdo->lastInsertId();

        $pdo->prepare('UPDATE sf_flashes SET translation_group_id = :gid WHERE id = :id')
            ->execute([':gid' => $newId, ':id' => $newId]);

        // === LOKITUS TIETOKANTAAN ===
        if (function_exists('sf_log_event')) {
            sf_log_event($newId, 'created', 'Safetyflash luotu');
        } else {
            try {
                $logStmt = $pdo->prepare(
                    'INSERT INTO safetyflash_logs (flash_id, user_id, event_type, description, created_at)
                     VALUES (?, ?, ?, ?, NOW())'
                );
                $logStmt->execute([$newId, $_SESSION['user_id'] ?? null, 'created', 'Safetyflash luotu']);
            } catch (Exception $e) {
                error_log('save_flash.php log fallback error: ' . $e->getMessage());
            }
        }

        $targetId = $newId;
    }

    if ($pdo->inTransaction()) {
        $pdo->commit();
    }

    // Sähköposti turvatiimille, jos lähetettiin tarkistettavaksi
    if ($newState === 'pending_review' && function_exists('sf_mail_to_safety_team')) {
        $stateBefore = $orig['state'] ?? 'draft';
        if ($submissionType === 'review') {
            sf_mail_to_safety_team($pdo, $targetId, $stateBefore);
        }
    }

    // === AUDIT LOG - tallennus onnistui ===
    $user = sf_current_user();

    sf_audit_log(
        $id > 0 ? 'flash_update' : 'flash_create',
        'flash',
        (int) $targetId,
        [
            'title' => $title,
            'state' => $newState,
            'type'  => $type,
            'site'  => $site,
        ],
        $user ? (int) $user['id'] : null
    );

    // notice-parametri
    $noticeParam = 'saved';

    if ($newState === 'pending_review') {
        $noticeParam = $isInvestigationUpdate
            ? 'investigation_sent'
            : 'sent_review';
    } elseif ($newState === 'draft') {
        $noticeParam = 'saved_draft';
    }

    header('Location: ' . $base . '/index.php?page=list&notice=' . $noticeParam);
    exit;
} catch (Throwable $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    error_log('save_flash.php ERROR: ' . $e->getMessage());

    if (!empty($newPreviewFilename)) {
        sf_unlink_if_exists(UPLOADS_PREVIEWS_DIR . $newPreviewFilename);
    }
    if (!empty($newPreviewFilename2)) {
        sf_unlink_if_exists(UPLOADS_PREVIEWS_DIR . $newPreviewFilename2);
    }
    if (!empty($uploadedFiles) && is_array($uploadedFiles)) {
        foreach ($uploadedFiles as $fn) {
            sf_unlink_if_exists(UPLOADS_IMAGES_DIR . $fn);
        }
    }

    die('Tallennusvirhe: ' . htmlspecialchars($e->getMessage()));
}