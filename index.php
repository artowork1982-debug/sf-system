<?php
session_start();

require_once __DIR__ . '/config.php';

// Debug-asetukset konfiguraation mukaan
if ($config['debug'] ?? false) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', '0');
    ini_set('display_startup_errors', '0');
    error_reporting(0);
    ini_set('log_errors', '1');
    ini_set('error_log', __DIR__ . '/app/logs/php_errors.log');
}

require_once __DIR__ . '/app/includes/log_app.php';
require_once __DIR__ . '/app/includes/statuses.php';
require_once __DIR__ . '/app/includes/csrf.php';
require_once __DIR__ . '/app/lib/sf_terms.php';

// UI-kieli (FI/EN)
$uiLang = $_SESSION['ui_lang'] ?? 'fi';
$termsConfig = sf_get_terms_config();
if (!in_array($uiLang, $termsConfig['languages'] ?? [], true)) {
    $uiLang = 'fi';
    $_SESSION['ui_lang'] = 'fi';
}

// Mikä sivu halutaan ladata?
$page = $_GET['page'] ?? 'list';

// Sallitut sivut
$allowed = [
    'list'          => '/app/pages/list.php',
    'form'          => '/app/pages/form.php',
    'form_language' => '/app/pages/form_language.php',
    'view'          => '/app/pages/view.php',
    'users'         => '/app/pages/users.php',
    'settings'      => '/app/pages/settings.php',
    'profile'       => '/app/pages/profile.php',
];

if (!isset($allowed[$page])) {
    $page = 'list';
}

$file = $allowed[$page];
$base = rtrim($config['base_url'], '/');
?>
<!DOCTYPE html>
<html lang="<?= htmlspecialchars($uiLang) ?>">
<head>
    <meta charset="UTF-8">

    <!-- ===== MOBIILI META TAGIT ===== -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="format-detection" content="telephone=no">

    <title>Safetyflash</title>

    <!-- Fontti -->
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Open+Sans:wght@300;400;600;700&display=swap">

    <!-- Yleiset CSS -->
    <link rel="stylesheet" href="<?= $base; ?>/assets/css/nav.css?v=4">
    <link rel="stylesheet" href="<?= $base; ?>/assets/css/global.css?v=4">

    <!-- Sivukohtaiset CSS -->
    <?php if ($page === 'list'): ?>
        <link rel="stylesheet" href="<?= $base; ?>/assets/css/list.css?v=4">
    <?php elseif ($page === 'form'): ?>
        <link rel="stylesheet" href="<?= $base; ?>/assets/css/form.css?v=5">
        <link rel="stylesheet" href="<?= $base; ?>/assets/css/render.css?v=4">
        <link rel="stylesheet" href="<?= $base; ?>/assets/css/preview.css?v=5">
        <link rel="stylesheet" href="<?= $base; ?>/assets/css/image-library.css?v=2">
    <?php elseif ($page === 'form_language'): ?>
        <link rel="stylesheet" href="<?= $base; ?>/assets/css/form.css?v=5">
        <link rel="stylesheet" href="<?= $base; ?>/assets/css/render.css?v=4">
        <link rel="stylesheet" href="<?= $base; ?>/assets/css/preview.css?v=5">
    <?php elseif ($page === 'view'): ?>
        <link rel="stylesheet" href="<?= $base; ?>/assets/css/view.css?v=4">
        <link rel="stylesheet" href="<?= $base; ?>/assets/css/render.css?v=4">
    <?php elseif ($page === 'settings'): ?>
        <link rel="stylesheet" href="<?= $base; ?>/assets/css/settings.css?v=3">
    <?php elseif ($page === 'profile'): ?>
        <link rel="stylesheet" href="<?= $base; ?>/assets/css/settings.css?v=3">
    <?php endif; ?>

    <!-- MODALS VIIMEISENÄ - yliajaa sivukohtaiset -->
    <link rel="stylesheet" href="<?= $base; ?>/assets/css/modals.css?v=5">

    <!-- html2canvas -->
    <script src="https://cdn.jsdelivr.net/npm/html2canvas@1.4.1/dist/html2canvas.min.js"></script>

    <!-- ===== PROGRESS BAR + SIVUN FADE (EI AJAX) ===== -->
    <style>
        /* Progress bar (yläreuna) */
        #sfProgress {
            position: fixed;
            top: 0;
            left: 0;
            height: 3px;
            width: 0%;
            background: #FEE000;
            z-index: 999999;
            opacity: 0;
            transform: translateZ(0);
            transition: width 220ms ease, opacity 180ms ease;
        }
        body.sf-loading #sfProgress {
            opacity: 1;
            width: 65%;
        }
        body.sf-loading.sf-loading-long #sfProgress {
            width: 90%;
        }

        /* Fade-out / fade-in */
        .sf-container {
            opacity: 1;
            transition: opacity 200ms ease;
        }
        body.sf-loading .sf-container {
            opacity: 0.35;
        }

        @media (prefers-reduced-motion: reduce) {
            .sf-container { transition: none; }
            #sfProgress { transition: none; }
        }
    </style>

    <!-- JS-konstantit -->
    <script>
        const SF_BASE_URL   = "<?= rtrim($config['base_url'], '/'); ?>";
        const SF_UPLOAD_URL = SF_BASE_URL + "/upload.php";
        const SF_SAVE_URL   = SF_BASE_URL + "/app/api/save_flash.php";
        const SF_IMAGES_URL = SF_BASE_URL + "/uploads/images";
    </script>
