<?php
/** @var array $rows */
/** @var array $filters */
/** @var array $counts */
$canManageAssignments = has_role('super_admin');
?>
<div class="page-head">
    <div>
        <h1><?= e(t('sup.title')) ?></h1>
        <p class="muted"><?= e(t('sup.sub')) ?></p>
    </div>
    <?php if ($canManageAssignments): ?>
        <a class="btn btn-primary" href="<?= e(url('sup.form')) ?>">+ <?= e(t('sup.new')) ?></a>
    <?php endif; ?>
</div>

<div class="grid grid-4">
    <div class="stat"><span class="muted"><?= e(t('sup.stat_total')) ?></span><strong><?= (int) $counts['total'] ?></strong></div>
    <div class="stat"><span class="muted"><?= e(t('sup.stat_in')) ?></span><strong><?= (int) $counts['checked_in'] ?></strong></div>
    <div class="stat"><span class="muted"><?= e(t('sup.stat_late')) ?></span><strong><?= (int) $counts['late'] ?></strong></div>
    <div class="stat"><span class="muted"><?= e(t('sup.stat_outside')) ?></span><strong><?= (int) $counts['outside'] ?></strong></div>
</div>

<section class="card">
    <form method="get" action="<?= e(base_path()) ?>/index.php" class="filters">
        <input type="hidden" name="r" value="sup.assignments">
        <label class="field-inline">
            <span><?= e(t('sup.filter_date')) ?></span>
            <input type="date" name="date" value="<?= e((string) $filters['date']) ?>">
        </label>
        <label class="field-inline">
            <span><?= e(t('staff.status')) ?></span>
            <select name="status">
                <option value=""><?= e(t('sup.all')) ?></option>
                <?php foreach (['dijadualkan', 'dalam_tindakan', 'selesai', 'tidak_hadir', 'dibatalkan'] as $st): ?>
                    <option value="<?= e($st) ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>>
                        <?= e(t('assign_status.' . $st)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn" type="submit"><?= e(t('sup.filter')) ?></button>
        <a class="btn btn-ghost" href="<?= e(url('sup.assignments')) ?>"><?= e(t('sup.reset')) ?></a>
    </form>

    <?php if ($rows === []): ?>
        <p class="muted"><?= e(t('sup.empty')) ?></p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th><?= e(t('staff.date')) ?></th>
                    <th><?= e(t('staff.time')) ?></th>
                    <th><?= e(t('staff.staff')) ?></th>
                    <th><?= e(t('staff.location')) ?></th>
                    <th><?= e(t('staff.status')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <?php
                    $badge = 'muted';
                    $label = t('assign_status.' . (string) $r['status']);
                    if ($r['attendance_id'] !== null) {
                        if (truthy($r['outside_radius'])) {
                            $badge = 'bad';
                            $label = t('status.outside');
                        } elseif (truthy($r['is_late'])) {
                            $badge = 'warn';
                            $label = t('status.late');
                        } else {
                            $badge = 'ok';
                            $label = t('status.on_time');
                        }
                    }
                    ?>
                    <tr>
                        <td><?= e(date('d/m/Y', strtotime((string) $r['assignment_date']))) ?></td>
                        <td><?= e(substr((string) $r['start_time'], 0, 5)) ?>–<?= e(substr((string) $r['end_time'], 0, 5)) ?></td>
                        <td><?= e((string) $r['staff_name']) ?></td>
                        <td><?= e((string) $r['location_name']) ?></td>
                        <td><span class="pill pill-<?= e($badge) ?>"><?= e($label) ?></span></td>
                        <td class="right">
                            <a class="btn btn-sm" href="<?= e(url('assignment', ['id' => (int) $r['id']])) ?>"><?= e(t('staff.view')) ?></a>
                            <?php if ($canManageAssignments): ?>
                                <a class="btn btn-sm btn-ghost" href="<?= e(url('sup.form', ['id' => (int) $r['id']])) ?>"><?= e(t('sup.edit')) ?></a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
