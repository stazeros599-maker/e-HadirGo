<?php
/** @var string $content */
/** @var string|null $title */
$u = current_user();
$flashes = take_flashes();
$lang = current_lang();
?>
<!doctype html>
<html lang="<?= e($lang) ?>">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#1f3864">
    <title><?= e(($title ?? '') !== '' ? $title . ' · ' : '') ?>e-HadirGo</title>
    <link rel="manifest" href="<?= e(base_path()) ?>/manifest.webmanifest">
    <link rel="icon" href="<?= e(asset('icon.svg')) ?>" type="image/svg+xml">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" crossorigin="">
    <link rel="stylesheet" href="<?= e(asset('app.css')) ?>?v=7">
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" crossorigin="" defer></script>
</head>
<body>
<header class="topbar">
    <div class="topbar-inner">
        <a class="brand" href="<?= e(url($u ? 'dashboard' : 'login')) ?>">
            <span class="brand-mark">e</span>HadirGo
        </a>

        <?php if ($u !== null): ?>
            <nav class="nav">
                <a href="<?= e(url('dashboard')) ?>"><?= e(t('nav.dashboard')) ?></a>
                <a href="<?= e(url('records')) ?>"><?= e(t('nav.records')) ?></a>
                <?php if (has_role('penyelia') || has_role('super_admin')): ?>
                    <a href="<?= e(url('sup.assignments')) ?>"><?= e(t('nav.assignments')) ?></a>
                    <a href="<?= e(url('sup.map')) ?>"><?= e(t('nav.map')) ?></a>
                    <a href="<?= e(url('sup.review')) ?>"><?= e(t('nav.review')) ?></a>
                    <a href="<?= e(url('sup.report')) ?>"><?= e(t('nav.report')) ?></a>
                <?php endif; ?>
                <?php if (has_role('super_admin')): ?>
                    <a href="<?= e(url('admin.users')) ?>"><?= e(t('nav.admin')) ?></a>
                <?php endif; ?>
            </nav>
        <?php endif; ?>

        <div class="topbar-right">
            <div class="langswitch">
                <a href="<?= e(url($_GET['r'] ?? 'dashboard', ['lang' => 'ms'])) ?>"
                   class="<?= $lang === 'ms' ? 'active' : '' ?>">BM</a>
                <span>/</span>
                <a href="<?= e(url($_GET['r'] ?? 'dashboard', ['lang' => 'en'])) ?>"
                   class="<?= $lang === 'en' ? 'active' : '' ?>">EN</a>
            </div>
            <?php if ($u !== null): ?>
                <a class="avatar" href="<?= e(url('settings')) ?>" title="<?= e((string) $u['name']) ?>">
                    <?= e(mb_strtoupper(mb_substr((string) $u['name'], 0, 1))) ?>
                </a>
                <a class="btn btn-ghost btn-sm" href="<?= e(url('logout')) ?>"><?= e(t('nav.logout')) ?></a>
            <?php endif; ?>
        </div>
    </div>
</header>

<main class="wrap">
    <?php foreach ($flashes as $f): ?>
        <div class="flash flash-<?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
    <?php endforeach; ?>

    <?= $content ?>
</main>

<footer class="foot">
    <span>e-HadirGo · Jabatan Perikanan Sabah</span>
    <span class="muted"><?= e(t('app.version')) ?></span>
</footer>

<script src="<?= e(asset('app.js')) ?>?v=7" defer></script>
</body>
</html>
