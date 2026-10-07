<?php
declare(strict_types=1);

function paypal_rest_configured(): bool
{
    return (string) config('paypal', 'client_id') !== '' && (string) config('paypal', 'client_secret') !== '';
}

function paypal_business_email(): string
{
    return trim((string) config('paypal', 'business_email', ''));
}

function paypal_is_configured(): bool
{
    return paypal_rest_configured() || paypal_business_email() !== '';
}

function paypal_web_base(): string
{
    return (string) config('paypal', 'mode') === 'live'
        ? 'https://www.paypal.com'
        : 'https://www.sandbox.paypal.com';
}

function paypal_reload_config(): void
{
    $GLOBALS['mm_config']['paypal'] = require APP_ROOT . '/config/paypal.php';
}

function paypal_test_connection(): string
{
    if (!paypal_rest_configured()) {
        if (paypal_business_email() !== '') {
            return 'PayPal redirect is ready (business email). Buyers will open ' . paypal_web_base() . ' to pay.';
        }
        throw new AppException('Save Client ID and Secret, or a PayPal business email first.');
    }
    $token = paypal_access_token();
    if ($token === '') {
        throw new AppException('PayPal did not return an access token.');
    }
    return 'Sandbox API accepted the Client ID and Secret. Checkout will open PayPal’s website.';
}

function paypal_api(string $method, string $path, ?array $body, ?string $token, ?string $basicUser = null): array
{
    if (!function_exists('curl_init')) {
        throw new AppException('PayPal requires the PHP curl extension.');
    }
    $url = rtrim((string) config('paypal', 'api_base'), '/') . $path;
    $ch = curl_init($url);
    $headers = ['Accept: application/json'];
    if ($token) {
        $headers[] = 'Authorization: Bearer ' . $token;
    }
    if ($body !== null) {
        $headers[] = 'Content-Type: application/json';
        $json = $body === [] ? '{}' : json_encode($body, JSON_UNESCAPED_SLASHES);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $json === false ? '{}' : $json);
    } elseif ($basicUser !== null) {
        $headers[] = 'Content-Type: application/x-www-form-urlencoded';
        curl_setopt($ch, CURLOPT_POSTFIELDS, 'grant_type=client_credentials');
        curl_setopt($ch, CURLOPT_USERPWD, $basicUser);
    }
    curl_setopt_array($ch, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT => 25,
        CURLOPT_HTTPHEADER => $headers,
    ]);
    $raw = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    if ($raw === false) {
        log_message('paypal', 'Connection failed on ' . $path . ': ' . $error);
        throw new AppException('PayPal could not be reached. Try again in a moment.');
    }
    $decoded = json_decode($raw, true);
    if (!is_array($decoded)) {
        log_message('paypal', 'Unexpected response on ' . $path . ' HTTP ' . $status);
        throw new AppException('PayPal returned an unexpected response.');
    }
    if ($status >= 400) {
        $issue = (string) ($decoded['name'] ?? $decoded['error'] ?? 'PAYPAL_ERROR');
        log_message('paypal', $path . ' rejected with HTTP ' . $status . ' ' . $issue);
        throw new AppException('PayPal could not complete that step. You can try again from your order.');
    }
    return $decoded;
}

function paypal_access_token(): string
{
    if (!paypal_rest_configured()) {
        throw new AppException('PayPal API credentials are not configured.');
    }
    $basic = (string) config('paypal', 'client_id') . ':' . (string) config('paypal', 'client_secret');
    $data = paypal_api('POST', '/v1/oauth2/token', null, null, $basic);
    if (empty($data['access_token']) || !is_string($data['access_token'])) {
        throw new AppException('PayPal credentials were rejected.');
    }
    return $data['access_token'];
}

/**
 * @return array{type:string,url?:string,action?:string,fields?:array<string,string>}
 */
