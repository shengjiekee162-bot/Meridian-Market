<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$number = query_string('order', 24);
if ($number === '' && isset($_POST['custom'])) {
    $number = input_string('custom', 24);
}
if ($number === '' && isset($_POST['invoice'])) {
    $number = input_string('invoice', 24);
}
$token = query_string('token', 40);
if ($token === '' && isset($_POST['token'])) {
    $token = input_string('token', 40);
}
$mode = query_string('mode', 20);
if ($mode === '' && isset($_POST['mode'])) {
    $mode = input_string('mode', 20);
}

try {
    if (!preg_match('/^ORD-\d{8}-\d{6}$/', $number)) {
        throw new AppException('The return link is missing an order number.');
    }
    if ($mode === 'standard' || ($token === '' && !paypal_rest_configured())) {
        complete_paypal_standard_return((int) $user['id'], $number);
        flash('success', 'Payment recorded. Your order is paid.');
    } else {
        capture_paypal_payment((int) $user['id'], $number, $token);
        flash('success', 'Payment verified. Your order is paid.');
    }
} catch (AppException $exception) {
    flash('error', $exception->getMessage());
}
redirect('public/order_detail.php?order=' . rawurlencode($number));
