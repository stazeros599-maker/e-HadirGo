<?php
$error = $error ?? '';
?>
<section class="auth">
    <div class="card auth-card">
        <h1><?= e(t('auth.login_title')) ?></h1>
        <p class="muted"><?= e(t('auth.login_sub')) ?></p>

        <?php if ($error !== ''): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

        <form method="post" action="<?= e(url('login')) ?>" class="stack">
            <?= csrf_field() ?>
            <label class="field">
                <span><?= e(t('auth.email')) ?></span>
                <input type="email" name="email" value="<?= e($email ?? '') ?>" required autofocus autocomplete="username">
            </label>
            <label class="field">
                <span><?= e(t('auth.password')) ?></span>
                <input type="password" name="password" required autocomplete="current-password">
            </label>
            <button class="btn btn-primary btn-block" type="submit"><?= e(t('auth.signin')) ?></button>
        </form>

        <div class="auth-links">
            <a href="<?= e(url('forgot')) ?>"><?= e(t('auth.forgot_link')) ?></a>
            <a href="<?= e(url('signup')) ?>"><?= e(t('auth.create_link')) ?></a>
        </div>
    </div>
</section>
