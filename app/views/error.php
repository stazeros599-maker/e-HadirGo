<?php
/** @var int $code */
/** @var string $message */
?>
<section class="error-page">
    <div class="error-code"><?= (int) $code ?></div>
    <p class="muted"><?= e($message) ?></p>
    <a class="btn btn-primary" href="<?= e(url(current_user() ? 'dashboard' : 'login')) ?>">
        <?= e(t('err.back')) ?>
    </a>
</section>
