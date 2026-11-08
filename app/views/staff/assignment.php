<?php
/** @var array $assignment */
/** @var array|null $attendance */
/** @var array $participants */
/** @var DateTimeImmutable $graceEnd */
$user = current_user();
$isOwner = (int) $assignment['staff_id'] === (int) $user['id'];

$status = 'dijadualkan';
if ($attendance !== null) {
    $status = truthy($attendance['outside_radius']) || truthy($attendance['location_unavailable'])
        ? 'luar_kawasan'
        : (truthy($attendance['is_late']) ? 'lewat' : (truthy($attendance['is_early']) ? 'awal' : 'tepat_masa'));
} elseif ($assignment['status'] === 'dibatalkan') {
    $status = 'dibatalkan';
}
?>
<div class="page-head">
    <div>
        <a class="back" href="<?= e(url('dashboard')) ?>">← <?= e(t('staff.dashboard')) ?></a>
        <h1><?= e((string) $assignment['purpose']) ?></h1>
        <p class="muted">
            <?= e(date('d/m/Y', strtotime((string) $assignment['assignment_date']))) ?>
            · <?= e(substr((string) $assignment['start_time'], 0, 5)) ?>–<?= e(substr((string) $assignment['end_time'], 0, 5)) ?>
            · <?= e((string) $assignment['supervisor_name']) ?>
        </p>
    </div>
    <span class="pill pill-<?= e(status_class($status)) ?>"><?= e(status_label($status)) ?></span>
</div>

<div class="grid grid-main">
    <section class="card">
        <div id="map"
             class="map"
             data-lat="<?= e((string) $assignment['lat']) ?>"
             data-lng="<?= e((string) $assignment['lng']) ?>"
             data-radius="<?= (int) $assignment['radius_m'] ?>"
             data-label="<?= e((string) $assignment['location_name']) ?>"></div>
        <p class="muted small">
            📍 <?= e((string) $assignment['location_name']) ?> · <?= e(t('staff.radius')) ?> <?= (int) $assignment['radius_m'] ?> m
        </p>
    </section>

    <section class="card">
        <h2><?= e($isOwner ? t('checkin.title') : t('staff.assignment')) ?></h2>

        <?php if (!$isOwner): ?>
            <dl class="kv">
                <dt><?= e(t('staff.staff')) ?></dt><dd><?= e((string) $assignment['staff_name']) ?></dd>
                <dt><?= e(t('staff.date')) ?></dt><dd><?= e((string) $assignment['assignment_date']) ?></dd>
                <dt><?= e(t('staff.radius')) ?></dt><dd><?= (int) $assignment['radius_m'] ?> m</dd>
                <?php if ($attendance !== null): ?>
                    <dt><?= e(t('checkin.at')) ?></dt><dd><?= e(fmt_dt((string) $attendance['check_in_at'])) ?></dd>
                    <?php if ($attendance['check_out_at'] !== null): ?>
                        <dt><?= e(t('checkout.at')) ?></dt><dd><?= e(fmt_dt((string) $attendance['check_out_at'])) ?></dd>
                    <?php endif; ?>
                    <?php if ($attendance['distance_m'] !== null): ?>
                        <dt><?= e(t('checkin.distance')) ?></dt><dd><?= (int) $attendance['distance_m'] ?> m</dd>
                    <?php endif; ?>
                <?php else: ?>
                    <dt><?= e(t('staff.attendance')) ?></dt><dd><?= e(t('assign_status.' . (string) $assignment['status'])) ?></dd>
                <?php endif; ?>
            </dl>

        <?php elseif ($attendance !== null): ?>
            <dl class="kv">
                <dt><?= e(t('checkin.at')) ?></dt><dd><?= e(fmt_dt((string) $attendance['check_in_at'])) ?></dd>
                <?php if ($attendance['distance_m'] !== null): ?>
                    <dt><?= e(t('checkin.distance')) ?></dt>
                    <dd><?= (int) $attendance['distance_m'] ?> m
                        <?php if (truthy($attendance['outside_radius'])): ?>
                            <span class="pill pill-bad"><?= e(t('status.outside')) ?></span>
                        <?php endif; ?>
                    </dd>
                <?php endif; ?>
                <?php if (!empty($attendance['accuracy_m'])): ?>
                    <dt><?= e(t('checkin.accuracy')) ?></dt><dd>±<?= (int) $attendance['accuracy_m'] ?> m</dd>
                <?php endif; ?>
                <?php if (!empty($attendance['outside_note'])): ?>
                    <dt><?= e(t('checkin.note')) ?></dt><dd><?= e((string) $attendance['outside_note']) ?></dd>
                <?php endif; ?>
                <?php if (!empty($attendance['late_reason'])): ?>
                    <dt><?= e(t('checkin.reason')) ?></dt><dd><?= e((string) $attendance['late_reason']) ?></dd>
                <?php endif; ?>
                <?php if ($attendance['check_out_at'] !== null): ?>
                    <dt><?= e(t('checkout.at')) ?></dt><dd><?= e(fmt_dt((string) $attendance['check_out_at'])) ?></dd>
                <?php endif; ?>
            </dl>

            <?php if ($attendance['check_out_at'] === null): ?>
                <form method="post" action="<?= e(url('checkout')) ?>" class="stack" id="checkout-form"
                      data-locating="<?= e(t('checkout.locating')) ?>"
                      data-location-failed="<?= e(t('checkout.location_failed')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="id" value="<?= (int) $assignment['id'] ?>">
                    <input type="hidden" name="lat" id="out-lat">
                    <input type="hidden" name="lng" id="out-lng">
                    <div id="checkout-status" class="geo-status" aria-live="polite"></div>
                    <button class="btn btn-primary btn-block" type="submit"><?= e(t('checkout.btn')) ?></button>
                </form>
            <?php endif; ?>

        <?php elseif ($assignment['status'] === 'dibatalkan'): ?>
            <p class="muted"><?= e(t('checkin.cancelled')) ?></p>

        <?php else: ?>
            <p class="muted">
                <?= e(t('checkin.window_hint')) ?><br>
                <?= e(t('checkin.grace_hint')) ?>
            </p>

            <div id="geo-status" class="geo-status"><?= e(t('checkin.locating')) ?></div>
            <div id="geo-warning" class="flash flash-warn hidden"></div>

            <form method="post" action="<?= e(url('checkin')) ?>" class="stack" id="checkin-form"
                  data-radius="<?= (int) $assignment['radius_m'] ?>"
                  data-target-lat="<?= e((string) $assignment['lat']) ?>"
                  data-target-lng="<?= e((string) $assignment['lng']) ?>">
                <?= csrf_field() ?>
                <input type="hidden" name="id" value="<?= (int) $assignment['id'] ?>">
                <input type="hidden" name="lat" id="in-lat">
                <input type="hidden" name="lng" id="in-lng">
                <input type="hidden" name="accuracy" id="in-accuracy">

                <label class="field">
                    <span><?= e(t('checkin.note')) ?></span>
                    <textarea name="note" rows="3" maxlength="500" placeholder="<?= e(t('checkin.note_ph')) ?>"></textarea>
                    <small class="muted"><?= e(t('checkin.note_hint')) ?></small>
                </label>

                <label class="field">
                    <span><?= e(t('checkin.reason')) ?></span>
                    <textarea name="reason" rows="3" maxlength="500" placeholder="<?= e(t('checkin.reason_ph')) ?>"></textarea>
                    <small class="muted"><?= e(t('checkin.reason_hint')) ?></small>
                </label>

                <button class="btn btn-primary btn-block" type="submit"><?= e(t('checkin.btn')) ?></button>
                <button class="btn btn-ghost btn-block" type="button" id="relocate"><?= e(t('checkin.retry')) ?></button>
            </form>
        <?php endif; ?>
    </section>
