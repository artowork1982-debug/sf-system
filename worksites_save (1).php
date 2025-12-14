<?php
/**
 * app/actions/worksites_save.php
 *
 * Handles:
 *  - Add new worksite (action=add)
 *  - Toggle worksite active/inactive (action=toggle)
 *
 * Works with both normal POST (redirect back) and AJAX/fetch (JSON).
 */

declare(strict_types=1);

require_once __DIR__ . '/../includes/protect.php';
require_once __DIR__ . '/../includes/audit_log.php';

$base = rtrim($config['base_url'] ?? '', '/');
$redirect = $base . '/index.php?page=settings&tab=worksites';

function sf_is_fetch_request(): bool
{
    $xrw = strtolower($_SERVER['HTTP_X_REQUESTED_WITH'] ?? '');
    if ($xrw === 'fetch' || $xrw === 'xmlhttprequest' || $xrw === 'sf-pjax') {
        return true;
    }
    $accept = strtolower($_SERVER['HTTP_ACCEPT'] ?? '');
    return strpos($accept, 'application/json') !== false;
}

function sf_json_response(array $payload, int $status = 200): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE);
    exit;
}

function sf_redirect_with_notice(string $url, string $notice): void
{
    $sep = (strpos($url, '?') !== false) ? '&' : '?';
    header('Location: ' . $url . $sep . 'notice=' . urlencode($notice));
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    if (sf_is_fetch_request()) {
        sf_json_response(['ok' => false, 'notice' => 'method_not_allowed'], 405);
    }
    header('Location: ' . $redirect);
    exit;
}

/**
 * CSRF: ÄLÄ käytä sf_csrf_check() if-lauseessa.
 * Käytä sf_csrf_validate() joka palauttaa booleanin.
 */
$csrfToken = $_POST['csrf_token'] ?? '';
$csrfToken = is_string($csrfToken) ? $csrfToken : '';
if (!function_exists('sf_csrf_validate') || !sf_csrf_validate($csrfToken)) {
    if (sf_is_fetch_request()) {
        sf_json_response(['ok' => false, 'notice' => 'csrf_invalid'], 403);
    }
    sf_redirect_with_notice($redirect, 'csrf_invalid');
}

$action = $_POST['action'] ?? '';
$action = is_string($action) ? $action : '';

$mysqli = sf_db();
if (!($mysqli instanceof mysqli)) {
    if (sf_is_fetch_request()) {
        sf_json_response(['ok' => false, 'notice' => 'db_error'], 500);
    }
    sf_redirect_with_notice($redirect, 'db_error');
}
$mysqli->set_charset('utf8mb4');

try {
    if ($action === 'add') {
        $name = trim((string)($_POST['name'] ?? ''));
        $description = trim((string)($_POST['description'] ?? ''));

        if ($name === '') {
            if (sf_is_fetch_request()) {
                sf_json_response(['ok' => false, 'notice' => 'missing_name'], 422);
            }
            sf_redirect_with_notice($redirect, 'missing_name');
        }

        $stmt = $mysqli->prepare('INSERT INTO sf_worksites (name, description, is_active) VALUES (?, ?, 1)');
        if (!$stmt) {
            throw new RuntimeException('prepare_failed');
        }
        $stmt->bind_param('ss', $name, $description);
        if (!$stmt->execute()) {
            throw new RuntimeException('insert_failed');
        }
        $newId = (int)$stmt->insert_id;
        $stmt->close();

        if (function_exists('sf_audit_log')) {
            sf_audit_log('worksite_added', 'worksite', $newId, [
                'name' => $name,
                'description' => $description,
            ]);
        }

        if (sf_is_fetch_request()) {
            sf_json_response(['ok' => true, 'notice' => 'worksite_added', 'id' => $newId], 200);
        }
        sf_redirect_with_notice($redirect, 'worksite_added');
    }

    if ($action === 'toggle') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) {
            if (sf_is_fetch_request()) {
                sf_json_response(['ok' => false, 'notice' => 'missing_id'], 422);
            }
            sf_redirect_with_notice($redirect, 'missing_id');
        }

        $stmt = $mysqli->prepare('SELECT name, is_active FROM sf_worksites WHERE id = ?');
        if (!$stmt) {
            throw new RuntimeException('prepare_failed');
        }
        $stmt->bind_param('i', $id);
        if (!$stmt->execute()) {
            throw new RuntimeException('select_failed');
        }
        $res = $stmt->get_result();
        $row = $res ? $res->fetch_assoc() : null;
        $stmt->close();

        if (!$row) {
            if (sf_is_fetch_request()) {
                sf_json_response(['ok' => false, 'notice' => 'not_found'], 404);
            }
            sf_redirect_with_notice($redirect, 'not_found');
        }

        $worksiteName = (string)($row['name'] ?? '');
        $currentActive = (int)($row['is_active'] ?? 0) === 1;
        $newActive = $currentActive ? 0 : 1;
        $notice = $newActive === 1 ? 'worksite_enabled' : 'worksite_disabled';

        $stmt = $mysqli->prepare('UPDATE sf_worksites SET is_active = ? WHERE id = ?');
        if (!$stmt) {
            throw new RuntimeException('prepare_failed');
        }
        $stmt->bind_param('ii', $newActive, $id);
        if (!$stmt->execute()) {
            throw new RuntimeException('update_failed');
        }
        $stmt->close();

        if (function_exists('sf_audit_log')) {
            sf_audit_log($notice, 'worksite', $id, [
                'name' => $worksiteName,
                'is_active' => $newActive,
            ]);
        }

        if (sf_is_fetch_request()) {
            sf_json_response(['ok' => true, 'notice' => $notice, 'id' => $id, 'is_active' => $newActive], 200);
        }
        sf_redirect_with_notice($redirect, $notice);
    }

    if (sf_is_fetch_request()) {
        sf_json_response(['ok' => false, 'notice' => 'bad_request'], 400);
    }
    sf_redirect_with_notice($redirect, 'bad_request');
} catch (Throwable $e) {
    if (sf_is_fetch_request()) {
        sf_json_response(['ok' => false, 'notice' => 'server_error'], 500);
    }
    sf_redirect_with_notice($redirect, 'server_error');
}