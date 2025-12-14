<?php
// app/pages/settings/tab_image_library.php
declare(strict_types=1);

// Kategoriat
$categories = [
    'body'      => sf_term('library_cat_body', $currentUiLang) ?? 'Hahmokuvat',
    'warning'   => sf_term('library_cat_warning', $currentUiLang) ?? 'Varoitusmerkit',
    'equipment' => sf_term('library_cat_equipment', $currentUiLang) ?? 'Laitteet',
    'template'  => sf_term('library_cat_template', $currentUiLang) ?? 'Pohjat',
];

// Suodatin
$filterCategory = $_GET['cat'] ?? '';

// Hae kuvat
$where  = 'WHERE 1=1';
$params = [];
$types  = '';

if ($filterCategory !== '' && isset($categories[$filterCategory])) {
    $where   .= ' AND category = ? ';
    $params[] = $filterCategory;
    $types   .= 's';
}

$sql = "SELECT il.*, u.email AS uploader_email 
        FROM sf_image_library il
        LEFT JOIN sf_users u ON u.id = il.created_by
        {$where}
        ORDER BY il.category ASC, il.sort_order ASC, il.title ASC";

$stmt = $mysqli->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$images = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);

// Laske per kategoria
$countSql = "SELECT category, COUNT(*) as cnt FROM sf_image_library WHERE is_active = 1 GROUP BY category";
$countRes = $mysqli->query($countSql);
$categoryCounts = [];
while ($row = $countRes->fetch_assoc()) {
    $categoryCounts[$row['category']] = (int) $row['cnt'];
}
$totalCount = array_sum($categoryCounts);
?>


