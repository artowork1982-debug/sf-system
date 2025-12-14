<?php
// app/api/login_process.php
declare(strict_types=1);

session_start();

require_once __DIR__ .'/../../config.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/csrf.php';
require_once __DIR__ . '/../includes/audit_log.php';


$base = rtrim($config['base_url'], '/');

// Tallenna valittu kieli
$lang = $_POST['lang'] ?? 'fi';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
  header('Location: ' . $base . '/app/pages/login.php');
  exit;
}

// CSRF-tarkistus
if (!sf_csrf_validate()) {
  header('Location: ' . $base . '/app/pages/login.php? error=csrf&lang=' . urlencode($lang));
  exit;
}

$email  = trim($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

if ($email === '' || $password === '') {
  header('Location: ' . $base . '/app/pages/login.php?error=1&lang=' . urlencode($lang));
  exit;
}

$mysqli = sf_db();

// Haetaan käyttäjä ja rooli
$stmt = $mysqli->prepare(
  'SELECT u.id, u.first_name, u.last_name, u.email, u.password_hash, 
      u.role_id, u.is_active, u.home_worksite_id,
      r.name AS role_name
   FROM sf_users u
   LEFT JOIN sf_roles r ON r.id = u.role_id
   WHERE u.email = ? 
   LIMIT 1'
);

if (!$stmt) {
  // Hätävaraksi virheen käsittely – ohjataan loginin virheeseen
  header('Location: ' . $base . '/app/pages/login.php?error=1&lang=' . urlencode($lang));
  exit;
}

$stmt->bind_param('s', $email);
$stmt->execute();
$result = $stmt->get_result();
$user  = $result->fetch_assoc();
$stmt->close();
$mysqli->close();

// Tarkistetaan käyttäjä + salasana + aktiivisuus
if (
  !$user ||
  (int)($user['is_active'] ??  0) !== 1 ||
  empty($user['password_hash']) ||
  ! password_verify($password, $user['password_hash'])
) {
  // Lokita epäonnistunut kirjautuminen
  require_once __DIR__ . '/../includes/audit_log.php';
  
  // Määritä syy
  $reason = 'wrong_password';
  if (!$user) {
      $reason = 'user_not_found';
  } elseif ((int)($user['is_active'] ??  0) !== 1) {
      $reason = 'inactive';
  }
  
  // Tallenna lokiin - käytä syötettyä sähköpostia
  try {
      $pdo = Database::getInstance();
      $stmt = $pdo->prepare(
          'INSERT INTO sf_audit_log 
          (user_id, user_email, action, target_type, target_id, details, ip_address, user_agent, created_at)
          VALUES (:user_id, :email, :action, :target_type, :target_id, :details, :ip, :ua, NOW())'
      );
      $stmt->execute([
          ':user_id' => $user ?  (int) $user['id'] : null,
          ':email' => $email, // Käytetään syötettyä sähköpostia
          ':action' => 'login_failed',
          ':target_type' => 'user',
          ':target_id' => $user ? (int) $user['id'] : null,
          ':details' => json_encode(['attempted_email' => $email, 'reason' => $reason], JSON_UNESCAPED_UNICODE),
          ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
          ':ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500),
      ]);
  } catch (Throwable $e) {
      error_log('Login failed audit log error: ' . $e->getMessage());
  }
  
  header('Location: ' . $base . '/app/pages/login.php?error=1&lang=' .  urlencode($lang));
  exit;
}

// Kirjataan käyttäjä sessioon (ilman password_hashia)
$_SESSION['sf_user'] = [
  'id'        => (int) $user['id'],
  'first_name'    => $user['first_name'] ?? '',
  'last_name'    => $user['last_name'] ?? '',
  'email'      => $user['email'],
  'role_id'     => (int) $user['role_id'],
  'role_name'    => $user['role_name'] ?? '',
  'home_worksite_id' => $user['home_worksite_id'] ? (int) $user['home_worksite_id'] : null,
];

// Tallenna kieli sessioon
$_SESSION['ui_lang'] = $lang;

// Uudista CSRF-token kirjautumisen jälkeen (session fixation -suojaus)
sf_csrf_regenerate();

// Lokita onnistunut kirjautuminen
if (function_exists('sf_audit_log')) {
    sf_audit_log(
        'login_success',
        'user',
        (int) $user['id'],
        ['email' => $user['email']],
        (int) $user['id']
    );
}

// Ohjaa etusivulle onnistumisviestillä
header('Location: ' . $base .  '/index.php?page=list&notice=logged_in');
exit;