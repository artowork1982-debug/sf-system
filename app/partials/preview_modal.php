<?php
/**
 * app/partials/preview_modal.php
 * Kieliversio-modaalin preview
 */
if (! isset($base)) {
    throw new RuntimeException('preview_modal.php requires $base to be defined');
}

// Apufunktio kuvan URL:n muodostamiseen
$getImageUrl = function ($filename) use ($base) {
    if (empty($filename)) {
        return "{$base}/assets/img/camera-placeholder.png";
    }
    
    // Tarkista ensin uploads/images
    $path = "uploads/images/{$filename}";
    if (file_exists(__DIR__ . "/../../{$path}")) {
        return "{$base}/{$path}";
    }
    
    // Tarkista uploads/library (kuvakirjasto)
    $libPath = "uploads/library/{$filename}";
    if (file_exists(__DIR__ .  "/../../{$libPath}")) {
        return "{$base}/{$libPath}";
    }
    
    // Vanha polku (legacy)
    $oldPath = "img/{$filename}";
    if (file_exists(__DIR__ . "/../../{$oldPath}")) {
        return "{$base}/{$oldPath}";
    }
    
    // Palauta placeholder jos tiedostoa ei löydy
    return "{$base}/assets/img/camera-placeholder.png";
};

// Hae kuvien URL:t
$imgMainUrl = $getImageUrl($flash['image_main'] ?? null);
$img2Url    = $getImageUrl($flash['image_2'] ?? null);
$img3Url    = $getImageUrl($flash['image_3'] ??  null);

$flashType = $flash['type'] ?? 'yellow';
$flashLang = $flash['lang'] ?? 'fi';

// Hae kuvamuunnokset alkuperäisestä
$img1Transform = $flash['image1_transform'] ?? '';
$img2Transform = $flash['image2_transform'] ?? '';
$img3Transform = $flash['image3_transform'] ?? '';

// Laske kuvien määrä
$imageCount = 0;
if (!empty($flash['image_main'])) $imageCount++;
if (!empty($flash['image_2'])) $imageCount++;
if (!empty($flash['image_3'])) $imageCount++;

// Valitse grid-tyyli kuvien määrän JA tallennetun tyylin mukaan
if ($imageCount <= 1) {
    $gridStyle = 'grid-main-only';
} elseif ($imageCount === 2) {
    $savedStyle = $flash['grid_style'] ?? '';
    if (in_array($savedStyle, ['grid-2-stacked', 'grid-2-overlay'])) {
        $gridStyle = $savedStyle;
    } else {
        $gridStyle = 'grid-2-overlay';
    }
} else {
    $savedStyle = $flash['grid_style'] ??  '';
    if (in_array($savedStyle, ['grid-3-main-top', 'grid-3-overlay'])) {
        $gridStyle = $savedStyle;
    } else {
        $gridStyle = 'grid-3-main-top';
    }
}

// Taustakuva - oletuskieli, JS päivittää kohdekielen mukaan
$bgImageUrl = "{$base}/assets/img/templates/SF_bg_{$flashType}_{$flashLang}.jpg";

// Kuvan transform CSS inline
$getTransformStyle = function($transformJson) {
    if (empty($transformJson)) return '';
    $t = json_decode($transformJson, true);
    if (! $t) return '';
    $x = $t['x'] ?? 0;
    $y = $t['y'] ?? 0;
    $scale = $t['scale'] ?? 1;
    return "transform: translate(calc(-50% + {$x}px), calc(-50% + {$y}px)) scale({$scale}); position: absolute; top: 50%; left: 50%;";
};

$img1Style = $getTransformStyle($img1Transform);
$img2Style = $getTransformStyle($img2Transform);
$img3Style = $getTransformStyle($img3Transform);
?>

<div
    class="sf-preview-card <?= htmlspecialchars($gridStyle, ENT_QUOTES, 'UTF-8') ?>"
    id="sfPreviewCard"
    data-type="<?= htmlspecialchars($flashType, ENT_QUOTES, 'UTF-8') ?>"
    data-lang="<?= htmlspecialchars($flashLang, ENT_QUOTES, 'UTF-8') ?>"
    data-base-url="<?= htmlspecialchars($base, ENT_QUOTES, 'UTF-8') ?>"
    data-image-count="<?= $imageCount ?>"
    data-grid-style="<?= htmlspecialchars($gridStyle, ENT_QUOTES, 'UTF-8') ?>"
    data-base-width="1920"
    data-base-height="1080"
    style="width: 1920px; height: 1080px;"
>
    <!-- TAUSTAKUVA -->
    <img
        src="<?= htmlspecialchars($bgImageUrl, ENT_QUOTES, 'UTF-8') ?>"
        alt=""
        class="sf-preview-bg"
        id="sfPreviewBg"
    >

    <div class="sf-preview-content">
        <div class="sf-preview-text-col">
            <h3 class="sf-preview-title" id="sfPreviewTitle">Otsikko...</h3>
            <div class="sf-preview-desc" id="sfPreviewDesc">Kuvaus...</div>
            <div class="sf-preview-meta-row">
                <div class="sf-preview-meta-box">
                    <div class="sf-preview-meta-label" id="sfPreviewSiteLabel">TYÖMAA</div>
                    <div class="sf-preview-meta-value" id="sfPreviewSite">–</div>
                </div>
                <div class="sf-preview-meta-box">
                    <div class="sf-preview-meta-label" id="sfPreviewDateLabel">MILLOIN? </div>
                    <div class="sf-preview-meta-value" id="sfPreviewDate">–</div>
                </div>
            </div>
        </div>

        <div class="sf-preview-image-col" id="sfImageCol">
            <div class="sf-preview-image-frame" id="sfFrame1" data-slot="1">
                <img
                    src="<?= htmlspecialchars($imgMainUrl, ENT_QUOTES, 'UTF-8') ?>"
                    id="sfPreviewImg1"
                    class="sf-preview-img-element"
                    <?php if ($img1Style): ?>style="<?= $img1Style ?>"<?php endif; ?>
                >
            </div>
            <div class="sf-preview-thumbs-row">
                <div class="sf-preview-thumb-frame" id="sfFrame2" data-slot="2">
                    <img
                        src="<?= htmlspecialchars($img2Url, ENT_QUOTES, 'UTF-8') ?>"
                        id="sfPreviewImg2"
                        class="sf-preview-img-element"
                        <?php if ($img2Style): ?>style="<?= $img2Style ?>"<?php endif; ?>
                    >
                </div>
                <div class="sf-preview-thumb-frame" id="sfFrame3" data-slot="3">
                    <img
                        src="<?= htmlspecialchars($img3Url, ENT_QUOTES, 'UTF-8') ?>"
                        id="sfPreviewImg3"
                        class="sf-preview-img-element"
                        <?php if ($img3Style): ?>style="<?= $img3Style ?>"<?php endif; ?>
                    >
                </div>
            </div>
        </div>
    </div>
</div>