<?php
// app/services/email_services.php

declare(strict_types=1);

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require_once __DIR__ . '/../includes/log_app.php';
require_once __DIR__ . '/../lib/phpmailer/Exception.php';
require_once __DIR__ . '/../lib/phpmailer/PHPMailer.php';
require_once __DIR__ . '/../lib/phpmailer/SMTP.php';

/**
 * Roolien ID:t sf_roles-taulussa.
 * Nämä vastaavat tietokannan arvoja:
 * 1 = Pääkäyttäjä, 2 = Kirjoittaja, 3 = Turvatiimi, 4 = Viestintä
 */
const SF_ROLE_ID_ADMIN       = 1;
const SF_ROLE_ID_AUTHOR      = 2; // Kirjoittaja
const SF_ROLE_ID_SAFETY_TEAM = 3; // Turvatiimi
const SF_ROLE_ID_COMMS       = 4; // Viestintä

/**
 * Hae yksittäinen asetus sf_settings-taulusta.
 * Jos asetusta ei ole, palautetaan oletusarvo.
 */
function sf_get_setting(PDO $pdo, string $key, ?string $default = null): ?string
{
    $stmt = $pdo->prepare("SELECT setting_value FROM sf_settings WHERE setting_key = ? LIMIT 1");
    $stmt->execute([$key]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$row) {
        return $default;
    }
    return (string)$row['setting_value'];
}

/**
 * Lähettää sähköpostin käyttäen SMTP-asetuksia (PHPMailer).
 *
 * @param string   $subject    Sähköpostin otsikko
 * @param string   $body       Sisältö (plain text)
 * @param string[] $recipients Vastaanottajat
 */
function sf_send_email(string $subject, string $body, array $recipients): void
{
    sf_app_log('sf_send_email: CALLED, recipients=' . implode(',', $recipients));

    if (empty($recipients)) {
        sf_app_log('sf_send_email: EMPTY RECIPIENTS, abort');
        return;
    }

    // Luodaan oma PDO-yhteys asetuksia varten (ei käytetä sf_get_pdo:a)
    try {
        require __DIR__ . '/../../config.php';
        $pdo = new PDO(
            "mysql:host={$config['db']['host']};dbname={$config['db']['name']};charset={$config['db']['charset']}",
            $config['db']['user'],
            $config['db']['pass'],
            [
                PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ]
        );
    } catch (Throwable $e) {
        sf_app_log('sf_send_email: PDO INIT ERROR: ' . $e->getMessage());
        return;
    }

    // Luetaan SMTP-asetukset tietokannasta
    $host       = sf_get_setting($pdo, 'smtp_host', 'localhost');
    $port       = (int) (sf_get_setting($pdo, 'smtp_port', '25'));
    $encryption = sf_get_setting($pdo, 'smtp_encryption', 'none'); // tls/ssl/none
    $username   = sf_get_setting($pdo, 'smtp_username', '');
    $password   = sf_get_setting($pdo, 'smtp_password', '');
    $fromEmail  = sf_get_setting($pdo, 'smtp_from_email', 'no-reply@tapojarvi.online');
    $fromName   = sf_get_setting($pdo, 'smtp_from_name', 'Safetyflash');

    $mail = new PHPMailer(true);

    try {
        // Palvelinasetukset
        $mail->isSMTP();
        $mail->Host       = $host;
        $mail->Port       = $port;
        $mail->SMTPAuth   = ($username !== '' || $password !== '');
        if ($encryption === 'ssl') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        } elseif ($encryption === 'tls') {
            $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        } else {
            $mail->SMTPSecure = false; // ei salausta
        }
        if ($mail->SMTPAuth) {
            $mail->Username = $username;
            $mail->Password = $password;
        }

        // UTF-8
        $mail->CharSet  = 'UTF-8';
        $mail->Encoding = 'base64';

        // From
        $mail->setFrom($fromEmail, $fromName);

        // Vastaanottajat
        foreach ($recipients as $to) {
            $to = trim($to);
            if ($to !== '') {
                $mail->addAddress($to);
            }
        }

        // Sisältö
        $mail->isHTML(false); // plain text
        $mail->Subject = $subject;
        $mail->Body    = $body;

        $mail->send();
        sf_app_log('sf_send_email: MAIL SENT OK');
    } catch (Exception $e) {
        sf_app_log('sf_send_email: SMTP ERROR: ' . $mail->ErrorInfo);
    }
}
/**
 * Palauttaa annettua roolia vastaavien aktiivisten käyttäjien sähköpostit.
 *
 * @param PDO $pdo
 * @param int $roleId sf_roles.id
 * @return string[]
 */
