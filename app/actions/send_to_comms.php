<?php
// app/actions/send_to_comms.php
declare(strict_types=1);

ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);

// Varmista sessio
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../includes/protect.php';
require_once __DIR__ . '/../includes/log_app.php';
require_once __DIR__ . '/../includes/statuses.php';
require_once __DIR__ . '/../includes/log.php';
if (is_file(__DIR__ . '/helpers.php')) {
    require_once __DIR__ . '/helpers.php';
}
require_once __DIR__ . '/../services/email_services.php';

$base = rtrim($config['base_url'] ?? '', '/');

// Tämä endpoint käsittelee vain POST-pyynnön
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header("Location: {$base}/index.php?page=list");
    exit;
}

// --- ID URL-parametrista ---
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    http_response_code(400);
    echo 'Virheellinen ID.';
    exit;
}

// --- Viesti lomakkeelta (rajoitettu) ---
$message = trim((string) ($_POST['message'] ?? ''));
if ($message !== '') {
    $message = mb_substr($message, 0, 2000);
}

try {
    // Yritetään käyttää apufunktiota (jos saatavilla), muuten luodaan PDO yhteys
    if (function_exists('sf_get_pdo')) {
        $pdo = sf_get_pdo();
    } else {
        if (empty($config['db'])) {
            throw new RuntimeException('Database configuration missing');
        }
        $pdo = new PDO(
            "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}",
            $config['db']['user'],
            $config['db']['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    }

    // Haetaan flash, jotta tiedetään ryhmätunnus
    $stmt = $pdo->prepare("SELECT id, translation_group_id, state FROM sf_flashes WHERE id = ? LIMIT 1");
    $stmt->execute([$id]);
    $flash = $stmt->fetch();

    if (!$flash) {
        header("Location: {$base}/index.php?page=list");
        exit;
    }

    // Määritetään logille käytettävä flash_id (ryhmän juuri)
    $logFlashId = !empty($flash['translation_group_id'])
        ? (int) $flash['translation_group_id']
        : (int) $flash['id'];

    // Päivitetään tila: to_comms (tämän tietyn kieliversion state)
    $newState = 'to_comms';
    $update = $pdo->prepare("
        UPDATE sf_flashes
        SET state = :state, updated_at = NOW()
        WHERE id = :id
    ");
    $update->execute([
        ':state' => $newState,
        ':id'    => $id,
    ]);

    // UI-kieli lokimerkintää varten
    $currentUiLang = $_SESSION['ui_lang'] ?? 'fi';

    // Tilateksti lokalisoituna
    $statusLabel = function_exists('sf_status_label')
        ? sf_status_label($newState, $currentUiLang)
        : $newState;

    // Lokaalit tekstipohjat logia varten
    $prefix = [
        'fi' => 'Tila asetettu',
        'sv' => 'Status satt',
        'en' => 'Status set',
        'it' => 'Stato impostato',
        'el' => 'Η κατάσταση ορίστηκε',
    ][$currentUiLang] ?? 'Tila asetettu';

    $msgLabel = [
        'fi' => 'Viesti viestinnälle',
        'sv' => 'Meddelande till kommunikation',
        'en' => 'Message to communications',
        'it' => 'Messaggio alla comunicazione',
        'el' => 'Μήνυμα προς το τμήμα επικοινωνίας',
    ][$currentUiLang] ?? 'Viesti viestinnälle';

    // Rakennetaan lokikuvaus
    $desc = "{$prefix}: {$statusLabel}.";
    if ($message !== '') {
        $desc .= "\n{$msgLabel}: " . $message;
    }

    // Käyttäjä
    $userId = null;
    if (function_exists('sf_current_user')) {
        $user = sf_current_user();
        $userId = isset($user['id']) ? (int)$user['id'] : null;
    } else {
        $userId = isset($_SESSION['user_id']) ? (int) $_SESSION['user_id'] : null;
    }

    // Kirjataan loki
    if (function_exists('sf_log_event')) {
        sf_log_event($logFlashId, 'sent_to_comms', $desc);
    } else {
        $log = $pdo->prepare("
            INSERT INTO safetyflash_logs (flash_id, user_id, event_type, description, created_at)
            VALUES (:flash_id, :user_id, :event_type, :description, NOW())
        ");
        $log->execute([
            ':flash_id'   => $logFlashId,
            ':user_id'    => $userId,
            ':event_type' => 'sent_to_comms',
            ':description'=> $desc,
        ]);
    }

    // Lähetä sähköposti viestinnälle (+ cc tekijälle, jos funktio niin tekee)
    if (function_exists('sf_mail_to_comms')) {
        sf_mail_to_comms($pdo, $id, $message, true);
    }

    // --- Uudelleenohjaus takaisin view-sivulle ---
    header("Location: {$base}/index.php?page=view&id=" . (int)$id . "&notice=comms_sent");
    exit;

} catch (Throwable $e) {
    error_log('send_to_comms.php ERROR: ' . $e->getMessage());
    header("Location: {$base}/index.php?page=view&id=" . (int)$id . "&notice=error");
    exit;
}