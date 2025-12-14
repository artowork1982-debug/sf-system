<?php
// app/includes/csrf.php
// CSRF-suojaus

/**
 * Generoi CSRF-token ja tallenna sessioon
 */
function sf_csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Palauttaa piilotetun input-kentän CSRF-tokenilla
 */
function sf_csrf_field(): string {
    $token = sf_csrf_token();
    return '<input type="hidden" name="csrf_token" value="' .htmlspecialchars($token, ENT_QUOTES, 'UTF-8') .'">';
}

/**
 * Validoi CSRF-token
 */
function sf_csrf_validate(? string $token = null): bool {
    if ($token === null) {
        $token = $_POST['csrf_token'] ?? $_GET['csrf_token'] ?? '';
    }
    
    if (empty($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Tarkista CSRF ja keskeytä jos virheellinen
 */
function sf_csrf_check(): void {
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        if (!sf_csrf_validate()) {
            http_response_code(403);
            die('Virheellinen tietoturvatarkistus. Päivitä sivu ja yritä uudelleen.');
        }
    }
}

/**
 * Uudista CSRF-token (käytä kirjautumisen jälkeen)
 */
function sf_csrf_regenerate(): string {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    return $_SESSION['csrf_token'];
}