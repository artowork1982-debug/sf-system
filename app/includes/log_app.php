<?php
// app/includes/log_app.php
declare(strict_types=1);

/**
 * Kirjoittaa sovelluskohtaisen logirivin omaan lokitiedostoon.
 * Käyttö: sf_app_log('jotain tapahtui: ' . $muuttuja);
 */
function sf_app_log(string $message): void
{
    $logDir  = __DIR__ . '/../logs';
    $logFile = $logDir . '/sf_errors.log';

    // yritetään varmistaa että hakemisto on olemassa
    if (!is_dir($logDir)) {
        @mkdir($logDir, 0755, true);
    }

    $ts  = date('Y-m-d H:i:s');
    $row = "[{$ts}] {$message}\n";

    @file_put_contents($logFile, $row, FILE_APPEND);
}
function sf_app_log_event(int $flashId, string $eventType, string $description): void {
    sf_app_log("Flash #{$flashId} [{$eventType}]: {$description}");
}