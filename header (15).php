<?php
require_once __DIR__ . '/../../config.php';
require_once __DIR__ . '/auth.php';

$base = rtrim($config['base_url'], '/');
$currentPage = $_GET['page'] ?? 'list';

$allowedPages = ['list', 'form', 'form_language', 'view', 'users', 'settings', 'profile'];
if (!in_array($currentPage, $allowedPages, true)) {
    $currentPage = 'list';
}

// nykyinen käyttäjä & rooli
$user    = sf_current_user();
$isAdmin = $user && (int)$user['role_id'] === 1;

// UI-kieli (olettaen että index tai auth asettaa tämän)
$uiLang = $_SESSION['ui_lang'] ?? 'fi';
$availableLangs = ['fi' => 'FI', 'en' => 'EN'];

// --- Yleinen notifikaatiologiikka kaikille sivuille ---
$notice = $_GET['notice'] ?? '';

$noticeData = [
    'logged_in'         => ['msg_key' => 'notice_logged_in',         'type' => 'success'],
    'sent_review'       => ['msg_key' => 'notice_sent_review',       'type' => 'success'],
    'saved_draft'       => ['msg_key' => 'notice_saved_draft',       'type' => 'info'],
    'sent'              => ['msg_key' => 'notice_sent',              'type' => 'info'],
    'saved'             => ['msg_key' => 'notice_saved',             'type' => 'info'],
    'deleted'           => ['msg_key' => 'notice_deleted',           'type' => 'danger'],
    'published'         => ['msg_key' => 'notice_published',         'type' => 'success'],
    'to_comms'          => ['msg_key' => 'notice_to_comms',          'type' => 'info'],
    'comms_sent'        => ['msg_key' => 'notice_to_comms',          'type' => 'info'],
    'info_requested'    => ['msg_key' => 'notice_info_requested',    'type' => 'info'],
    'translation_saved' => ['msg_key' => 'notice_translation_saved', 'type' => 'success'],
    'user_created'      => ['msg_key' => 'notice_user_created',      'type' => 'success'],
    'user_updated'      => ['msg_key' => 'notice_user_updated',      'type' => 'info'],
    'user_deleted'      => ['msg_key' => 'notice_user_deleted',      'type' => 'danger'],
    'user_pass_reset'   => ['msg_key' => 'notice_user_pass_reset',   'type' => 'info'],
    'bulk_deleted'      => ['msg_key' => 'notice_bulk_deleted',      'type' => 'success'],
'worksite_added' => ['type' => 'success', 'msg_key' => 'worksite_added'],
'worksite_enabled' => ['type' => 'success', 'msg_key' => 'worksite_enabled'],
'worksite_disabled' => ['type' => 'info', 'msg_key' => 'worksite_disabled'],
];

$noticeConfig = $noticeData[$notice] ?? null;
$noticeType   = $noticeConfig['type'] ?? '';

// Erikoiskäsittely bulk_deleted – näytä poistettujen määrä
if ($notice === 'bulk_deleted' && isset($_GET['count'])) {
    $count = (int)$_GET['count'];
    $noticeText = str_replace('{count}', (string)$count, sf_term('notice_bulk_deleted', $uiLang));
} else {
    $noticeText = $noticeConfig ? sf_term($noticeConfig['msg_key'], $uiLang) : '';
}

// Onko notifikaatioparametreja URL:ssa?
$hasNoticeParams = isset($_GET['notice']) || isset($_GET['count']) || isset($_GET['deleted']) || isset($_GET['saved']) || isset($_GET['error']) || isset($_GET['success']);

// 🔒 Vaadi kirjautuminen ennen kuin mitään HTML:ää tulostetaan
sf_require_login();
?>

