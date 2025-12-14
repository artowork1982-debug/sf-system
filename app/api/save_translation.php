<?php
// app/api/save_translation.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../includes/protect.php';   // varmista, että käyttäjä on kirjautunut (tarvittaessa)
require_once __DIR__ . '/../includes/statuses.php';  // sf_status_label, sf_current_ui_lang yms.
require_once __DIR__ . '/../includes/log.php';       // sf_log_event

// --- DB: PDO (sama malli kuin save_flash.php:ssa) ---
try {
    $pdo = new PDO(
        'mysql:host=' . $config['db']['host'] .
        ';dbname='   . $config['db']['name'] .
        ';charset='  . $config['db']['charset'],
        $config['db']['user'],
        $config['db']['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Throwable $e) {
    error_log('save_translation.php DB ERROR: ' . $e->getMessage());
    http_response_code(500);
    echo 'Tietokantavirhe (save_translation)';
    exit;
}

// Lukeminen POSTista (turvallista trimmausta)
$fromId  = isset($_POST['from_id']) ? (int) $_POST['from_id'] : 0;
$newLang = isset($_POST['lang']) ? trim((string)$_POST['lang']) : '';
$groupId = isset($_POST['translation_group_id']) ? (int) $_POST['translation_group_id'] : 0;

$titleShort  = isset($_POST['title_short']) ? trim((string)$_POST['title_short']) : '';
$summary     = isset($_POST['summary']) ? trim((string)$_POST['summary']) : '';
$description = isset($_POST['description']) ? trim((string)$_POST['description']) : '';

if ($fromId <= 0 || $newLang === '') {
    http_response_code(400);
    echo 'Virhe: puuttuva from_id tai lang.';
    exit;
}

try {
    // Hae pohjaflash
    $stmt = $pdo->prepare('SELECT * FROM sf_flashes WHERE id = :id LIMIT 1');
    $stmt->execute([':id' => $fromId]);
    $baseFlash = $stmt->fetch();

    if (!$baseFlash) {
        http_response_code(404);
        echo 'Virhe: pohjatiedotetta ei löytynyt.';
        exit;
    }

    // Varmista translation_group_id
    if ($groupId <= 0) {
        if (!empty($baseFlash['translation_group_id'])) {
            $groupId = (int) $baseFlash['translation_group_id'];
        } else {
            $groupId = (int) $baseFlash['id'];
        }
    }

    // Päivitä pohjatiedotteelle group id, jos puuttui
    if (empty($baseFlash['translation_group_id'])) {
        $u = $pdo->prepare('UPDATE sf_flashes SET translation_group_id = :gid WHERE id = :id');
        $u->execute([
            ':gid' => $groupId,
            ':id'  => (int) $baseFlash['id'],
        ]);
    }

    // Valmistele rivin data (kopioidaan tarvittavat kentät pohjaflashista)
    $type       = $baseFlash['type'] ?? '';
    $title      = $baseFlash['title'] ?? '';
    $site       = $baseFlash['site'] ?? '';
    $siteDetail = $baseFlash['site_detail'] ?? null;
    $occurredAt = $baseFlash['occurred_at'] ?? null;
    $status     = $baseFlash['status'] ?? null;
    $imageMain  = $baseFlash['image_main'] ?? null;
    $image2     = $baseFlash['image_2'] ?? null;
    $image3     = $baseFlash['image_3'] ?? null;
    $preview    = $baseFlash['preview_filename'] ?? null;

// Käännös perii alkuperäisen tilan - ei muuteta
$state = $baseFlash['state'] ?? 'to_comms';

    // INSERT uusi kieliversio
    $ins = $pdo->prepare('
        INSERT INTO sf_flashes 
        (translation_group_id, lang, type, title, title_short, summary, description, site, site_detail, occurred_at, state, status, image_main, image_2, image_3, preview_filename, created_at, updated_at)
        VALUES
        (:tgid, :lang, :type, :title, :title_short, :summary, :description, :site, :site_detail, :occurred_at, :state, :status, :image_main, :image_2, :image_3, :preview_filename, NOW(), NOW())
    ');

    $ins->execute([
        ':tgid'             => $groupId,
        ':lang'             => $newLang,
        ':type'             => $type,
        ':title'            => $title,
        ':title_short'      => $titleShort,
        ':summary'          => $summary,
        ':description'      => $description,
        ':site'             => $site,
        ':site_detail'      => $siteDetail,
        ':occurred_at'      => $occurredAt,
        ':state'            => $state,
        ':status'           => $status,
        ':image_main'       => $imageMain,
        ':image_2'          => $image2,
        ':image_3'          => $image3,
        ':preview_filename' => $preview,
        ':preview_filename_2' => '', // Tyhjä, koska käännöksellä ei ole kahta korttia
    ]);

    $newId = (int) $pdo->lastInsertId();

    if ($newId) {
        // Lokissa käytetään aina ryhmän juurta → $groupId
        $logFlashId    = (int)$groupId;
        $currentUiLang = function_exists('sf_current_ui_lang') ? sf_current_ui_lang() : 'fi';
        $statusLabel   = function_exists('sf_status_label') ? sf_status_label($state, $currentUiLang) : $state;

        // 1) Perus merkintä: kieliversio tallennettu
        $desc = "Kieliversio ({$newLang}) tallennettu. Tila: {$statusLabel}.";

        if (function_exists('sf_log_event')) {
            sf_log_event($logFlashId, 'translation_saved', $desc);
        } else {
            // fallback: suora insert jos sf_log_event puuttuu
            $log = $pdo->prepare("
                INSERT INTO safetyflash_logs (flash_id, user_id, event_type, description, created_at)
                VALUES (:flash_id, :user_id, :event_type, :description, NOW())
            ");
            $userId = $_SESSION['user_id'] ?? null;
            $log->execute([
                ':flash_id'   => $logFlashId,
                ':user_id'    => $userId,
                ':event_type' => 'translation_saved',
                ':description'=> $desc,
            ]);
        }

        // 2) Jos haluat käsitellä pending_review-tapauksen, voit lisätä sen tähän
        // (esim. jos state olisi 'pending_review', lisättäisiin 'status_changed' lokiin)

        // Lopuksi redirect uuteen kieliversioon + notice
        $base = rtrim($config['base_url'] ?? '', '/');
        header('Location: ' . $base . '/index.php?page=view&id=' . $newId . '&notice=translation_saved');
        exit;
    }

    // jos ei insertattu
    http_response_code(500);
    echo 'Virhe: kieliversion tallennus epäonnistui.';
    exit;

} catch (Throwable $e) {
    error_log('save_translation.php ERROR: ' . $e->getMessage());
    http_response_code(500);
    echo 'Virhe tallennuksessa.';
    exit;
}