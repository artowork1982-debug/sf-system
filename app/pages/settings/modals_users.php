<?php
// app/pages/settings/modals_users.php
// Modaalit käyttäjien hallintaan
?>

<!-- MODAALI – Lisää / Muokkaa käyttäjä -->
<div class="sf-modal hidden" id="sfUserModal">
    <div class="sf-modal-content">
        <h2 id="sfUserModalTitle">
            <?= htmlspecialchars(sf_term('users_modal_add_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </h2>
        <form id="sfUserForm">
            <input type="hidden" name="id" id="sfUserId">
            
            <label><?= htmlspecialchars(sf_term('users_label_first_name', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="text" name="first_name" id="sfUserFirst" required>
            
            <label><?= htmlspecialchars(sf_term('users_label_last_name', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="text" name="last_name" id="sfUserLast" required>
            
            <label><?= htmlspecialchars(sf_term('users_label_email', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="email" name="email" id="sfUserEmail" required>
            
            <label><?= htmlspecialchars(sf_term('users_label_role', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></label>
            <select name="role_id" id="sfUserRole" required>
                <?php foreach ($roles as $r): ?>
                    <option value="<?= (int)$r['id'] ?>">
                        <?= htmlspecialchars($r['name'], ENT_QUOTES, 'UTF-8') ?>
                    </option>
                <?php endforeach; ?>
            </select>
            
            <label><?= htmlspecialchars(sf_term('users_label_home_worksite', $currentUiLang) ?? 'Kotityömaa', ENT_QUOTES, 'UTF-8') ?></label>
            <select name="home_worksite_id" id="sfUserHomeWorksite">
                <option value="">
                    <?= htmlspecialchars(sf_term('users_home_worksite_none', $currentUiLang) ?? '–', ENT_QUOTES, 'UTF-8') ?>
                </option>
<?php foreach ($worksites as $ws): ?>
    <option value="<?= (int)$ws['id'] ?>">
        <?= htmlspecialchars($ws['name'], ENT_QUOTES, 'UTF-8') ?>
    </option>
<?php endforeach; ?>
            </select>
            
            <label><?= htmlspecialchars(sf_term('users_label_password_new', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></label>
            <input type="password" name="password" id="sfUserPassword">
            
            <div class="sf-modal-actions">
                <button type="button" class="sf-btn sf-btn-secondary" id="sfUserCancel">
                    <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
                <button type="submit" class="sf-btn sf-btn-primary">
                    <?= htmlspecialchars(sf_term('btn_save', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>
            </div>
        </form>
    </div>
</div>

<!-- MODAALI – Poisto -->
<div class="sf-modal hidden" id="sfDeleteModal">
    <div class="sf-modal-content">
        <h2><?= htmlspecialchars(sf_term('users_delete_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></h2>
        <p>
            <?= htmlspecialchars(sf_term('users_delete_text', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            <strong id="sfDeleteUserName"></strong>?
        </p>
        <div class="sf-modal-actions">
            <button type="button" class="sf-btn sf-btn-secondary" id="sfDeleteCancel">
                <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button type="button" class="sf-btn sf-btn-danger" id="sfDeleteConfirm">
                <?= htmlspecialchars(sf_term('btn_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>
</div>

<!-- MODAALI – Salasanan resetointi -->
<div class="sf-modal hidden" id="sfResetModal">
    <div class="sf-modal-content">
        <h2><?= htmlspecialchars(sf_term('users_reset_title', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></h2>
        <p>
            <?= htmlspecialchars(sf_term('users_reset_text_prefix', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            <strong id="sfResetUserName"></strong>
            <?= htmlspecialchars(sf_term('users_reset_text_suffix', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
        </p>
        <div class="sf-modal-actions">
            <button type="button" class="sf-btn sf-btn-secondary" id="sfResetCancel">
                <?= htmlspecialchars(sf_term('btn_cancel', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
            <button type="button" class="sf-btn sf-btn-primary" id="sfResetConfirm">
                <?= htmlspecialchars(sf_term('users_action_reset_pass', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
            </button>
        </div>
    </div>
</div>