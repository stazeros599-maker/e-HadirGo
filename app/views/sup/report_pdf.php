<?php
/** @var array $rows */
/** @var array $filters */
/** @var DateTimeImmutable $generated */
/** @var string $author */
?>
<style>
    .pdf-report { color: #172033; }
    .pdf-report-head { border-bottom: 3px solid #1f3864; margin-bottom: 20px; padding-bottom: 14px; }
    .pdf-report-head h1 { color: #1f3864; margin: 0 0 6px; }
    .pdf-report-meta { color: #526579; font-size: 13px; }
    .pdf-report-actions { display: flex; gap: 8px; margin-bottom: 18px; }
    .pdf-report table { border-collapse: collapse; font-size: 12px; width: 100%; }
    .pdf-report th { background: #1f3864; color: #fff; text-align: left; }
    .pdf-report th, .pdf-report td { border: 1px solid #d5dce5; padding: 7px 8px; vertical-align: top; }
    .pdf-report tbody tr:nth-child(even) { background: #f3f6fa; }
    .pdf-report .report-status { font-weight: 700; white-space: nowrap; }
    .pdf-report .report-foot { border-top: 1px solid #d5dce5; color: #526579; font-size: 11px; margin-top: 16px; padding-top: 8px; }
    @media print {
        @page { size: landscape; margin: 12mm; }
        body { background: #fff !important; color: #172033; }
        .topbar, .foot, .pdf-report-actions { display: none !important; }
        .wrap { margin: 0 !important; max-width: none !important; padding: 0 !important; }
        .pdf-report-head { break-after: avoid; }
        .pdf-report table { font-size: 9px; }
        .pdf-report th, .pdf-report td { padding: 5px; }
        .pdf-report thead { display: table-header-group; }
        .pdf-report tr { break-inside: avoid; }
        .pdf-report tbody tr:nth-child(even) { print-color-adjust: exact; -webkit-print-color-adjust: exact; }
    }
</style>

<section class="pdf-report">
    <div class="pdf-report-actions">
        <button class="btn btn-primary" type="button" onclick="window.print()">
            <?= e(t('sup.pdf_print')) ?>
        </button>
        <a class="btn btn-ghost" href="<?= e(url('sup.report', array_filter([
            'from' => $filters['from'],
            'to' => $filters['to'],
            'status' => $filters['status'],
        ], static fn ($value): bool => $value !== ''))) ?>">
            <?= e(t('sup.report_title')) ?>
        </a>
    </div>

    <header class="pdf-report-head">
        <h1><?= e(t('sup.report_title')) ?></h1>
        <div class="pdf-report-meta">
            <?= e(t('sup.from')) ?>: <?= e($filters['from'] !== '' ? date('d/m/Y', strtotime($filters['from'])) : '—') ?>
            · <?= e(t('sup.to')) ?>: <?= e($filters['to'] !== '' ? date('d/m/Y', strtotime($filters['to'])) : '—') ?>
            · <?= count($rows) ?> <?= e(t('sup.rows')) ?>
        </div>
        <div class="pdf-report-meta">
            <?= e(t('sup.pdf_generated', [
                'date' => $generated->format('d/m/Y H:i'),
                'name' => $author,
            ])) ?>
        </div>
    </header>

    <?php if ($rows === []): ?>
        <p><?= e(t('sup.pdf_empty')) ?></p>
    <?php else: ?>
        <table>
            <thead>
                <tr>
                    <th><?= e(t('staff.date')) ?></th>
                    <th><?= e(t('staff.time')) ?></th>
                    <th><?= e(t('staff.staff')) ?></th>
                    <th><?= e(t('auth.unit')) ?></th>
                    <th><?= e(t('staff.location')) ?></th>
                    <th><?= e(t('staff.status')) ?></th>
                    <th><?= e(t('checkin.at')) ?></th>
                    <th><?= e(t('checkout.at')) ?></th>
                    <th><?= e(t('checkin.distance')) ?></th>
                    <th><?= e(t('sup.decision')) ?></th>
                    <th><?= e(t('sup.pdf_notes')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($rows as $row): ?>
                    <tr>
                        <td><?= e(date('d/m/Y', strtotime((string) $row['tarikh']))) ?></td>
                        <td><?= e(substr((string) $row['masa_mula'], 0, 5)) ?></td>
                        <td><?= e((string) $row['staff_name']) ?></td>
                        <td><?= e(trim((string) ($row['daerah'] ?? '') . ' / ' . (string) ($row['bahagian'] ?? ''), ' /')) ?></td>
                        <td><?= e((string) $row['location_name']) ?></td>
                        <td class="report-status"><?= e(status_label((string) $row['status_kehadiran'])) ?></td>
                        <td><?= e(fmt_dt($row['check_in_at'], 'H:i')) ?></td>
                        <td><?= e(fmt_dt($row['check_out_at'], 'H:i')) ?></td>
                        <td><?= $row['distance_m'] !== null ? (int) $row['distance_m'] . ' m' : '—' ?></td>
                        <td><?= e((string) ($row['supervisor_decision'] ?? '—')) ?></td>
                        <td>
                            <?php if (!empty($row['late_reason'])): ?>
                                <div><strong><?= e(t('checkin.reason')) ?>:</strong> <?= e((string) $row['late_reason']) ?></div>
                            <?php endif; ?>
                            <?php if (!empty($row['outside_note'])): ?>
                                <div><strong><?= e(t('checkin.note')) ?>:</strong> <?= e((string) $row['outside_note']) ?></div>
                            <?php endif; ?>
                            <?php if (empty($row['late_reason']) && empty($row['outside_note'])): ?>—<?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>

    <footer class="report-foot">e-HadirGo · Jabatan Perikanan Sabah</footer>
</section>
