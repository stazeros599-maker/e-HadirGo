<?php
/** @var array $rows */
?>
<div class="page-head">
    <div>
        <h1><?= e(t('staff.records')) ?></h1>
        <p class="muted"><?= e(t('staff.records_sub')) ?></p>
    </div>
</div>

<section class="card">
    <?php if ($rows === []): ?>
        <p class="muted"><?= e(t('staff.no_records')) ?></p>
    <?php else: ?>
        <table class="table">
            <thead>
                <tr>
                    <th><?= e(t('staff.date')) ?></th>
                    <th><?= e(t('staff.location')) ?></th>
                    <th><?= e(t('checkin.at')) ?></th>
                    <th><?= e(t('checkout.at')) ?></th>
                    <th><?= e(t('staff.status')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <?php
                    if ($row['check_in_at'] === null) {
                        $status = $row['status'] === 'dibatalkan' ? 'dibatalkan' : 'tidak_hadir';
                    } elseif (truthy($row['outside_radius']) || truthy($row['location_unavailable'])) {
                        $status = 'luar_kawasan';
                    } elseif (truthy($row['is_late'])) {
                        $status = 'lewat';
                    } else {
                        $status = 'tepat_masa';
                    }
                    ?>
                    <tr>
                        <td><?= e(date('d/m/Y', strtotime((string) $row['assignment_date']))) ?></td>
                        <td>
                            <a href="<?= e(url('assignment', ['id' => (int) $row['id']])) ?>">
                                <?= e((string) $row['location_name']) ?>
                            </a>
                        </td>
                        <td><?= e(fmt_dt($row['check_in_at'], 'H:i')) ?></td>
                        <td><?= e(fmt_dt($row['check_out_at'], 'H:i')) ?></td>
                        <td><span class="pill pill-<?= e(status_class($status)) ?>"><?= e(status_label($status)) ?></span></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
