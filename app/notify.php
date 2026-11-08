<?php

declare(strict_types=1);

/**
 * Notifications: Telegram first, e-mail as the formal record, both logged.
 */

function telegram_api(string $method, array $payload): array
{
    $token = config('TELEGRAM_BOT_TOKEN');
    if ($token === null) {
        return ['ok' => false, 'error' => 'TELEGRAM_BOT_TOKEN is not set'];
    }

    $url = 'https://api.telegram.org/bot' . $token . '/' . $method;

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_CONNECTTIMEOUT => 5,
        ]);
        $raw = curl_exec($ch);
        $err = curl_error($ch);
        curl_close($ch);

        if ($raw === false) {
            return ['ok' => false, 'error' => $err !== '' ? $err : 'curl failed'];
        }
    } else {
        $context = stream_context_create([
            'http' => [
                'method'        => 'POST',
                'header'        => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content'       => http_build_query($payload),
                'timeout'       => 10,
                'ignore_errors' => true,
            ],
        ]);
        $raw = @file_get_contents($url, false, $context);
        if ($raw === false) {
            return ['ok' => false, 'error' => 'request failed'];
        }
    }

    $data = json_decode((string) $raw, true);

    return [
        'ok'    => is_array($data) && ($data['ok'] ?? false) === true,
        'error' => is_array($data) && ($data['ok'] ?? false) !== true ? (string) ($data['description'] ?? 'unknown') : null,
    ];
}

function telegram_send(string $chatId, string $text): array
{
    return telegram_api('sendMessage', [
        'chat_id'                  => $chatId,
        'text'                     => $text,
        'parse_mode'               => 'HTML',
        'disable_web_page_preview' => 'true',
    ]);
}

/**
 * Minimal SMTP client (STARTTLS + AUTH LOGIN), falling back to mail().
 */
function smtp_send(string $to, string $subject, string $html): array
{
    $host = config('SMTP_HOST');
    if ($host === null) {
        return ['ok' => false, 'error' => 'SMTP not configured'];
    }

    $port = (int) config('SMTP_PORT', '587');
    $user = (string) config('SMTP_USER', '');
    $pass = (string) config('SMTP_PASS', '');
    $from = (string) config('MAIL_FROM', 'no-reply@sabah.gov.my');
    $fromName = (string) config('MAIL_FROM_NAME', 'e-HadirGo');

    $socket = @stream_socket_client('tcp://' . $host . ':' . $port, $errno, $errstr, 10);
    if ($socket === false) {
        return ['ok' => false, 'error' => $errstr !== '' ? $errstr : 'connect failed'];
    }
    stream_set_timeout($socket, 10);

    $read = static function () use ($socket): string {
        $data = '';
        while (($line = fgets($socket, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') {
                break;
            }
        }

        return $data;
    };

    $cmd = static function (string $command) use ($socket, $read): string {
        fwrite($socket, $command . "\r\n");

        return $read();
    };

    $read();

    if (str_starts_with($cmd('EHLO e-hadirgo'), '250') === false) {
        fclose($socket);

        return ['ok' => false, 'error' => 'EHLO rejected'];
    }

    if ($port === 587) {
        if (str_starts_with($cmd('STARTTLS'), '220') === false) {
            fclose($socket);

            return ['ok' => false, 'error' => 'STARTTLS rejected'];
        }
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            fclose($socket);

            return ['ok' => false, 'error' => 'TLS negotiation failed'];
        }
        $cmd('EHLO e-hadirgo');
    }

    if ($user !== '') {
        if (str_starts_with($cmd('AUTH LOGIN'), '334') === false) {
            fclose($socket);

            return ['ok' => false, 'error' => 'AUTH rejected'];
        }
        $cmd(base64_encode($user));
        if (str_starts_with($cmd(base64_encode($pass)), '235') === false) {
            fclose($socket);

            return ['ok' => false, 'error' => 'authentication failed'];
        }
    }

    if (str_starts_with($cmd('MAIL FROM:<' . $from . '>'), '250') === false) {
        fclose($socket);

        return ['ok' => false, 'error' => 'MAIL FROM rejected'];
    }
    if (str_starts_with($cmd('RCPT TO:<' . $to . '>'), '250') === false) {
        fclose($socket);

        return ['ok' => false, 'error' => 'RCPT TO rejected'];
    }
    if (str_starts_with($cmd('DATA'), '354') === false) {
        fclose($socket);

        return ['ok' => false, 'error' => 'DATA rejected'];
    }

    $headers = [
        'From: ' . sprintf('=?UTF-8?B?%s?=', base64_encode($fromName)) . ' <' . $from . '>',
        'To: <' . $to . '>',
        'Subject: =?UTF-8?B?' . base64_encode($subject) . '?=',
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Date: ' . date('r'),
    ];

    fwrite($socket, implode("\r\n", $headers) . "\r\n\r\n" . $html . "\r\n.\r\n");
    $response = $read();
    $cmd('QUIT');
    fclose($socket);

    return str_starts_with($response, '250')
        ? ['ok' => true, 'error' => null]
        : ['ok' => false, 'error' => trim($response)];
}

function mail_send(string $to, string $subject, string $html): array
{
    if (config('SMTP_HOST') !== null) {
        return smtp_send($to, $subject, $html);
    }

    $from = (string) config('MAIL_FROM', 'no-reply@sabah.gov.my');
    $headers = implode("\r\n", [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'From: ' . $from,
        'Reply-To: ' . $from,
    ]);

    $ok = @mail($to, '=?UTF-8?B?' . base64_encode($subject) . '?=', $html, $headers);

    return ['ok' => $ok, 'error' => $ok ? null : 'mail() returned false'];
}

function log_notification(?int $userId, ?int $assignmentId, string $event, string $channel, string $status, ?string $error = null): void
{
    db_insert('notification_log', [
        'user_id'       => $userId,
        'assignment_id' => $assignmentId,
        'event_code'    => $event,
        'channel_type'  => $channel,
        'status'        => $status,
        'error_text'    => $error === null ? null : substr($error, 0, 500),
        'sent_at'       => $status === 'sent' ? gmdate('Y-m-d H:i:s') : null,
    ]);
}

/**
 * Send one event to one user: preferred verified channel first, then the rest.
 *
 * @param array $vars Placeholders for the message template.
 */
function notify_event(int $userId, string $event, array $vars = [], ?int $assignmentId = null): bool
{
    $user = user_by_id($userId);
    if ($user === null) {
        return false;
    }

    $lang = in_array($user['language'], ['ms', 'en'], true) ? $user['language'] : 'ms';
    $text = tmpl($lang, 'msg.' . $event, $vars);
    $subject = tmpl($lang, 'subj.' . $event, $vars);

    $channels = db_all(
        'SELECT * FROM notification_channels WHERE user_id = ? ORDER BY is_primary DESC, id',
        [$userId]
    );

    $attempted = false;

    foreach ($channels as $channel) {
        if (!truthy($channel['verified'])) {
            continue;
        }

        $attempted = true;

        if ($channel['channel_type'] === 'telegram') {
            $result = telegram_send((string) $channel['identifier'], $text);
        } else {
            $result = mail_send((string) $channel['identifier'], $subject, nl2br(e($text)));
        }

        if ($result['ok']) {
            log_notification($userId, $assignmentId, $event, (string) $channel['channel_type'], 'sent');

            return true;
        }

        log_notification($userId, $assignmentId, $event, (string) $channel['channel_type'], 'failed', (string) $result['error']);
    }

    if (!$attempted) {
        log_notification($userId, $assignmentId, $event, 'none', 'skipped', 'No verified channel');
    }

    return false;
}
