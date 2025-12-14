<?php
// app/pages/settings/tab_users.php
declare(strict_types=1);

// Muuttujat tulevat settings.php:stä: $mysqli, $baseUrl, $currentUiLang

// Hae työmaat (vain aktiiviset, ei passivoituja kotityömaa-valikkoon)
$worksites = [];
$worksitesRes = $mysqli->query("SELECT id, name, is_active FROM sf_worksites WHERE is_active = 1 ORDER BY name ASC");
while ($w = $worksitesRes->fetch_assoc()) {
    $worksites[] = $w;
}

// Hae roolit
$roles = [];
$rolesRes = $mysqli->query('SELECT id, name FROM sf_roles ORDER BY id ASC');
while ($r = $rolesRes->fetch_assoc()) {
    $roles[] = $r;
}

// Hae käyttäjät
$users = [];
$sqlUsers = "
    SELECT 
        u.id,
        u.first_name,
        u.last_name,
        u.email,
        u.role_id,
        u.home_worksite_id,
        u.created_at,
        r.name AS role_name,
        ws.name AS home_worksite_name
    FROM sf_users u
    JOIN sf_roles r ON r.id = u.role_id
    LEFT JOIN sf_worksites ws ON ws.id = u.home_worksite_id
    WHERE u.is_active = 1
    ORDER BY u.created_at DESC
";
$resUsers = $mysqli->query($sqlUsers);
while ($row = $resUsers->fetch_assoc()) {
    $users[] = $row;
}
?>

<h2>
    <img src="<?= $baseUrl ?>/assets/img/icons/users.svg" alt="" class="sf-heading-icon" aria-hidden="true">
    <?= htmlspecialchars(sf_term('users_heading', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
</h2>

<div class="sf-users-header">
    <button class="sf-btn sf-btn-primary" id="sfUserAddBtn">
        <?= htmlspecialchars(sf_term('users_add_button', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
    </button>
</div>

<!-- MOBIILI: Korttinäkymä -->
<div class="sf-users-cards">
    <?php foreach ($users as $u): ?>
        <div class="sf-user-card">
            <div class="sf-user-card-header">
                <div class="sf-user-card-name">
                    <?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?>
                </div>
                <div class="sf-user-card-role">
                    <?= htmlspecialchars($u['role_name'] ?? '') ?>
                </div>
            </div>
            <div class="sf-user-card-email">
                <?= htmlspecialchars($u['email'] ?? '') ?>
            </div>
            <?php if (!empty($u['home_worksite_name'])): ?>
                <div class="sf-user-card-worksite">
                    🏗️ <?= htmlspecialchars($u['home_worksite_name']) ?>
                </div>
            <?php endif; ?>
            <div class="sf-user-card-actions">
                <button
                    class="sf-btn-small sf-edit-user"
                    data-id="<?= (int) $u['id'] ?>"
                    data-first="<?= htmlspecialchars($u['first_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-last="<?= htmlspecialchars($u['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-email="<?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                    data-role="<?= (int) $u['role_id'] ?>"
                    data-home-worksite="<?= (int) ($u['home_worksite_id'] ?? 0) ?>"
                >
                    ✏️ <?= htmlspecialchars(sf_term('users_action_edit', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>

                <button
                    class="sf-btn-small sf-reset-pass"
                    data-id="<?= (int) $u['id'] ?>"
                >
                    🔑 <?= htmlspecialchars(sf_term('users_action_reset_pass', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                </button>

                <button
                    class="sf-btn-small sf-delete-user"
                    data-id="<?= (int) $u['id'] ?>"
                >
                    🗑️
                </button>
            </div>
        </div>
    <?php endforeach; ?>
</div>

<!-- DESKTOP: Taulukkonäkymä -->
<table class="sf-table sf-table-users">
    <thead>
        <tr>
            <th><?= htmlspecialchars(sf_term('users_col_name', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></th>
            <th><?= htmlspecialchars(sf_term('users_col_email', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></th>
            <th><?= htmlspecialchars(sf_term('users_col_role', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></th>
            <th><?= htmlspecialchars(sf_term('users_col_home_worksite', $currentUiLang) ?? 'Kotityömaa', ENT_QUOTES, 'UTF-8') ?></th>
            <th><?= htmlspecialchars(sf_term('users_col_created', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></th>
            <th><?= htmlspecialchars(sf_term('users_col_actions', $currentUiLang), ENT_QUOTES, 'UTF-8') ?></th>
        </tr>
    </thead>
    <tbody>
        <?php foreach ($users as $u): ?>
            <tr>
                <td><?= htmlspecialchars(trim(($u['first_name'] ?? '') . ' ' . ($u['last_name'] ?? ''))) ?></td>
                <td><?= htmlspecialchars($u['email'] ?? '') ?></td>
                <td><?= htmlspecialchars($u['role_name'] ?? '') ?></td>
                <td>
                    <?php
                    if (!empty($u['home_worksite_name'])) {
                        echo htmlspecialchars($u['home_worksite_name'], ENT_QUOTES, 'UTF-8');
                    } else {
                        echo htmlspecialchars(
                            sf_term('users_home_worksite_none', $currentUiLang) ?? '–',
                            ENT_QUOTES,
                            'UTF-8'
                        );
                    }
                    ?>
                </td>
                <td><?= htmlspecialchars($u['created_at'] ?? '', ENT_QUOTES, 'UTF-8') ?></td>
                <td>
                    <button
                        class="sf-btn-small sf-edit-user"
                        data-id="<?= (int) $u['id'] ?>"
                        data-first="<?= htmlspecialchars($u['first_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        data-last="<?= htmlspecialchars($u['last_name'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        data-email="<?= htmlspecialchars($u['email'] ?? '', ENT_QUOTES, 'UTF-8') ?>"
                        data-role="<?= (int) $u['role_id'] ?>"
                        data-home-worksite="<?= (int) ($u['home_worksite_id'] ?? 0) ?>"
                    >
                        <?= htmlspecialchars(sf_term('users_action_edit', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </button>

                    <button
                        class="sf-btn-small sf-reset-pass"
                        data-id="<?= (int) $u['id'] ?>"
                    >
                        <?= htmlspecialchars(sf_term('users_action_reset_pass', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </button>

                    <button
                        class="sf-btn-small sf-delete-user"
                        data-id="<?= (int) $u['id'] ?>"
                    >
                        <?= htmlspecialchars(sf_term('users_action_delete', $currentUiLang), ENT_QUOTES, 'UTF-8') ?>
                    </button>
                </td>
            </tr>
        <?php endforeach; ?>
    </tbody>
</table>

<!-- MODAALIT -->
<?php include __DIR__ . '/modals_users.php'; ?>