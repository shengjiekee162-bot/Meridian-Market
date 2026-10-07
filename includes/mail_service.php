<?php
declare(strict_types=1);

function mail_is_configured(): bool
{
    return trim((string) config('mail', 'host')) !== '';
}

function send_app_mail(string $to, string $subject, string $body): bool
{
    $to = trim($to);
    if (filter_var($to, FILTER_VALIDATE_EMAIL) === false) {
        return false;
    }
    if (!mail_is_configured()) {
        if (APP_ENV === 'development') {
            log_message('mail', 'Dev mailbox | To: ' . $to . ' | Subject: ' . $subject . ' | ' . $body);
        } else {
            log_message('mail', 'Mail is not configured. A message for ' . $to . ' was not sent.');
        }
        return false;
    }
    try {
        smtp_send($to, $subject, $body);
        return true;
    } catch (Throwable $exception) {
        log_message('mail', 'Delivery failed for ' . $to . ': ' . $exception->getMessage());
        return false;
    }
}

function smtp_send(string $to, string $subject, string $body): void
{
    $host = (string) config('mail', 'host');
    $port = (int) config('mail', 'port', 587);
    $user = (string) config('mail', 'username');
    $pass = (string) config('mail', 'password');
    $encryption = (string) config('mail', 'encryption', 'tls');
    $from = (string) config('mail', 'from_address');
    $fromName = (string) config('mail', 'from_name');
    if (filter_var($from, FILTER_VALIDATE_EMAIL) === false) {
        throw new RuntimeException('The sender address is not valid.');
    }

    $remote = ($encryption === 'ssl' ? 'ssl://' : 'tcp://') . $host . ':' . $port;
    $socket = @stream_socket_client($remote, $errno, $error, 8, STREAM_CLIENT_CONNECT);
    if (!$socket) {
        throw new RuntimeException('SMTP connection failed.');
    }
    stream_set_timeout($socket, 8);
    smtp_expect($socket, [220]);
    smtp_command($socket, 'EHLO meridianmarket.local', [250]);
    if ($encryption === 'tls') {
        smtp_command($socket, 'STARTTLS', [220]);
        if (!stream_socket_enable_crypto($socket, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) {
            throw new RuntimeException('SMTP encryption failed.');
        }
        smtp_command($socket, 'EHLO meridianmarket.local', [250]);
    }
    if ($user !== '') {
        smtp_command($socket, 'AUTH LOGIN', [334]);
        smtp_command($socket, base64_encode($user), [334]);
        smtp_command($socket, base64_encode($pass), [235]);
    }
    smtp_command($socket, 'MAIL FROM:<' . $from . '>', [250]);
    smtp_command($socket, 'RCPT TO:<' . $to . '>', [250, 251]);
    smtp_command($socket, 'DATA', [354]);
    $headers = [
        'From: ' . encode_mail_name($fromName) . ' <' . $from . '>',
        'To: <' . $to . '>',
        'Subject: ' . encode_mail_header($subject),
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
    ];
    $payload = implode("\r\n", $headers) . "\r\n\r\n" . smtp_dot_stuff($body) . "\r\n.";
    smtp_command($socket, $payload, [250]);
    smtp_command($socket, 'QUIT', [221]);
    fclose($socket);
}

function smtp_command($socket, string $command, array $ok): void
{
    fwrite($socket, $command . "\r\n");
    smtp_expect($socket, $ok);
}

function smtp_expect($socket, array $ok): void
{
    $response = '';
    while (($line = fgets($socket, 515)) !== false) {
        $response .= $line;
        if (isset($line[3]) && $line[3] === ' ') {
            break;
        }
    }
    $code = (int) substr($response, 0, 3);
    if (!in_array($code, $ok, true)) {
        throw new RuntimeException('SMTP refused the command (' . $code . ').');
    }
}

function encode_mail_header(string $value): string
{
    $clean = str_replace(["\r", "\n"], '', $value);
    return '=?UTF-8?B?' . base64_encode($clean) . '?=';
}

function encode_mail_name(string $value): string
{
    return encode_mail_header($value);
}

function smtp_dot_stuff(string $body): string
{
    $body = str_replace(["\r\n", "\r"], "\n", $body);
    $lines = explode("\n", $body);
    foreach ($lines as &$line) {
        if (str_starts_with($line, '.')) {
            $line = '.' . $line;
        }
    }
    unset($line);
    return implode("\r\n", $lines);
}

function send_order_email(int $userId, string $orderNumber, string $method, string $total): bool
{
    $user = user_contact($userId);
    if (!$user) {
        return false;
    }
    $body = "Hello {$user['name']},\n\n"
        . "We received order {$orderNumber}.\n"
        . 'Total: ' . money($total) . "\n"
        . 'Payment method: ' . $method . "\n\n"
        . 'You can review it here: ' . APP_URL . '/public/order_detail.php?order=' . rawurlencode($orderNumber) . "\n\n"
        . (string) config('app', 'name');
    return send_app_mail($user['email'], 'Order ' . $orderNumber, $body);
}

function send_status_email(int $userId, string $orderNumber, string $status): bool
{
    $user = user_contact($userId);
    if (!$user) {
        return false;
    }
    $body = "Hello {$user['name']},\n\n"
        . "Order {$orderNumber} is now {$status}.\n"
        . APP_URL . '/public/order_detail.php?order=' . rawurlencode($orderNumber) . "\n\n"
        . (string) config('app', 'name');
    return send_app_mail($user['email'], 'Update for ' . $orderNumber, $body);
}

function send_reset_email(string $email, string $name, string $link): bool
{
    $body = "Hello {$name},\n\n"
        . "Use this link to choose a new password. It expires in one hour.\n"
        . $link . "\n\n"
        . "If you did not ask for this, you can ignore the message.\n\n"
        . (string) config('app', 'name');
    return send_app_mail($email, 'Reset your Meridian Market password', $body);
}

function user_contact(int $userId): ?array
{
    $stmt = db()->prepare('SELECT name, email FROM users WHERE id = ? LIMIT 1');
    $stmt->execute([$userId]);
    $row = $stmt->fetch();
    return $row ?: null;
}
