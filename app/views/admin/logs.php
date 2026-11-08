<?php
/** @var array $notifications */
/** @var array $audit */
?>
<div class="page-head">
    <div>
        <h1><?= e(t('admin.logs')) ?></h1>
        <p class="muted"><?= e(t('admin.logs_sub')) ?></p>
    </div>
    <a class="btn btn-ghost" href="<?= e(url('admin.users')) ?>"><?= e(t('admin.users')) ?></a>
</div>

<section class="card">
    <h2><?= e(t('admin.deliveries')) ?></h2>
    <?php if ($notifications === []): ?>
        <p class="muted">—</p>
    <?php else: ?>
        <table class="table table-compact">
            <thead>
                <tr>
                    <th><?= e(t('staff.date')) ?></th>
                    <th><?= e(t('staff.staff')) ?></th>
                    <th><?= e(t('admin.event')) ?></th>
                    <th><?= e(t('admin.channel')) ?></th>
                    <th><?= e(t('staff.status')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($notifications as $n): ?>
                    <tr>
                        <td><?= e(fmt_dt((string) $n['created_at'])) ?></td>
                        <td><?= e((string) ($n['user_name'] ?? '—')) ?></td>
                        <td><?= e((string) $n['event_code']) ?></td>
                        <td><?= e((string) $n['channel_type']) ?></td>
                        <td>
                            <span class="pill pill-<?= $n['status'] === 'sent' ? 'ok' : ($n['status'] === 'failed' ? 'bad' : 'muted') ?>">
                                <?= e((string) $n['status']) ?>
                            </span>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>

<section class="card">
    <h2><?= e(t('admin.audit')) ?></h2>
    <?php if ($audit === []): ?>
        <p class="muted">—</p>
    <?php else: ?>
        <table class="table table-compact">
            <thead>
                <tr>
                    <th><?= e(t('staff.date')) ?></th>
                    <th><?= e(t('staff.staff')) ?></th>
                    <th><?= e(t('admin.action')) ?></th>
                    <th><?= e(t('admin.entity')) ?></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($audit as $a): ?>
                    <tr>
                        <td><?= e(fmt_dt((string) $a['created_at'])) ?></td>
                        <td><?= e((string) ($a['actor_name'] ?? 'system')) ?></td>
                        <td><?= e((string) $a['action']) ?></td>
                        <td><?= e((string) $a['entity']) ?> #<?= (int) ($a['entity_id'] ?? 0) ?></td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    <?php endif; ?>
</section>