function build_paypal_handoff(int $userId, string $orderNumber): array
{
    $order = fetch_order_for_user($orderNumber, $userId);
    if (!$order || $order['payment_method'] !== 'paypal') {
        throw new AppException('That PayPal order was not found.');
    }
    if ($order['order_status'] === 'cancelled') {
        throw new AppException('This order was cancelled.');
    }
    if ($order['payment_status'] === 'paid') {
        throw new AppException('This order is already paid.');
    }

    if (paypal_rest_configured()) {
        return [
            'type' => 'redirect',
            'url' => start_paypal_checkout($userId, $orderNumber),
        ];
    }

    $business = paypal_business_email();
    if ($business === '') {
        throw new AppException('PayPal is not configured. Add API keys or a business email under Admin → PayPal.');
    }

    $currency = (string) config('paypal', 'currency', 'MYR');
    if ($currency !== (string) config('app', 'currency', 'MYR')) {
        throw new AppException('PayPal currency does not match the store currency.');
    }

    $reference = 'STD-' . $orderNumber;
    $stmt = db()->prepare(
        'UPDATE payments SET provider_reference = ?, response_data = ?, updated_at = NOW() WHERE order_id = ?'
    );
    $stmt->execute([
        $reference,
        json_encode(['mode' => 'standard', 'business' => $business], JSON_UNESCAPED_SLASHES),
        (int) $order['id'],
    ]);

    $return = APP_URL . '/public/payment_success.php?order=' . rawurlencode($orderNumber) . '&mode=standard';
    $cancel = APP_URL . '/public/payment_cancel.php?order=' . rawurlencode($orderNumber);

    return [
        'type' => 'form',
        'action' => paypal_web_base() . '/cgi-bin/webscr',
        'fields' => [
            'cmd' => '_xclick',
            'business' => $business,
            'item_name' => (string) config('app', 'name') . ' ' . $orderNumber,
            'amount' => number_format((float) $order['total_amount'], 2, '.', ''),
            'currency_code' => $currency,
            'custom' => $orderNumber,
            'invoice' => $orderNumber,
            'return' => $return,
            'cancel_return' => $cancel,
            'rm' => '2',
            'no_shipping' => '1',
            'no_note' => '1',
            'charset' => 'utf-8',
        ],
    ];
}

function start_paypal_checkout(int $userId, string $orderNumber): string
{
    $order = fetch_order_for_user($orderNumber, $userId);
    if (!$order || $order['payment_method'] !== 'paypal') {
        throw new AppException('That PayPal order was not found.');
    }
    if ($order['order_status'] === 'cancelled') {
        throw new AppException('This order was cancelled.');
    }
    if ($order['payment_status'] === 'paid') {
        throw new AppException('This order is already paid.');
    }
    $currency = (string) config('paypal', 'currency', 'MYR');
    if ($currency !== (string) config('app', 'currency', 'MYR')) {
        throw new AppException('PayPal currency does not match the store currency.');
    }
    $token = paypal_access_token();
    $return = APP_URL . '/public/payment_success.php?order=' . rawurlencode($orderNumber);
    $cancel = APP_URL . '/public/payment_cancel.php?order=' . rawurlencode($orderNumber);
    $payload = [
        'intent' => 'CAPTURE',
        'purchase_units' => [[
            'reference_id' => $orderNumber,
            'custom_id' => $orderNumber,
            'description' => config('app', 'name') . ' ' . $orderNumber,
            'amount' => [
                'currency_code' => $currency,
                'value' => number_format((float) $order['total_amount'], 2, '.', ''),
            ],
        ]],
        'application_context' => [
            'brand_name' => (string) config('app', 'name'),
            'shipping_preference' => 'NO_SHIPPING',
            'user_action' => 'PAY_NOW',
            'return_url' => $return,
            'cancel_url' => $cancel,
            'landing_page' => 'LOGIN',
        ],
    ];
    $created = paypal_api('POST', '/v2/checkout/orders', $payload, $token);
    $paypalId = (string) ($created['id'] ?? '');
    $approve = '';
    foreach ($created['links'] ?? [] as $link) {
        $rel = (string) ($link['rel'] ?? '');
        if (in_array($rel, ['approve', 'payer-action'], true) && is_string($link['href'] ?? null)) {
            $approve = (string) $link['href'];
            if ($rel === 'approve') {
                break;
            }
        }
    }
    if ($paypalId === '' || $approve === '') {
        log_message('paypal', 'Create order response missing approval link for ' . $orderNumber);
        throw new AppException('PayPal did not return a checkout link.');
    }
    $stmt = db()->prepare(
        'UPDATE payments SET provider_reference = ?, response_data = ?, updated_at = NOW() WHERE order_id = ?'
    );
    $stmt->execute([$paypalId, json_encode(['paypal_order_id' => $paypalId]), (int) $order['id']]);
    return $approve;
}

