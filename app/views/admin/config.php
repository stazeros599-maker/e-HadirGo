<?php
/** @var array $units */
/** @var array $locations */
?>
<div class="page-head">
    <div>
        <h1><?= e(t('admin.config')) ?></h1>
        <p class="muted"><?= e(t('admin.config_sub')) ?></p>
    </div>
</div>

<div class="grid grid-2">
    <section class="card">
        <h2><?= e(t('admin.units')) ?></h2>

        <form method="post" action="<?= e(url('admin.unit.save')) ?>" class="row">
            <?= csrf_field() ?>
            <input type="text" name="daerah" placeholder="<?= e(t('admin.daerah')) ?>" required maxlength="80">
            <input type="text" name="bahagian" placeholder="<?= e(t('admin.bahagian')) ?>" required maxlength="120">
            <button class="btn btn-primary" type="submit"><?= e(t('admin.add')) ?></button>
        </form>

        <table class="table table-compact">
            <thead>
                <tr>
                    <th><?= e(t('admin.daerah')) ?></th>
                    <th><?= e(t('admin.bahagian')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($units as $u): ?>
                    <tr>
                        <td><?= e((string) $u['daerah']) ?></td>
                        <td><?= e((string) $u['bahagian']) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>

    <section class="card">
        <h2><?= e(t('admin.locations')) ?></h2>

        <form method="post" action="<?= e(url('admin.location.save')) ?>" class="stack">
            <?= csrf_field() ?>
            <input type="text" name="name" placeholder="<?= e(t('staff.location')) ?>" required maxlength="150">
            <div class="row">
                <input type="number" step="0.000001" name="lat" placeholder="Lat" required>
                <input type="number" step="0.000001" name="lng" placeholder="Lng" required>
                <input type="number" name="radius_m" value="250" min="50" max="5000" required>
            </div>
            <button class="btn btn-primary" type="submit"><?= e(t('admin.add')) ?></button>
        </form>

        <table class="table table-compact">
            <thead>
                <tr>
                    <th><?= e(t('staff.location')) ?></th>
                    <th>Lat</th>
                    <th>Lng</th>
                    <th><?= e(t('staff.radius')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($locations as $l): ?>
                    <tr>
                        <td><?= e((string) $l['name']) ?></td>
                        <td><?= e((string) $l['lat']) ?></td>
                        <td><?= e((string) $l['lng']) ?></td>
                        <td><?= (int) $l['radius_m'] ?> m</td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
</div>
