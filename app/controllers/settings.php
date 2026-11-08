<?php

declare(strict_types=1);

/**
 * Settings: profile, unit, language, notification channels, password.
 */

function settings_page(): void
{
    require_login();
    $user = current_user();

    $channels = db_all('SELECT * FROM notification_channels WHERE user_id = ?', [(int) $user['id']]);
    $byType = [];
    foreach ($channels as $c) {
        $byType[$c['channel_type']] = $c;
    }

    page('settings', [
        'title'      => t('settings.title'),
        'units'      => units_all(),
        'channels'   => $byType,
        'telegramOk' => config('TELEGRAM_BOT_TOKEN') !== null,
        'tgLink'     => $_SESSION['tg_link'] ?? null,
        'botName'    => config('TELEGRAM_BOT_USERNAME', ''),
    ]);

    unset($_SESSION['tg_link']);
}

function settings_profile(): void
{
    require_login();
    csrf_verify();

    $user = current_user();
    $name = trim((string) ($_POST['name'] ?? ''));
    $phone = trim((string) ($_POST['phone'] ?? ''));

    if (mb_strlen($name) < 3) {
        flash('error', t('settings.err_name'));
        redirect(url('settings'));
    }

    db_exec('UPDATE users SET name = ?, phone = ?, updated_at = ? WHERE id = ?', [
        $name,
        $phone !== '' ? $phone : null,
        gmdate('Y-m-d H:i:s'),
        (int) $user['id'],
    ]);

    audit_write((int) $user['id'], 'profile_update', 'users', (int) $user['id'], ['name' => $user['name'], 'phone' => $user['phone']], ['name' => $name, 'phone' => $phone]);
    flash('ok', t('settings.saved'));
    redirect(url('settings'));
}

function settings_unit(): void
{
    require_login();
    csrf_verify();

    $user = current_user();
    $unitId = (int) ($_POST['unit_id'] ?? 0);
    $newUnit = unit_by_id($unitId);

    if ($newUnit === null) {
        flash('error', t('settings.err_unit'));
        redirect(url('settings'));
    }

    if ($unitId === (int) $user['unit_id']) {
        redirect(url('settings'));
    }

    $oldUnit = $user['unit_id'] !== null ? unit_by_id((int) $user['unit_id']) : null;

    db_exec('UPDATE users SET unit_id = ?, updated_at = ? WHERE id = ?', [
        $unitId,
        gmdate('Y-m-d H:i:s'),
        (int) $user['id'],
    ]);

    audit_write((int) $user['id'], 'unit_change', 'users', (int) $user['id'], $oldUnit, $newUnit);

    $admins = db_all(
        'SELECT u.id, u.name FROM users u
           JOIN user_roles r ON r.user_id = u.id
          WHERE r.role_code = ? AND u.is_active',
        ['super_admin']
    );

    foreach ($admins as $admin) {
        notify_event((int) $admin['id'], 'unit_changed', [
            'staff' => (string) $user['name'],
            'from'  => $oldUnit !== null ? $oldUnit['daerah'] . ' / ' . $oldUnit['bahagian'] : '—',
            'to'    => $newUnit['daerah'] . ' / ' . $newUnit['bahagian'],
        ]);
    }

    flash('ok', t('settings.unit_saved'));
    redirect(url('settings'));
}

function settings_language(): void
{
    require_login();
    csrf_verify();

    $lang = (string) ($_POST['language'] ?? 'ms');
    if (!in_array($lang, ['ms', 'en'], true)) {
        $lang = 'ms';
    }

    $_SESSION['lang'] = $lang;
    db_exec('UPDATE users SET language = ?, updated_at = ? WHERE id = ?', [
        $lang,
        gmdate('Y-m-d H:i:s'),
        (int) current_user()['id'],
    ]);
    flash('ok', t('settings.saved'));
    redirect(url('settings'));
}

function settings_password(): void
{
    require_login();
    csrf_verify();

    $user = current_user();
    $current = (string) ($_POST['current_password'] ?? '');
    $new = (string) ($_POST['new_password'] ?? '');

    if (!password_verify($current, (string) $user['password_hash'])) {
        flash('error', t('settings.err_current_password'));
        redirect(url('settings'));
    }

    if (password_problem($new) !== null) {
        flash('error', t('auth.err.password_weak'));
        redirect(url('settings'));
    }

    db_exec('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [
        password_hash($new, PASSWORD_DEFAULT),
        gmdate('Y-m-d H:i:s'),
        (int) $user['id'],
    ]);

    audit_write((int) $user['id'], 'password_change', 'users', (int) $user['id']);
    flash('ok', t('settings.password_saved'));
    redirect(url('settings'));
}

