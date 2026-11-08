<?php
/** @var array $rows */
$markers = [];
foreach ($rows as $r) {
    $markers[] = [
        'lat'    => (float) $r['lat'],
        'lng'    => (float) $r['lng'],
        'radius' => (int) $r['radius_m'],
        'name'   => (string) $r['staff_name'],
        'place'  => (string) $r['location_name'],
        'time'   => substr((string) $r['start_time'], 0, 5) . '–' . substr((string) $r['end_time'], 0, 5),
        'state'  => $r['check_in_at'] === null
            ? 'pending'
            : (truthy($r['outside_radius']) ? 'outside' : (truthy($r['is_late']) ? 'late' : 'ok')),
    ];
}
?>
<div class="page-head">
    <div>
        <h1><?= e(t('sup.map_title')) ?></h1>
        <p class="muted"><?= e(now_local()->format('d/m/Y')) ?> · <?= count($rows) ?> <?= e(t('sup.tasks')) ?></p>
    </div>
</div>

<section class="card">
    <div id="livemap" class="map map-xl"></div>

    <div class="legend">
        <span><i class="dot dot-ok"></i> <?= e(t('status.on_time')) ?></span>
        <span><i class="dot dot-warn"></i> <?= e(t('status.late')) ?></span>
        <span><i class="dot dot-bad"></i> <?= e(t('status.outside')) ?></span>
        <span><i class="dot dot-muted"></i> <?= e(t('sup.pending')) ?></span>
    </div>
</section>

<?php if ($rows === []): ?>
    <section class="card"><p class="muted"><?= e(t('sup.no_tasks_today')) ?></p></section>
<?php endif; ?>

<script>
window.EHADIR = Object.assign(window.EHADIR || {}, {
    liveMap: <?= json_encode($markers, JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>
});
</script>
