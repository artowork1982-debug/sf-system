<?php
/**
 * app/partials/preview.php
 * RED/YELLOW tyyppien preview - tiivis layout
 */

if (!isset($base)) {
    throw new RuntimeException('preview.php requires $base to be defined');
}

// Määritä tyyppi - tarkista tyhjät merkkijonot
$previewType = 'yellow'; // oletus
if (!empty($type_val)) {
    $previewType = $type_val;
} elseif (!empty($flash['type'])) {
    $previewType = $flash['type'];
}

$previewLang = !empty($flash['lang']) ? $flash['lang'] : 'fi';

// Rakenna taustakuvan URL
$bgImageUrl = "{$base}/assets/img/templates/SF_bg_{$previewType}_{$previewLang}.jpg";

$getImageUrl = function ($filename) use ($base) {
    if (empty($filename)) {
        return "{$base}/assets/img/camera-placeholder.png";
    }
    if (file_exists(__DIR__ . "/../../uploads/images/{$filename}")) {
        return "{$base}/uploads/images/{$filename}";
    }
    // Fallback
    return "{$base}/assets/img/camera-placeholder.png";
};

$imgMain = $getImageUrl($flash['image_main'] ?? null);
$img2    = $getImageUrl($flash['image_2'] ?? null);
$img3    = $getImageUrl($flash['image_3'] ?? null);

$title      = $flash['title_short'] ?? $flash['summary'] ?? '';
$desc       = $flash['description'] ?? '';
$site       = $flash['site'] ?? '';
$siteDetail = $flash['site_detail'] ?? '';
$eventDate  = $flash['occurred_at'] ?? '';

$siteText = $site . (!empty($siteDetail) ? ' – ' . $siteDetail : '');

$formattedDate = '–';
if (!empty($eventDate)) {
    $dt = DateTime::createFromFormat('Y-m-d\TH:i', $eventDate)
        ?: DateTime::createFromFormat('Y-m-d H:i:s', $eventDate)
        ?: (strtotime($eventDate) ? new DateTime($eventDate) : null);

    if ($dt) {
        $formattedDate = $dt->format('d.m.Y H:i');
    }
}

$labels = [
    'fi' => ['site' => 'Työmaa:',    'date' => 'Milloin?'],
    'sv' => ['site' => 'Arbetsplats:', 'date' => 'När?'],
    'en' => ['site' => 'Worksite:',  'date' => 'When?'],
];

$siteLabel = $labels[$previewLang]['site'] ?? $labels['fi']['site'];
$dateLabel = $labels[$previewLang]['date'] ?? $labels['fi']['date'];
?>

