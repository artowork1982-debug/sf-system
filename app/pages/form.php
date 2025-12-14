<?php
// app/pages/form.php
// THE COMPLETE, UNTRUNCATED, AND CORRECTED FILE
declare(strict_types=1);

require_once __DIR__ . '/../includes/protect.php';
require_once __DIR__ . '/../includes/statuses.php';

$base = rtrim($config['base_url'] ?? '/', '/');

// --- Työmaat kannasta (sf_worksites) ---
$worksites = [];

try {
    $worksites = Database::fetchAll(
        "SELECT id, name FROM sf_worksites WHERE is_active = 1 ORDER BY name ASC"
    );
} catch (Throwable $e) {
    error_log('form.php worksites error: ' .  $e->getMessage());
    $worksites = [];
}

// --- Tutkintatiedotteen pohjana olevat julkaistut ensitiedotteet / vaaratilanteet ---
$relatedOptions = [];

try {
    $relatedOptions = Database::fetchAll("
        SELECT id, type, title, title_short, site, site_detail, description, 
               occurred_at, image_main, image_2, image_3
        FROM sf_flashes
        WHERE state = 'published' AND type IN ('red', 'yellow')
        ORDER BY occurred_at DESC
    ");
} catch (Throwable $e) {
    error_log('form.php load related flashes error: ' . $e->getMessage());
}

// --- Jos muokkaus (id annettu), ladataan tietue PDO:lla ja esitäytetään kentät ---
$editing = false;
$flash   = [];
$editId  = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($editId > 0) {
    try {
        $flash = Database::fetchOne(
            "SELECT * FROM sf_flashes WHERE id = :id LIMIT 1",
            [':id' => $editId]
        );
        if ($flash) {
            $editing = true;
        } else {
            $flash = [];
        }
    } catch (Throwable $e) {
        error_log('form.php load flash error: ' .  $e->getMessage());
    }
}

$uiLang    = $_SESSION['ui_lang'] ?? 'fi';
$flashLang = $flash['language'] ?? 'fi';

$configLanguages = sf_get_terms_config()['languages'];
if (!in_array($flashLang, $configLanguages, true)) {
    $flashLang = 'fi';
}
if (!in_array($uiLang, $configLanguages, true)) {
    $uiLang = 'fi';
}

// --- Esitäytettävät arvot ---
$title            = $flash['title'] ?? '';
$title_short      = $flash['title_short'] ?? ($flash['summary'] ?? '');
$short_text       = $title_short;
$summary          = $flash['summary'] ?? '';
$description      = $flash['description'] ?? '';
$root_causes      = $flash['root_causes'] ?? '';
$actions          = $flash['actions'] ?? '';
$worksite_val     = $flash['site'] ?? '';
$site_detail_val  = $flash['site_detail'] ?? '';
$event_date_val   = !empty($flash['occurred_at']) ? date('Y-m-d\TH:i', strtotime($flash['occurred_at'])) : '';
$type_val         = $flash['type'] ?? '';
$state_val        = $flash['state'] ?? '';
$preview_filename = $flash['preview_filename'] ?? '';
$image_main       = $flash['image_main'] ?? '';

// Mahdolliset transform-arvot (JSON) kolmelle kuvalle
$image1_transform = $flash['image1_transform'] ?? '';
$image2_transform = $flash['image2_transform'] ?? '';
$image3_transform = $flash['image3_transform'] ?? '';

// initial step param (optional)
$initialStep = isset($_GET['step']) ? (int) $_GET['step'] : 1;

// Kuvapolku muokkaustilassa
$getImageUrl = function ($filename) use ($base) {
    if (empty($filename)) {
        return "{$base}/assets/img/camera-placeholder.png";
    }
    $path = "uploads/images/{$filename}";
    if (file_exists(__DIR__ . "/../../{$path}")) {
        return "{$base}/{$path}";
    }
    $oldPath = "img/{$filename}";
    if (file_exists(__DIR__ . "/../../{$oldPath}")) {
        return "{$base}/{$oldPath}";
    }
    return "{$base}/assets/img/camera-placeholder.png";
};
?>
<form
  id="sf-form"
  method="post"
  action="<?php echo $base; ?>/app/api/save_flash.php"
  class="sf-form"
  enctype="multipart/form-data"
  novalidate
>
  <?= sf_csrf_field() ?>
  <?php if ($editing): ?>
    <input type="hidden" name="id" value="<?= (int) $editId ?>">
  <?php endif; ?>
  <input type="hidden" id="initialStep" value="<?= (int) $initialStep ?>">
  
  <!-- Related flash ID tutkintatiedotteelle (päivittää alkuperäisen) -->
  <input type="hidden" name="related_flash_id" id="sf-related-flash-id" value="">

  <!-- Progressbar -->
  <div class="sf-progress">
    <div class="sf-progress-track">
      <div class="sf-progress-bar" id="sfProgressBar"></div>
    </div>
    <div class="sf-progress-steps">
      <span data-step="1">1</span>
      <span data-step="2">2</span>
      <span data-step="3">3</span>
      <span data-step="4">4</span>
      <span data-step="5">5</span>
    </div>
  </div>

  <!-- VAIHE 1: kieli ja tyyppivalinta -->
  <div class="sf-step-content active" data-step="1">
    <h2><?= htmlspecialchars(sf_term('step1_heading', $uiLang), ENT_QUOTES, 'UTF-8') ?></h2>
    <!-- Kielivalinta -->
    <div class="sf-lang-selection">
      <label class="sf-label"><?= htmlspecialchars(sf_term('lang_selection_label', $uiLang), ENT_QUOTES, 'UTF-8') ?></label>
      <div class="sf-lang-options">
        <?php
        $langOptions = [
            'fi' => ['label' => 'Suomi',    'flag' => 'finnish-flag.png'],
            'sv' => ['label' => 'Svenska',  'flag' => 'swedish-flag.png'],
            'en' => ['label' => 'English',  'flag' => 'english-flag.png'],
            'it' => ['label' => 'Italiano', 'flag' => 'italian-flag.png'],
            'el' => ['label' => 'Ελληνικά', 'flag' => 'greece-flag.png'],
        ];
        $selectedLang = $flash['lang'] ?? 'fi';
        foreach ($langOptions as $langCode => $langData):
        ?>
          <label class="sf-lang-box" data-lang="<?php echo $langCode; ?>">
            <input type="radio" name="lang" value="<?php echo $langCode; ?>" <?php echo $selectedLang === $langCode ? 'checked' : ''; ?>>
            <div class="sf-lang-box-content">
              <img src="<?php echo $base; ?>/assets/img/<?php echo $langData['flag']; ?>" alt="<?php echo $langData['label']; ?>" class="sf-lang-flag">
              <span class="sf-lang-label"><?php echo htmlspecialchars($langData['label']); ?></span>
            </div>
          </label>
        <?php endforeach; ?>
      </div>
      <p class="sf-help-text"><?= htmlspecialchars(sf_term('lang_selection_help', $uiLang), ENT_QUOTES, 'UTF-8') ?></p>
    </div>

    <hr class="sf-divider">

    <!-- Tyyppivalinta -->
    <h3><?= htmlspecialchars(sf_term('type_selection_label', $uiLang), ENT_QUOTES, 'UTF-8') ?></h3>

    <div class="sf-type-selection" role="radiogroup" aria-label="Valitse tiedotteen tyyppi">

      <!-- RED -->
      <label class="sf-type-box" data-type="red">
        <input type="radio" name="type" value="red" <?= $type_val === 'red' ? 'checked' : '' ?>>
        <div class="sf-type-box-content">
          <img src="<?= $base ?>/assets/img/icon-red.png" alt="" class="sf-type-icon" aria-hidden="true">
          <div class="sf-type-text">
            <h4 class="sf-type-title">
              <?= htmlspecialchars(sf_term('first_release', $uiLang), ENT_QUOTES, 'UTF-8') ?>
            </h4>
            <p>Kiireellinen – tapaturma tai vakava vaara</p>
          </div>
        </div>
        <img src="<?= $base ?>/assets/img/icons/checkmark_icon.png" alt="Valittu" class="sf-type-checkmark">
      </label>

      <!-- YELLOW -->
      <label class="sf-type-box" data-type="yellow">
        <input type="radio" name="type" value="yellow" <?= $type_val === 'yellow' ? 'checked' : '' ?>>
        <div class="sf-type-box-content">
          <img src="<?= $base ?>/assets/img/icon-yellow.png" alt="" class="sf-type-icon" aria-hidden="true">
          <div class="sf-type-text">
            <h4 class="sf-type-title">
              <?= htmlspecialchars(sf_term('dangerous_situation', $uiLang), ENT_QUOTES, 'UTF-8') ?>
            </h4>
            <p>Vaaratilanne – läheltä piti</p>
          </div>
        </div>
        <img src="<?= $base ?>/assets/img/icons/checkmark_icon.png" alt="Valittu" class="sf-type-checkmark">
      </label>

      <!-- GREEN -->
      <label class="sf-type-box" data-type="green">
        <input type="radio" name="type" value="green" <?= $type_val === 'green' ? 'checked' : '' ?>>
        <div class="sf-type-box-content">
          <img src="<?= $base ?>/assets/img/icon-green.png" alt="" class="sf-type-icon" aria-hidden="true">
          <div class="sf-type-text">
            <h4 class="sf-type-title">
              <?= htmlspecialchars(sf_term('investigation_report', $uiLang), ENT_QUOTES, 'UTF-8') ?>
            </h4>
            <p>Tutkinta valmis – toimenpiteet määritelty</p>
          </div>
        </div>
        <img src="<?= $base ?>/assets/img/icons/checkmark_icon.png" alt="Valittu" class="sf-type-checkmark">
      </label>

    </div>

    <!-- Vaihe 1 napit (alhaalla) -->
    <div class="sf-step-actions sf-step-actions-bottom">
      <button type="button" id="sfNext" class="sf-btn sf-btn-primary" disabled>
        <?php echo htmlspecialchars(sf_term('btn_next', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
      </button>
    </div>
  </div>

  <!-- VAIHE 2: konteksti -->
  <div class="sf-step-content" data-step="2">
    <h2><?= htmlspecialchars(sf_term('step2_heading', $uiLang), ENT_QUOTES, 'UTF-8') ?></h2>

    <div id="sf-step2-incident" class="sf-step2-section">
      <div class="sf-field">
        <label for="sf-related-flash" class="sf-label">
          <?= htmlspecialchars(sf_term('related_flash_label', $uiLang), ENT_QUOTES, 'UTF-8') ?>
        </label>
        <select name="related_flash_id" id="sf-related-flash" class="sf-select">
          <option value="">
            <?= htmlspecialchars(sf_term('related_flash_placeholder', $uiLang), ENT_QUOTES, 'UTF-8') ?>
          </option>
          <?php foreach ($relatedOptions as $opt):
              $optDate = !empty($opt['occurred_at'])
                  ? date('d.m.Y', strtotime($opt['occurred_at']))
                  : '–';

              $optSite  = $opt['site'] ?? '–';
              $optTitle = $opt['title'] ?? $opt['title_short'] ?? '–';

              // Väripallo tyypin mukaan
              $colorDot = ($opt['type'] === 'red') ? '🔴' : '🟡';

              // Muoto: väripallo + päivämäärä + työmaa + otsikko
              $optLabel = "{$colorDot} {$optDate} – {$optSite} – {$optTitle}";
          ?>
            <option
              value="<?= (int) $opt['id'] ?>"
              data-site="<?= htmlspecialchars($opt['site'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-site-detail="<?= htmlspecialchars($opt['site_detail'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-date="<?= htmlspecialchars($opt['occurred_at'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-title="<?= htmlspecialchars($opt['title'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-title-short="<?= htmlspecialchars($opt['title_short'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-description="<?= htmlspecialchars($opt['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-image-main="<?= htmlspecialchars($opt['image_main'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-image-2="<?= htmlspecialchars($opt['image_2'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              data-image-3="<?= htmlspecialchars($opt['image_3'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
              <?= (isset($flash['related_flash_id']) && (int) $flash['related_flash_id'] === (int) $opt['id']) ? 'selected' : '' ?>
            >
              <?= htmlspecialchars($optLabel, ENT_QUOTES, 'UTF-8') ?>
            </option>
          <?php endforeach; ?>
        </select>
        <p class="sf-help-text" id="sf-related-flash-help">
          <?= htmlspecialchars(sf_term('related_flash_help', $uiLang), ENT_QUOTES, 'UTF-8') ?>
        </p>
      </div>
    </div>

    <!-- Alkuperäisen tiedotteen kompakti näkymä (näkyy kun related flash valittu) -->
    <div id="sf-original-flash-preview" class="sf-original-flash-compact hidden">
      <img src="<?= $base ?>/assets/img/icon-yellow.png" alt="" class="sf-original-icon" id="sf-original-icon">
      <div class="sf-original-info">
        <span class="sf-original-title" id="sf-original-title">--</span>
        <span class="sf-original-meta">
          <span id="sf-original-site">--</span>
          <span id="sf-original-date">--</span>
        </span>
      </div>
    </div>

    <!-- Tutkintatiedotteen osio (ei tarvitse erillistä info-tekstiä) -->
    <div id="sf-step2-investigation-worksite" class="sf-step2-section"></div>

<!-- Työmaa ja päivämäärä - käytetään KAIKILLE tyypeille (red, yellow, green) -->
<div id="sf-step2-worksite" class="sf-step2-section">
  <div class="sf-field-row">
    <div class="sf-field">
      <label for="sf-worksite" class="sf-label">
        <?= htmlspecialchars(sf_term('site_label', $flashLang), ENT_QUOTES, 'UTF-8') ?>
      </label>
      <select name="worksite" id="sf-worksite" class="sf-select">
        <option value="">
          <?= htmlspecialchars(sf_term('worksite_select_placeholder', $uiLang), ENT_QUOTES, 'UTF-8') ?>
        </option>
        <?php foreach ($worksites as $site): ?>
          <option
            value="<?= htmlspecialchars($site['name'], ENT_QUOTES, 'UTF-8') ?>"
            <?= $worksite_val === $site['name'] ? 'selected' : '' ?>
          >
            <?= htmlspecialchars($site['name'], ENT_QUOTES, 'UTF-8') ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="sf-field">
      <label for="sf-site-detail" class="sf-label">
        <?= htmlspecialchars(sf_term('site_detail_label', $uiLang), ENT_QUOTES, 'UTF-8') ?>
      </label>
      <input
        type="text"
        name="site_detail"
        id="sf-site-detail"
        class="sf-input"
        placeholder="<?= htmlspecialchars(sf_term('site_detail_placeholder', $uiLang), ENT_QUOTES, 'UTF-8') ?>"
        value="<?= htmlspecialchars($site_detail_val, ENT_QUOTES, 'UTF-8') ?>"
      >
    </div>
  </div>

  <div class="sf-field-row">
    <div class="sf-field">
      <label for="sf-date" class="sf-label">
        <?= htmlspecialchars(sf_term('when_label', $uiLang), ENT_QUOTES, 'UTF-8') ?>
      </label>
      <input
        type="datetime-local"
        name="event_date"
        id="sf-date"
        class="sf-input"
        required
        step=""
        value="<?= htmlspecialchars($event_date_val, ENT_QUOTES, 'UTF-8') ?>"
      >
    </div>
  </div>

  <p class="sf-help-text">
    <?= htmlspecialchars(sf_term('step2_help', $uiLang), ENT_QUOTES, 'UTF-8') ?>
  </p>
</div>

    <!-- Vaihe 2 napit -->
    <div class="sf-step-actions sf-step-actions-bottom">
      <button type="button" id="sfPrev2" class="sf-btn sf-btn-secondary sf-prev-btn">
        <?= htmlspecialchars(sf_term('btn_prev', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
      </button>
      <button type="button" id="sfNext2" class="sf-btn sf-btn-primary sf-next-btn">
        <?= htmlspecialchars(sf_term('btn_next', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
      </button>
    </div>
  </div>

  <!-- VAIHE 3: itse sisältö -->
  <div class="sf-step-content" data-step="3">
    <h2><?= htmlspecialchars(sf_term('step3_heading', $uiLang), ENT_QUOTES, 'UTF-8') ?></h2>

    <div class="sf-field">
      <label for="sf-title" class="sf-label">
        <?= htmlspecialchars(sf_term('title_internal_label', $uiLang), ENT_QUOTES, 'UTF-8') ?>
      </label>
      <input
        type="text"
        name="title"
        id="sf-title"
        class="sf-input"
        required
        placeholder="<?= htmlspecialchars(sf_term('title_internal_placeholder', $uiLang), ENT_QUOTES, 'UTF-8') ?>"
        value="<?= htmlspecialchars($title, ENT_QUOTES, 'UTF-8') ?>"
      >
    </div>

    <div class="sf-field">
      <label for="sf-short-text" class="sf-label">
        <?= htmlspecialchars(sf_term('short_title_label', $uiLang), ENT_QUOTES, 'UTF-8') ?>
      </label>
      <textarea
        name="short_text"
        id="sf-short-text"
        class="sf-textarea"
        rows="2"
        required
        maxlength="85"
        placeholder="<?= htmlspecialchars(sf_term('short_text_placeholder', $uiLang), ENT_QUOTES, 'UTF-8') ?>"
      ><?= htmlspecialchars($short_text, ENT_QUOTES, 'UTF-8') ?></textarea>
      <p class="sf-char-count"><span id="sf-short-text-count">0</span>/85</p>
    </div>

    <div class="sf-field">
      <label for="sf-description" class="sf-label">
        <?= htmlspecialchars(sf_term('description_label', $uiLang), ENT_QUOTES, 'UTF-8') ?>
      </label>
      <textarea
        name="description"
        id="sf-description"
        class="sf-textarea"
        rows="8"
        required
        placeholder="<?= htmlspecialchars(sf_term('description_placeholder', $uiLang), ENT_QUOTES, 'UTF-8') ?>"
      ><?= htmlspecialchars($description, ENT_QUOTES, 'UTF-8') ?></textarea>
      <p class="sf-char-count"><span id="sf-description-count">0</span>/950</p>
    </div>

    <div id="sf-investigation-extra" class="sf-step3-investigation hidden">
      <div class="sf-field">
        <label for="sf-root-causes" class="sf-label">
          <?= htmlspecialchars(sf_term('root_cause_label', $uiLang), ENT_QUOTES, 'UTF-8') ?>
        </label>
        <textarea
          name="root_causes"
          id="sf-root-causes"
          class="sf-textarea"
          rows="4"
          maxlength="1500"
          placeholder="<?= htmlspecialchars(sf_term('root_causes_placeholder', $uiLang), ENT_QUOTES, 'UTF-8') ?>"
        ><?= htmlspecialchars($root_causes, ENT_QUOTES, 'UTF-8') ?></textarea>
        <p class="sf-help-text">
          <?= htmlspecialchars(sf_term('root_causes_help', $uiLang), ENT_QUOTES, 'UTF-8') ?>
        </p>
      </div>

      <div class="sf-field">
        <label for="sf-actions" class="sf-label">
          <?= htmlspecialchars(sf_term('actions_label', $uiLang), ENT_QUOTES, 'UTF-8') ?>
        </label>
        <textarea
          name="actions"
          id="sf-actions"
          class="sf-textarea"
          rows="4"
          maxlength="1500"
          placeholder="<?= htmlspecialchars(sf_term('actions_placeholder', $uiLang), ENT_QUOTES, 'UTF-8') ?>"
        ><?= htmlspecialchars($actions, ENT_QUOTES, 'UTF-8') ?></textarea>
        <p class="sf-help-text">
          <?= htmlspecialchars(sf_term('actions_help', $uiLang), ENT_QUOTES, 'UTF-8') ?>
        </p>
      </div>
    </div>

<div class="sf-two-slides-notice" id="sfTwoSlidesNotice" style="display: none;">
    <div class="sf-notice-icon">ℹ️</div>
    <div class="sf-notice-text">
        <strong><?= sf_term('two_slides_notice_title', $uiLang) ?></strong>
        <span><?= sf_term('two_slides_notice_text', $uiLang) ?></span>
    </div>
</div>

    <!-- Vaihe 3 napit -->
    <div class="sf-step-actions sf-step-actions-bottom">
      <button type="button" id="sfPrev2" class="sf-btn sf-btn-secondary sf-prev-btn">
        <?= htmlspecialchars(sf_term('btn_prev', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
      </button>
      <button type="button" id="sfNext2" class="sf-btn sf-btn-primary sf-next-btn">
        <?= htmlspecialchars(sf_term('btn_next', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
      </button>
    </div>
  </div>

  <!-- VAIHE 4: Kuvat -->
  <div class="sf-step-content" data-step="4">
    <h2><?= htmlspecialchars(sf_term('step4_heading', $uiLang), ENT_QUOTES, 'UTF-8') ?></h2>

    <p class="sf-help-text">
      <?= htmlspecialchars(sf_term('step4_help', $uiLang), ENT_QUOTES, 'UTF-8') ?>
    </p>

    <div class="sf-image-upload-grid">
      <!-- Pääkuva -->
      <div class="sf-image-upload-card" data-slot="1">
        <label class="sf-image-upload-label">
          <?= htmlspecialchars(sf_term('img_main_label', $uiLang) ?? 'Pääkuva', ENT_QUOTES, 'UTF-8') ?> *
        </label>

        <div class="sf-image-upload-area">
          <div class="sf-image-preview" id="sfImagePreview1">
            <img
              src="<?= $getImageUrl($flash['image_main'] ?? null) ?>"
              alt="Pääkuva"
              id="sfImageThumb1"
              data-placeholder="<?= $base ?>/assets/img/camera-placeholder.png"
            >
            <button
              type="button"
              class="sf-image-remove-btn <?= empty($flash['image_main']) ? 'hidden' : '' ?>"
              data-slot="1"
              title="Poista kuva"
            >
              <svg width="16" height="16" viewBox="0 0 24 24"
                   fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="sf-image-upload-actions">
            <label class="sf-image-upload-btn">
              <input type="file" name="image1" accept="image/*" id="sf-image1" class="sf-image-input">
              <img src="<?= $base ?>/assets/img/icons/upload.svg" alt="" class="sf-btn-icon">
              <span>Lataa</span>
            </label>

            <button type="button" class="sf-image-library-btn" data-slot="1">
              <img src="<?= $base ?>/assets/img/icons/image.svg" alt="" class="sf-btn-icon">
              <span>Kuvapankki</span>
            </button>
          </div>
        </div>

        <input type="hidden" name="library_image_1" id="sfLibraryImage1" value="">
      </div>

      <!-- Lisäkuva 1 -->
      <div class="sf-image-upload-card" data-slot="2">
        <label class="sf-image-upload-label">
          <?= htmlspecialchars(sf_term('img_2_label', $uiLang) ?? 'Lisäkuva 1', ENT_QUOTES, 'UTF-8') ?>
        </label>

        <div class="sf-image-upload-area">
          <div class="sf-image-preview" id="sfImagePreview2">
            <img
              src="<?= $getImageUrl($flash['image_2'] ?? null) ?>"
              alt="Kuva 2"
              id="sfImageThumb2"
              data-placeholder="<?= $base ?>/assets/img/camera-placeholder.png"
            >
            <button
              type="button"
              class="sf-image-remove-btn <?= empty($flash['image_2']) ? 'hidden' : '' ?>"
              data-slot="2"
              title="Poista kuva"
            >
              <svg width="16" height="16" viewBox="0 0 24 24"
                   fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="sf-image-upload-actions">
            <label class="sf-image-upload-btn">
              <input type="file" name="image2" accept="image/*" id="sf-image2" class="sf-image-input">
              <img src="<?= $base ?>/assets/img/icons/upload.svg" alt="" class="sf-btn-icon">
              <span>Lataa</span>
            </label>

            <button type="button" class="sf-image-library-btn" data-slot="2">
              <img src="<?= $base ?>/assets/img/icons/image.svg" alt="" class="sf-btn-icon">
              <span>Kuvapankki</span>
            </button>
          </div>
        </div>

        <input type="hidden" name="library_image_2" id="sfLibraryImage2" value="">
      </div>

      <!-- Lisäkuva 2 -->
      <div class="sf-image-upload-card" data-slot="3">
        <label class="sf-image-upload-label">
          <?= htmlspecialchars(sf_term('img_3_label', $uiLang) ?? 'Lisäkuva 2', ENT_QUOTES, 'UTF-8') ?>
        </label>

        <div class="sf-image-upload-area">
          <div class="sf-image-preview" id="sfImagePreview3">
            <img
              src="<?= $getImageUrl($flash['image_3'] ?? null) ?>"
              alt="Kuva 3"
              id="sfImageThumb3"
              data-placeholder="<?= $base ?>/assets/img/camera-placeholder.png"
            >
            <button
              type="button"
              class="sf-image-remove-btn <?= empty($flash['image_3']) ? 'hidden' : '' ?>"
              data-slot="3"
              title="Poista kuva"
            >
              <svg width="16" height="16" viewBox="0 0 24 24"
                   fill="none" stroke="currentColor" stroke-width="2">
                <path d="M18 6L6 18M6 6l12 12"/>
              </svg>
            </button>
          </div>

          <div class="sf-image-upload-actions">
            <label class="sf-image-upload-btn">
              <input type="file" name="image3" accept="image/*" id="sf-image3" class="sf-image-input">
              <img src="<?= $base ?>/assets/img/icons/upload.svg" alt="" class="sf-btn-icon">
              <span>Lataa</span>
            </label>

            <button type="button" class="sf-image-library-btn" data-slot="3">
              <img src="<?= $base ?>/assets/img/icons/image.svg" alt="" class="sf-btn-icon">
              <span>Kuvapankki</span>
            </button>
          </div>
        </div>

        <input type="hidden" name="library_image_3" id="sfLibraryImage3" value="">
      </div>
    </div>

    <p class="sf-help-text sf-mt-1">
      <img
        src="<?= $base ?>/assets/img/icons/image.svg"
        alt=""
        style="width:16px; height:16px; vertical-align:middle; opacity:0.6; margin-right:4px;"
      >
      <?= htmlspecialchars(
          sf_term('image_library_help', $uiLang) ??
          'Valitse valmis hahmokuva tai pohja kuvapankista.',
          ENT_QUOTES,
          'UTF-8'
      ) ?>
    </p>

    <div class="sf-step-actions sf-step-actions-bottom">
      <button type="button" class="sf-btn sf-btn-secondary sf-prev-btn">
        <?= htmlspecialchars(sf_term('btn_prev', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
      </button>
      <button type="button" class="sf-btn sf-btn-primary sf-next-btn">
        <?= htmlspecialchars(sf_term('btn_next', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
      </button>
    </div>
  </div>

  <!-- Piilotetut transform-kentät (ennen vaihetta 5, lomakkeen sisällä) -->
  <input
    type="hidden"
    id="sf-image1-transform"
    name="image1_transform"
    value="<?= htmlspecialchars($image1_transform, ENT_QUOTES, 'UTF-8') ?>"
  >
  <input
    type="hidden"
    id="sf-image2-transform"
    name="image2_transform"
    value="<?= htmlspecialchars($image2_transform, ENT_QUOTES, 'UTF-8') ?>"
  >
  <input
    type="hidden"
    id="sf-image3-transform"
    name="image3_transform"
    value="<?= htmlspecialchars($image3_transform, ENT_QUOTES, 'UTF-8') ?>"
  >
  <input
    type="hidden"
    id="sf-annotations-data"
    name="annotations_data"
    value="<?= htmlspecialchars($flash['annotations_data'] ?? '[]', ENT_QUOTES, 'UTF-8') ?>"
  >

  <!-- VAIHE 5: Esikatselu ja lähetys -->
  <div class="sf-step-content" data-step="5">
    <?php
    // HUOM: Tämä lataa molemmat, ja JS päättää kumpi näytetään.
    // Tämä ratkaisee ongelman, jossa tutkintapreview ei lataudu uutta luodessa.
    ?>
    <div id="sfPreviewContainerRedYellow" class="sf-preview-container">
      <?php require __DIR__ . '/../partials/preview.php'; ?>
    </div>
    <div id="sfPreviewContainerGreen" class="sf-preview-container hidden">
      <?php require __DIR__ . '/../partials/preview_tutkinta.php'; ?>
    </div>
    <script>
      document.addEventListener('DOMContentLoaded', function() {
        // PreviewScaler ei ole käytössä - Preview ja PreviewTutkinta hoitavat skaalauksen
        // Alustetaan oikea preview tyypistä riippuen
        var currentType = document.querySelector('input[name="type"]:checked');
        if (currentType && currentType.value === 'green') {
          if (typeof PreviewTutkinta !== 'undefined' && PreviewTutkinta.init) {
            PreviewTutkinta.init();
          }
        } else {
          if (typeof Preview !== 'undefined' && Preview.init) {
            Preview.init();
          }
        }
      });
    </script>

    <!-- Submit-painikkeet (lomakkeen sisällä) -->
    <div class="sf-preview-actions">
      <button
        type="submit"
        name="submission_type"
        value="draft"
        id="sfSaveDraft"
        class="sf-btn sf-btn-secondary"
      >
        <?= htmlspecialchars(sf_term('btn_save_draft', $uiLang), ENT_QUOTES, 'UTF-8') ?>
      </button>
      <button
        type="submit"
        name="submission_type"
        value="review"
        id="sfSubmitReview"
        class="sf-btn sf-btn-primary"
      >
        <?= htmlspecialchars(sf_term('btn_send_review', $uiLang), ENT_QUOTES, 'UTF-8') ?>
      </button>
    </div>

    <!-- Vaihe 5 napit -->
    <div class="sf-step-actions sf-step-actions-bottom">
      <button type="button" class="sf-btn sf-btn-secondary sf-prev-btn">
        <?= htmlspecialchars(sf_term('btn_prev', $uiLang), ENT_QUOTES, 'UTF-8'); ?>
      </button>
    </div>
  </div>

  <!-- Lopullinen preview-kuva base64:na -->
  <input type="hidden" name="preview_image_data" id="sf-preview-image-data" value="">
  <input type="hidden" name="preview_image_data_2" id="sf-preview-image-data-2" value="">
</form>

<!-- VAHVISTUSMODAL - Lomakkeen ulkopuolella jotta JS löytää sen -->
<div
  class="sf-modal hidden"
  id="sfConfirmModal"
  role="dialog"
  aria-modal="true"
  aria-labelledby="sfConfirmModalTitle"
>
  <div class="sf-modal-content">
    <h2 id="sfConfirmModalTitle">
      <?= htmlspecialchars(sf_term('confirm_submit_title', $uiLang), ENT_QUOTES, 'UTF-8') ?>
    </h2>
    <p><?= htmlspecialchars(sf_term('confirm_submit_text', $uiLang), ENT_QUOTES, 'UTF-8') ?></p>
    <p class="sf-help-text">
      <?= htmlspecialchars(sf_term('confirm_submit_help', $uiLang), ENT_QUOTES, 'UTF-8') ?>
    </p>
    <div class="sf-modal-actions">
      <button
        type="button"
        class="sf-btn sf-btn-secondary"
        data-modal-close="sfConfirmModal"
      >
        <?= htmlspecialchars(sf_term('btn_cancel', $uiLang), ENT_QUOTES, 'UTF-8') ?>
      </button>
      <button type="button" class="sf-btn sf-btn-primary" id="sfConfirmSubmit">
        <?= htmlspecialchars(sf_term('btn_confirm_yes', $uiLang), ENT_QUOTES, 'UTF-8') ?>
      </button>
    </div>
  </div>
</div>

<?php
// Kuvapankki-modaali
$currentUiLang = $uiLang;
include __DIR__ . '/../partials/image_library_modal.php';
?>

<script>
document.addEventListener('DOMContentLoaded', function() {
  if (window.ImageLibrary) {
    ImageLibrary.init('<?= htmlspecialchars($base) ?>');
  }
});
</script>