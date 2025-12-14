<?php
// app/includes/audit_log.php
declare(strict_types=1);

/**
 * Kirjaa tapahtuman lokiin
 * 
 * @param string $action Toiminto (login, logout, flash_create, flash_delete, jne.)
 * @param string|null $targetType Kohteen tyyppi (user, flash, worksite, jne.)
 * @param int|null $targetId Kohteen ID
 * @param array|null $details Lisätiedot (tallennetaan JSON:na)
 * @param int|null $userId Käyttäjä-ID (jos ei annettu, haetaan sessiosta)
 */
function sf_audit_log(
    string $action,
    ?string $targetType = null,
    ?int $targetId = null,
    ?array $details = null,
    ?int $userId = null
): bool {
    try {
        $mysqli = sf_db();
        
        // Hae käyttäjätiedot
        if ($userId === null) {
            $user = sf_current_user();
            $userId = $user ? (int) $user['id'] : null;
            $userEmail = $user ? $user['email'] : null;
        } else {
            // Hae email käyttäjä-ID:n perusteella
            $stmt = $mysqli->prepare('SELECT email FROM sf_users WHERE id = ?');
            $stmt->bind_param('i', $userId);
            $stmt->execute();
            $result = $stmt->get_result();
            $row = $result->fetch_assoc();
            $userEmail = $row['email'] ?? null;
        }
        
        $ipAddress   = $_SERVER['REMOTE_ADDR'] ?? null;
        $userAgent   = substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500);
        $detailsJson = $details ? json_encode($details, JSON_UNESCAPED_UNICODE) : null;
        
        $stmt = $mysqli->prepare(
            'INSERT INTO sf_audit_log 
            (user_id, user_email, action, target_type, target_id, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        
        $stmt->bind_param(
            'isssssss',
            $userId,
            $userEmail,
            $action,
            $targetType,
            $targetId,
            $detailsJson,
            $ipAddress,
            $userAgent
        );
        
        return $stmt->execute();
    } catch (Throwable $e) {
        error_log('Audit log error: ' . $e->getMessage());
        return false;
    }
}

/**
 * Palauttaa toiminnon käännöksen
 */
/**
 * Palauttaa toiminnon käännöksen
 * Käyttää keskitettyä sf_term() -funktiota
 */
function sf_audit_action_label(string $action, string $lang = 'fi'): string
{
    // Kokeile ensin keskitettyä käännöstä
    $translated = sf_term($action, $lang);

    if ($translated !== null && $translated !== $action) {
        return $translated;
    }

    // Fallback: muotoile action luettavaksi
    $label = str_replace('_', ' ', $action);
    return ucfirst($label);
}