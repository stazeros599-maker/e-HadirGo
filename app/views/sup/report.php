<?php
/** @var array $rows */
/** @var array $filters */
$exportParams = array_filter([
    'r'      => 'sup.report.pdf',
    'from'   => $filters['from'],
    'to'     => $filters['to'],
    'status' => $filters['status'],
    'lang'   => current_lang(),
], static fn ($value): bool => $value !== '');
?>
<div class="page-head">
    <div>
        <h1><?= e(t('sup.report_title')) ?></h1>
        <p class="muted"><?= count($rows) ?> <?= e(t('sup.rows')) ?></p>
    </div>
    <a class="btn btn-primary" href="<?= e(base_path() . '/index.php?' . http_build_query($exportParams)) ?>">
        <?= e(t('sup.export')) ?>
    </a>
</div>

<section class="card">
    <form method="get" action="<?= e(base_path()) ?>/index.php" class="filters">
        <input type="hidden" name="r" value="sup.report">
        <label class="field-inline">
            <span><?= e(t('sup.from')) ?></span>
            <input type="date" name="from" value="<?= e((string) $filters['from']) ?>">
        </label>
        <label class="field-inline">
            <span><?= e(t('sup.to')) ?></span>
            <input type="date" name="to" value="<?= e((string) $filters['to']) ?>">
        </label>
        <label class="field-inline">
            <span><?= e(t('staff.status')) ?></span>
            <select name="status">
                <option value=""><?= e(t('sup.all')) ?></option>
                <?php foreach (['tepat_masa', 'awal', 'lewat', 'luar_kawasan', 'tidak_hadir', 'dibatalkan'] as $st): ?>
                    <option value="<?= e($st) ?>" <?= $filters['status'] === $st ? 'selected' : '' ?>>
                        <?= e(status_label($st)) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="btn" type="submit"><?= e(t('sup.filter')) ?></button>
    </form>

    <?php if ($rows === []): ?>
        <p class="muted"><?= e(t('sup.report_empty')) ?></p>
    <?php else: ?>
        <table class="table table-compact">
            <thead>
                <tr>
                    <th><?= e(t('staff.date')) ?></th>
                    <th><?= e(t('staff.staff')) ?></th>
                    <th><?= e(t('staff.location')) ?></th>
                    <th><?= e(t('checkin.at')) ?></th>
                    <th><?= e(t('staff.status')) ?></th>
                    <th><?= e(t('sup.decision')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $r): ?>
                    <tr>
                        <td><?= e(date('d/m/Y', strtotime((string) $r['tarikh']))) ?></td>
                        <td><?= e((string) $r['staff_name']) ?></td>
                        <td><?= e((string) $r['location_name']) ?></td>
                        <td><?= e(fmt_dt($r['check_in_at'], 'H:i')) ?></td>
                        <td>
                            <span class="pill pill-<?= e(status_class((string) $r['status_kehadiran'])) ?>">
                                <?= e(status_label((string) $r['status_kehadiran'])) ?>
                            </span>
                        </td>
                        <td><?= e((string) ($r['supervisor_decision'] ?? '—')) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