function capture_paypal_payment(int $userId, string $orderNumber, string $paypalOrderId): void
{
    if (!preg_match('/^[A-Za-z0-9]{8,40}$/', $paypalOrderId)) {
        throw new AppException('The PayPal reference was not valid.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = ? AND user_id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$orderNumber, $userId]);
        $order = $stmt->fetch();
        if (!$order || $order['payment_method'] !== 'paypal') {
            throw new AppException('That PayPal order was not found.');
        }
        if ($order['payment_status'] === 'paid') {
            $pdo->commit();
            return;
        }
        if ($order['order_status'] === 'cancelled') {
            throw new AppException('This order was cancelled before payment completed.');
        }
        $paymentStmt = $pdo->prepare('SELECT * FROM payments WHERE order_id = ? LIMIT 1 FOR UPDATE');
        $paymentStmt->execute([(int) $order['id']]);
        $payment = $paymentStmt->fetch();
        if (!$payment || !hash_equals((string) $payment['provider_reference'], $paypalOrderId)) {
            throw new AppException('This payment does not match the order we created.');
        }

        $token = paypal_access_token();
        $encodedId = rawurlencode($paypalOrderId);
        try {
            $capture = paypal_api('POST', '/v2/checkout/orders/' . $encodedId . '/capture', [], $token);
        } catch (AppException) {
            $capture = paypal_api('GET', '/v2/checkout/orders/' . $encodedId, null, $token);
        }
        $verified = extract_paypal_capture($capture, $order);
        $pdo->prepare(
            'UPDATE orders
             SET payment_status = \'paid\', order_status = \'paid\', transaction_id = ?, updated_at = NOW()
             WHERE id = ?'
        )->execute([$verified['capture_id'], (int) $order['id']]);
        $pdo->prepare(
            'UPDATE payments
             SET status = \'paid\', transaction_id = ?, response_data = ?, updated_at = NOW()
             WHERE order_id = ?'
        )->execute([
            $verified['capture_id'],
            json_encode([
                'paypal_order_id' => $paypalOrderId,
                'capture' => $capture,
            ], JSON_UNESCAPED_SLASHES),
            (int) $order['id'],
        ]);
        record_order_event($pdo, (int) $order['id'], 'paid', 'PayPal payment verified', $userId);
        notify($userId, 'Payment confirmed', 'PayPal confirmed payment for ' . $orderNumber . '.', 'payment');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        log_message('paypal', 'Capture failed: ' . $exception->getMessage());
        throw $exception instanceof AppException ? $exception : new AppException('PayPal payment could not be verified.');
    }
}

function complete_paypal_standard_return(int $userId, string $orderNumber): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE order_number = ? AND user_id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$orderNumber, $userId]);
        $order = $stmt->fetch();
        if (!$order || $order['payment_method'] !== 'paypal') {
            throw new AppException('That PayPal order was not found.');
        }
        if ($order['payment_status'] === 'paid') {
            $pdo->commit();
            return;
        }
        if ($order['order_status'] === 'cancelled') {
            throw new AppException('This order was cancelled before payment completed.');
        }

        $status = strtoupper((string) ($_POST['payment_status'] ?? $_GET['st'] ?? $_POST['st'] ?? ''));
        $amount = (string) ($_POST['mc_gross'] ?? $_GET['amt'] ?? $_POST['amt'] ?? '');
        $txn = (string) ($_POST['txn_id'] ?? $_GET['tx'] ?? $_POST['tx'] ?? ('STD-' . $orderNumber));
        $custom = (string) ($_POST['custom'] ?? $_GET['cm'] ?? $_POST['cm'] ?? $orderNumber);

        if ($custom !== '' && $custom !== $orderNumber) {
            throw new AppException('PayPal returned a different order reference.');
        }
        if ($amount !== '' && to_cents($amount) !== to_cents($order['total_amount'])) {
            throw new AppException('PayPal amount does not match this order.');
        }
        if ($status !== '' && !in_array($status, ['COMPLETED', 'PENDING'], true)) {
            throw new AppException('PayPal did not report a completed payment.');
        }

        if (mb_strlen($txn) > 80) {
            $txn = mb_substr($txn, 0, 80);
        }

        $pdo->prepare(
            'UPDATE orders
             SET payment_status = \'paid\', order_status = \'paid\', transaction_id = ?, updated_at = NOW()
             WHERE id = ?'
        )->execute([$txn, (int) $order['id']]);
        $pdo->prepare(
            'UPDATE payments
             SET status = \'paid\', transaction_id = ?, response_data = ?, updated_at = NOW()
             WHERE order_id = ?'
        )->execute([
            $txn,
            json_encode([
                'mode' => 'standard',
                'return' => array_merge($_GET, $_POST),
            ], JSON_UNESCAPED_SLASHES),
            (int) $order['id'],
        ]);
        record_order_event($pdo, (int) $order['id'], 'paid', 'PayPal standard return recorded', $userId);
        notify($userId, 'Payment confirmed', 'PayPal payment recorded for ' . $orderNumber . '.', 'payment');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        log_message('paypal', 'Standard return failed: ' . $exception->getMessage());
        throw $exception instanceof AppException ? $exception : new AppException('PayPal payment could not be verified.');
    }
}

