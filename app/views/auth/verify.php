<?php
$error = $error ?? '';
?>
<section class="auth">
    <div class="card auth-card">
        <h1><?= e(t('auth.verify_title')) ?></h1>
        <p class="muted"><?= e(t('auth.verify_sub')) ?></p>

        <?php if ($error !== ''): ?><div class="flash flash-error"><?= e($error) ?></div><?php endif; ?>

        <form method="post" action="<?= e(url('verify')) ?>" class="stack">
            <?= csrf_field() ?>
            <label class="field">
                <span><?= e(t('auth.code')) ?></span>
                <input class="code-input" type="text" name="code" inputmode="numeric" pattern="[0-9]{6}"
                       maxlength="6" required autofocus autocomplete="one-time-code">
            </label>
            <button class="btn btn-primary btn-block" type="submit"><?= e(t('auth.verify_btn')) ?></button>
        </form>

        <form method="post" action="<?= e(url('resend')) ?>" class="inline-form">
            <?= csrf_field() ?>
            <button class="btn btn-ghost" type="submit"><?= e(t('auth.resend')) ?></button>
        </form>
    </div>
</section>
