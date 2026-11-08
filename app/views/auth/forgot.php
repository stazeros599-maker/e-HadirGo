<section class="auth">
    <div class="card auth-card">
        <h1><?= e(t('auth.forgot_title')) ?></h1>

        <?php if (!empty($sent)): ?>
            <div class="flash flash-ok"><?= e(t('auth.forgot_sent')) ?></div>
            <a class="btn btn-ghost btn-block" href="<?= e(url('login')) ?>"><?= e(t('auth.back_login')) ?></a>
        <?php else: ?>
            <p class="muted"><?= e(t('auth.forgot_sub')) ?></p>
            <form method="post" action="<?= e(url('forgot')) ?>" class="stack">
                <?= csrf_field() ?>
                <label class="field">
                    <span><?= e(t('auth.email')) ?></span>
                    <input type="email" name="email" required autofocus>
                </label>
                <button class="btn btn-primary btn-block" type="submit"><?= e(t('auth.send_link')) ?></button>
            </form>
        <?php endif; ?>
    </div>
</section>