<h2>
    <img src="<?= $baseUrl ?>/assets/img/icons/image.svg" alt="" class="sf-heading-icon" aria-hidden="true">
    <?= htmlspecialchars(
        sf_term('settings_image_library_heading', $currentUiLang) ?? 'Kuvapankin hallinta',
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</h2>

<p class="sf-help-text" style="margin-bottom: 1.5rem;">
    <?= htmlspecialchars(
        sf_term('settings_image_library_help', $currentUiLang) ??
        'Lisää kuvia, joita käyttäjät voivat valita tiedotteisiin. Esimerkiksi hahmokuvia loukkaantumiskohtien merkintään.',
        ENT_QUOTES,
        'UTF-8'
    ) ?>
</p>

<!-- LISÄÄ KUVA -->
<div class="sf-library-upload-section">
    <h3><?= htmlspecialchars(sf_term('library_upload_heading', $currentUiLang) ?? 'Lisää uusi kuva', ENT_QUOTES, 'UTF-8') ?></h3>

    <form method="post"
          action="<?= $baseUrl ?>/app/actions/image_library_save.php"
          enctype="multipart/form-data"
          class="sf-library-upload-form">

        <input type="hidden" name="action" value="add">

        <div class="sf-library-form-grid">
            <div class="sf-field">
                <label for="lib-image"><?= htmlspecialchars(sf_term('library_label_image', $currentUiLang) ?? 'Kuvatiedosto', ENT_QUOTES, 'UTF-8') ?> *</label>
                <input type="file" id="lib-image" name="image" accept="image/*" required class="sf-input">
            </div>

            <div class="sf-field">
                <label for="lib-title"><?= htmlspecialchars(sf_term('library_label_title', $currentUiLang) ?? 'Otsikko', ENT_QUOTES, 'UTF-8') ?> *</label>
                <input type="text" id="lib-title" name="title" required class="sf-input" placeholder="esim. Ihmishahmo edestä">
            </div>

            <div class="sf-field">
                <label for="lib-category"><?= htmlspecialchars(sf_term('library_label_category', $currentUiLang) ?? 'Kategoria', ENT_QUOTES, 'UTF-8') ?> *</label>
                <select id="lib-category" name="category" required class="sf-select">
                    <?php foreach ($categories as $key => $label): ?>
                        <option value="<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>

            <div class="sf-field">
                <label for="lib-description"><?= htmlspecialchars(sf_term('library_label_description', $currentUiLang) ?? 'Kuvaus (valinnainen)', ENT_QUOTES, 'UTF-8') ?></label>
                <input type="text" id="lib-description" name="description" class="sf-input" placeholder="Lyhyt kuvaus käyttötarkoituksesta">
            </div>
        </div>

        <button type="submit" class="sf-btn sf-btn-primary" id="sfLibraryUploadBtn">
            <span class="btn-text">
                <?= htmlspecialchars(sf_term('btn_add', $currentUiLang) ?? 'Lisää', ENT_QUOTES, 'UTF-8') ?>
            </span>
            <span class="btn-spinner hidden"></span>
        </button>
    </form>
</div>

<!-- SUODATIN -->
<div class="sf-library-filters">
    <span class="sf-library-filter-label">Näytä:</span>

    <a href="<?= $baseUrl ?>/index.php?page=settings&tab=image_library"
       class="sf-library-filter-btn <?= $filterCategory === '' ? 'active' : '' ?>">
        Kaikki (<?= $totalCount ?>)
    </a>

    <?php foreach ($categories as $key => $label): ?>
        <a href="<?= $baseUrl ?>/index.php?page=settings&tab=image_library&cat=<?= htmlspecialchars($key, ENT_QUOTES, 'UTF-8') ?>"
           class="sf-library-filter-btn <?= $filterCategory === $key ? 'active' : '' ?>">
            <?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?> (<?= $categoryCounts[$key] ?? 0 ?>)
        </a>
    <?php endforeach; ?>
</div>

<!-- KUVAGALLERIA -->
<?php if (empty($images)): ?>
    <div class="sf-library-empty">
        <p>Ei kuvia<?= $filterCategory ? ' tässä kategoriassa' : '' ?>.</p>
    </div>
<?php else: ?>
    <div class="sf-library-admin-grid">
        <?php foreach ($images as $img): ?>
            <div class="sf-library-admin-item <?= $img['is_active'] ? '' : 'inactive' ?>"
                 data-id="<?= (int) $img['id'] ?>">

                <div class="sf-library-admin-thumb">
                    <img src="<?= $baseUrl ?>/uploads/library/<?= htmlspecialchars($img['filename'], ENT_QUOTES, 'UTF-8') ?>"
                         alt="<?= htmlspecialchars($img['title'], ENT_QUOTES, 'UTF-8') ?>">

                    <?php if (!$img['is_active']): ?>
                        <span class="sf-library-inactive-badge">Piilotettu</span>
                    <?php endif; ?>
                </div>

                <div class="sf-library-admin-info">
                    <div class="sf-library-admin-title"><?= htmlspecialchars($img['title'], ENT_QUOTES, 'UTF-8') ?></div>

                    <div class="sf-library-admin-meta">
                        <span class="sf-library-category-badge category-<?= htmlspecialchars($img['category'], ENT_QUOTES, 'UTF-8') ?>">
                            <?= htmlspecialchars($categories[$img['category']] ?? $img['category'], ENT_QUOTES, 'UTF-8') ?>
                        </span>
                    </div>

                    <?php if (!empty($img['description'])): ?>
                        <div class="sf-library-admin-desc"><?= htmlspecialchars($img['description'], ENT_QUOTES, 'UTF-8') ?></div>
                    <?php endif; ?>
                </div>

                <div class="sf-library-admin-actions">
                    <form method="post"
                          action="<?= $baseUrl ?>/app/actions/image_library_save.php"
                          style="display:inline;">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="id" value="<?= (int) $img['id'] ?>">
                        <button type="submit" class="sf-btn-small <?= $img['is_active'] ? '' : 'sf-btn-success' ?>">
                            <?= $img['is_active'] ? '👁️ Piilota' : '👁️ Näytä' ?>
                        </button>
                    </form>

                    <button type="button"
                            class="sf-btn-small sf-btn-danger sf-delete-library-image"
                            data-id="<?= (int) $img['id'] ?>"
                            data-title="<?= htmlspecialchars($img['title'], ENT_QUOTES, 'UTF-8') ?>">
                        🗑️
                    </button>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- POISTO-MODAALI -->
<div class="sf-modal hidden" id="sfLibraryDeleteModal">
    <div class="sf-modal-content">
        <h2>Poista kuva</h2>
        <p>Haluatko varmasti poistaa kuvan <strong id="sfLibraryDeleteTitle"></strong>?</p>
        <p class="sf-help-text">Kuva poistetaan pysyvästi kuvapankista.</p>

        <form method="post" action="<?= $baseUrl ?>/app/actions/image_library_save.php">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" id="sfLibraryDeleteId">

            <div class="sf-modal-actions">
                <button type="button"
                        class="sf-btn sf-btn-secondary"
                        onclick="document.getElementById('sfLibraryDeleteModal').classList.add('hidden')">
                    Peruuta
                </button>
                <button type="submit" class="sf-btn sf-btn-danger">
                    Poista pysyvästi
                </button>
            </div>
        </form>
    </div>
</div>

<script>
// Poisto-modaali
document.querySelectorAll('.sf-delete-library-image').forEach(btn => {
    btn.addEventListener('click', function () {
        document.getElementById('sfLibraryDeleteId').value = this.dataset.id;
        document.getElementById('sfLibraryDeleteTitle').textContent = this.dataset.title;
        document.getElementById('sfLibraryDeleteModal').classList.remove('hidden');
    });
});

// Upload spinner
const uploadForm = document.querySelector('.sf-library-upload-form');
const uploadBtn  = document.getElementById('sfLibraryUploadBtn');

if (uploadForm && uploadBtn) {
    uploadForm.addEventListener('submit', function () {
        const btnText    = uploadBtn.querySelector('.btn-text');
        const btnSpinner = uploadBtn.querySelector('.btn-spinner');

        if (btnText && btnSpinner) {
            btnText.textContent = 'Ladataan...';
            btnSpinner.classList.remove('hidden');
            uploadBtn.disabled = true;
        }
    });
}
</script>