<?php
/** @var array $rows */
?>
<div class="page-head">
    <div>
        <h1><?= e(t('sup.review_title')) ?></h1>
        <p class="muted"><?= e(t('sup.review_sub')) ?></p>
    </div>
</div>

<?php if ($rows === []): ?>
    <section class="card">
        <p class="muted"><?= e(t('sup.review_empty')) ?></p>
    </section>
<?php else: ?>
    <?php foreach ($rows as $r): ?>
        <section class="card review">
            <div class="review-head">
                <div>
                    <h3><?= e((string) $r['staff_name']) ?> — <?= e((string) $r['location_name']) ?></h3>
                    <p class="muted small">
                        <?= e(date('d/m/Y', strtotime((string) $r['assignment_date']))) ?>
                        · <?= e((string) ($r['daerah'] ?? '')) ?> / <?= e((string) ($r['bahagian'] ?? '')) ?>
                        · <?= e(fmt_dt((string) $r['check_in_at'])) ?>
                    </p>
                </div>
                <div>
                    <?php if (truthy($r['outside_radius'])): ?>
                        <span class="pill pill-bad"><?= e(t('status.outside')) ?></span>
                    <?php endif; ?>
                    <?php if (truthy($r['is_late'])): ?>
                        <span class="pill pill-warn"><?= e(t('status.late')) ?></span>
                    <?php endif; ?>
                    <?php if (truthy($r['location_unavailable'])): ?>
                        <span class="pill pill-muted"><?= e(t('checkin.no_gps')) ?></span>
                    <?php endif; ?>
                </div>
            </div>

            <dl class="kv">
                <dt><?= e(t('checkin.distance')) ?></dt>
                <dd><?= $r['distance_m'] !== null ? (int) $r['distance_m'] . ' m / ' . (int) $r['radius_m'] . ' m' : '—' ?></dd>
                <dt><?= e(t('checkin.accuracy')) ?></dt>
                <dd><?= $r['accuracy_m'] !== null ? '±' . (int) $r['accuracy_m'] . ' m' : '—' ?></dd>
                <dt><?= e(t('checkin.note')) ?></dt>
                <dd><?= e((string) ($r['outside_note'] ?? '—')) ?></dd>
                <dt><?= e(t('checkin.reason')) ?></dt>
                <dd><?= e((string) ($r['late_reason'] ?? '—')) ?></dd>
            </dl>

            <form method="post" action="<?= e(url('sup.decide')) ?>" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="assignment_id" value="<?= (int) $r['assignment_id'] ?>">
                <label class="field">
                    <span><?= e(t('sup.supervisor_note')) ?></span>
                    <input type="text" name="note" maxlength="255"
                           placeholder="<?= e(t('sup.note_ph')) ?>">
                </label>
                <div class="row">
                    <button class="btn btn-primary" type="submit" name="decision" value="terima">
                        <?= e(t('sup.accept')) ?>
                    </button>
                    <button class="btn btn-ghost" type="submit" name="decision" value="tolak">
                        <?= e(t('sup.reject')) ?>
                    </button>
                </div>
            </form>
        </section>
    <?php endforeach; ?>
<?php endif; ?>