function settings_telegram_link(): void
{
    require_login();
    csrf_verify();

    $bot = (string) config('TELEGRAM_BOT_USERNAME', '');
    if ($bot === '') {
        flash('error', t('settings.err_bot'));
        redirect(url('settings'));
    }

    $token = telegram_link_token((int) current_user()['id']);
    $_SESSION['tg_link'] = 'https://t.me/' . $bot . '?start=' . $token;

    redirect(url('settings') . '#notifications');
}

function settings_telegram_unlink(): void
{
    require_login();
    csrf_verify();

    db_exec('DELETE FROM notification_channels WHERE user_id = ? AND channel_type = ?', [
        (int) current_user()['id'],
        'telegram',
    ]);

    flash('ok', t('settings.tg_unlinked'));
    redirect(url('settings') . '#notifications');
}

function settings_test_email(): void
{
    require_login();
    csrf_verify();

    $user = current_user();
    settings_send_test_message($user, 'email', (string) $user['email']);
}

function settings_test_telegram(): void
{
    require_login();
    csrf_verify();

    $user = current_user();
    $channel = db_one(
        'SELECT identifier FROM notification_channels WHERE user_id = ? AND channel_type = ? AND verified = ?',
        [(int) $user['id'], 'telegram', true]
    );

    if ($channel === null) {
        log_notification((int) $user['id'], null, 'test', 'telegram', 'skipped', 'No verified Telegram channel');
        flash('error', t('settings.telegram_test_not_linked'));
        redirect(url('settings') . '#notifications');
    }

    settings_send_test_message($user, 'telegram', (string) $channel['identifier']);
}

/**
 * Send a test directly through the requested channel, without fallback.
 */
function settings_send_test_message(array $user, string $channelType, string $recipient): void
{
    $lang = in_array($user['language'], ['ms', 'en'], true) ? $user['language'] : 'ms';
    $text = tmpl($lang, 'msg.test');

    if ($channelType === 'telegram') {
        $result = telegram_send($recipient, $text);
    } else {
        $result = mail_send($recipient, tmpl($lang, 'subj.test'), nl2br(e($text)));
    }

    $sent = $result['ok'];
    log_notification(
        (int) $user['id'],
        null,
        'test',
        $channelType,
        $sent ? 'sent' : 'failed',
        $sent ? null : (string) $result['error']
    );

    $messageKey = 'settings.' . $channelType . '_test_' . ($sent ? 'sent' : 'failed');
    flash($sent ? 'ok' : 'error', t($messageKey));
    redirect(url('settings') . '#notifications');
}

/**
 * Telegram webhook: handles "/start <token>" and links the chat.
 */
function telegram_webhook(): void
{
    $secret = config('TELEGRAM_WEBHOOK_SECRET');
    if ($secret !== null && ($_SERVER['HTTP_X_TELEGRAM_BOT_API_SECRET_TOKEN'] ?? '') !== $secret) {
        json_out(['ok' => false], 403);
    }

    $raw = file_get_contents('php://input');
    $update = json_decode((string) $raw, true);
    $message = $update['message'] ?? null;

    if (!is_array($message) || !isset($message['chat']['id'])) {
        json_out(['ok' => true]);
    }

    $chatId = (string) $message['chat']['id'];
    $text = trim((string) ($message['text'] ?? ''));

    if (!preg_match('/^\/start\s+(\S+)/', $text, $m)) {
        telegram_send($chatId, t('tg.hello'));
        json_out(['ok' => true]);
    }

    $userId = telegram_token_user($m[1]);
    if ($userId === null) {
        telegram_send($chatId, t('tg.bad_token'));
        json_out(['ok' => true]);
    }

    $existing = db_one(
        'SELECT id FROM notification_channels WHERE user_id = ? AND channel_type = ?',
        [$userId, 'telegram']
    );

    if ($existing !== null) {
        db_exec('UPDATE notification_channels SET identifier = ?, verified = ?, linked_at = ? WHERE id = ?', [
            $chatId,
            true,
            gmdate('Y-m-d H:i:s'),
            (int) $existing['id'],
        ]);
    } else {
        db_insert('notification_channels', [
            'user_id'      => $userId,
            'channel_type' => 'telegram',
            'identifier'   => $chatId,
            'verified'     => true,
            'is_primary'   => true,
            'linked_at'    => gmdate('Y-m-d H:i:s'),
        ]);
    }

    $user = user_by_id($userId);
    telegram_send($chatId, tmpl(
        $user !== null && $user['language'] === 'en' ? 'en' : 'ms',
        'tg.linked',
        ['name' => (string) ($user['name'] ?? '')]
    ));

    json_out(['ok' => true]);
}