</head>

<body
    data-page="<?= htmlspecialchars($page); ?>"
    class="<?= ($page === 'form' || $page === 'form_language') ? 'form-page' : ''; ?>"
>
<!-- Progress bar -->
<div id="sfProgress" aria-hidden="true"></div>

<!-- ===== VAAKAKÄYTÖN VAROITUS ===== -->
<div class="sf-rotate-warning" id="sfRotateWarning">
    <div class="sf-rotate-warning-icon">📱</div>
    <div class="sf-rotate-warning-text">Käännä puhelin pystyasentoon</div>
    <div class="sf-rotate-warning-subtext">Sovellus toimii parhaiten pystyasennossa</div>
</div>

<?php include __DIR__ . '/app/includes/header.php'; ?>

<div class="sf-container" id="sfContainer">
    <?php include __DIR__ . $file; ?>
</div>

<?php if ($page === 'form'): ?>
    <!-- Preview-kortin skaalaus -->
    <script src="<?= $base; ?>/assets/js/previewScaler.js?v=1"></script>
    <!-- Uusi modulaarinen lomakelogiikka -->
    <script type="module" src="<?= $base; ?>/assets/js/form.js?v=7"></script>
    <script src="<?= $base; ?>/assets/js/image-library.js?v=2"></script>

<?php elseif ($page === 'form_language'): ?>
    <script src="<?= $base; ?>/assets/js/form_language.js?v=4"></script>

<?php elseif ($page === 'view'): ?>
    <script src="<?= $base; ?>/assets/js/view.js?v=4"></script>

<?php elseif ($page === 'users'): ?>
    <script src="<?= $base; ?>/assets/js/users.js?v=4"></script>

<?php elseif ($page === 'settings'): ?>
    <script src="<?= $base; ?>/assets/js/users.js?v=4"></script>
    <script src="<?= $base; ?>/assets/js/settings.js?v=2"></script>
<?php endif; ?>

<!-- Globaali modaalien hallinta -->
<script src="<?= $base; ?>/assets/js/modals.js?v=1"></script>

<!-- Mobiilituki -->
<script src="<?= $base; ?>/assets/js/mobile.js?v=1"></script>

<script>
(function () {
    const progress = document.getElementById("sfProgress");

    function startLoading() {
        document.body.classList.add("sf-loading");
        // jos lataus kestää > 600ms, nostetaan palkki lähemmäs loppua
        window.__sfLoadingTimer = window.setTimeout(() => {
            document.body.classList.add("sf-loading-long");
        }, 600);
    }

    function stopLoading() {
        window.clearTimeout(window.__sfLoadingTimer);
        document.body.classList.remove("sf-loading-long");
        if (progress) progress.style.width = "100%";
        // pieni viive, jotta “100%” näkyy
        window.setTimeout(() => {
            document.body.classList.remove("sf-loading");
            if (progress) progress.style.width = "";
        }, 140);
    }

    // Linkkiklikit (vain samaan originin navigaatio)
    document.addEventListener("click", (e) => {
        const a = e.target.closest("a");
        if (!a) return;

        // ohita: uuteen välilehteen, ankkurit, javascript:, tyhjät
        const href = a.getAttribute("href") || "";
        if (!href || href.startsWith("#") || href.startsWith("javascript:")) return;
        if (a.target && a.target !== "_self") return;
        if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

        // Varmista sama origin (käytä URL-parsintaa)
        try {
            const url = new URL(href, window.location.href);
            if (url.origin !== window.location.origin) return;
        } catch (err) {
            return;
        }

        startLoading();
    }, true);

    // Lomakkeen submit
    document.addEventListener("submit", () => {
        startLoading();
    }, true);

    // Kun sivu on valmis
    window.addEventListener("pageshow", () => {
        // pageshow triggaa myös BFCache-paluuissa -> pysyy “freshinä”
        stopLoading();
    });

    window.addEventListener("load", () => {
        stopLoading();
    });

    // Toastin piilotus (kuten sinulla)
    document.addEventListener("DOMContentLoaded", function () {
        const t = document.getElementById('toastNotice');
        if (t) setTimeout(() => t.classList.add('hide'), 4000);
    });
})();
</script>

</body>
</html>