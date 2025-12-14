<?php
// app/pages/list.php
declare(strict_types=1);

require_once __DIR__ . '/../includes/protect.php';
require_once __DIR__ . '/../includes/statuses.php';

$baseUrl = rtrim($config['base_url'] ?? '', '/');

// Käyttöliittymän kieli
$uiLang = $_SESSION['ui_lang'] ?? 'fi';

// Tarkista onko käyttäjä ylläpitäjä
$user = sf_current_user();
$isAdmin = $user && (int)($user['role_id'] ?? 0) === 1;

// --- DB connection ---
try {
    $pdo = new PDO(
        'mysql:host=' . $config['db']['host'] .
        ';dbname=' . $config['db']['name'] .
        ';charset=' . $config['db']['charset'],
        $config['db']['user'],
        $config['db']['pass'],
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (Throwable $e) {
    http_response_code(500);
    echo '<p>Tietokantavirhe.</p>';
    exit;
}

// Tuetut kielet ja lippukuvat (käytetään 'el' kreikalle)
$supportedLangs = [
    'fi' => ['label' => 'FI', 'icon' => 'finnish-flag.png'],
    'sv' => ['label' => 'SV', 'icon' => 'swedish-flag.png'],
    'en' => ['label' => 'EN', 'icon' => 'english-flag.png'],
    'it' => ['label' => 'IT', 'icon' => 'italian-flag.png'],
    'el' => ['label' => 'EL', 'icon' => 'greece-flag.png'],
];

// Nykyinen käyttäjä
$user = sf_current_user();

// Tarkista onko käyttäjällä kotityömaa ja ohjaa automaattisesti suodatettuun näkymään
$homeWorksiteName = '';
if ($user) {
    $homeWorksiteId = $user['home_worksite_id'] ?? null;
    if ($homeWorksiteId) {
        $stmtWs = $pdo->prepare("SELECT name FROM sf_worksites WHERE id = :id LIMIT 1");
        $stmtWs->execute([':id' => $homeWorksiteId]);
        $wsRow = $stmtWs->fetch();
        if ($wsRow && ! empty($wsRow['name'])) {
            $homeWorksiteName = $wsRow['name'];
        }
    }
}

// Filters (from URL)
$type     = $_GET['type']      ??  '';
$state    = $_GET['state']     ?? '';
$site     = $_GET['site']      ?? '';
$q        = trim((string)($_GET['q']    ?? ''));
$from     = $_GET['date_from'] ?? '';
$to       = $_GET['date_to']   ??  '';

// Tarkista onko käyttäjällä mitään suodattimia URL:ssa
$hasAnyFilter = isset($_GET['type']) || isset($_GET['state']) || isset($_GET['site']) || isset($_GET['q']) || isset($_GET['date_from']) || isset($_GET['date_to']);

// Jos ei ole suodattimia URL:ssa ja käyttäjällä on kotityömaa, käytä sitä automaattisesti
if (! $hasAnyFilter && $homeWorksiteName !== '') {
    $site = $homeWorksiteName;
}

// Dropdown näyttää valitun työmaan (joko URL:sta tai automaattisesti asetetun)
$autoSite = $site;
// --- Työmaat dropdownia varten (kaikki aktiiviset työmaat) ---
$sites = $pdo->query("SELECT name FROM sf_worksites WHERE is_active = 1 ORDER BY name ASC")
             ->fetchAll(PDO::FETCH_COLUMN);

// --- Build SQL ---
$where  = [];
$params = [];

if ($type !== '') {
    $where[]         = "f.type = :type";
    $params[':type'] = $type;
}

if ($state !== '') {
    $where[]          = "f.state = :state";
    $params[':state'] = $state;
}

if ($site !== '') {
    $where[]         = "f.site = :site";
    $params[':site'] = $site;
}

if ($q !== '') {
    $where[]      = "(f.title LIKE :q 
                  OR f.title_short LIKE :q 
                  OR f.summary LIKE :q 
                  OR f.description LIKE :q)";
    $params[':q'] = "%$q%";
}

if ($from !== '') {
    $where[]         = "f.occurred_at >= :from";
    $params[':from'] = "$from 00:00:00";
}

if ($to !== '') {
    $where[]       = "f.occurred_at <= :to";
    $params[':to'] = "$to 23:59:59";
}

$sql = "SELECT f.*,
        DATE_FORMAT(f.occurred_at, '%d.%m.%Y %H:%i') AS occurredFmt,
        DATE_FORMAT(f.updated_at, '%d.%m.%Y %H:%i') AS updatedFmt,
        (SELECT COUNT(*) 
         FROM safetyflash_logs sl 
         WHERE sl.flash_id = f.id 
           AND sl.event_type = 'comment_added'
           AND sl.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ) AS new_comment_count
        FROM sf_flashes f";

if ($where) {
    $sql .= " WHERE " . implode(" AND ", $where);
}

$sql .= " ORDER BY f.created_at DESC LIMIT 1000";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$fetched = $stmt->fetchAll();

$groups = [];
foreach ($fetched as $r) {
    $groupId = !empty($r['translation_group_id']) ? (int)$r['translation_group_id'] : (int)$r['id'];
    $groups[$groupId][] = $r;
}

$statePriority = [
    'published'      => 100,
    'to_comms'       => 90,
    'request_info'   => 80,
    'reviewed'       => 70,
    'pending_review' => 60,
    'draft'          => 50,
];
$defaultPriority = 10;

$rows = [];
foreach ($groups as $gid => $items) {
    usort($items, function($a, $b) use ($statePriority, $defaultPriority) {
        $pa = $statePriority[$a['state']] ?? $defaultPriority;
        $pb = $statePriority[$b['state']] ?? $defaultPriority;
        if ($pa !== $pb) return $pb <=> $pa;
        return strtotime($b['created_at'] ?? 0) <=> strtotime($a['created_at'] ?? 0);
    });
    $rows[] = $items[0];
}

// --- NÄKYVYYSLOGIIKKA: roolien mukaan ---

$currentUserId = $user ? (int)$user['id'] : 0;
$roleId        = $user ? (int)$user['role_id'] : 0;

// 1 = Pääkäyttäjä, 2 = Kirjoittaja, 3 = Turvatiimi, 4 = Viestintä
$ROLE_ADMIN  = 1;
$ROLE_WRITER = 2;
$ROLE_SAFETY = 3;
$ROLE_COMMS  = 4;

$visibleRows = [];

foreach ($rows as $r) {
    $stateVal  = $r['state'];
    $createdBy = (int)($r['created_by'] ?? 0);

    $isOwner   = $currentUserId > 0 && $createdBy === $currentUserId;
    $isSafety  = $roleId === $ROLE_SAFETY;
    $isComms   = $roleId === $ROLE_COMMS;
    $isAdmin   = $roleId === $ROLE_ADMIN;

    $visible = false;

    if ($isAdmin) {
        $visible = true;
    } else {
        switch ($stateVal) {
            case 'draft':
                $visible = $isOwner;
                break;

            case 'pending_review':
            case 'request_info':
                $visible = $isOwner || $isSafety;
                break;

            case 'to_comms':
                $visible = $isOwner || $isSafety || $isComms;
                break;

            case 'published':
                $visible = true;
                break;

            default:
                $visible = $isOwner || $isSafety;
                break;
        }
    }

    if ($visible) {
        $visibleRows[] = $r;
    }
}

usort($visibleRows, function($a, $b) {
    return strtotime($b['created_at'] ?? 0) <=> strtotime($a['created_at'] ?? 0);
});

$rows = $visibleRows;

// Helpers
function typeBadgeClass($t) {
    return [
        'red'    => 'badge-red',
        'yellow' => 'badge-yellow',
        'green'  => 'badge-green',
    ][$t] ?? 'badge-default';
}

function sf_get_flash_translations_list(PDO $pdo, int $groupId): array {
    $stmt = $pdo->prepare("SELECT id, lang FROM sf_flashes WHERE translation_group_id = :gid OR id = :gid");
    $stmt->execute([':gid' => $groupId]);
    $rows = $stmt->fetchAll();
    $map = [];
    foreach ($rows as $r) {
        $map[$r['lang']] = (int)$r['id'];
    }
    return $map;
}

$currentUiLang = $uiLang ?? 'fi';
?>

<div class="sf-list-page">

    <h1 class="page-title">
        <?= htmlspecialchars(sf_term('list_heading', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
    </h1>

<!-- FILTER BAR -->
<form method="get" class="filters">
    <input type="hidden" name="page" value="list">

    <!-- Mobiili: Toggle-nappi suodattimille -->
    <button type="button" class="filters-toggle" id="filtersToggle">
        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <!-- polygon korjattu -->
            <polygon points="22,3 2,3 10,12.46 10,19 14,21 14,12.46"/>
        </svg>

        <span>Suodattimet</span>

        <svg class="toggle-arrow" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <polyline points="6,9 12,15 18,9"/>
        </svg>
    </button>

    <div class="filters-grid" id="filtersGrid" role="search" aria-label="Suodattimet">
            <div class="filter-item">
                <label for="f-type">
                    <?= htmlspecialchars(sf_term('filter_type', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </label>
                <select id="f-type" name="type">
                    <option value="">
                        <?= htmlspecialchars(sf_term('filter_all', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <option value="red" <?= $type === 'red' ? 'selected' : '' ?>>
                        <?= htmlspecialchars(sf_term('first_release', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <option value="yellow" <?= $type === 'yellow' ? 'selected' : '' ?>>
                        <?= htmlspecialchars(sf_term('dangerous_situation', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <option value="green" <?= $type === 'green' ? 'selected' : '' ?>>
                        <?= htmlspecialchars(sf_term('investigation_report', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                </select>
            </div>

            <div class="filter-item">
                <label for="f-state">
                    <?= htmlspecialchars(sf_term('filter_state', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </label>
                <select id="f-state" name="state">
                    <option value="">
                        <?= htmlspecialchars(sf_term('filter_all', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <option value="draft" <?= $state==='draft' ? 'selected' : '' ?>>
                        <?= htmlspecialchars(sf_status_label('draft', $currentUiLang)) ?>
                    </option>
                    <option value="pending_review" <?= $state==='pending_review' ? 'selected' : '' ?>>
                        <?= htmlspecialchars(sf_status_label('pending_review', $currentUiLang)) ?>
                    </option>

                    <option value="to_comms" <?= $state==='to_comms' ? 'selected' : '' ?>>
                        <?= htmlspecialchars(sf_status_label('to_comms', $currentUiLang)) ?>
                    </option>
                    <option value="published" <?= $state==='published' ? 'selected' : '' ?>>
                        <?= htmlspecialchars(sf_status_label('published', $currentUiLang)) ?>
                    </option>
                </select>
            </div>

            <div class="filter-item">
                <label for="f-site">
                    <?= htmlspecialchars(sf_term('filter_site', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </label>
                <select id="f-site" name="site">
                    <option value="">
                        <?= htmlspecialchars(sf_term('filter_all', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </option>
                    <?php foreach ($sites as $s): ?>
                        <option value="<?= htmlspecialchars($s) ?>"
                            <?= ($site !== '' ? $site : $autoSite) === $s ? 'selected' : '' ?>>
                            <?= htmlspecialchars($s) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="filter-item filter-search">
                <label for="f-q">
                    <?= htmlspecialchars(sf_term('filter_search_label', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </label>
                <div class="search-row">
                    <input
                        id="f-q"
                        type="text"
                        name="q"
                        placeholder="<?= htmlspecialchars(sf_term('filter_search_placeholder', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>"
                        value="<?= htmlspecialchars($q) ?>"
                    >
                    <button class="btn-icon" type="submit">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>
                        </svg>
                    </button>
                </div>
            </div>

            <div class="filter-item">
                <label for="f-from">
                    <?= htmlspecialchars(sf_term('filter_date_from', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </label>
                <input id="f-from" type="date" name="date_from" value="<?= htmlspecialchars($from) ?>">
            </div>

            <div class="filter-item">
                <label for="f-to">
                    <?= htmlspecialchars(sf_term('filter_date_to', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </label>
                <input id="f-to" type="date" name="date_to" value="<?= htmlspecialchars($to) ?>">
            </div>

            <div class="filter-actions">
                <button class="btn-primary" type="submit">
                    <?= htmlspecialchars(sf_term('filter_apply', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <a class="btn-secondary" href="<?= $baseUrl ?>/index.php?page=list">
                    <?= htmlspecialchars(sf_term('filter_clear', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </a>
            </div>
        </div>
    </form>

    <!-- LIST -->
<!-- ADMIN: MONIVALINTAPOISTO -->
<?php if ($isAdmin): ?>
<div class="sf-bulk-actions" id="sfBulkActions">
    <div class="sf-bulk-bar">
        <label class="sf-bulk-select-all">
            <input type="checkbox" id="sfSelectAll">
            <span>Valitse kaikki</span>
        </label>

        <span class="sf-bulk-count" id="sfBulkCount">0 valittu</span>

<button type="button" class="sf-btn sf-btn-danger" id="sfBulkDelete" disabled>
    <img src="<?= $baseUrl ?>/assets/img/icons/delete_icon.svg" alt="" class="btn-icon-img">
    Poista valitut
</button>
    </div>
</div>
<?php endif; ?>

<!-- LIST -->
<div class="card-list">        <?php if (empty($rows)): ?>
    <div class="no-results-box">
        <div class="no-results-icon-wrap">
            <svg class="no-results-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
                <circle cx="11" cy="11" r="8"/>
                <line x1="21" y1="21" x2="16.65" y2="16.65"/>
                <line x1="8" y1="11" x2="14" y2="11"/>
            </svg>
        </div>

        <p class="no-results-text">
            <?= htmlspecialchars(sf_term('no_results', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </p>

        <p class="no-results-hint">
            <?= htmlspecialchars(sf_term('no_results_hint', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </p>

        <a href="<?= $baseUrl ?>/index.php?page=form" class="no-results-cta">
            <img src="<?= $baseUrl ?>/assets/img/icons/add_new_icon.png"
                 alt=""
                 class="no-results-cta-icon"
                 aria-hidden="true">
            <span><?= htmlspecialchars(sf_term('nav_new_safetyflash', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></span>
        </a>
    </div>
<?php endif; ?>

<?php foreach ($rows as $r):
    $badgeClass = typeBadgeClass($r['type']);

    $typeKeyMap = [
        'red'    => 'first_release',
        'yellow' => 'dangerous_situation',
        'green'  => 'investigation_report',
    ];
    $typeKey   = $typeKeyMap[$r['type']] ?? null;
    $typeLabel = $typeKey ? sf_term($typeKey, $currentUiLang) : 'Safetyflash';

    $stateText = sf_status_label($r['state'], $currentUiLang);
    $stateClassMap = [
        'draft'          => 'status-draft',
        'pending_review' => 'status-pending',
        'request_info'   => 'status-request-info',
        'reviewed'       => 'status-reviewed',
        'to_comms'       => 'status-to-comms',
        'published'      => 'status-published',
    ];
    $stateClass = $stateClassMap[$r['state']] ?? 'status-other';

    $thumb = $r['preview_filename']
        ? "$baseUrl/uploads/previews/" . $r['preview_filename']
        : "$baseUrl/assets/img/camera-placeholder.png";

    $title = trim((string)($r['title'] ?? ''));
    if ($title === '') {
        $title = trim((string)($r['title_short'] ?? ''));
    }
    if ($title === '') {
        $title = trim((string)($r['summary'] ?? '')) ?: "(Ei otsikkoa)";
    }

    $siteText    = $r['site'] . (!empty($r['site_detail']) ? " – " . $r['site_detail'] : "");
    $groupId     = !empty($r['translation_group_id']) ? (int)$r['translation_group_id'] : (int)$r['id'];
    $translations = sf_get_flash_translations_list($pdo, $groupId);
    $baseLang    = $r['lang'] ?: 'fi';
?>
<div class="card type-<?= htmlspecialchars($r['type'] ?? '') ?>" data-flash-id="<?= (int)$r['id'] ?>">

    <?php if ($isAdmin): ?>
        <div class="card-checkbox">
            <input type="checkbox" class="sf-flash-checkbox" value="<?= (int)$r['id'] ?>">
        </div>
    <?php endif; ?>

<a href="<?= $baseUrl ?>/index.php?page=view&id=<?= (int)$r['id'] ?>" class="card-thumb">
    <img src="<?= htmlspecialchars($thumb) ?>" alt="thumb">

    <?php if (!empty($r['new_comment_count']) && (int)$r['new_comment_count'] > 0): ?>
        <span class="comment-badge" title="<?= (int)$r['new_comment_count'] ?> uutta kommenttia">
            <svg class="comment-badge-icon" viewBox="0 0 100 100" fill="currentColor">
                <path d="M100 10.495v67.2c0 2.212-1.793 4.005-4.005 4.005H68.53c-1.063 0-2.082.422-2.833 1.174L51.412 97.167c-1.564 1.565-4.1 1.565-5.665 0L31.453 82.874c-.751-.751-1.77-1.173-2.832-1.173H4.005C1.793 81.701 0 79.908 0 77.696V10.495C0 8.283 1.793 6.49 4.005 6.49h91.99C98.207 6.49 100 8.283 100 10.495Z"/>
            </svg>

            <span class="comment-badge-count"><?= (int)$r['new_comment_count'] ?></span>
        </span>
    <?php endif; ?>
</a>

    <div class="card-mid">
        <div class="card-top">
            <div class="left">
                <span class="badge <?= htmlspecialchars($badgeClass) ?>">
                    <?= htmlspecialchars($typeLabel) ?>
                </span>
                <span class="status <?= htmlspecialchars($stateClass) ?>">
                    <?= htmlspecialchars($stateText) ?>
                </span>
            </div>
        </div>

        <div class="card-title"><?= htmlspecialchars($title) ?></div>
        <div class="card-site"><?= htmlspecialchars($siteText) ?></div>

        <div class="card-meta">
            <span class="card-date">
                <svg class="meta-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor"
                     stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <rect x="3" y="4" width="18" height="18" rx="2" ry="2"></rect>
                    <line x1="16" y1="2" x2="16" y2="6"></line>
                    <line x1="8" y1="2" x2="8" y2="6"></line>
                    <line x1="3" y1="10" x2="21" y2="10"></line>
                </svg>
                <?= htmlspecialchars($r['occurredFmt'] ?? '') ?>
            </span>

            <span class="card-updated">
                <?= htmlspecialchars(sf_term('card_modified', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>:
                <?= htmlspecialchars($r['updatedFmt'] ?? '') ?>
            </span>
        </div>
    </div>

    <div class="card-actions">
        <a href="<?= $baseUrl ?>/index.php?page=view&id=<?= (int)$r['id'] ?>" class="open-btn">
            <?= htmlspecialchars(sf_term('card_open', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </a>

        <div class="card-lang-actions">
            <?php if (isset($supportedLangs[$baseLang])): ?>
               <a href="<?= $baseUrl ?>/index.php?page=view&id=<?= (int)$r['id'] ?>" class="lang-flag-link">
                    <img class="list-lang-flag"
                         src="<?= $baseUrl ?>/assets/img/<?= $supportedLangs[$baseLang]['icon'] ?>"
                         alt="<?= htmlspecialchars($supportedLangs[$baseLang]['label']) ?>">
                </a>
            <?php endif; ?>

            <?php foreach ($supportedLangs as $langCode => $langData): ?>
                <?php if ($langCode !== $baseLang && isset($translations[$langCode])): ?>
                    <a href="<?= $baseUrl ?>/index.php?page=view&id=<?= (int)$translations[$langCode] ?>" class="lang-flag-link">
                        <img class="list-lang-flag"
                             src="<?= $baseUrl ?>/assets/img/<?= $langData['icon'] ?>"
                             alt="<?= htmlspecialchars($langData['label']) ?>">
                    </a>
                <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</div>
<?php endforeach; ?>    </div>
<?php if ($isAdmin): ?>
<!-- POISTOVAHVISTUS-MODAALI -->
<div class="sf-modal hidden" id="modalBulkDelete" role="dialog" aria-modal="true">
    <div class="sf-modal-content">
        <h2>Vahvista poisto</h2>
        <p id="sfBulkDeleteText">Haluatko varmasti poistaa valitut tiedotteet? Tämä poistaa myös kaikki liitetyt kuvat pysyvästi.</p>
        <div class="sf-bulk-delete-list" id="sfBulkDeleteList"></div>
        <div class="sf-modal-actions">
            <button type="button" class="sf-btn sf-btn-secondary" data-modal-close="modalBulkDelete">Peruuta</button>
            <button type="button" class="sf-btn sf-btn-danger" id="sfConfirmBulkDelete">Poista pysyvästi</button>
        </div>
    </div>
</div>

<script>
(function() {
    const selectAll = document.getElementById('sfSelectAll');
    const bulkCount = document.getElementById('sfBulkCount');
    const bulkDeleteBtn = document.getElementById('sfBulkDelete');
    const checkboxes = () => document.querySelectorAll('.sf-flash-checkbox');
    const modal = document.getElementById('modalBulkDelete');
    const confirmBtn = document.getElementById('sfConfirmBulkDelete');
    const deleteList = document.getElementById('sfBulkDeleteList');

    function updateCount() {
        const checked = document.querySelectorAll('.sf-flash-checkbox:checked');
        const count = checked.length;
        bulkCount.textContent = count + ' valittu';
        bulkDeleteBtn.disabled = count === 0;
    }

    if (selectAll) {
        selectAll.addEventListener('change', function() {
            checkboxes().forEach(cb => cb.checked = this.checked);
            updateCount();
        });
    }

    document.addEventListener('change', function(e) {
        if (e.target.classList.contains('sf-flash-checkbox')) {
            updateCount();
            const all = checkboxes();
            const checkedCount = document.querySelectorAll('.sf-flash-checkbox:checked').length;
            if (selectAll) selectAll.checked = checkedCount === all.length && all.length > 0;
        }
    });

    if (bulkDeleteBtn) {
        bulkDeleteBtn.addEventListener('click', function() {
            const checked = document.querySelectorAll('.sf-flash-checkbox:checked');
            if (checked.length === 0) return;

            let html = '<ul>';
            checked.forEach(cb => {
                const card = cb.closest('.card');
                const title = card?.querySelector('.card-title')?.textContent || 'ID: ' + cb.value;
                html += '<li>' + title + '</li>';
            });
            html += '</ul>';
            deleteList.innerHTML = html;

            modal.classList.remove('hidden');
        });
    }

    document.querySelectorAll('[data-modal-close="modalBulkDelete"]').forEach(btn => {
        btn.addEventListener('click', () => modal.classList.add('hidden'));
    });

    if (confirmBtn) {
        confirmBtn.addEventListener('click', async function() {
            const checked = document.querySelectorAll('.sf-flash-checkbox:checked');
            const ids = Array.from(checked).map(cb => parseInt(cb.value));

            if (ids.length === 0) return;

confirmBtn.disabled = true;
confirmBtn.innerHTML = '<span class="btn-spinner"></span> Poistetaan... ';

            try {
                const response = await fetch('<?= $baseUrl ?>/app/actions/bulk_delete.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
body: JSON.stringify({ ids: ids, csrf_token: '<?= sf_csrf_token() ?>' })
                });

                const result = await response.json();

if (result.success) {
    // Uudelleenohjaa samalle sivulle notice-parametrilla
    const url = new URL(window.location.href);
    url.searchParams.set('notice', 'bulk_deleted');
    url.searchParams.set('count', result.deleted);
    window.location.href = url.toString();
} else {
    alert('Virhe: ' + (result.error || 'Tuntematon virhe'));
}
            } catch (err) {
                console.error('Bulk delete error:', err);
                alert('Virhe poistettaessa.');
            } finally {
                confirmBtn.disabled = false;
                confirmBtn.textContent = 'Poista pysyvästi';
            }
        });
    }
})();
</script>
<?php endif; ?>

<!-- Suodattimien toggle (kaikille käyttäjille) -->
<script>
(function() {
    const toggle = document.getElementById('filtersToggle');
    const grid = document.getElementById('filtersGrid');

    if (toggle && grid) {
        toggle.addEventListener('click', function() {
            toggle.classList.toggle('open');
            grid.classList.toggle('open');
        });

        // Jos on aktiivisia suodattimia, avaa automaattisesti
        const hasFilters = <?= json_encode(
            $type !== '' ||
            $state !== '' ||
            ($site !== '' && $site !== $homeWorksiteName) ||
            $q !== '' ||
            $from !== '' ||
            $to !== ''
        ) ?>;

        if (hasFilters) {
            toggle.classList.add('open');
            grid.classList.add('open');
        }
    }
})();
</script>

</div> <!-- .sf-list-page -->
