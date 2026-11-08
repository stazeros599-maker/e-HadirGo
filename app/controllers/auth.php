<?php

declare(strict_types=1);

/**
 * Auth routes: login, signup, verify, forgot, reset, logout.
 */

function auth_login(): void
{
    if (current_user() !== null) {
        redirect(url('dashboard'));
    }

    $error = '';
    $email = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();
        $email = trim((string) ($_POST['email'] ?? ''));
        $result = login_attempt($email, (string) ($_POST['password'] ?? ''));

        if ($result['ok']) {
            login_session((int) $result['user']['id']);
            redirect(url('dashboard'));
        }

        if ($result['error'] === 'unverified') {
            $_SESSION['pending_uid'] = (int) $result['user']['id'];
            flash('warn', t('auth.err.unverified'));
            redirect(url('verify'));
        }

        $error = t('auth.err.bad_credentials');
    }

    page('auth/login', [
        'title' => t('auth.login_title'),
        'error' => $error,
        'email' => $email,
    ]);
}

function auth_signup(): void
{
    if (current_user() !== null) {
        redirect(url('dashboard'));
    }

    $units = units_all();
    $error = '';
    $old = ['name' => '', 'email' => '', 'unit_id' => ''];
    $showDummyConfirm = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();

        $old = [
            'name'    => trim((string) ($_POST['name'] ?? '')),
            'email'   => trim((string) ($_POST['email'] ?? '')),
            'unit_id' => (string) ($_POST['unit_id'] ?? ''),
        ];

        $confirmDummy = isset($_POST['confirm_dummy']);
        $result = signup_user($_POST, $confirmDummy);

        if ($result['ok']) {
            $userId = (int) $result['user_id'];
            $code = issue_otp($userId);
            $user = user_by_id($userId);

            $body = '<p>' . e(t('auth.otp_body')) . '</p><p style="font-size:28px;letter-spacing:6px;font-weight:700">'
                . e($code) . '</p><p>' . e(t('auth.otp_ttl', ['min' => OTP_TTL_MINUTES])) . '</p>';
            $sent = mail_send((string) $user['email'], t('subj.otp'), $body);
            log_notification($userId, null, 'otp', 'email', $sent['ok'] ? 'sent' : 'failed', $sent['error']);

            $_SESSION['pending_uid'] = $userId;

            if (config('APP_DEBUG') === '1') {
                flash('info', 'DEBUG OTP: ' . $code);
            }

            redirect(url('verify'));
        }

        $error = t('auth.err.' . $result['error']);
        $showDummyConfirm = in_array($result['error'], ['email_unverified'], true);
    }

    page('auth/signup', [
        'title'            => t('auth.signup_title'),
        'error'            => $error,
        'old'              => $old,
        'units'            => $units,
        'showDummyConfirm' => $showDummyConfirm,
    ]);
}

function auth_verify(): void
{
    $userId = (int) ($_SESSION['pending_uid'] ?? 0);
    if ($userId === 0) {
        redirect(url('login'));
    }

    $error = '';

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();

        if (verify_otp($userId, (string) ($_POST['code'] ?? ''))) {
            unset($_SESSION['pending_uid']);
            flash('ok', t('auth.verified'));

            redirect(url('login'));
        }

        $error = t('auth.err.code_invalid');
    }

    page('auth/verify', [
        'title' => t('auth.verify_title'),
        'error' => $error,
    ]);
}

function auth_resend_otp(): void
{
    csrf_verify();

    $userId = (int) ($_SESSION['pending_uid'] ?? 0);
    if ($userId === 0) {
        redirect(url('login'));
    }

    $code = issue_otp($userId);
    $user = user_by_id($userId);
    if ($user !== null) {
        $body = '<p>' . e(t('auth.otp_body')) . '</p><p style="font-size:28px;letter-spacing:6px;font-weight:700">'
            . e($code) . '</p>';
        $sent = mail_send((string) $user['email'], t('subj.otp'), $body);
        log_notification($userId, null, 'otp', 'email', $sent['ok'] ? 'sent' : 'failed', $sent['error']);
    }

    flash('ok', t('auth.code_resent'));

    if (config('APP_DEBUG') === '1') {
        flash('info', 'DEBUG OTP: ' . $code);
    }

    redirect(url('verify'));
}

function auth_forgot(): void
{
    $sent = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();

        $reset = create_password_reset((string) ($_POST['email'] ?? ''));

        if ($reset !== null) {
            $user = user_by_id($reset['user_id']);
            $link = url('reset', ['token' => $reset['token']]);
            $body = '<p>' . e(t('auth.reset_body')) . '</p><p><a href="' . e($link) . '">' . e($link) . '</a></p>';

            if ($user !== null) {
                $result = mail_send((string) $user['email'], t('subj.reset'), $body);
                log_notification((int) $user['id'], null, 'password_reset', 'email', $result['ok'] ? 'sent' : 'failed', $result['error']);
            }

            if (config('APP_DEBUG') === '1') {
                flash('info', 'DEBUG LINK: ' . $link);
            }
        }

        $sent = true;
    }

    page('auth/forgot', [
        'title' => t('auth.forgot_title'),
        'sent'  => $sent,
    ]);
}

function auth_reset(): void
{
    $token = (string) ($_GET['token'] ?? ($_POST['token'] ?? ''));
    $error = '';
    $done = false;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        csrf_verify();

        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirm'] ?? '');

        if ($password !== $confirm) {
            $error = t('auth.err.password_mismatch');
        } elseif (reset_password($token, $password)) {
            $done = true;
        } else {
            $error = t('auth.err.token_invalid');
        }
    }

    page('auth/reset', [
        'title' => t('auth.reset_title'),
        'error' => $error,
        'done'  => $done,
        'token' => $token,
    ]);
}

function auth_logout(): void
{
    logout_user();
    redirect(url('login'));
}
