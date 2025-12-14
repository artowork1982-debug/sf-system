<?php
// app/actions/request_info.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/protect.php';
require_once __DIR__ . '/../includes/statuses.php';
require_once __DIR__ . '/../includes/log.php';
require_once __DIR__ . '/../includes/log_app.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../services/email_services.php';

$base = rtrim($config['base_url'], '/');

// Tämä sivu käsittelee vain Palauta-lomakkeen POST-pyynnön
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    header("Location: {$base}/index.php?page=list");
    exit;
}

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header("Location: {$base}/index.php?page=list");
    exit;
}

// Tässä käytetään helpers.php:n sf_get_pdo()-funktiota
$pdo = sf_get_pdo();

// Haetaan flash, jotta tiedetään ryhmätunnus (yhteinen loki kieliversioille)
$stmt = $pdo->prepare("SELECT id, translation_group_id FROM sf_flashes WHERE id=? LIMIT 1");
$stmt->execute([$id]);
$flash = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$flash) {
    header("Location: {$base}/index.php?page=list");
    exit;
}

$logFlashId = !empty($flash['translation_group_id'])
    ? (int)$flash['translation_group_id']
    : (int)$flash['id'];

$newState = 'request_info';

// Päivitetään tila tälle nimenomaiselle kieliversiolle
$pdo->prepare("
    UPDATE sf_flashes 
    SET state = :state,
        updated_at = NOW()
    WHERE id = :id
")->execute([
    ':state' => $newState,
    ':id'    => $id,
]);

// Lomakkeelta tullut viesti
$message = trim($_POST['message'] ?? '');

// Loki-otsikko ja kuvaus
$currentUiLang = $_SESSION['ui_lang'] ?? 'fi';
$statusLabel   = sf_status_label($newState, $currentUiLang);

// Lokaalit tekstipohjat logia varten (voi siirtää termistöön halutessa)
$prefix = [
    'fi' => 'Tila asetettu',
    'sv' => 'Status satt',
    'en' => 'Status set',
    'it' => 'Stato impostato',
    'el' => 'Η κατάσταση ορίστηκε',
][$currentUiLang] ?? 'Tila asetettu';

$commentLabel = [
    'fi' => 'Kommentti',
    'sv' => 'Kommentar',
    'en' => 'Comment',
    'it' => 'Commento',
    'el' => 'Σχόλιο',
][$currentUiLang] ?? 'Kommentti';

$desc = "{$prefix}: {$statusLabel}.";
if ($message !== '') {
    // max ~2000 merkkiä varmuuden vuoksi
    $safeMsg = mb_substr($message, 0, 2000);
    $desc   .= "\n{$commentLabel}: " . $safeMsg;
}

// Kirjataan loki RYHMÄN JUUREEN → näkyy kaikissa kieliversioissa
sf_log_event($logFlashId, 'info_requested', $desc);

// Lähetä sähköposti tekijälle
if (function_exists('sf_mail_request_info')) {
    try {
        sf_app_log("request_info: calling sf_mail_request_info for flashId={$id}");
        // HUOM: käytetään yksittäisen flashin id:tä ($id), ei translation_group_id:tä
        sf_mail_request_info($pdo, $id, $message);
        sf_app_log("request_info: sf_mail_request_info DONE for flashId={$id}");
    } catch (Throwable $e) {
        // Kirjoitetaan omaan sovelluslokiin, mutta EI kaadeta käyttäjää
        sf_app_log('request_info: sf_mail_request_info ERROR: ' . $e->getMessage());
    }
}

// Takaisin katselunäkymään
header("Location: {$base}/index.php?page=view&id={$id}&notice=request_info");
exit;