function extract_paypal_capture(array $payload, array $order): array
{
    $units = $payload['purchase_units'] ?? null;
    if (!is_array($units) || $units === []) {
        throw new AppException('PayPal did not return purchase details.');
    }
    $unit = $units[0];
    $reference = (string) ($unit['reference_id'] ?? $unit['custom_id'] ?? '');
    if ($reference !== '' && $reference !== (string) $order['order_number']) {
        log_message('paypal', 'Reference mismatch for order ' . $order['order_number']);
        throw new AppException('PayPal returned a different order reference.');
    }
    $status = strtoupper((string) ($payload['status'] ?? ''));
    $captures = $unit['payments']['captures'][0] ?? null;
    if (!is_array($captures) && isset($payload['purchase_units'][0]['payments']['captures'][0])) {
        $captures = $payload['purchase_units'][0]['payments']['captures'][0];
    }
    if ($status !== 'COMPLETED' && (!is_array($captures) || strtoupper((string) ($captures['status'] ?? '')) !== 'COMPLETED')) {
        throw new AppException('PayPal has not completed the payment.');
    }
    $capture = is_array($captures) ? $captures : null;
    if (!$capture) {
        throw new AppException('PayPal has not completed the payment.');
    }
    $amount = (string) ($capture['amount']['value'] ?? '');
    $currency = (string) ($capture['amount']['currency_code'] ?? '');
    $expected = number_format((float) $order['total_amount'], 2, '.', '');
    if (!hash_equals($expected, $amount) || strtoupper($currency) !== (string) config('paypal', 'currency', 'MYR')) {
        log_message('paypal', 'Amount mismatch for order ' . $order['order_number']);
        throw new AppException('PayPal amount does not match this order.');
    }
    $captureId = (string) ($capture['id'] ?? '');
    if ($captureId === '') {
        throw new AppException('PayPal did not return a transaction id.');
    }
    return ['capture_id' => $captureId];
}
