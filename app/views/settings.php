<?php
/** @var array $units */
/** @var array $channels */
/** @var bool $telegramOk */
/** @var string|null $tgLink */
/** @var string $botName */
$user = current_user();
$tg = $channels['telegram'] ?? null;
$email = $channels['email'] ?? null;
?>
<div class="page-head">
    <div>
        <h1><?= e(t('settings.title')) ?></h1>
        <p class="muted"><?= e((string) $user['email']) ?></p>
    </div>
</div>

<div class="grid grid-2">
    <section class="card">
        <h2><?= e(t('settings.profile')) ?></h2>
        <form method="post" action="<?= e(url('settings.profile')) ?>" class="stack">
            <?= csrf_field() ?>
            <label class="field">
                <span><?= e(t('settings.name')) ?></span>
                <input type="text" name="name" value="<?= e((string) $user['name']) ?>" required minlength="3">
            </label>
            <label class="field">
                <span><?= e(t('settings.phone')) ?></span>
                <input type="tel" name="phone" value="<?= e((string) ($user['phone'] ?? '')) ?>" placeholder="0123456789">
            </label>
            <label class="field">
                <span><?= e(t('settings.email')) ?></span>
                <input type="email" value="<?= e((string) $user['email']) ?>" disabled>
                <small class="muted"><?= e(t('settings.email_hint')) ?></small>
            </label>
            <button class="btn btn-primary" type="submit"><?= e(t('settings.save')) ?></button>
        </form>
    </section>

    <section class="card">
        <h2><?= e(t('settings.unit')) ?></h2>
        <form method="post" action="<?= e(url('settings.unit')) ?>" class="stack">
            <?= csrf_field() ?>
            <label class="field">
                <span><?= e(t('auth.unit')) ?></span>
                <select name="unit_id">
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= (int) $unit['id'] ?>"
                            <?= (int) $user['unit_id'] === (int) $unit['id'] ? 'selected' : '' ?>>
                            <?= e($unit['daerah'] . ' — ' . $unit['bahagian']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <small class="muted"><?= e(t('settings.unit_hint')) ?></small>
            </label>
            <button class="btn btn-primary" type="submit"><?= e(t('settings.save')) ?></button>
        </form>

        <hr>

        <h3><?= e(t('settings.language')) ?></h3>
        <form method="post" action="<?= e(url('settings.language')) ?>" class="row">
            <?= csrf_field() ?>
            <select name="language">
                <option value="ms" <?= current_lang() === 'ms' ? 'selected' : '' ?>>Bahasa Melayu</option>
                <option value="en" <?= current_lang() === 'en' ? 'selected' : '' ?>>English</option>
            </select>
            <button class="btn" type="submit"><?= e(t('settings.save')) ?></button>
        </form>
    </section>
</div>

<section class="card" id="notifications">
    <h2><?= e(t('settings.notifications')) ?></h2>

    <div class="channel">
        <div>
            <strong>Telegram</strong>
            <?php if ($tg !== null && truthy($tg['verified'])): ?>
                <span class="pill pill-ok"><?= e(t('settings.linked')) ?></span>
                <div class="muted small">chat <?= e((string) $tg['identifier']) ?></div>
            <?php else: ?>
                <span class="pill pill-muted"><?= e(t('settings.not_linked')) ?></span>
                <div class="muted small"><?= e(t('settings.tg_hint')) ?></div>
            <?php endif; ?>
        </div>

        <div class="row">
            <form method="post" action="<?= e(url('settings.test.telegram')) ?>">
                <?= csrf_field() ?>
                <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('settings.telegram_test')) ?></button>
            </form>
            <?php if ($tg !== null && truthy($tg['verified'])): ?>
                <form method="post" action="<?= e(url('settings.telegram.unlink')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('settings.unlink')) ?></button>
                </form>
            <?php else: ?>
                <form method="post" action="<?= e(url('settings.telegram.link')) ?>">
                    <?= csrf_field() ?>
                    <button class="btn btn-primary btn-sm" type="submit" <?= $telegramOk ? '' : 'disabled' ?>>
                        <?= e(t('settings.link')) ?>
                    </button>
                </form>
            <?php endif; ?>
        </div>
    </div>

    <?php if (!empty($tgLink)): ?>
        <div class="flash flash-info">
            <?= e(t('settings.tg_open')) ?>
            <a class="btn btn-sm btn-primary" href="<?= e($tgLink) ?>" target="_blank" rel="noopener">Telegram</a>
            <div class="muted small"><?= e($tgLink) ?></div>
        </div>
    <?php endif; ?>

    <div class="channel">
        <div>
            <strong>E-mel</strong>
            <span class="pill pill-<?= ($email !== null && truthy($email['verified'])) ? 'ok' : 'muted' ?>">
                <?= e($email !== null ? (string) $email['identifier'] : (string) $user['email']) ?>
            </span>
            <div class="muted small"><?= e(t('settings.email_channel_hint')) ?></div>
        </div>
        <form method="post" action="<?= e(url('settings.test.email')) ?>">
            <?= csrf_field() ?>
            <button class="btn btn-ghost btn-sm" type="submit"><?= e(t('settings.email_test')) ?></button>
        </form>
    </div>

    <?php if (!$telegramOk): ?>
        <p class="muted small"><?= e(t('settings.no_bot')) ?></p>
    <?php endif; ?>
</section>

<section class="card">
    <h2><?= e(t('settings.security')) ?></h2>
    <form method="post" action="<?= e(url('settings.password')) ?>" class="stack">
        <?= csrf_field() ?>
        <label class="field">
            <span><?= e(t('settings.current_password')) ?></span>
            <input type="password" name="current_password" required autocomplete="current-password">
        </label>
        <label class="field">
            <span><?= e(t('settings.new_password')) ?></span>
            <input type="password" name="new_password" required minlength="8" autocomplete="new-password">
        </label>
        <button class="btn btn-primary" type="submit"><?= e(t('settings.change_password')) ?></button>
    </form>
</section>
