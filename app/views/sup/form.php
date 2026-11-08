<?php
/** @var array|null $assignment */
/** @var array $staff */
/** @var array $locations */
/** @var string $today */
$a = $assignment;
?>
<div class="page-head">
    <div>
        <a class="back" href="<?= e(url('sup.assignments')) ?>">← <?= e(t('sup.title')) ?></a>
        <h1><?= e($a === null ? t('sup.new') : t('sup.edit')) ?></h1>
    </div>
</div>

<form method="post" action="<?= e(url('sup.form', $a !== null ? ['id' => (int) $a['id']] : [])) ?>">
    <?= csrf_field() ?>

    <div class="grid grid-main">
        <section class="card">
            <div class="grid grid-2">
                <?php if ($a === null): ?>
                    <div class="field" id="participants-picker"
                         data-empty="<?= e(t('sup.participants_none')) ?>"
                         data-confirmed="<?= e(t('sup.participants_confirmed')) ?>"
                         data-needed="<?= e(t('sup.participants_needed')) ?>"
                         data-no-match="<?= e(t('sup.participants_no_match')) ?>">
                        <span><?= e(t('sup.participants')) ?></span>
                        <button class="btn btn-ghost" type="button" id="participants-open">
                            <?= e(t('sup.choose_participants')) ?>
                        </button>
                        <p class="muted small" id="participants-summary" aria-live="polite">
                            <?= e(t('sup.participants_none')) ?>
                        </p>
                        <div id="participants-values"></div>
                        <input type="hidden" name="participants_confirmed" id="participants-confirmed" value="0">
                    </div>

                    <div class="picker-dialog hidden" id="participants-dialog"
                         role="dialog" aria-modal="true" aria-hidden="true"
                         aria-labelledby="participants-dialog-title" tabindex="-1">
                        <section class="picker-dialog-panel">
                            <div class="picker-dialog-head">
                                <h2 id="participants-dialog-title"><?= e(t('sup.choose_participants')) ?></h2>
                                <button class="btn btn-ghost btn-sm" type="button" id="participants-cancel">
                                    <?= e(t('sup.cancel_participants')) ?>
                                </button>
                            </div>
                            <label class="field">
                                <span><?= e(t('sup.search_staff')) ?></span>
                                <input type="search" id="participants-search" autocomplete="off">
                            </label>
                            <div class="participant-list" id="participant-list">
                                <?php foreach ($staff as $s): ?>
                                    <label class="participant-option">
                                        <input type="checkbox" value="<?= (int) $s['id'] ?>">
                                        <span>
                                            <strong><?= e($s['name']) ?></strong>
                                            <small><?= e((string) ($s['daerah'] ?? '')) ?><?= !empty($s['bahagian']) ? ' — ' . e((string) $s['bahagian']) : '' ?></small>
                                        </span>
                                    </label>
                                <?php endforeach; ?>
                                <p class="muted small hidden" id="participants-no-match">
                                    <?= e(t('sup.participants_no_match')) ?>
                                </p>
                            </div>
                            <div class="picker-dialog-actions">
                                <button class="btn btn-primary" type="button" id="participants-confirm">
                                    <?= e(t('sup.confirm_participants')) ?>
                                </button>
                            </div>
                        </section>
                    </div>
                <?php else: ?>
                    <label class="field">
                        <span><?= e(t('staff.staff')) ?></span>
                        <select name="staff_id" required>
                            <option value=""><?= e(t('sup.choose_staff')) ?></option>
                            <?php foreach ($staff as $s): ?>
                                <option value="<?= (int) $s['id'] ?>"
                                    <?= (int) $a['staff_id'] === (int) $s['id'] ? 'selected' : '' ?>>
                                    <?= e($s['name']) ?> — <?= e((string) ($s['daerah'] ?? '')) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </label>
                <?php endif; ?>

                <label class="field">
                    <span><?= e(t('sup.purpose')) ?></span>
                    <input type="text" name="purpose" required maxlength="255"
                           value="<?= e((string) ($a['purpose'] ?? '')) ?>" placeholder="<?= e(t('sup.purpose_ph')) ?>">
                </label>

                <label class="field">
                    <span><?= e(t('staff.date')) ?></span>
                    <input type="date" name="assignment_date" required
                           value="<?= e((string) ($a['assignment_date'] ?? $today)) ?>">
                </label>

                <div class="row">
                    <label class="field">
                        <span><?= e(t('sup.start')) ?></span>
                        <input type="time" name="start_time" required
                               value="<?= e(substr((string) ($a['start_time'] ?? '08:00'), 0, 5)) ?>">
                    </label>
                    <label class="field">
                        <span><?= e(t('sup.end')) ?></span>
                        <input type="time" name="end_time" required
                               value="<?= e(substr((string) ($a['end_time'] ?? '12:00'), 0, 5)) ?>">
                    </label>
                </div>
            </div>

            <hr>

            <label class="field">
                <span><?= e(t('sup.saved_location')) ?></span>
                <select id="saved-location">
                    <option value=""><?= e(t('sup.choose_saved')) ?></option>
                    <?php foreach ($locations as $loc): ?>
                        <option value="<?= e((string) $loc['id']) ?>"
                                data-name="<?= e((string) $loc['name']) ?>"
                                data-lat="<?= e((string) $loc['lat']) ?>"
                                data-lng="<?= e((string) $loc['lng']) ?>"
                                data-radius="<?= (int) $loc['radius_m'] ?>">
                            <?= e((string) $loc['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <div class="field place-search" id="place-search"
                 data-endpoint="<?= e(url('sup.places')) ?>"
                 data-searching="<?= e(t('sup.place_searching')) ?>"
                 data-too-short="<?= e(t('sup.place_search_min')) ?>"
                 data-empty="<?= e(t('sup.place_search_empty')) ?>"
                 data-error="<?= e(t('sup.place_search_error')) ?>"
                 data-google-open="<?= e(t('sup.google_manual_open')) ?>"
                 data-google-title="<?= e(t('sup.google_manual_title')) ?>"
                 data-google-help="<?= e(t('sup.google_manual_help')) ?>"
                 data-google-paste="<?= e(t('sup.google_manual_paste')) ?>"
                 data-google-placeholder="<?= e(t('sup.google_manual_ph')) ?>"
                 data-google-confirm="<?= e(t('sup.google_manual_confirm')) ?>"
                 data-google-invalid="<?= e(t('sup.google_manual_invalid')) ?>"
                 data-google-applied="<?= e(t('sup.google_manual_applied')) ?>">
                <label for="place-query"><?= e(t('sup.place_search')) ?></label>
                <div class="row">
                    <input type="search" id="place-query" maxlength="150"
                           placeholder="<?= e(t('sup.place_search_ph')) ?>">
                    <button class="btn btn-ghost" type="button" id="place-search-btn">
                        <?= e(t('sup.place_search_btn')) ?>
                    </button>
                </div>
                <div id="place-results" class="place-results hidden" aria-live="polite"></div>
                <section id="google-place-fallback" class="google-place-fallback hidden">
                    <h3 id="google-place-title"></h3>
                    <p class="muted small" id="google-place-help"></p>
                    <a class="btn btn-ghost btn-sm" id="google-maps-link"
                       href="https://www.google.com/maps/search/" target="_blank" rel="noopener noreferrer"></a>
                    <label class="field">
                        <span id="google-place-paste-label"></span>
                        <input type="text" id="google-place-value" maxlength="1000" autocomplete="off">
                    </label>
                    <button class="btn btn-primary" type="button" id="google-place-confirm"></button>
                    <p class="small hidden" id="google-place-status" aria-live="polite"></p>
                </section>
            </div>

            <div class="grid grid-2">
                <label class="field">
                    <span><?= e(t('staff.location')) ?></span>
                    <input type="text" name="location_name" id="location_name" required maxlength="150"
                           value="<?= e((string) ($a['location_name'] ?? '')) ?>" placeholder="<?= e(t('sup.location_ph')) ?>">
                </label>
                <label class="field">
                    <span><?= e(t('staff.radius')) ?></span>
                    <select name="radius_m" id="radius_m">
                        <?php foreach ([100, 250, 500, 1000, 2000] as $r): ?>
                            <option value="<?= $r ?>" <?= (int) ($a['radius_m'] ?? 250) === $r ? 'selected' : '' ?>>
                                <?= $r >= 1000 ? ($r / 1000) . ' km' : $r . ' m' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </label>
            </div>

            <p class="muted small"><?= e(t('sup.map_hint')) ?></p>
        </section>

        <section class="card">
            <div id="picker"
                 class="map map-tall"
                 data-lat="<?= e((string) ($a['lat'] ?? '5.9804')) ?>"
                 data-lng="<?= e((string) ($a['lng'] ?? '116.0735')) ?>"
                 data-radius="<?= (int) ($a['radius_m'] ?? 250) ?>"
                 data-unavailable="<?= e(t('sup.map_unavailable')) ?>"></div>

            <input type="hidden" name="lat" id="lat" value="<?= e((string) ($a['lat'] ?? '')) ?>">
            <input type="hidden" name="lng" id="lng" value="<?= e((string) ($a['lng'] ?? '')) ?>">

            <p class="muted small" id="picker-info">
                <?= e($a !== null ? (string) $a['lat'] . ', ' . (string) $a['lng'] : t('sup.pin_first')) ?>
            </p>

            <button class="btn btn-ghost btn-block" type="button" id="use-location"><?= e(t('sup.use_location')) ?></button>
        </section>
    </div>

    <div class="actions">
        <a class="btn btn-ghost" href="<?= e(url('sup.assignments')) ?>"><?= e(t('sup.cancel')) ?></a>
        <button class="btn btn-primary" type="submit"><?= e(t('sup.save')) ?></button>
    </div>
</form>
