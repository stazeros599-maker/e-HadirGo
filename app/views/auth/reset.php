<?php
$error = $error ?? '';
?>
<section class="auth">
    <div class="card auth-card">
        <h1><?= e(t('auth.reset_title')) ?></h1>

        <?php if (!empty($done)): ?>
            <div class="flash flash-ok"><?= e(t('auth.reset_done')) ?></div>
            <a class="btn btn-primary btn-block" href="<?= e(url('login')) ?>"><?= e(t('auth.back_login')) ?></a>
        <?php else: ?>
            <?php if ($error !== ''): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>
            <form method="post" action="<?= e(url('reset')) ?>" class="stack">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token ?? '') ?>">
                <label class="field">
                    <span><?= e(t('auth.new_password')) ?></span>
                    <input type="password" name="password" required minlength="8" autocomplete="new-password">
                </label>
                <label class="field">
                    <span><?= e(t('auth.confirm_password')) ?></span>
                    <input type="password" name="password_confirm" required minlength="8" autocomplete="new-password">
                </label>
                <button class="btn btn-primary btn-block" type="submit"><?= e(t('auth.reset_btn')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</section>
