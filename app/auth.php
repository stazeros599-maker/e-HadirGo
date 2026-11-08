<?php

declare(strict_types=1);

/**
 * Authentication: sign-up, e-mail verification, login, password reset.
 */

const OTP_TTL_MINUTES = 10;
const OTP_MAX_ATTEMPTS = 5;

/**
 * Reject disposable domains and domains without an MX record.
 *
 * @return array{ok:bool, reason:string, gov:bool}
 */
function check_email_domain(string $email): array
{
    if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        return ['ok' => false, 'reason' => 'format', 'gov' => false];
    }

    $domain = strtolower(substr($email, strrpos($email, '@') + 1));
    $blocked = ['mailinator.com', 'tempmail.com', 'guerrillamail.com', '10minutemail.com',
                'example.com', 'example.org', 'dummy.test', 'test.my', 'localhost'];

    if (in_array($domain, $blocked, true)) {
        return ['ok' => false, 'reason' => 'disposable', 'gov' => false];
    }

    $gov = str_ends_with($domain, 'sabah.gov.my');

    // checkdnsrr needs network access; a missing record is a soft failure
    // because the OTP step is the real proof the mailbox works.
    $hasMx = false;
    if (function_exists('checkdnsrr')) {
        $hasMx = @checkdnsrr($domain, 'MX') || @checkdnsrr($domain, 'A');
    }

    return ['ok' => $hasMx, 'reason' => $hasMx ? '' : 'no_mx', 'gov' => $gov];
}

function password_problem(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'password_short';
    }
    if (!preg_match('/[A-Za-z]/', $password) || !preg_match('/\d/', $password)) {
        return 'password_weak';
    }

    return null;
}

/**
 * Create an account. Every account starts as "staf".
 *
 * @return array{ok:bool, error:string, user_id:int|null}
 */
function signup_user(array $in, bool $confirmDummy): array
{
    $email = strtolower(trim((string) ($in['email'] ?? '')));
    $name = trim((string) ($in['name'] ?? ''));
    $password = (string) ($in['password'] ?? '');
    $unitId = (int) ($in['unit_id'] ?? 0);

    $domain = check_email_domain($email);

    if ($domain['reason'] === 'format') {
        return ['ok' => false, 'error' => 'email_invalid', 'user_id' => null];
    }

    if (!$domain['ok'] && !$confirmDummy) {
        return ['ok' => false, 'error' => 'email_unverified', 'user_id' => null];
    }

    if (mb_strlen($name) < 3) {
        return ['ok' => false, 'error' => 'name_short', 'user_id' => null];
    }

    $pwProblem = password_problem($password);
    if ($pwProblem !== null) {
        return ['ok' => false, 'error' => $pwProblem, 'user_id' => null];
    }

    if (db_val('SELECT id FROM users WHERE email = ?', [$email]) !== null) {
        return ['ok' => false, 'error' => 'email_taken', 'user_id' => null];
    }

    if (db_val('SELECT id FROM units WHERE id = ?', [$unitId]) === null) {
        return ['ok' => false, 'error' => 'unit_invalid', 'user_id' => null];
    }

    $userId = db_insert('users', [
        'email'          => $email,
        'name'           => $name,
        'password_hash'  => password_hash($password, PASSWORD_DEFAULT),
        'unit_id'        => $unitId,
        'email_verified' => false,
        'is_dummy'       => !$domain['ok'] || !$domain['gov'],
        'language'       => current_lang(),
        'is_active'      => true,
    ]);

    db_insert('user_roles', ['user_id' => $userId, 'role_code' => 'staf']);

    return ['ok' => true, 'error' => '', 'user_id' => $userId];
}

/**
 * Generate a 6-digit OTP, store its hash, return the plain code.
 */
function issue_otp(int $userId): string
{
    $code = str_pad((string) random_int(0, 999999), 6, '0', STR_PAD_LEFT);

    db_exec('UPDATE email_verifications SET consumed_at = ? WHERE user_id = ? AND consumed_at IS NULL', [
        gmdate('Y-m-d H:i:s'),
        $userId,
    ]);

    db_insert('email_verifications', [
        'user_id'    => $userId,
        'code_hash'  => password_hash($code, PASSWORD_DEFAULT),
        'expires_at' => gmdate('Y-m-d H:i:s', time() + OTP_TTL_MINUTES * 60),
        'attempts'   => 0,
    ]);

    return $code;
}

