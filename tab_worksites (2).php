<?php
// app/pages/settings/tab_worksites.php

$worksites = [];
$res = $mysqli->query("SELECT id, name, description, is_active, created_at FROM sf_worksites ORDER BY name ASC");
if ($res) {
    while ($row = $res->fetch_assoc()) {
        $worksites[] = $row;
    }
    $res->free();
}
?>

<section class="sf-settings-section">
    <h2>Työmaat</h2>

    <div class="sf-card">
        <h3>Lisää uusi työmaa</h3>
        <form class="sf-form-inline" method="post" action="<?= $baseUrl ?>/app/actions/worksites_save.php" data-sf-ajax="1">
            <input type="hidden" name="action" value="add">
            <?= sf_csrf_field(); ?>

            <label>
                <span>Nimi</span>
                <input type="text" name="name" required>
            </label>
            <label>
                <span>Kuvaus</span>
                <input type="text" name="description">
            </label>
            <button type="submit" class="sf-btn sf-btn-primary">Lisää</button>
        </form>
    </div>

    <div class="sf-card">
        <h3>Työmaat lista</h3>
        <table class="sf-table">
            <thead>
                <tr>
                    <th>Nimi</th>
                    <th>Kuvaus</th>
                    <th>Aktiivinen</th>
                    <th>Toiminnot</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($worksites as $w): ?>
                    <tr class="<?= ((int)$w['is_active'] === 1) ? '' : 'is-inactive' ?>">
                        <td><?= htmlspecialchars($w['name'] ?? '') ?></td>
                        <td><?= htmlspecialchars($w['description'] ?? '') ?></td>
                        <td><?= ((int)$w['is_active'] === 1) ? 'Kyllä' : 'Ei' ?></td>
                        <td>
                            <form method="post"
                                  action="<?= $baseUrl ?>/app/actions/worksites_save.php"
                                  class="sf-inline-form"
                                  data-sf-ajax="1">
                                <input type="hidden" name="action" value="toggle">
                                <?= sf_csrf_field(); ?>
                                <input type="hidden" name="id" value="<?= (int)$w['id'] ?>">
                                <button type="submit"
                                        class="sf-btn sf-btn-small <?= ((int)$w['is_active'] === 1) ? 'sf-btn-warning' : 'sf-btn-success' ?>">
                                    <?= ((int)$w['is_active'] === 1) ? 'Passivoi' : 'Aktivoi' ?>
                                </button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>