function sf_get_emails_by_role(PDO $pdo, int $roleId): array
{
    $stmt = $pdo->prepare("
        SELECT email
        FROM sf_users
        WHERE role_id = :role_id
          AND is_active = 1
          AND email <> ''
    ");
    $stmt->execute([':role_id' => $roleId]);

    $emails = [];
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $email = trim((string)$row['email']);
        if ($email !== '') {
            $emails[] = $email;
        }
    }

    return array_values(array_unique($emails));
}

/**
 * Turvatiimille menevät viestit (rooli: SF_ROLE_ID_SAFETY_TEAM).
 */
function sf_get_safety_team_emails(PDO $pdo): array
{
    return sf_get_emails_by_role($pdo, SF_ROLE_ID_SAFETY_TEAM);
}

/**
 * Viestintä-tiimille menevät viestit (rooli: SF_ROLE_ID_COMMS).
 */
function sf_get_comms_team_emails(PDO $pdo): array
{
    return sf_get_emails_by_role($pdo, SF_ROLE_ID_COMMS);
}

/**
 * Julkaisuosoitteet.
 * TESTIVAIHEESSA kovakoodattu arto.huhta@gmail.com
 * (myöhemmin kannattaa lukea tämäkin sf_settings-taulusta).
 */
function sf_get_publish_target_emails(): array
{
    return ['arto.huhta@gmail.com'];
}

/**
 * Haetaan tekijän sähköposti flashin perusteella (sf_flashes.created_by -> sf_users.email).
 */