<div class="sf-preview-section">
    <!-- OTSIKKO -->
    <h2 class="sf-preview-step-title">Esikatselu</h2>

    <!-- PREVIEW-KORTTI WRAPPER -->
    <div class="sf-preview-wrapper" id="sfPreviewWrapper">

        <!-- GRID-VALITSIN KORTIN YLÄPUOLELLA -->
        <div class="sf-grid-selector-row" id="sfGridSelector">
            <span class="sf-layout-label">Asettelu:</span>
            <div class="sf-grid-buttons">
                <!-- 2 KUVAN LAYOUTIT -->
                <button
                    type="button"
                    class="sf-grid-btn active"
                    data-grid="grid-2-stacked"
                    data-for="2"
                    title="Päällekkäin"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <rect x="2" y="2" width="20" height="9" rx="1" />
                        <rect x="2" y="13" width="20" height="9" rx="1" />
                    </svg>
                </button>
                <button
                    type="button"
                    class="sf-grid-btn"
                    data-grid="grid-2-overlay"
                    data-for="2"
                    title="Overlay"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <rect x="2" y="2" width="20" height="20" rx="1" />
                        <rect x="12" y="14" width="8" height="6" rx="1" fill="#666" />
                    </svg>
                </button>

                <!-- 3 KUVAN LAYOUTIT -->
                <button
                    type="button"
                    class="sf-grid-btn active"
                    data-grid="grid-3-main-top"
                    data-for="3"
                    title="Iso ylhäällä"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <rect x="2" y="2" width="20" height="12" rx="1" />
                        <rect x="2" y="16" width="9" height="6" rx="1" />
                        <rect x="13" y="16" width="9" height="6" rx="1" />
                    </svg>
                </button>
                <button
                    type="button"
                    class="sf-grid-btn"
                    data-grid="grid-3-overlay"
                    data-for="3"
                    title="Overlay"
                >
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="currentColor">
                        <rect x="2" y="2" width="20" height="20" rx="1" />
                        <rect x="4" y="14" width="7" height="6" rx="1" fill="#666" />
                        <rect x="13" y="14" width="7" height="6" rx="1" fill="#666" />
                    </svg>
                </button>
            </div>
        </div>

        <!-- PREVIEW-KORTTI -->
        <div
            class="sf-preview-card"
            id="sfPreviewCard"
            data-type="<?= htmlspecialchars($previewType, ENT_QUOTES, 'UTF-8') ?>"
            data-lang="<?= htmlspecialchars($previewLang, ENT_QUOTES, 'UTF-8') ?>"
            data-base-url="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>"
            data-base-width="1920"
            data-base-height="1080"
        >
            <img
                src="<?= htmlspecialchars($bgImageUrl, ENT_QUOTES, 'UTF-8') ?>"
                alt=""
                class="sf-preview-bg"
                id="sfPreviewBg"
            >

            <div class="sf-preview-content">
                <div class="sf-preview-text-col">
                    <h3 class="sf-preview-title" id="sfPreviewTitle">
                        <?= htmlspecialchars($title ?: 'Lyhyt kuvaus tapahtumasta', ENT_QUOTES, 'UTF-8') ?>
                    </h3>
                    <div class="sf-preview-desc" id="sfPreviewDesc">
                        <?= nl2br(htmlspecialchars($desc ?: 'Tarkempi kuvaus / tilannekuva', ENT_QUOTES, 'UTF-8')) ?>
                    </div>
                    <div class="sf-preview-meta-row">
                        <div class="sf-preview-meta-box">
                            <div class="sf-preview-meta-label" id="sfPreviewSiteLabel">
                                <?= htmlspecialchars($siteLabel, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="sf-preview-meta-value" id="sfPreviewSite">
                                <?= htmlspecialchars($siteText ?: '–', ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                        <div class="sf-preview-meta-box">
                            <div class="sf-preview-meta-label" id="sfPreviewDateLabel">
                                <?= htmlspecialchars($dateLabel, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                            <div class="sf-preview-meta-value" id="sfPreviewDate">
                                <?= htmlspecialchars($formattedDate, ENT_QUOTES, 'UTF-8') ?>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="sf-preview-image-col" id="sfImageCol">
                    <div class="sf-preview-image-frame" id="sfFrame1" data-slot="1">
                        <img
                            src="<?= htmlspecialchars($imgMain, ENT_QUOTES, 'UTF-8') ?>"
                            id="sfPreviewImg1"
                            class="sf-preview-img-element"
                            alt="Pääkuva"
                        >
                    </div>
                    <div class="sf-preview-thumbs-row">
                        <div class="sf-preview-thumb-frame" id="sfFrame2" data-slot="2">
                            <img
                                src="<?= htmlspecialchars($img2, ENT_QUOTES, 'UTF-8') ?>"
                                id="sfPreviewImg2"
                                class="sf-preview-img-element"
                                alt="Kuva 2"
                            >
                        </div>
                        <div class="sf-preview-thumb-frame" id="sfFrame3" data-slot="3">
                            <img
                                src="<?= htmlspecialchars($img3, ENT_QUOTES, 'UTF-8') ?>"
                                id="sfPreviewImg3"
                                class="sf-preview-img-element"
                                alt="Kuva 3"
                            >
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Sisällytä yhteiset työkalut (sliderit ja merkinnät)
    $idSuffix   = '';
    $extraClass = '';
    include __DIR__ . '/preview_tools.php';
    ?>

</div>