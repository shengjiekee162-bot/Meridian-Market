<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$number = query_string('order', 24);
$order = fetch_order_for_user($number, (int) $user['id']);

if ($order && $order['payment_method'] === 'paypal' && $order['payment_status'] === 'unpaid' && $order['order_status'] === 'pending') {
    try {
        cancel_order((int) $order['id'], (int) $user['id'], 'Customer cancelled PayPal checkout', false);
        flash('success', 'PayPal checkout was cancelled. Stock has been returned and the order is marked cancelled.');
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
    }
} else {
    flash('error', 'No unpaid PayPal order was cancelled.');
}
redirect($order ? 'public/order_detail.php?order=' . rawurlencode($number) : 'public/orders.php');
