<?php
declare(strict_types=1);

session_start();
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../includes/log_app.php';

// CSRF - lataa csrf.php
$csrfPath = __DIR__ . '/../includes/csrf.php';
if (file_exists($csrfPath)) {
    require_once $csrfPath;
}

function sf_is_fetch(): bool {
    $xrw = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    if ($xrw === 'fetch' || $xrw === 'xmlhttprequest') return true;
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    return str_contains($accept, 'application/json');
}

function sf_json(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

$base = rtrim((string)($config['base_url'] ?? ''), '/');

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    if (sf_is_fetch()) sf_json(['ok' => false, 'error' => 'Method not allowed'], 405);
    header("Location: {$base}/index.php?page=settings&tab=worksites");
    exit;
}

// CSRF validate - KORJATTU: käytetään oikeaa funktiota sf_csrf_validate()
if (function_exists('sf_csrf_validate')) {
    $csrfToken = $_POST['csrf_token'] ?? '';
    if (!sf_csrf_validate($csrfToken)) {
        if (sf_is_fetch()) sf_json(['ok' => false, 'error' => 'CSRF invalid', 'notice' => 'csrf_invalid'], 403);
        header("Location: {$base}/index.php?page=settings&tab=worksites&notice=error");
        exit;
    }
}

// Yhteys: käytetään config['db'] ja mysqli fallback
$db = $config['db'] ?? null;
if (!is_array($db)) {
    if (sf_is_fetch()) sf_json(['ok' => false, 'error' => 'DB config missing'], 500);
    exit('DB config missing');
}
$mysqli = new mysqli((string)$db['host'], (string)$db['user'], (string)$db['pass'], (string)$db['name']);
if ($mysqli->connect_errno) {
    if (sf_is_fetch()) sf_json(['ok' => false, 'error' => $mysqli->connect_error], 500);
    exit('DB connect failed');
}
$mysqli->set_charset((string)($db['charset'] ?? 'utf8mb4'));

$action = (string)($_POST['action'] ?? '');

try {
    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            if (sf_is_fetch()) sf_json(['ok' => false, 'error' => 'Missing id'], 422);
            header("Location: {$base}/index.php?page=settings&tab=worksites&notice=error");
            exit;
        }

        // hae ennen muutosta (lokitusta ja palautetta varten)
        $stmt = $mysqli->prepare("SELECT id, name, is_active FROM sf_worksites WHERE id=? LIMIT 1");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$row) {
            if (sf_is_fetch()) sf_json(['ok' => false, 'error' => 'Not found'], 404);
            header("Location: {$base}/index.php?page=settings&tab=worksites&notice=error");
            exit;
        }

        $newActive = ((int)$row['is_active'] === 1) ? 0 : 1;

        $upd = $mysqli->prepare("UPDATE sf_worksites SET is_active=? WHERE id=?");
        $upd->bind_param("ii", $newActive, $id);
        $upd->execute();
        $upd->close();

        // lokitus
        $logAction = $newActive ? 'worksite_activated' : 'worksite_deactivated';
        if (function_exists('log_app_event')) {
            log_app_event($logAction, [
                'worksite_id' => $id,
                'worksite_name' => $row['name'],
                'is_active' => $newActive,
            ]);
        }

        // notice key (toast)
        $notice = $newActive ? 'worksite_enabled' : 'worksite_disabled';

        if (sf_is_fetch()) {
            sf_json([
                'ok' => true,
                'id' => $id,
                'is_active' => $newActive,
                'notice' => $notice,
                'action' => $logAction,
            ]);
        }

        header("Location: {$base}/index.php?page=settings&tab=worksites&notice={$notice}");
        exit;
    }

    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        if ($name === '') {
            if (sf_is_fetch()) sf_json(['ok' => false, 'error' => 'Missing name'], 422);
            header("Location: {$base}/index.php?page=settings&tab=worksites&notice=error");
            exit;
        }

        $ins = $mysqli->prepare("INSERT INTO sf_worksites (name, is_active) VALUES (?, 1)");
        $ins->bind_param("s", $name);
        $ins->execute();
        $newId = (int)$ins->insert_id;
        $ins->close();

        if (function_exists('log_app_event')) {
            log_app_event('worksite_create', [
                'worksite_id' => $newId,
                'worksite_name' => $name,
                'is_active' => 1,
            ]);
        }

        if (sf_is_fetch()) sf_json(['ok' => true, 'id' => $newId, 'notice' => 'worksite_added']);
        header("Location: {$base}/index.php?page=settings&tab=worksites&notice=worksite_added");
        exit;
    }

    if (sf_is_fetch()) sf_json(['ok' => false, 'error' => 'Unknown action'], 400);
    header("Location: {$base}/index.php?page=settings&tab=worksites&notice=error");
    exit;

} catch (Throwable $e) {
    if (sf_is_fetch()) sf_json(['ok' => false, 'error' => $e->getMessage()], 500);
    header("Location: {$base}/index.php?page=settings&tab=worksites&notice=error");
    exit;
}