function verify_otp(int $userId, string $code): bool
{
    $code = preg_replace('/\D/', '', $code) ?? '';

    $row = db_one(
        'SELECT id, code_hash, attempts, expires_at FROM email_verifications
          WHERE user_id = ? AND consumed_at IS NULL
          ORDER BY id DESC LIMIT 1',
        [$userId]
    );

    if ($row === null) {
        return false;
    }

    if ((int) $row['attempts'] >= OTP_MAX_ATTEMPTS) {
        return false;
    }

    $expiresTs = utc_timestamp((string) $row['expires_at']);
    if ($expiresTs === null || $expiresTs < time()) {
        return false;
    }

    if (!password_verify($code, (string) $row['code_hash'])) {
        db_exec('UPDATE email_verifications SET attempts = attempts + 1 WHERE id = ?', [(int) $row['id']]);

        return false;
    }

    db_exec('UPDATE email_verifications SET consumed_at = ? WHERE id = ?', [
        gmdate('Y-m-d H:i:s'),
        (int) $row['id'],
    ]);
    db_exec('UPDATE users SET email_verified = ?, updated_at = ? WHERE id = ?', [
        true,
        gmdate('Y-m-d H:i:s'),
        $userId,
    ]);

    return true;
}

/**
 * @return array{ok:bool, error:string, user:array|null}
 */
function login_attempt(string $email, string $password): array
{
    $email = strtolower(trim($email));

    $user = db_one('SELECT * FROM users WHERE email = ? AND is_active', [$email]);
    if ($user === null) {
        return ['ok' => false, 'error' => 'bad_credentials', 'user' => null];
    }

    if (!password_verify($password, (string) $user['password_hash'])) {
        return ['ok' => false, 'error' => 'bad_credentials', 'user' => null];
    }

    if (!truthy($user['email_verified'])) {
        return ['ok' => false, 'error' => 'unverified', 'user' => $user];
    }

    db_exec('UPDATE users SET last_login_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), (int) $user['id']]);

    return ['ok' => true, 'error' => '', 'user' => $user];
}

function login_session(int $userId): void
{
    session_regenerate_id(true);
    $_SESSION['uid'] = $userId;
    unset($_SESSION['csrf']);
}

function logout_user(): void
{
    $_SESSION = [];
    session_destroy();
}

/**
 * @return array{token:string, user_id:int}|null
 */
function create_password_reset(string $email): ?array
{
    $user = db_one('SELECT id FROM users WHERE email = ? AND is_active', [strtolower(trim($email))]);
    if ($user === null) {
        return null;
    }

    $token = bin2hex(random_bytes(32));

    db_insert('password_resets', [
        'user_id'    => (int) $user['id'],
        'token_hash' => hash('sha256', $token),
        'expires_at' => gmdate('Y-m-d H:i:s', time() + 3600),
    ]);

    return ['token' => $token, 'user_id' => (int) $user['id']];
}

function reset_password(string $token, string $password): bool
{
    $problem = password_problem($password);
    if ($problem !== null) {
        return false;
    }

    $row = db_one(
        'SELECT id, user_id FROM password_resets
          WHERE token_hash = ? AND used_at IS NULL AND expires_at > ?',
        [hash('sha256', $token), gmdate('Y-m-d H:i:s')]
    );

    if ($row === null) {
        return false;
    }

    db_exec('UPDATE users SET password_hash = ?, updated_at = ? WHERE id = ?', [
        password_hash($password, PASSWORD_DEFAULT),
        gmdate('Y-m-d H:i:s'),
        (int) $row['user_id'],
    ]);
    db_exec('UPDATE password_resets SET used_at = ? WHERE id = ?', [gmdate('Y-m-d H:i:s'), (int) $row['id']]);

    return true;
}

// ---------------------------------------------------------------------------
// Telegram link tokens (stateless, HMAC-signed)
// ---------------------------------------------------------------------------

function telegram_link_token(int $userId): string
{
    $expires = time() + 900;
    $payload = $userId . '.' . $expires;
    $sig = hash_hmac('sha256', $payload, (string) config('APP_SECRET', 'dev-secret'));

    return $payload . '.' . substr($sig, 0, 32);
}

/**
 * @return int|null User id when the token is valid and unexpired.
 */
function telegram_token_user(string $token): ?int
{
    $parts = explode('.', $token);
    if (count($parts) !== 3) {
        return null;
    }

    [$userId, $expires, $sig] = $parts;
    if ((int) $expires < time()) {
        return null;
    }

    $expected = substr(hash_hmac('sha256', $userId . '.' . $expires, (string) config('APP_SECRET', 'dev-secret')), 0, 32);

    return hash_equals($expected, $sig) ? (int) $userId : null;
}