<?php if ($noticeText): ?>
<div class="sf-toast sf-toast-<?= htmlspecialchars($noticeType) ?>" id="sfToast">
    <div class="sf-toast-icon">
        <?php if ($noticeType === 'success'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3">
                <polyline points="20 6 9 17 4 12"></polyline>
            </svg>

        <?php elseif ($noticeType === 'danger'): ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="15" y1="9" x2="9" y2="15"></line>
                <line x1="9" y1="9" x2="15" y2="15"></line>
            </svg>

        <?php else: ?>
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                <circle cx="12" cy="12" r="10"></circle>
                <line x1="12" y1="16" x2="12" y2="12"></line>
                <line x1="12" y1="8" x2="12.01" y2="8"></line>
            </svg>
        <?php endif; ?>
    </div>

    <span class="sf-toast-text"><?= htmlspecialchars($noticeText) ?></span>

    <button class="sf-toast-close" type="button"
            onclick="document.getElementById('sfToast').remove();">
        ×
    </button>
</div>

<script>
    setTimeout(function () {
        const toast = document.getElementById('sfToast');
        if (toast) {
            toast.classList.add('sf-toast-hide');
            setTimeout(() => toast.remove(), 300);
        }
    }, 4000);
</script>
<?php endif; ?>

<?php if ($hasNoticeParams): ?>
<!-- Poista notifikaatioparametrit URL:sta -->
<script>
(function() {
    var params = ['notice', 'count', 'deleted', 'saved', 'created', 'updated', 'error', 'success', 'reset', 'msg'];
    var url = new URL(window.location.href);
    var changed = false;

    for (var i = 0; i < params.length; i++) {
        if (url.searchParams.has(params[i])) {
            url.searchParams.delete(params[i]);
            changed = true;
        }
    }

    if (changed) {
        history.replaceState(null, '', url.pathname + url.search);
    }
})();
</script>
<?php endif; ?>

<div class="sf-nav">
    <div class="sf-nav-inner">
        <!-- Logo vasemmalle -->
        <div class="sf-nav-left">
            <a href="<?= htmlspecialchars($base) ?>/index.php?page=list" class="sf-brand-link">
                <img
                  src="<?= htmlspecialchars($base) ?>/assets/img/tapojarvi_logo.png"
                  alt="Tapojärvi Logo"
                  class="tapojarvi-logo-img"
                >
            </a>
        </div>

        <!-- Navigaatiolinkit (keskelle) -->
        <div class="sf-nav-center">
            <button class="hamburger-menu" type="button" aria-label="Menu" aria-expanded="false">
                <span></span>
                <span></span>
                <span></span>
            </button>

            <nav class="sf-nav-links-wrapper" aria-label="Päävalikko">
                <div class="sf-nav-links">

                    <!-- Lista -->
                    <a href="<?= htmlspecialchars($base) ?>/index.php?page=list"
                       class="sf-nav-link <?= $currentPage === 'list' ? 'sf-nav-active' : '' ?>">
                        <img src="<?= htmlspecialchars($base) ?>/assets/img/icons/list_icon.png"
                             alt=""
                             class="sf-nav-link-icon"
                             aria-hidden="true">
                        <span><?= htmlspecialchars(sf_term('nav_list', $uiLang), ENT_QUOTES, 'UTF-8') ?></span>
                    </a>

                    <!-- UUSI SAFETYFLASH -->
                    <a href="<?= htmlspecialchars($base) ?>/index.php?page=form"
                       class="sf-nav-cta <?= $currentPage === 'form' ? 'sf-nav-cta-active' : '' ?>">
                        <img src="<?= htmlspecialchars($base) ?>/assets/img/icons/add_new_icon.png"
                             alt=""
                             class="sf-nav-cta-icon-img"
                             aria-hidden="true">
                        <span class="sf-nav-cta-text">
                            <?= htmlspecialchars(sf_term('nav_new_safetyflash', $uiLang), ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </a>

                    <!-- Asetukset (vain admin) -->
                    <?php if ($user && (int)$user['role_id'] === 1): ?>
                        <a href="<?= htmlspecialchars($base) ?>/index.php?page=settings"
                           class="sf-nav-link <?= $currentPage === 'settings' ? 'sf-nav-active' : '' ?>">
                            <span><?= htmlspecialchars(sf_term('settings_heading', $uiLang) ?? 'Asetukset', ENT_QUOTES, 'UTF-8') ?></span>
                        </a>
                    <?php endif; ?>

                </div>
            </nav>
        </div>

        <!-- Kieli + käyttäjä + logout oikealle -->
        <div class="sf-nav-right">
            <form
              method="post"
              action="<?= htmlspecialchars($base) ?>/app/actions/set_language.php"
              class="sf-lang-switcher"
            >
                <?php 
                $langFlags = [
                    'fi' => 'finnish-flag.png',
                    'en' => 'english-flag.png',
                    'sv' => 'swedish-flag.png',
                ];
                foreach ($availableLangs as $code => $label): 
                    $flagFile = $langFlags[$code] ?? 'finnish-flag.png';
                ?>
                    <button
                      type="submit"
                      name="lang"
                      value="<?= htmlspecialchars($code) ?>"
                      class="sf-lang-flag-btn <?= $uiLang === $code ? 'active' : '' ?>"
                      aria-label="<?= htmlspecialchars($label) ?>"
                      aria-pressed="<?= $uiLang === $code ? 'true' : 'false' ?>"
                      title="<?= htmlspecialchars($label) ?>"
                    >
                        <img 
                          src="<?= htmlspecialchars($base) ?>/assets/img/<?= $flagFile ?>" 
                          alt="<?= htmlspecialchars($label) ?>"
                          class="sf-lang-flag-img"
                        >
                    </button>
                <?php endforeach; ?>
            </form>

            <?php if ($user): ?>
                <a href="<?= htmlspecialchars($base) ?>/index.php?page=profile"
                   class="sf-user-info <?= $currentPage === 'profile' ? 'sf-user-active' : '' ?>"
                   title="<?= htmlspecialchars($user['email'] ?? '') ?>">
                    
                    <span class="sf-user-name">
                        <?= htmlspecialchars(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')) ?>
                    </span>

                    <svg class="sf-user-icon" width="16" height="16" viewBox="0 0 24 24" fill="none"
                         stroke="currentColor" stroke-width="2">
                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/>
                        <circle cx="12" cy="7" r="4"/>
                    </svg>
                </a>

                <!-- Uloskirjautuminen -->
                <a href="<?= htmlspecialchars($base) ?>/app/api/logout.php" class="sf-nav-logout">
                    <img src="<?= htmlspecialchars($base) ?>/assets/img/icons/log_out.svg"
                         alt=""
                         class="logout-icon"
                         aria-hidden="true">
                    <span class="logout-text">
                        <?= htmlspecialchars(sf_term('nav_logout', $uiLang), ENT_QUOTES, 'UTF-8') ?>
                    </span>
                </a>
            <?php endif; ?>
        </div>
    </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", () => {
    const hamburgerMenu = document.querySelector(".hamburger-menu");
    const navLinksWrapper = document.querySelector(".sf-nav-links-wrapper");

    if (hamburgerMenu && navLinksWrapper) {
        hamburgerMenu.addEventListener("click", () => {
            const expanded = hamburgerMenu.getAttribute("aria-expanded") === "true";
            hamburgerMenu.setAttribute("aria-expanded", String(!expanded));
            navLinksWrapper.classList.toggle("sf-nav-visible");
        });
    }
});
</script>