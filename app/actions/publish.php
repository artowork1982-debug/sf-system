<?php
// app/actions/publish.php
declare(strict_types=1);

session_start();

require_once __DIR__ . '/../includes/protect.php';
require_once __DIR__ . '/../includes/log_app.php';
require_once __DIR__ . '/../includes/statuses.php';
require_once __DIR__ . '/../includes/audit_log.php';
require_once __DIR__ . '/helpers.php';
require_once __DIR__ . '/../services/email_services.php';

$id  = sf_validate_id();
$pdo = sf_get_pdo();

// Haetaan flash
$stmt = $pdo->prepare("
    SELECT id, translation_group_id, title 
    FROM sf_flashes 
    WHERE id = :id 
    LIMIT 1
");
$stmt->execute([':id' => $id]);
$flash = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$flash) {
    sf_redirect($config['base_url'] . "/index.php?page=list");
    exit;
}

$logFlashId = !empty($flash['translation_group_id'])
    ? (int)$flash['translation_group_id']
    : (int)$flash['id'];

// Päivitetään tila published
$pdo->prepare("
    UPDATE sf_flashes 
    SET state = 'published', status = 'JULKAISTU', updated_at = NOW()
    WHERE id = :id
")->execute([':id' => $id]);

// Lokimerkintä safetyflash_logs-tauluun
$currentUiLang = $_SESSION['ui_lang'] ?? 'fi';
$statusLabel   = sf_status_label('published', $currentUiLang);

$prefix = [
    'fi' => 'Tila asetettu',
    'sv' => 'Status satt',
    'en' => 'Status set',
][$currentUiLang] ?? 'Tila asetettu';

$desc   = "{$prefix}: {$statusLabel}. ";
$userId = $_SESSION['user_id'] ?? null;

$log = $pdo->prepare("
    INSERT INTO safetyflash_logs (flash_id, user_id, event_type, description, created_at)
    VALUES (:flash_id, :user_id, :event_type, :description, NOW())
");
$log->execute([
    ':flash_id'   => $logFlashId,
    ':user_id'    => $userId,
    ':event_type' => 'published',
    ':description'=> $desc,
]);

// ========== AUDIT LOG ==========
$user = sf_current_user();

sf_audit_log(
    'flash_publish',                 // action (vastaa sf_audit_action_label-listaa)
    'flash',                         // target type
    (int)$id,                        // target id
    [
        'title'      => $flash['title'] ?? null,
        'new_status' => 'published',
    ],
    $user ? (int)$user['id'] : null  // user id
);
// ================================

// Lähetetään julkaisu-sähköposti
if (function_exists('sf_mail_published')) {
    sf_mail_published($pdo, $id);
}

// Huom: korjattu väli "? page" -> "?page"
sf_redirect($config['base_url'] . "/index.php?page=view&id={$id}&notice=published");