function sf_get_flash_creator_email(PDO $pdo, int $flashId): ?string
{
    $stmt = $pdo->prepare("
        SELECT u.email
        FROM sf_flashes f
        LEFT JOIN sf_users u ON u.id = f.created_by
        WHERE f.id = ?
        LIMIT 1
    ");
    $stmt->execute([$flashId]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$row || empty($row['email'])) {
        return null;
    }

    return trim((string)$row['email']);
}

/**
 * Turvatiimille: uusi tai uudelleen lähetetty tarkistukseen.
 *
 * Käyttö:
 *  - kun tila vaihtuu esim. draft -> pending_review TAI request_info -> pending_review,
 *    kutsu sf_mail_to_safety_team($pdo, $flashId, $stateBefore)
 *
 * $stateBefore:
 *  - jos ennen oli 'request_info' -> teksti kertoo että tekijä on päivittänyt ja lähettänyt uudelleen
 *  - muuten -> "Uusi Safetyflash on lähetetty tarkistettavaksi."
 */
function sf_mail_to_safety_team(PDO $pdo, int $flashId, string $stateBefore): void
{
    sf_app_log("sf_mail_to_safety_team: CALLED for flashId={$flashId}, stateBefore={$stateBefore}");

    $recipients = sf_get_safety_team_emails($pdo);
    if (empty($recipients)) {
        sf_app_log('sf_mail_to_safety_team: NO RECIPIENTS (Turvatiimi-ryhmä tyhjä)');
        return;
    }

    $subject = "Safetyflash tarkistettavaksi (ID: {$flashId})";

    $body  = "Hei,\n\n";
    if ($stateBefore === 'request_info') {
        $body .= "Tekijä on päivittänyt Safetyflashin ja lähettänyt sen uudelleen tarkistettavaksi.\n";
    } else {
        $body .= "Uusi Safetyflash on lähetetty tarkistettavaksi.\n";
    }
    $body .= "Tunniste: {$flashId}\n\n";
    $body .= "Kirjaudu Safetyflash-sovellukseen tarkistaaksesi tiedot.\n\n";
    $body .= "Terveisin,\nSafetyflash-järjestelmä";

    sf_send_email($subject, $body, $recipients);
}

/**
 * Tekijälle: turvatiimi pyytää lisätietoja (request_info).
 * Tämä EI mene rooliryhmille, vaan vain Safetyflashin luojalle.
 */
function sf_mail_request_info(PDO $pdo, int $flashId, string $message): void
{
    sf_app_log("sf_mail_request_info: CALLED for flashId={$flashId}");

    $email = sf_get_flash_creator_email($pdo, $flashId);
    if ($email === null) {
        sf_app_log("sf_mail_request_info: NO CREATOR EMAIL for flashId={$flashId}");
        return;
    }

    sf_app_log("sf_mail_request_info: SENDING TO {$email}");

    $subject = "Safetyflash: lisätietoja pyydetty (ID: {$flashId})";

    $body  = "Hei,\n\n";
    $body .= "Turvatiimi on pyytänyt lisätietoja Safetyflashiin.\n";
    $body .= "Tunniste: {$flashId}\n\n";
    if ($message !== '') {
        $body .= "Viesti turvatiimiltä:\n{$message}\n\n";
    }
    $body .= "Kirjaudu Safetyflash-sovellukseen muokataksesi tietoja ja lähetä se uudelleen tarkistettavaksi.\n\n";
    $body .= "Terveisin,\nSafetyflash-järjestelmä";

    sf_send_email($subject, $body, [$email]);
}

/**
 * Viestinnälle: turvatiimi lähetti flashin viestintään (to_comms).
 * Lisäksi voidaan cc:llä tekijä (ccCreator = true).
 * Tämä kutsutaan, kun tila vaihtuu to_comms-tilaan.
 */
function sf_mail_to_comms(PDO $pdo, int $flashId, string $message, bool $ccCreator = true): void
{
    sf_app_log("sf_mail_to_comms: CALLED for flashId={$flashId}");

    $recipients = sf_get_comms_team_emails($pdo);

    if ($ccCreator) {
        $creator = sf_get_flash_creator_email($pdo, $flashId);
        if ($creator !== null) {
            $recipients[] = $creator;
        }
    }

    if (empty($recipients)) {
        sf_app_log('sf_mail_to_comms: NO RECIPIENTS (Viestintä-ryhmä + cc tyhjä)');
        return;
    }

    $subject = "Safetyflash toimitettu viestintään (ID: {$flashId})";

    $body  = "Hei,\n\n";
    $body .= "Safetyflash on tarkistettu ja toimitettu viestintään.\n";
    $body .= "Tunniste: {$flashId}\n\n";
    if ($message !== '') {
        $body .= "Turvatiimin viesti viestinnälle:\n{$message}\n\n";
    }
    $body .= "Kirjaudu Safetyflash-sovellukseen viimeistelemään ja julkaisemaan tiedote.\n\n";
    $body .= "Terveisin,\nSafetyflash-järjestelmä";

    sf_send_email($subject, $body, $recipients);
}

/**
 * Turvatiimille: viestintä kommentoi to_comms-tilassa (lisäkysymys tms.).
 *
 * Tämä funktio EI lähetä viestiä luojalle, vaan nimenomaan turvatiimiroolille.
 * Kutsu tätä, kun:
 *  - tila on 'to_comms'
 *  - kommentoija on viestintä-roolissa
 *  - lisätään kommentti lokiin
 */
function sf_mail_comms_comment_to_safety(
    PDO $pdo,
    int $logFlashId,
    string $message,
    ?int $fromUserId,
    ?int $creatorId
): void {
    sf_app_log("sf_mail_comms_comment_to_safety: CALLED for groupId={$logFlashId}");

    $recipients = sf_get_safety_team_emails($pdo);
    if (empty($recipients)) {
        sf_app_log('sf_mail_comms_comment_to_safety: NO RECIPIENTS (Turvatiimi-ryhmä tyhjä)');
        return;
    }

    $subject = "Viestinnän kommentti Safetyflashiin (ryhmä-ID: {$logFlashId})";

    $body  = "Hei,\n\n";
    $body .= "Viestintä on lisännyt kommentin Safetyflash-ryhmään (ID: {$logFlashId}).\n\n";
    if ($message !== '') {
        $body .= "Kommentti:\n{$message}\n\n";
    }
    $body .= "Kirjaudu Safetyflash-sovellukseen katsomaan lisätietoja lokista.\n\n";
    $body .= "Terveisin,\nSafetyflash-järjestelmä";

    sf_send_email($subject, $body, $recipients);
}

/**
 * Julkaisu: valmis flash julkaistu, lähetetään esim. safetyflash@tapojarvi.online:hin.
 * Nyt testissä: arto.huhta@gmail.com (sf_get_publish_target_emails()).
 */
function sf_mail_published(PDO $pdo, int $flashId): void
{
    sf_app_log("sf_mail_published: CALLED for flashId={$flashId}");

    $recipients = sf_get_publish_target_emails();
    if (empty($recipients)) {
        sf_app_log('sf_mail_published: NO RECIPIENTS');
        return;
    }

    $subject = "Safetyflash julkaistu (ID: {$flashId})";

    $body  = "Hei,\n\n";
    $body .= "Safetyflash on merkitty julkaistuksi.\n";
    $body .= "Tunniste: {$flashId}\n\n";
    $body .= "Voit hakea kuvat ja tarkemmat tiedot Safetyflash-sovelluksesta.\n\n";
    $body .= "Terveisin,\nSafetyflash-järjestelmä";

    sf_send_email($subject, $body, $recipients);
}