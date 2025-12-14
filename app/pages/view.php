<?php
// app/pages/view.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/protect.php';
require_once __DIR__ . '/../includes/statuses.php';

$base = rtrim($config['base_url'] ?? '', '/');

// --- DB: PDO ---
try {
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
    echo "<p>Tietokantavirhe</p>";
    exit;
}

// --- ID ---
$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
if ($id <= 0) {
    echo "<p>Virheellinen ID.</p>";
    exit;
}

// --- Safetyflash ---
$stmt = $pdo->prepare("
    SELECT *,
        DATE_FORMAT(created_at, '%d.%m.%Y %H:%i')   AS createdFmt,
        DATE_FORMAT(updated_at, '%d.%m.%Y %H:%i')   AS updatedFmt,
        DATE_FORMAT(occurred_at, '%d.%m.%Y %H:%i')  AS occurredFmt
    FROM sf_flashes
    WHERE id = ?
    LIMIT 1
");
$stmt->execute([$id]);
$flash = $stmt->fetch();

if (!$flash) {
    echo "<p>Safetyflashia ei löytynyt.</p>";
    exit;
}

$currentUiLang   = $uiLang ?? 'fi';
// Mäppää state -> CSS-luokka view-sivun pillille
$stateClassMap = [
    'draft'          => 'status-pill-draft',
    'pending_review' => 'status-pill-pending',
    'request_info'   => 'status-pill-request',
    'reviewed'       => 'status-pill-reviewed',
    'to_comms'       => 'status-pill-comms',
    'published'      => 'status-pill-published',
];
$metaStatusClass = $stateClassMap[$flash['state']] ?? '';
$statusLabel     = function_exists('sf_status_label') ? (sf_status_label($flash['state'], $currentUiLang) ?? '') : '';

// Lokia varten ryhmän juuri
$logFlashId = !empty($flash['translation_group_id'])
    ? (int)$flash['translation_group_id']
    : (int)$flash['id'];

// Hae lokit (ryhmän juurella)
$logs = [];
$logStmt = $pdo->prepare("
    SELECT 
        l.id,
        l.event_type,
        l.description,
        l.created_at,
        u.first_name,
        u.last_name
    FROM safetyflash_logs l
    LEFT JOIN sf_users u ON u.id = l.user_id
    WHERE l.flash_id = ?
    ORDER BY l.created_at DESC
");
$logStmt->execute([$logFlashId]);
$logs = $logStmt->fetchAll();

// Onko tämä kieliversio vai alkuperäinen flash?
$isTranslation = !empty($flash['translation_group_id'])
    && (int) $flash['translation_group_id'] !== (int) $flash['id'];

// Muokkaa-linkin osoite → vie lomakkeelle esitäytettynä ja suoraan vaiheeseen 3
$editPage = $isTranslation ? 'form_language' : 'form';
$editUrl  = $base . '/index.php?page=' . $editPage . '&id=' . $id . '&step=3';

// --- Tuetut kielet ja lippujen ikonit ---
$supportedLangs = [
    'fi' => ['label' => 'FI', 'icon' => 'finnish-flag.png'],
    'sv' => ['label' => 'SV', 'icon' => 'swedish-flag.png'],
    'en' => ['label' => 'EN', 'icon' => 'english-flag.png'],
    'it' => ['label' => 'IT', 'icon' => 'italian-flag.png'],
    'el' => ['label' => 'EL', 'icon' => 'greece-flag.png'], // 'el' on Kreikan kielikoodi
];

// --- Kieliversiot & preview ---
require_once __DIR__ . '/../services/render_services.php';

$currentId   = (int) ($flash['id'] ?? 0);
$currentLang = $flash['lang'] ?? 'fi';

$translationGroupId = !empty($flash['translation_group_id'])
    ? (int) $flash['translation_group_id']
    : $currentId;

$translations = [];
if ($translationGroupId > 0 && function_exists('sf_get_flash_translations')) {
    $translations = sf_get_flash_translations($pdo, $translationGroupId);
    if (!isset($translations[$currentLang]) && $currentId > 0) {
        $translations[$currentLang] = $currentId;
    }
}

// Jos preview_filename puuttuu, yritä generoida se
if (empty($flash['preview_filename']) && $currentId > 0 && function_exists('sf_generate_flash_preview')) {
    try {
        sf_generate_flash_preview($pdo, $currentId);
        // Hae uudelleen
        $stmtPrev = $pdo->prepare("SELECT preview_filename FROM sf_flashes WHERE id = ?");
        $stmtPrev->execute([$currentId]);
        $prevRow = $stmtPrev->fetch();
        if ($prevRow && !empty($prevRow['preview_filename'])) {
            $flash['preview_filename'] = $prevRow['preview_filename'];
        }
    } catch (Throwable $e) {
        error_log("Could not auto-generate preview for flash {$currentId}: " . $e->getMessage());
    }
}

// --- Preview-kuva 1 ---
$previewUrl = "{$base}/assets/img/camera-placeholder.png";
if (!empty($flash['preview_filename'])) {
    $filename = $flash['preview_filename'];
    $previewPathNew = __DIR__ . '/../../uploads/previews/' . $filename;
    $previewPathOld = __DIR__ . '/../../img/' . $filename; // legacy
    if (is_file($previewPathNew)) {
        $previewUrl = "{$base}/uploads/previews/" . $filename;
    } elseif (is_file($previewPathOld)) {
        $previewUrl = "{$base}/img/" . $filename;
    }
}

// --- Preview-kuva 2 (vain tutkintatiedotteille) ---
$previewUrl2 = null;
$hasSecondCard = false;

if ($flash['type'] === 'green') {
    // Tarkista onko juurisyitä tai toimenpiteitä
    $hasRootCauses = !empty(trim((string)($flash['root_causes'] ?? '')));
    $hasActions = !empty(trim((string)($flash['actions'] ?? '')));
    $hasSecondCard = $hasRootCauses || $hasActions;

    if (!empty($flash['preview_filename_2'])) {
        $filename2 = $flash['preview_filename_2'];
        $previewPath2New = __DIR__ . '/../../uploads/previews/' . $filename2;
        $previewPath2Old = __DIR__ . '/../../img/' . $filename2;
        if (is_file($previewPath2New)) {
            $previewUrl2 = "{$base}/uploads/previews/" . $filename2;
        } elseif (is_file($previewPath2Old)) {
            $previewUrl2 = "{$base}/img/" . $filename2;
        }
    }
}
// Kuvapolkujen muodostaminen JS:lle
$getImageUrlForJs = function ($filename) use ($base) {
    if (empty($filename)) {
        return '';
    }
    
    // Tarkista ensin uploads/images
    $path = "uploads/images/{$filename}";
    $fullPath = __DIR__ . "/../../{$path}";
    if (file_exists($fullPath)) {
        return "{$base}/{$path}";
    }
    
    // Tarkista uploads/library (kuvakirjasto)
    $libPath = "uploads/library/{$filename}";
    $libFullPath = __DIR__ . "/../../{$libPath}";
    if (file_exists($libFullPath)) {
        return "{$base}/{$libPath}";
    }
    
    // Vanha polku (legacy)
    $oldPath = "img/{$filename}";
    $oldFullPath = __DIR__ .  "/../../{$oldPath}";
    if (file_exists($oldFullPath)) {
        return "{$base}/{$oldPath}";
    }
    
    // Palauta tyhjä jos ei löydy
    return '';
};

$flashDataForJs = [
    'id' => $flash['id'],
    'type' => $flash['type'],
    'title' => $flash['title'],
    'title_short' => $flash['title_short'] ?? $flash['summary'] ??   '',
    'description' => $flash['description'] ?? '',
    'root_causes' => $flash['root_causes'] ?? '',
    'actions' => $flash['actions'] ?? '',
    'site' => $flash['site'] ?? '',
    'site_detail' => $flash['site_detail'] ??  '',
    'occurred_at' => $flash['occurred_at'] ?? '',
    'lang' => $flash['lang'] ?? 'fi',
    // Kuvatiedostojen nimet
    'image_main' => $flash['image_main'] ?? '',
    'image_2' => $flash['image_2'] ?? '',
    'image_3' => $flash['image_3'] ??  '',
    // Täydelliset kuvapolut
    'image_main_url' => $getImageUrlForJs($flash['image_main'] ?? null),
    'image_2_url' => $getImageUrlForJs($flash['image_2'] ??  null),
    'image_3_url' => $getImageUrlForJs($flash['image_3'] ?? null),
    // Muunnokset
    'image1_transform' => $flash['image1_transform'] ?? '',
    'image2_transform' => $flash['image2_transform'] ??   '',
    'image3_transform' => $flash['image3_transform'] ??  '',
    'grid_style' => $flash['grid_style'] ??  'grid-3-main-top',
];
// --- Tyyppien labelit termistön kautta ---
$typeKeyMap = [
    'red'    => 'first_release',
    'yellow' => 'dangerous_situation',
    'green'  => 'investigation_report',
];
$typeKey   = $typeKeyMap[$flash['type']] ?? null;
$typeLabel = $typeKey ? sf_term($typeKey, $currentUiLang) : 'Safetyflash';

// --- Apu: generaattori lokirivin avataria varten (nimi -> initials) ---
function sf_avatar_initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $initials = '';
    foreach ($parts as $p) {
        if ($p !== '') $initials .= mb_strtoupper(mb_substr($p, 0, 1));
        if (mb_strlen($initials) >= 2) break;
    }
    return $initials ?: 'SF';
}
?>
<div class="view-container">
    <div class="view-back">
        <a
          href="<?= htmlspecialchars($base) ?>/index.php?page=list"
          class="btn-back"
          aria-label="<?= htmlspecialchars(sf_term('back_to_list', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>"
        >
          ← <?= htmlspecialchars(sf_term('back_to_list', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </a>
    </div>

    <div
      class="lang-switcher"
      role="tablist"
      aria-label="<?= htmlspecialchars(sf_term('view_languages_aria', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>"
    >
        <?php foreach ($supportedLangs as $langCode => $langData):
            $hasTranslation = isset($translations[$langCode]);
            $isActive = ($langCode === $currentLang);
        ?>
            <div class="lang-chip <?= $isActive ? 'active' : '' ?> <?= $hasTranslation ? 'has-version' : 'no-version' ?>" role="button" tabindex="0">
                <?php if ($hasTranslation): ?>
                    <a href="index.php?page=view&id=<?= (int)$translations[$langCode] ?>" class="lang-link">
                        <img class="lang-flag-img" src="<?= htmlspecialchars($base) ?>/assets/img/<?= htmlspecialchars($langData['icon']) ?>" alt="<?= htmlspecialchars($langData['label']) ?>">
                        <span class="lang-label"><?= htmlspecialchars($langData['label']) ?></span>
                    </a>
                <?php else: ?>
                    <button type="button" class="lang-add-button" data-lang="<?= htmlspecialchars($langCode) ?>" data-base-id="<?= (int)$currentId ?>" onclick="sfAddTranslation(this)">
                        <img class="lang-flag-img" src="<?= htmlspecialchars($base) ?>/assets/img/<?= htmlspecialchars($langData['icon']) ?>" alt="<?= htmlspecialchars($langData['label']) ?>">
                        <span class="lang-label">+ <?= htmlspecialchars($langData['label']) ?></span>
                    </button>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="view-grid">
        <div class="view-box preview-box">
    <?php if ($flash['type'] === 'green' && $hasSecondCard): ?>
        <!-- TUTKINTATIEDOTE: Välilehdet kahdelle kortille -->
        <div class="sf-view-preview-tabs" id="sfViewPreviewTabs">
            <button type="button"
                    class="sf-view-tab-btn active"
                    data-target="preview1">
                <?= htmlspecialchars(sf_term('card_1_summary', $currentUiLang) ?? '1. Yhteenveto', ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button type="button"
                    class="sf-view-tab-btn"
                    data-target="preview2">
                <?= htmlspecialchars(sf_term('card_2_investigation', $currentUiLang) ?? '2. Juurisyyt & toimenpiteet', ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>

        <div class="sf-view-preview-cards">
            <div class="sf-view-preview-card active" id="viewPreview1">
                <img src="<?= htmlspecialchars($previewUrl) ?>" alt="Preview kortti 1"
                     class="preview-image" id="viewPreviewImage1">
            </div>
            <div class="sf-view-preview-card" id="viewPreview2" style="display:none;">
                <?php if ($previewUrl2): ?>
                    <img src="<?= htmlspecialchars($previewUrl2) ?>" alt="Preview kortti 2"
                         class="preview-image" id="viewPreviewImage2">
                <?php else: ?>
                    <div class="sf-preview-placeholder">
                        <p>
                            <?= htmlspecialchars(
                                sf_term('preview_2_not_generated', $currentUiLang)
                                ?? 'Kortin 2 preview-kuvaa ei ole vielä generoitu.',
                                ENT_QUOTES,
                                'UTF-8'
                            ) ?>
                        </p>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($flash['preview_filename']) || !empty($flash['preview_filename_2'])): ?>
            <div class="preview-download-wrapper">
                <a href="<?= htmlspecialchars($previewUrl) ?>"
                   download="safetyflash_<?= (int)$flash['id'] ?>_<?= htmlspecialchars($flash['lang'] ?? 'fi') ?>_1.jpg"
                   class="btn-download-preview"
                   title="<?= htmlspecialchars(sf_term('download_card_1', $currentUiLang) ?? 'Lataa kortti 1', ENT_QUOTES, 'UTF-8') ?>">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"
                              stroke="currentColor" stroke-width="2"
                              stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                        <polyline points="7 10 12 15 17 10"
                                  stroke="currentColor" stroke-width="2"
                                  stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                        <line x1="12" y1="15" x2="12" y2="3"
                              stroke="currentColor" stroke-width="2"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>
                        <?= htmlspecialchars(sf_term('card_1', $currentUiLang) ?? 'Kortti 1', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </a>

                <?php if ($previewUrl2): ?>
                    <a href="<?= htmlspecialchars($previewUrl2) ?>"
                       download="safetyflash_<?= (int)$flash['id'] ?>_<?= htmlspecialchars($flash['lang'] ?? 'fi') ?>_2.jpg"
                       class="btn-download-preview"
                       title="<?= htmlspecialchars(sf_term('download_card_2', $currentUiLang) ?? 'Lataa kortti 2', ENT_QUOTES, 'UTF-8') ?>">
                        <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"
                                  stroke="currentColor" stroke-width="2"
                                  stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                            <polyline points="7 10 12 15 17 10"
                                      stroke="currentColor" stroke-width="2"
                                      stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                            <line x1="12" y1="15" x2="12" y2="3"
                                  stroke="currentColor" stroke-width="2"
                                  stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                        <span>
                            <?= htmlspecialchars(sf_term('card_2', $currentUiLang) ?? 'Kortti 2', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </a>
                <?php endif; ?>
            </div>
        <?php endif; ?>

    <?php else: ?>
        <!-- NORMAALI: Yksi preview-kuva (red/yellow tai green ilman toista korttia) -->
        <img src="<?= htmlspecialchars($previewUrl) ?>" alt="Preview"
             class="preview-image" id="viewPreviewImage">

        <?php if (!empty($flash['preview_filename'])): ?>
            <div class="preview-download-wrapper">
                <a href="<?= htmlspecialchars($previewUrl) ?>"
                   download="safetyflash_<?= (int)$flash['id'] ?>_<?= htmlspecialchars($flash['lang'] ?? 'fi') ?>.jpg"
                   class="btn-download-preview"
                   title="<?= htmlspecialchars(sf_term('download_preview', $currentUiLang) ?? 'Lataa kuva', ENT_QUOTES, 'UTF-8') ?>">
                    <svg viewBox="0 0 24 24" width="20" height="20" aria-hidden="true">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"
                              stroke="currentColor" stroke-width="2"
                              stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                        <polyline points="7 10 12 15 17 10"
                                  stroke="currentColor" stroke-width="2"
                                  stroke-linecap="round" stroke-linejoin="round" fill="none"/>
                        <line x1="12" y1="15" x2="12" y2="3"
                              stroke="currentColor" stroke-width="2"
                              stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                    <span>
                        <?= htmlspecialchars(sf_term('download_preview', $currentUiLang) ?? 'Lataa JPG', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </a>
            </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

        <div class="view-box meta-box">
            <div class="meta-status-top">
                <div class="meta-status-left" aria-hidden="true">
                    <span class="meta-status-label">
                        <?= htmlspecialchars(sf_term('view_status', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                    <span class="status-pill <?= htmlspecialchars($metaStatusClass ?: '') ?>">
                        <?= htmlspecialchars($statusLabel) ?>
                    </span>
                </div>
            </div>

            <!-- SISÄLTÖ: Safetyflashin tiedot -->
            <h2 class="section-heading">
                <span class="section-heading-icon" aria-hidden="true">
                    <!-- Dokumentti-ikoni -->
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M6 3h9l3 3v15H6V3z" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linejoin="round"/>
                        <path d="M9 9h6M9 13h6M9 17h4" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="section-heading-text">
                    <?= htmlspecialchars(sf_term('view_details_heading', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </h2>

            <div class="meta-item">
                <strong><?= htmlspecialchars(sf_term('meta_title_internal', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>:</strong>
                <div><?= htmlspecialchars($flash['title'] ?? '') ?></div>
            </div>

            <div class="meta-item">
                <strong><?= htmlspecialchars(sf_term('meta_summary_short', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>:</strong>
                <div><?= nl2br(htmlspecialchars($flash['summary'] ?? '')) ?></div>
            </div>

<div class="meta-item">
    <strong><?= htmlspecialchars(sf_term('meta_description_long', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>:</strong>
    <div><?= nl2br(htmlspecialchars($flash['description'] ?? '')) ?></div>
</div>

<?php if ($flash['type'] === 'green'): ?>

    <?php if (!empty($flash['root_causes'])): ?>
        <div class="meta-item">
            <strong><?= htmlspecialchars(sf_term('root_causes_label', $currentUiLang) ?? 'Juurisyyt', ENT_QUOTES, 'UTF-8') ?>:</strong>
            <div><?= nl2br(htmlspecialchars($flash['root_causes'])) ?></div>
        </div>
    <?php endif; ?>

    <?php if (!empty($flash['actions'])): ?>
        <div class="meta-item">
            <strong><?= htmlspecialchars(sf_term('actions_label', $currentUiLang) ?? 'Toimenpiteet', ENT_QUOTES, 'UTF-8') ?>:</strong>
            <div><?= nl2br(htmlspecialchars($flash['actions'])) ?></div>
        </div>
    <?php endif; ?>

<?php endif; ?>


<div class="meta-item">
    <strong><?= htmlspecialchars(sf_term('meta_type', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>:</strong>
    <div><?= htmlspecialchars($typeLabel) ?></div>
</div>

<div class="meta-item">
    <strong><?= htmlspecialchars(sf_term('meta_site', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>:</strong>
    <div>
        <?= htmlspecialchars($flash['site'] ?? '') ?>
        <?php if (!empty($flash['site_detail'])): ?>
            &nbsp;–&nbsp;<?= htmlspecialchars($flash['site_detail']) ?>
        <?php endif; ?>
    </div>
</div>

            <div class="meta-item">
                <strong><?= htmlspecialchars(sf_term('meta_occurred_at', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>:</strong>
                <div><?= htmlspecialchars($flash['occurredFmt'] ?? '') ?></div>
            </div>

            <!-- JÄRJESTELMÄTIEDOT: kieli, luotu, muokattu -->
            <hr class="meta-separator">

            <h2 class="section-heading section-heading-system">
                <span class="section-heading-icon" aria-hidden="true">
                    <!-- Ratas-ikoni -->
                    <svg viewBox="0 0 24 24" focusable="false">
                        <path d="M12 8a4 4 0 1 1 0 8 4 4 0 0 1 0-8z" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M4 12h2M18 12h2M12 4v2M12 18v2M7 7l1.5 1.5M15.5 15.5L17 17M7 17l1.5-1.5M15.5 8.5L17 7"
                              fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round"/>
                    </svg>
                </span>
                <span class="section-heading-text">
                    <?= htmlspecialchars(sf_term('meta_system_heading', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </h2>

            <div class="meta-item">
                <strong><?= htmlspecialchars(sf_term('meta_language', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>:</strong>
                <div><?= htmlspecialchars(ucfirst((string)($flash['lang'] ?? ''))) ?></div>
            </div>

            <div class="meta-item">
                <strong><?= htmlspecialchars(sf_term('meta_created_at', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>:</strong>
                <div><?= htmlspecialchars($flash['createdFmt'] ?? '') ?></div>
            </div>

            <div class="meta-item">
                <strong><?= htmlspecialchars(sf_term('meta_updated_at', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>:</strong>
                <div><?= htmlspecialchars($flash['updatedFmt'] ?? '') ?></div>
            </div>
        </div>

        <div class="sf-log-panel log-box view-box" aria-live="polite">
            <h2 class="section-heading section-heading-log">
                <span class="section-heading-icon" aria-hidden="true">
                    <!-- Kello / historia-ikoni -->
                    <svg viewBox="0 0 24 24" focusable="false">
                        <circle cx="12" cy="12" r="8" fill="none" stroke="currentColor" stroke-width="1.6"/>
                        <path d="M12 8v4l3 2" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round"/>
                    </svg>
                </span>
                <span class="section-heading-text">
                    <?= htmlspecialchars(sf_term('log_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </span>
            </h2>

            <?php if (empty($logs)): ?>
                <p class="sf-log-empty">
                    <?= htmlspecialchars(sf_term('log_empty', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </p>
            <?php else: ?>
                <ul class="sf-log-list">
                    <?php foreach ($logs as $logRow):
                        $first = trim((string)($logRow['first_name'] ?? ''));
                        $last  = trim((string)($logRow['last_name'] ?? ''));
                        $fullName = trim($first . ' ' . $last);
                        $avatarTxt = sf_avatar_initials($fullName);

$eventKey   = $logRow['event_type'] ?? 'UNKNOWN_EVENT';
$eventLabel = sf_term($eventKey, $currentUiLang);

                        $descRaw = $logRow['description'] ?? '';

                        // Korvaa statukset pilleiksi ja suojaa perussisältö
                        $descProcessed = function_exists('sf_log_status_replace')
                            ? sf_log_status_replace($descRaw, $currentUiLang)
                            : htmlspecialchars($descRaw);

                        // Sallitaan vain pillien span-tagit
                        $descAllowed = strip_tags($descProcessed, '<span>');

// Korostetaan eri viestityypit (kommentti, viesti viestinnälle, palautus)
$messageLabels = [
    'comment' => [
        'fi' => 'Kommentti:',
        'sv' => 'Kommentar:',
        'en' => 'Comment:',
        'it' => 'Commento:',
        'el' => 'Σχόλιο:',
    ],
    'comms' => [
        'fi' => 'Viesti viestinnälle:',
        'sv' => 'Meddelande till kommunikation:',
        'en' => 'Message to communications:',
        'it' => 'Messaggio alla comunicazione:',
        'el' => 'Μήνυμα προς επικοινωνία:',
    ],
    'return' => [
        'fi' => 'Palautteen syy:',
        'sv' => 'Anledning till återkoppling:',
        'en' => 'Reason for return:',
        'it' => 'Motivo del reso:',
        'el' => 'Λόγος επιστροφής:',
    ],
];

$descHighlighted = $descAllowed;

// Käy läpi kaikki viestityypit ja korvaa ne tyylitellyillä laatikoilla
foreach ($messageLabels as $msgType => $labels) {
    $label = $labels[$currentUiLang] ?? $labels['en'];
    $cssClass = 'sf-log-' . $msgType; // sf-log-comment, sf-log-comms, sf-log-return

    $pattern = '/(^|\R)\s*' . preg_quote($label, '/') . '\s*(.+)$/u';
    $descHighlighted = preg_replace(
        $pattern,
        '$1<div class="sf-log-message-box ' . $cssClass . '"><span class="sf-log-message-label">' . htmlspecialchars($label) . '</span> $2</div>',
        $descHighlighted
    );
}
                        $plainDescLen = mb_strlen(strip_tags($descAllowed));
                        $needsMore    = $plainDescLen > 300;
                    ?>
                        <li class="sf-log-item" id="log-<?= (int)$logRow['id'] ?>">
                            <div class="sf-log-avatar" data-name="<?= htmlspecialchars($fullName) ?>">
                                <?= htmlspecialchars($avatarTxt) ?>
                            </div>

                            <div>
                                <div class="sf-log-header-row" role="group" aria-label="<?= htmlspecialchars($eventLabel) ?>">
                                    <span class="sf-log-type"><?= htmlspecialchars($eventLabel) ?></span>
                                    <span class="sf-log-time"><?= htmlspecialchars($logRow['created_at'] ?? '') ?></span>
                                </div>

                                <div class="sf-log-message<?= $needsMore ? '' : ' expanded' ?>">
                                    <?= nl2br($descHighlighted) ?>
                                </div>

                                <?php if ($needsMore): ?>
                                    <div
                                      class="sf-log-more"
                                      role="button"
                                      tabindex="0"
                                      aria-expanded="false"
                                    >
                                      <?= htmlspecialchars(sf_term('log_show_more', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                                    </div>
                                <?php endif; ?>
                            </div>

                            <div class="sf-log-meta" aria-hidden="false">
                                <div class="sf-log-user">
                                    <?= $fullName !== '' ? htmlspecialchars($fullName) : htmlspecialchars(sf_term('log_system_user', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                                </div>
                            </div>
                        </li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

    </div> <!-- .view-grid -->
</div> <!-- .view-container -->

<!-- ===== MODALIT ===== -->
<div class="sf-modal hidden" id="modalEdit" role="dialog" aria-modal="true" aria-labelledby="modalEditTitle">
    <div class="sf-modal-content">
        <h2 id="modalEditTitle">
            <?= htmlspecialchars(sf_term('modal_edit_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p>
            <?= htmlspecialchars(sf_term('modal_edit_text', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <div class="sf-modal-actions">
            <button
              type="button"
              class="sf-btn sf-btn-secondary"
              data-modal-close="modalEdit"
            >
              <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button
              type="button"
              class="sf-btn sf-btn-primary"
              id="modalEditOk"
            >
              <?= htmlspecialchars(sf_term('btn_ok_edit', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>
</div>

<div class="sf-modal hidden" id="modalComment" role="dialog" aria-modal="true" aria-labelledby="modalCommentTitle">
    <div class="sf-modal-content">
        <h2 id="modalCommentTitle">
            <?= htmlspecialchars(sf_term('modal_comment_title', $currentUiLang) ?? 'Lisää kommentti', ENT_QUOTES, 'UTF-8') ?>
        </h2>
<form method="post" action="<?= htmlspecialchars($base) ?>/app/actions/comment. php?id=<?= (int)$id ?>">
    <?= sf_csrf_field() ?>
    <label for="commentMessage">
        <?= htmlspecialchars(sf_term('modal_comment_label', $currentUiLang) ?? 'Kommentti', ENT_QUOTES, 'UTF-8') ?>
    </label>
    <textarea
      id="commentMessage"
      name="message"
      rows="4"
      placeholder="<?= htmlspecialchars(sf_term('modal_comment_placeholder', $currentUiLang) ?? '', ENT_QUOTES, 'UTF-8') ?>"
    ></textarea>
    <div class="sf-modal-actions">
        <button
          type="button"
          class="sf-btn sf-btn-secondary"
          data-modal-close="modalComment"
        >
          <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </button>
        <button type="submit" class="sf-btn sf-btn-primary">
          <?= htmlspecialchars(sf_term('btn_comment_send', $currentUiLang) ?? 'Tallenna kommentti', ENT_QUOTES, 'UTF-8') ?>
        </button>
    </div>
</form>
    </div>
</div>

<div class="sf-modal hidden" id="modalRequestInfo" role="dialog" aria-modal="true" aria-labelledby="modalRequestInfoTitle">
    <div class="sf-modal-content">
        <h2 id="modalRequestInfoTitle">
            <?= htmlspecialchars(sf_term('modal_request_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <form method="post" action="<?= htmlspecialchars($base) ?>/app/actions/request_info.php?id=<?= (int)$id ?>">
            <label for="reqMessage">
                <?= htmlspecialchars(sf_term('modal_request_label', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </label>
            <textarea
              id="reqMessage"
              name="message"
              rows="4"
              placeholder="<?= htmlspecialchars(sf_term('modal_request_placeholder', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>"
            ></textarea>
            <div class="sf-modal-actions">
                <button
                  type="button"
                  class="sf-btn sf-btn-secondary"
                  data-modal-close="modalRequestInfo"
                >
                  <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="submit" class="sf-btn sf-btn-primary">
                  <?= htmlspecialchars(sf_term('btn_send_request', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="sf-modal hidden" id="modalToComms" role="dialog" aria-modal="true" aria-labelledby="modalToCommsTitle">
    <div class="sf-modal-content">
        <h2 id="modalToCommsTitle">
            <?= htmlspecialchars(sf_term('modal_to_comms_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <form method="post" action="<?= htmlspecialchars($base) ?>/app/actions/send_to_comms.php?id=<?= (int)$id ?>">
            <label for="commsMessage">
                <?= htmlspecialchars(sf_term('modal_to_comms_label', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </label>
            <textarea
              id="commsMessage"
              name="message"
              rows="4"
              placeholder="<?= htmlspecialchars(sf_term('modal_to_comms_placeholder', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>"
            ></textarea>
            <div class="sf-modal-actions">
                <button
                  type="button"
                  class="sf-btn sf-btn-secondary"
                  data-modal-close="modalToComms"
                >
                  <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="submit" class="sf-btn sf-btn-primary">
                  <?= htmlspecialchars(sf_term('btn_send_comms', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="sf-modal hidden" id="modalPublish" role="dialog" aria-modal="true" aria-labelledby="modalPublishTitle">
    <div class="sf-modal-content">
        <h2 id="modalPublishTitle">
            <?= htmlspecialchars(sf_term('modal_publish_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p>
            <?= htmlspecialchars(sf_term('modal_publish_text', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <form method="post" action="<?= htmlspecialchars($base) ?>/app/actions/publish.php?id=<?= (int)$id ?>">
            <div class="sf-modal-actions">
                <button
                  type="button"
                  class="sf-btn sf-btn-secondary"
                  data-modal-close="modalPublish"
                >
                  <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="submit" class="sf-btn sf-btn-primary">
                  <?= htmlspecialchars(sf_term('btn_publish', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<div class="sf-modal hidden" id="modalDelete" role="dialog" aria-modal="true" aria-labelledby="modalDeleteTitle">
    <div class="sf-modal-content">
        <h2 id="modalDeleteTitle">
            <?= htmlspecialchars(sf_term('modal_delete_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <p>
            <?= htmlspecialchars(sf_term('modal_delete_text', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <form method="post" action="<?= htmlspecialchars($base) ?>/app/actions/delete.php?id=<?= (int)$id ?>">
            <div class="sf-modal-actions">
                <button
                  type="button"
                  class="sf-btn sf-btn-secondary"
                  data-modal-close="modalDelete"
                >
                  <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="submit" class="sf-btn sf-btn-danger">
                  <?= htmlspecialchars(sf_term('btn_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- KIELIVERSIO-MODAALI (vaiheittainen) -->
<div class="sf-modal hidden" id="modalTranslation" role="dialog" aria-modal="true" aria-labelledby="modalTranslationTitle">
    <div class="sf-modal-content sf-modal-translation">
        
        <!-- VAIHE 1: Lomake -->
        <div class="sf-translation-step" id="translationStep1">
            <h2 id="modalTranslationTitle">
                <?php echo htmlspecialchars(sf_term('modal_translation_title', $currentUiLang) ?? 'Luo kieliversio', ENT_QUOTES, 'UTF-8'); ?>
            </h2>
            
            <div class="sf-step-indicator">
                <span class="sf-step active">1</span>
                <span class="sf-step-line"></span>
                <span class="sf-step">2</span>
            </div>

            <form id="translationForm">
                <input type="hidden" name="source_id" value="<?php echo (int)$flash['id']; ?>">
                <input type="hidden" name="target_lang" id="translationTargetLang" value="">
                
                <div class="sf-field">
                    <label class="sf-label">
                        <?php echo htmlspecialchars(sf_term('translation_target_lang', $currentUiLang) ?? 'Kohdekieli', ENT_QUOTES, 'UTF-8'); ?>
                    </label>
                    <div class="sf-translation-lang-display" id="translationLangDisplay"></div>
                </div>

                <div class="sf-field">
                    <label for="translationTitleShort" class="sf-label">
                        <?php echo htmlspecialchars(sf_term('short_title_label', $currentUiLang) ?? 'Lyhyt kuvaus', ENT_QUOTES, 'UTF-8'); ?> *
                    </label>
                    <textarea 
                        name="title_short" 
                        id="translationTitleShort" 
                        class="sf-textarea" 
                        rows="2" 
                        maxlength="125"
                        required
                    ></textarea>
                    <div class="sf-char-count"><span id="titleCharCount">0</span>/125</div>
                </div>

                <div class="sf-field">
                    <label for="translationDescription" class="sf-label">
                        <?php echo htmlspecialchars(sf_term('description_label', $currentUiLang) ?? 'Kuvaus', ENT_QUOTES, 'UTF-8'); ?> *
                    </label>
                    <textarea 
                        name="description" 
                        id="translationDescription" 
                        class="sf-textarea" 
                        rows="5"
                        maxlength="650"
                        required
                    ></textarea>
                    <div class="sf-char-count"><span id="descCharCount">0</span>/650</div>
                </div>

                <?php if ($flash['type'] === 'green'): ?>
                    <div class="sf-field">
                        <label for="translationRootCauses" class="sf-label">
                            <?php echo htmlspecialchars(sf_term('root_cause_label', $currentUiLang) ?? 'Juurisyyt', ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                        <textarea name="root_causes" id="translationRootCauses" class="sf-textarea" rows="3"></textarea>
                    </div>

                    <div class="sf-field">
                        <label for="translationActions" class="sf-label">
                            <?php echo htmlspecialchars(sf_term('actions_label', $currentUiLang) ?? 'Toimenpiteet', ENT_QUOTES, 'UTF-8'); ?>
                        </label>
                        <textarea name="actions" id="translationActions" class="sf-textarea" rows="3"></textarea>
                    </div>
                <?php endif; ?>
            </form>

            <div class="sf-modal-actions">
                <button type="button" class="sf-btn sf-btn-secondary" data-modal-close="modalTranslation">
                    <?php echo htmlspecialchars(sf_term('btn_cancel', $currentUiLang) ?? 'Peruuta', ENT_QUOTES, 'UTF-8'); ?>
                </button>
                <button type="button" class="sf-btn sf-btn-primary" id="btnToStep2">
                    <?php echo htmlspecialchars(sf_term('btn_next', $currentUiLang) ?? 'Seuraava', ENT_QUOTES, 'UTF-8'); ?> →
                </button>
            </div>
        </div>

        <!-- VAIHE 2: Esikatselu -->
        <div class="sf-translation-step hidden" id="translationStep2">
            <h2>
                <?php echo htmlspecialchars(sf_term('preview_and_save', $currentUiLang) ?? 'Esikatselu ja tallennus', ENT_QUOTES, 'UTF-8'); ?>
            </h2>
            
            <div class="sf-step-indicator">
                <span class="sf-step done">✓</span>
                <span class="sf-step-line done"></span>
                <span class="sf-step active">2</span>
            </div>

            <div class="sf-translation-preview-wrapper">
                <div id="sfTranslationPreviewContainer">
                    <?php require __DIR__ . '/../partials/preview_modal.php'; ?>
                </div>
            </div>

            <div id="translationStatus" class="sf-translation-status"></div>

            <div class="sf-modal-actions">
                <button type="button" class="sf-btn sf-btn-secondary" id="btnBackToStep1">
                    ← <?php echo htmlspecialchars(sf_term('btn_back', $currentUiLang) ?? 'Takaisin', ENT_QUOTES, 'UTF-8'); ?>
                </button>
                <button type="button" class="sf-btn sf-btn-primary" id="btnSaveTranslation">
                    <?php echo htmlspecialchars(sf_term('btn_save_translation', $currentUiLang) ?? 'Tallenna kieliversio', ENT_QUOTES, 'UTF-8'); ?>
                </button>
            </div>
        </div>

    </div>
</div>


<!-- ===== FOOTER ACTION BAR ===== -->
<?php
// Haetaan käyttäjän rooli ja tarkistetaan omistajuus
$currentUser    = sf_current_user();
$currentUserId  = $currentUser ? (int) $currentUser['id'] : 0;
$currentRoleId  = $currentUser ? (int) $currentUser['role_id'] : 0;
$flashCreatedBy = (int) ($flash['created_by'] ?? 0);

$isOwner = ($currentUserId > 0 && $flashCreatedBy === $currentUserId);

// Roolit
$ROLE_ADMIN  = 1;
$ROLE_WRITER = 2;
$ROLE_SAFETY = 3;
$ROLE_COMMS  = 4;

$isAdmin  = ($currentRoleId === $ROLE_ADMIN);
$isWriter = ($currentRoleId === $ROLE_WRITER);
$isSafety = ($currentRoleId === $ROLE_SAFETY);
$isComms  = ($currentRoleId === $ROLE_COMMS);

// Kuka voi kommentoida: omistaja, turvatiimi, viestintä tai admin
$canComment = ($isOwner || $isSafety || $isComms || $isAdmin);

$state = $flash['state'] ?? '';

// Ikonipolut
$iconBase = $base . '/assets/img/icons/';
?>
<div class="view-footer-actions" role="toolbar" aria-label="Toiminnot">
    <div class="view-footer-buttons-4col">

        <?php if ($state === 'draft'): ?>
            <?php if ($isOwner || $isAdmin): ?>
                <?php if ($canComment): ?>
                    <button class="footer-btn fb-comment" id="footerComment" type="button"
                        aria-label="<?= htmlspecialchars(sf_term('footer_comment', $currentUiLang) ?? 'Kommentti', ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= $iconBase ?>comment_icon.svg" alt="" class="footer-icon">
                        <span class="btn-label">
                            <?= htmlspecialchars(sf_term('footer_comment', $currentUiLang) ?? 'Kommentti', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </button>
                <?php endif; ?>

                <button class="footer-btn fb-edit" id="footerEdit" type="button"
                    aria-label="<?= htmlspecialchars(sf_term('footer_edit', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>edit_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label">
                        <?= htmlspecialchars(sf_term('footer_edit', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </button>

                <button class="footer-btn fb-delete" id="footerDelete" type="button"
                    aria-label="<?= htmlspecialchars(sf_term('footer_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>delete_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label">
                        <?= htmlspecialchars(sf_term('footer_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </button>
            <?php endif; ?>

        <?php elseif ($state === 'pending_review'): ?>
            <?php if ($isSafety || $isAdmin || $isComms): ?>
                <?php if ($canComment): ?>
                    <button class="footer-btn fb-comment" id="footerComment" type="button"
                        aria-label="<?= htmlspecialchars(sf_term('footer_comment', $currentUiLang) ?? 'Kommentti', ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= $iconBase ?>comment_icon.svg" alt="" class="footer-icon">
                        <span class="btn-label">
                            <?= htmlspecialchars(sf_term('footer_comment', $currentUiLang) ?? 'Kommentti', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </button>
                <?php endif; ?>

                <button class="footer-btn fb-edit" id="footerEdit" type="button"
                    aria-label="<?= htmlspecialchars(sf_term('footer_edit', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>edit_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label">
                        <?= htmlspecialchars(sf_term('footer_edit', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </button>

                <button class="footer-btn fb-request" id="footerRequest" type="button"
                    aria-label="<?= htmlspecialchars(sf_term('footer_return', $currentUiLang) ?? 'Palauta', ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>reverse_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label">
                        <?= htmlspecialchars(sf_term('footer_return', $currentUiLang) ?? 'Palauta', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </button>

                <button class="footer-btn fb-comms" id="footerComms" type="button"
                    aria-label="<?= htmlspecialchars(sf_term('footer_to_comms', $currentUiLang) ?? 'Viestintään', ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>communications_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label">
                        <?= htmlspecialchars(sf_term('footer_to_comms', $currentUiLang) ?? 'Viestintään', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </button>

                <button class="footer-btn fb-delete" id="footerDelete" type="button"
                    aria-label="<?= htmlspecialchars(sf_term('footer_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>delete_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label">
                        <?= htmlspecialchars(sf_term('footer_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </button>
            <?php elseif ($isOwner): ?>
                <!-- Omistaja näkee vain kommentoinnin pending_review-tilassa -->
                <?php if ($canComment): ?>
                    <button class="footer-btn fb-comment" id="footerComment" type="button"
                        aria-label="<?= htmlspecialchars(sf_term('footer_comment', $currentUiLang) ?? 'Kommentti', ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= $iconBase ?>comment_icon.svg" alt="" class="footer-icon">
                        <span class="btn-label">
                            <?= htmlspecialchars(sf_term('footer_comment', $currentUiLang) ?? 'Kommentti', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </button>
                <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($state === 'request_info'): ?>
            <?php if ($isOwner || $isSafety || $isAdmin || $isComms): ?>
                <?php if ($canComment): ?>
                    <button class="footer-btn fb-comment" id="footerComment" type="button"
                        aria-label="<?= htmlspecialchars(sf_term('footer_comment', $currentUiLang) ?? 'Kommentti', ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= $iconBase ?>comment_icon.svg" alt="" class="footer-icon">
                        <span class="btn-label">
                            <?= htmlspecialchars(sf_term('footer_comment', $currentUiLang) ?? 'Kommentti', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </button>
                <?php endif; ?>

                <?php if ($isOwner || $isAdmin): ?>
                    <form method="post"
                          action="<?= htmlspecialchars($base) ?>/app/actions/send_to_review.php?id=<?= (int) $id ?>"
                          class="footer-form">
                        <button class="footer-btn fb-edit" id="footerEdit" type="button"
                            aria-label="<?= htmlspecialchars(sf_term('footer_edit', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                            <img src="<?= $iconBase ?>edit_icon.svg" alt="" class="footer-icon">
                            <span class="btn-label">
                                <?= htmlspecialchars(sf_term('footer_edit', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </button>

                        <button class="footer-btn fb-comms" type="submit">
                            <img src="<?= $iconBase ?>communications_icon.svg" alt="" class="footer-icon">
                            <span class="btn-label">
                                <?= htmlspecialchars(sf_term('footer_send_to_review', $currentUiLang) ?? 'Tarkistukseen', ENT_QUOTES, 'UTF-8') ?>
                            </span>
                        </button>
                    </form>
                <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($state === 'to_comms'): ?>
            <?php if ($isComms || $isAdmin || $isSafety || $isOwner): ?>
                <?php if ($canComment): ?>
                    <button class="footer-btn fb-comment" id="footerComment" type="button"
                        aria-label="<?= htmlspecialchars(sf_term('footer_comment', $currentUiLang) ?? 'Kommentti', ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= $iconBase ?>comment_icon.svg" alt="" class="footer-icon">
                        <span class="btn-label">
                            <?= htmlspecialchars(sf_term('footer_comment', $currentUiLang) ?? 'Kommentti', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </button>
                <?php endif; ?>

                <?php if ($isComms || $isAdmin || $isSafety): ?>
                    <button class="footer-btn fb-edit" id="footerEdit" type="button"
                        aria-label="<?= htmlspecialchars(sf_term('footer_edit', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= $iconBase ?>edit_icon.svg" alt="" class="footer-icon">
                        <span class="btn-label">
                            <?= htmlspecialchars(sf_term('footer_edit', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </button>

                    <button class="footer-btn fb-request" id="footerRequest" type="button"
                        aria-label="<?= htmlspecialchars(sf_term('footer_return', $currentUiLang) ?? 'Palauta', ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= $iconBase ?>reverse_icon.svg" alt="" class="footer-icon">
                        <span class="btn-label">
                            <?= htmlspecialchars(sf_term('footer_return', $currentUiLang) ?? 'Palauta', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </button>

                    <button class="footer-btn fb-publish" id="footerPublish" type="button"
                        aria-label="<?= htmlspecialchars(sf_term('footer_publish', $currentUiLang) ?? 'Julkaise', ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= $iconBase ?>publish_icon.svg" alt="" class="footer-icon">
                        <span class="btn-label">
                            <?= htmlspecialchars(sf_term('footer_publish', $currentUiLang) ?? 'Julkaise', ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </button>
                <?php endif; ?>

                <?php if ($isAdmin): ?>
                    <button class="footer-btn fb-delete" id="footerDelete" type="button"
                        aria-label="<?= htmlspecialchars(sf_term('footer_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                        <img src="<?= $iconBase ?>delete_icon.svg" alt="" class="footer-icon">
                        <span class="btn-label">
                            <?= htmlspecialchars(sf_term('footer_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </button>
                <?php endif; ?>
            <?php endif; ?>

        <?php elseif ($state === 'published'): ?>
            <?php if ($canComment): ?>
                <button class="footer-btn fb-comment" id="footerComment" type="button"
                    aria-label="<?= htmlspecialchars(sf_term('footer_comment', $currentUiLang) ?? 'Kommentti', ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>comment_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label">
                        <?= htmlspecialchars(sf_term('footer_comment', $currentUiLang) ?? 'Kommentti', ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </button>
            <?php endif; ?>

            <?php if ($isAdmin): ?>
                <button class="footer-btn fb-edit" id="footerEdit" type="button"
                    aria-label="<?= htmlspecialchars(sf_term('footer_edit', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>edit_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label">
                        <?= htmlspecialchars(sf_term('footer_edit', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </button>

                <button class="footer-btn fb-delete" id="footerDelete" type="button"
                    aria-label="<?= htmlspecialchars(sf_term('footer_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>">
                    <img src="<?= $iconBase ?>delete_icon.svg" alt="" class="footer-icon">
                    <span class="btn-label">
                        <?= htmlspecialchars(sf_term('footer_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </button>
            <?php endif; ?>

        <?php endif; ?>

    </div>
</div></div><!-- html2canvas tarvitaan kuvan generointiin -->
<script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>

<!-- Safetyflash CSS & JS -->
<link rel="stylesheet" href="<?php echo htmlspecialchars($base); ?>/assets/css/preview.css">
<script src="<?php echo htmlspecialchars($base); ?>/assets/js/view.js"></script>
<script src="<?php echo htmlspecialchars($base); ?>/assets/js/translation.js"></script>

<!-- Sivukohtaiset datat -->
<script>
window.SF_LOG_SHOW_MORE   = <?php echo json_encode(sf_term('log_show_more', $currentUiLang)); ?>;
window.SF_LOG_SHOW_LESS   = <?php echo json_encode(sf_term('log_show_less', $currentUiLang)); ?>;
window.SF_BASE_URL        = <?php echo json_encode($base); ?>;
window.SF_EDIT_URL        = <?php echo json_encode($editUrl); ?>;
window.SF_FLASH_DATA      = <?php echo json_encode($flashDataForJs); ?>;
window.SF_SUPPORTED_LANGS = <?php echo json_encode($supportedLangs); ?>;
</script>