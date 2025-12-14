<?php
// app/api/users_create.php
declare(strict_types=1);

// ===== KÄYNNISTÄ SESSIO AINA ENSIMMÄISENÄ =====
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ===== LADATAAN LOKI =====
require_once __DIR__ . '/../includes/log_app.php'; // Sovellusloki

// ===== DEBUG: Kirjaa joka vaihe molempiin lokiin =====
$error_prefix = "DEBUG users_create.php: ";
$error_msg = $error_prefix . "käynnistyy, session_id=" . session_id();
error_log($error_msg);            // PHP:n error_log
sf_app_log($error_msg);           // Sovellusloki sf_errors.log

require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/protect.php';
require_once __DIR__ . '/../includes/csrf.php';

header('Content-Type: application/json; charset=utf-8');

// Kirjaa POST-data
$post_info = $error_prefix . "POST=" . print_r($_POST, true);
error_log($post_info);
sf_app_log($post_info);

// ===== VAIN PÄÄKÄYTTÄJÄ =====
sf_app_log($error_prefix . "kutsu sf_require_role");
sf_require_role([1]);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $msg = $error_prefix . "väärä metodi";
    error_log($msg);
    sf_app_log($msg);
    echo json_encode(['ok' => false, 'error' => 'Invalid method']);
    exit;
}

// ===== CSRF-TARKISTUS =====
$csrf_post = $_POST['csrf_token'] ?? 'PUUTTUU';
$csrf_session = $_SESSION['csrf_token'] ?? 'PUUTTUU';
$csrf_msg = $error_prefix . "CSRF ennen validointia: post_token=$csrf_post, session_token=$csrf_session";
error_log($csrf_msg);
sf_app_log($csrf_msg);

if (!sf_csrf_validate()) {
    $fail_msg = $error_prefix . "CSRF FAIL: post_token=$csrf_post, session_token=$csrf_session, session_id=" . session_id();
    error_log($fail_msg);
    sf_app_log($fail_msg);
    $debug = [
        'post_token' => substr($csrf_post, 0, 20),
        'session_token' => substr($csrf_session, 0, 20),
        'session_id' => session_id()
    ];
    echo json_encode([
        'ok' => false,
        'error' => 'Virheellinen tietoturvatarkistus',
        'debug' => $debug
    ]);
    exit;
}

// ===== YHDISTÄ TIETOKANTAAN =====
sf_app_log($error_prefix . "yhdistetään tietokantaan");
$mysqli = sf_db();
if (!$mysqli) {
    $db_msg = $error_prefix . "EI TIETOKANTAYHTEYTTÄ";
    error_log($db_msg);
    sf_app_log($db_msg);
    echo json_encode(['ok' => false, 'error' => 'Tietokantavirhe']);
    exit;
}

// ===== FORM DATA =====
$first = trim($_POST['first_name'] ?? '');
$last  = trim($_POST['last_name'] ?? '');
$email = trim($_POST['email'] ?? '');
$role  = (int)($_POST['role_id'] ?? 0);
$pass  = $_POST['password'] ?? '';

if ($first === '' || $last === '' || $email === '' || $role <= 0 || $pass === '') {
    $form_msg = $error_prefix . "puuttuvia tietoja: first=$first last=$last email=$email role=$role pass=" . ($pass ? '***' : 'PUUTTUU');
    error_log($form_msg);
    sf_app_log($form_msg);
    echo json_encode(['ok' => false, 'error' => 'Puuttuvia tietoja']);
    exit;
}

// ===== TARKISTA KÄYTTÄJÄ JO ONKO OLEMASSA =====
$stmt = $mysqli->prepare('SELECT id, is_active FROM sf_users WHERE email = ? LIMIT 1');
if (!$stmt) {
    $prep_msg = $error_prefix . "PREPARE ERROR: " . $mysqli->error;
    error_log($prep_msg);
    sf_app_log($prep_msg);
    echo json_encode(['ok' => false, 'error' => 'DB prepare error']);
    exit;
}
$stmt->bind_param('s', $email);
$stmt->execute();
$stmt->store_result();

if ($stmt->num_rows > 0) {
    $stmt->bind_result($existingId, $active);
    $stmt->fetch();
    $stmt->close();

    if ((int)$active === 1) {
        $exist_msg = $error_prefix . "käyttäjä on jo aktiivinen email=$email";
        error_log($exist_msg);
        sf_app_log($exist_msg);
        echo json_encode(['ok' => false, 'error' => 'Tällä sähköpostilla on jo käyttäjä.']);
        exit;
    } else {
        $react_msg = $error_prefix . "käyttäjä inaktiivinen -> aktivoidaan id=$existingId email=$email";
        error_log($react_msg);
        sf_app_log($react_msg);
        $hashReact = password_hash($pass, PASSWORD_DEFAULT);
        $upd = $mysqli->prepare(
            'UPDATE sf_users
             SET first_name = ?, last_name = ?, email = ?, password_hash = ?, role_id = ?, is_active = 1, updated_at = NOW()
             WHERE id = ?
             LIMIT 1'
        );
        if (!$upd) {
            $upd_msg = $error_prefix . "UPDATE ERROR: " . $mysqli->error;
            error_log($upd_msg);
            sf_app_log($upd_msg);
            echo json_encode(['ok' => false, 'error' => 'DB-virhe käyttäjän aktivoinnissa']);
            exit;
        }
        $upd->bind_param('ssssii', $first, $last, $email, $hashReact, $role, $existingId);

        if (!$upd->execute()) {
            $upd_exec_msg = $error_prefix . "UPDATE EXEC ERROR: " . $upd->error;
            error_log($upd_exec_msg);
            sf_app_log($upd_exec_msg);
            echo json_encode(['ok' => false, 'error' => 'DB-virhe käyttäjän aktivoinnissa']);
            exit;
        }

        $done_msg = $error_prefix . "käyttäjä aktivoitu: id=$existingId email=$email";
        error_log($done_msg);
        sf_app_log($done_msg);
        echo json_encode(['ok' => true, 'reactivated' => true]);
        exit;
    }
}
$stmt->close();

// --- UUSI KÄYTTÄJÄ ---
$hash = password_hash($pass, PASSWORD_DEFAULT);

$stmt = $mysqli->prepare(
    'INSERT INTO sf_users (first_name, last_name, email, password_hash, role_id, is_active, created_at)
     VALUES (?, ?, ?, ?, ?, 1, NOW())'
);
if (!$stmt) {
    $ins_msg = $error_prefix . "INSERT ERROR: " . $mysqli->error;
    error_log($ins_msg);
    sf_app_log($ins_msg);
    echo json_encode(['ok' => false, 'error' => 'DB prepare error']);
    exit;
}
$stmt->bind_param('ssssi', $first, $last, $email, $hash, $role);

if (!$stmt->execute()) {
    $ins_exec_msg = $error_prefix . "INSERT EXEC ERROR: " . $stmt->error;
    error_log($ins_exec_msg);
    sf_app_log($ins_exec_msg);
    echo json_encode(['ok' => false, 'error' => 'DB-virhe lisäyksessä']);
    exit;
}

$success_msg = $error_prefix . "käyttäjä luotu: email=$email id=" . $stmt->insert_id;
error_log($success_msg);
sf_app_log($success_msg);

echo json_encode(['ok' => true]);
exit;