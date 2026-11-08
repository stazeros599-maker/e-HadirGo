<?php
$error = $error ?? '';
?>
<section class="auth">
    <div class="card auth-card">
        <h1><?= e(t('auth.signup_title')) ?></h1>
        <p class="muted"><?= e(t('auth.signup_sub')) ?></p>

        <?php if ($error !== ''): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

        <form method="post" action="<?= e(url('signup')) ?>" class="stack">
            <?= csrf_field() ?>

            <label class="field">
                <span><?= e(t('auth.name')) ?></span>
                <input type="text" name="name" value="<?= e($old['name'] ?? '') ?>" required minlength="3" maxlength="120">
            </label>

            <label class="field">
                <span><?= e(t('auth.email')) ?></span>
                <input type="email" name="email" value="<?= e($old['email'] ?? '') ?>" required>
                <small class="muted"><?= e(t('auth.gov_hint')) ?></small>
            </label>

            <label class="field">
                <span><?= e(t('auth.password')) ?></span>
                <input type="password" name="password" required minlength="8" autocomplete="new-password">
                <small class="muted"><?= e(t('auth.password_hint')) ?></small>
            </label>

            <label class="field">
                <span><?= e(t('auth.unit')) ?></span>
                <select name="unit_id" required>
                    <option value=""><?= e(t('auth.choose_unit')) ?></option>
                    <?php foreach ($units as $unit): ?>
                        <option value="<?= (int) $unit['id'] ?>"
                            <?= (string) ($old['unit_id'] ?? '') === (string) $unit['id'] ? 'selected' : '' ?>>
                            <?= e($unit['daerah'] . ' — ' . $unit['bahagian']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </label>

            <?php if (!empty($showDummyConfirm)): ?>
                <div class="flash flash-warn">
                    <?= e(t('auth.dummy_warning')) ?>
                    <label class="check">
                        <input type="checkbox" name="confirm_dummy" value="1">
                        <span><?= e(t('auth.dummy_confirm')) ?></span>
                    </label>
                </div>
            <?php endif; ?>

            <button class="btn btn-primary btn-block" type="submit"><?= e(t('auth.create')) ?></button>
        </form>

        <div class="auth-links">
            <a href="<?= e(url('login')) ?>"><?= e(t('auth.have_account')) ?></a>
        </div>
    </div>
</section>
