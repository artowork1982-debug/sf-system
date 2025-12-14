<?php
// app/includes/auth.php
declare(strict_types=1);

require_once __DIR__ . '/../../config.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Luo uuden mysqli-yhteyden.
 */
function sf_db(): mysqli
{
    global $config;

    $db = $config['db'] ?? [];
    $mysqli = new mysqli(
        $db['host'] ?? 'localhost',
        $db['user'] ?? '',
        $db['pass'] ?? '',
        $db['name'] ?? ''
    );

    if ($mysqli->connect_error) {
        die('DB connection error: ' . $mysqli->connect_error);
    }

    $mysqli->set_charset($db['charset'] ?? 'utf8mb4');
    return $mysqli;
}

/**
 * Aseta nykyinen käyttäjä sessioon ja synkkaa myös user_id-avain.
 *
 * Käytä tätä onnistuneen kirjautumisen jälkeen:
 *   sf_set_current_user($userRow);
 */
function sf_set_current_user(array $user): void
{
    $_SESSION['sf_user'] = $user;
    // Tämä avain on se, jota save_flash.php käyttää created_by:tä varten
    $_SESSION['user_id'] = $user['id'] ?? null;
}

/**
 * Palauta nykyinen käyttäjä sessiosta.
 * Samalla varmistetaan, että user_id-avain on synkassa.
 */
function sf_current_user(): ?array
{
    if (!isset($_SESSION['sf_user'])) {
        return null;
    }

    $user = $_SESSION['sf_user'];

    // Synkkaa user_id, jos sitä ei ole vielä asetettu
    if (!isset($_SESSION['user_id']) && isset($user['id'])) {
        $_SESSION['user_id'] = $user['id'];
    }

    return $user;
}

/**
 * Ohjaa oikeaan login-sivuun base_url:n mukaan.
 */
function sf_redirect_to_login(): void
{
    global $config;

    // base_url konfigista
    $base = rtrim($config['base_url'] ?? '', '/');

    // fallback — jos joku jättäisi base_urlin määrittelemättä
    if (!$base) {
        $dir = rtrim(dirname($_SERVER['SCRIPT_NAME']), '/');
        $base = $dir !== '/' ? $dir : '';
    }

    header('Location: ' . $base . '/app/pages/login.php');
    exit;
}

/**
 * Vaadi kirjautuminen.
 */
function sf_require_login(): void
{
    if (!sf_current_user()) {
        sf_redirect_to_login();
    }
}

/**
 * Vaadi tietty rooli / roolilista.
 *
 * @param array<int> $allowedRoleIds
 */
function sf_require_role(array $allowedRoleIds): void
{
    $user = sf_current_user();
    if (!$user || !in_array((int)$user['role_id'], $allowedRoleIds, true)) {
        http_response_code(403);
        echo 'Ei käyttöoikeutta.';
        exit;
    }
}
function sf_current_user_has_role(string $roleName): bool {
    $user = sf_current_user();
    if (!$user || !isset($user['role_id'])) {
        return false;
    }
    
    // Mäppää rooli-id:t nimiin
    $roleMap = [
        1 => ['admin', 'Pääkäyttäjä'],
        2 => ['writer', 'Kirjoittaja'],
        3 => ['safety_team', 'Turvatiimi'],
        4 => ['comms', 'Viestintä']
    ];
    
    $userRoleId = (int)$user['role_id'];
    if (isset($roleMap[$userRoleId])) {
        return in_array($roleName, $roleMap[$userRoleId], true);
    }
    
    return false;
}