</div>

<?php if (count($participants) > 1): ?>
    <section class="card">
        <h2><?= e(t('staff.group_attendance')) ?></h2>
        <table class="table">
            <thead>
                <tr>
                    <th><?= e(t('staff.staff')) ?></th>
                    <th><?= e(t('staff.attendance')) ?></th>
                    <th><?= e(t('checkin.at')) ?></th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($participants as $participant): ?>
                    <?php
                    $participantStatus = (string) $participant['status'];
                    if ($participant['check_in_at'] !== null) {
                        $participantStatus = truthy($participant['outside_radius']) || truthy($participant['location_unavailable'])
                            ? 'luar_kawasan'
                            : (truthy($participant['is_late']) ? 'lewat' : (truthy($participant['is_early']) ? 'awal' : 'tepat_masa'));
                    }
                    ?>
                    <tr>
                        <td><?= e((string) $participant['staff_name']) ?></td>
                        <td><?= e($participant['check_in_at'] === null
                            ? t('staff.not_checked_in')
                            : status_label($participantStatus)) ?></td>
                        <td><?= $participant['check_in_at'] === null
                            ? '—'
                            : e(fmt_dt((string) $participant['check_in_at'])) ?></td>
                        <td class="right">
                            <a class="btn btn-sm" href="<?= e(url('assignment', ['id' => (int) $participant['id']])) ?>">
                                <?= e(t('staff.view')) ?>
                            </a>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </section>
<?php endif; ?>

<?php if ($attendance !== null && truthy($attendance['outside_radius'])): ?>
    <section class="card">
        <h2><?= e(t('checkin.review_state')) ?></h2>
        <p class="muted">
            <?= $attendance['supervisor_decision'] === null
                ? e(t('checkin.pending_review'))
                : e(t('checkin.decision_' . (string) $attendance['supervisor_decision'])) ?>
        </p>
    </section>
<?php endif; ?>
