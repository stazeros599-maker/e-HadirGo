<?php
/** @var array $rows */
?>
<div class="page-head">
    <div>
        <h1><?= e(t('admin.users')) ?></h1>
        <p class="muted"><?= e(t('admin.users_sub')) ?></p>
    </div>
    <a class="btn btn-ghost" href="<?= e(url('admin.logs')) ?>"><?= e(t('admin.logs')) ?></a>
</div>

<section class="card">
    <table class="table">
        <thead>
            <tr>
                <th><?= e(t('auth.name')) ?></th>
                <th><?= e(t('auth.email')) ?></th>
                <th><?= e(t('auth.unit')) ?></th>
                <th><?= e(t('admin.roles')) ?></th>
                <th></th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rows as $u): ?>
                <?php $isSupervisor = in_array('penyelia', $u['roles'], true); ?>
                <tr>
                    <td>
                        <?= e((string) $u['name']) ?>
                        <?php if (truthy($u['is_dummy'])): ?>
                            <span class="pill pill-muted"><?= e(t('admin.dummy')) ?></span>
                        <?php endif; ?>
                    </td>
                    <td><?= e((string) $u['email']) ?></td>
                    <td><?= e(trim((string) ($u['daerah'] ?? '') . ' / ' . (string) ($u['bahagian'] ?? ''), ' /')) ?></td>
                    <td>
                        <?php foreach ($u['roles'] as $role): ?>
                            <span class="pill pill-info"><?= e($role) ?></span>
                        <?php endforeach; ?>
                    </td>
                    <td class="right">
                        <?php if (!in_array('super_admin', $u['roles'], true)): ?>
                            <form method="post" action="<?= e(url('admin.roles')) ?>">
                                <?= csrf_field() ?>
                                <input type="hidden" name="user_id" value="<?= (int) $u['id'] ?>">
                                <?php if ($isSupervisor): ?>
                                    <input type="hidden" name="action" value="revoke">
                                    <button class="btn btn-sm btn-ghost" type="submit"><?= e(t('admin.revoke')) ?></button>
                                <?php else: ?>
                                    <input type="hidden" name="action" value="grant">
                                    <button class="btn btn-sm" type="submit"><?= e(t('admin.grant')) ?></button>
                                <?php endif; ?>
                            </form>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</section>
