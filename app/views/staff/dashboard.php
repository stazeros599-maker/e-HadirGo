<?php
/** @var array $today */
/** @var array $upcoming */
/** @var bool $hasTg */
$user = current_user();
?>
<div class="page-head">
    <div>
        <h1><?= e(t('staff.hello', ['name' => (string) $user['name']])) ?></h1>
        <p class="muted"><?= e(now_local()->format('l, d/m/Y')) ?> · <?= e(t('nav.dashboard')) ?></p>
    </div>
    <a class="btn btn-ghost" href="<?= e(url('records')) ?>"><?= e(t('staff.all_records')) ?></a>
</div>

<?php if (!$hasTg): ?>
    <div class="flash flash-warn flash-row">
        <span><?= e(t('staff.tg_banner')) ?></span>
        <a class="btn btn-sm btn-primary" href="<?= e(url('settings')) ?>#notifications"><?= e(t('staff.tg_link')) ?></a>
    </div>
<?php endif; ?>

<section class="card">
    <h2><?= e(t('staff.today')) ?></h2>

    <?php if ($today === []): ?>
        <p class="muted"><?= e(t('staff.no_today')) ?></p>
    <?php else: ?>
        <div class="grid grid-2">
            <?php foreach ($today as $a): ?>
                <?php
                $status = 'dijadualkan';
                if ($a['attendance_id'] !== null) {
                    $status = truthy($a['outside_radius']) ? 'luar_kawasan' : (truthy($a['is_late']) ? 'lewat' : 'tepat_masa');
                }
                ?>
                <article class="card task">
                    <div class="task-top">
                        <span class="pill pill-<?= e(status_class($status)) ?>">
                            <?= e(status_label($status)) ?>
                        </span>
                        <span class="muted"><?= e(substr((string) $a['start_time'], 0, 5)) ?> – <?= e(substr((string) $a['end_time'], 0, 5)) ?></span>
                    </div>
                    <h3><?= e((string) $a['purpose']) ?></h3>
                    <p class="muted">📍 <?= e((string) $a['location_name']) ?> · <?= (int) $a['radius_m'] ?> m</p>
                    <a class="btn btn-primary btn-block" href="<?= e(url('assignment', ['id' => (int) $a['id']])) ?>">
                        <?= e($a['attendance_id'] === null ? t('staff.checkin') : t('staff.view')) ?>
                    </a>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>

<section class="card">
    <h2><?= e(t('staff.upcoming')) ?></h2>

    <?php if ($upcoming === []): ?>
        <p class="muted"><?= e(t('staff.no_upcoming')) ?></p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th><?= e(t('staff.date')) ?></th>
                    <th><?= e(t('staff.time')) ?></th>
                    <th><?= e(t('staff.location')) ?></th>
                    <th><?= e(t('staff.purpose')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($upcoming as $a): ?>
                    <tr>
                        <td><?= e(date('d/m/Y', strtotime((string) $a['assignment_date']))) ?></td>
                        <td><?= e(substr((string) $a['start_time'], 0, 5)) ?></td>
                        <td><?= e((string) $a['location_name']) ?></td>
                        <td><?= e((string) $a['purpose']) ?></td>
                        <td class="right">
                            <a class="btn btn-sm" href="<?= e(url('assignment', ['id' => (int) $a['id']])) ?>"><?= e(t('staff.view')) ?></a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
