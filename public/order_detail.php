<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$number = query_string('order', 24);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $order = fetch_order_for_user($number !== '' ? $number : input_string('order', 24), (int) $user['id']);
        if (!$order) {
            throw new AppException('That order was not found.');
        }
        $action = input_string('action', 20);
        if ($action === 'cancel') {
            cancel_order((int) $order['id'], (int) $user['id'], input_string('reason', 255), false);
            flash('success', 'The order was cancelled and stock was restored.');
        } elseif ($action === 'pay') {
            redirect('public/paypal_go.php?order=' . rawurlencode((string) $order['order_number']));
        } else {
            throw new AppException('That order action is not available.');
        }
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
    }
    redirect('public/order_detail.php?order=' . rawurlencode($number !== '' ? $number : input_string('order', 24)));
}

$order = fetch_order_for_user($number, (int) $user['id']);
$pageTitle = $order ? (string) $order['order_number'] : 'Order';
require APP_ROOT . '/includes/header.php';
?>
<main id="content" class="container py-4">
    <?php if (!$order): ?>
        <?php render_empty('fa-receipt', 'Order not found.', 'Check the order number or return to your history.', url('public/orders.php'), 'My orders'); ?>
    <?php else: ?>
        <?php $items = order_items((int) $order['id']); $events = order_events((int) $order['id']); $payment = order_payment((int) $order['id']); ?>
        <p class="eyebrow">Order</p>
        <h1><?= e($order['order_number']) ?></h1>
        <p class="muted">Placed <?= e(date('M j, Y g:i a', strtotime((string) $order['created_at']))) ?></p>
        <div class="row g-4">
            <div class="col-lg-7">
                <?php foreach ($items as $item): ?>
                    <article class="order-card">
                        <div>
                            <strong><?= e($item['product_name']) ?></strong>
                            <p class="muted mb-0"><?= e($item['shop_name'] ?: 'Studio') ?></p>
                        </div>
                        <div><?= e((string) $item['quantity']) ?> × <?= e(money($item['unit_price'])) ?></div>
                        <strong><?= e(money($item['subtotal'])) ?></strong>
                    </article>
                <?php endforeach; ?>
                <section class="form-card mt-3">
                    <h2>Shipping</h2>
                    <p class="mb-1"><?= e($order['shipping_name']) ?></p>
                    <p class="mb-1"><?= e($order['shipping_phone']) ?> · <?= e($order['shipping_email']) ?></p>
                    <p><?= nl2br(e($order['shipping_address'])) ?></p>
                </section>
                <?php if (customer_can_cancel($order)): ?>
                    <form method="post" class="form-card mt-3" data-confirm="Cancel this order and restore stock?">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order" value="<?= e($order['order_number']) ?>">
                        <input type="hidden" name="action" value="cancel">
                        <label class="form-label" for="reason">Cancellation reason</label>
                        <textarea class="form-control mb-3" id="reason" name="reason" required minlength="3" maxlength="255"></textarea>
                        <button class="btn btn-danger-soft" type="submit">Cancel order</button>
                    </form>
                <?php endif; ?>
                <?php if ($order['payment_method'] === 'paypal' && $order['payment_status'] === 'unpaid' && $order['order_status'] === 'pending'): ?>
                    <form method="post" class="mt-3">
                        <?= csrf_field() ?>
                        <input type="hidden" name="order" value="<?= e($order['order_number']) ?>">
                        <input type="hidden" name="action" value="pay">
                        <button class="btn btn-accent" type="submit">Continue to PayPal</button>
                    </form>
                <?php endif; ?>
                <?php if ($order['payment_method'] === 'bank' && $order['payment_status'] !== 'paid' && $order['order_status'] !== 'cancelled'): ?>
                    <section class="form-card mt-3">
                        <h2>Bank transfer</h2>
                        <p><?= e((string) config('app', 'bank_instructions')) ?></p>
                    </section>
                <?php endif; ?>
            </div>
            <div class="col-lg-5">
                <aside class="summary-card">
                    <div class="totals">
                        <div><span>Subtotal</span><span><?= e(money($order['subtotal'])) ?></span></div>
                        <div><span>Discount</span><span>-<?= e(money($order['discount_amount'])) ?></span></div>
                        <div><span>Shipping</span><span><?= e(money($order['shipping_fee'])) ?></span></div>
                        <div class="grand"><span>Total</span><span><?= e(money($order['total_amount'])) ?></span></div>
                    </div>
                    <p>Payment: <?= e(strtoupper((string) $order['payment_method'])) ?> · <span class="badge-status badge-<?= e($order['payment_status']) ?>"><?= e($order['payment_status']) ?></span></p>
                    <p>Status: <span class="badge-status badge-<?= e($order['order_status']) ?>"><?= e($order['order_status']) ?></span></p>
                    <?php if ($order['transaction_id']): ?><p>Transaction: <?= e($order['transaction_id']) ?></p><?php endif; ?>
                    <?php if ($order['cancellation_reason']): ?><p>Cancellation: <?= e($order['cancellation_reason']) ?></p><?php endif; ?>
                    <h2 class="h5">Timeline</h2>
                    <ol class="timeline">
                        <?php foreach (order_timeline($order) as $step): ?>
                            <li class="<?= e($step['state']) ?>"><?= e($step['label']) ?></li>
                        <?php endforeach; ?>
                    </ol>
                    <?php if ($events): ?>
                        <h2 class="h5 mt-3">Notes</h2>
                        <?php foreach ($events as $event): ?>
                            <?php if ($event['note']): ?><p class="mb-1"><strong><?= e($event['status']) ?>:</strong> <?= e($event['note']) ?></p><?php endif; ?>
                        <?php endforeach; ?>
                    <?php endif; ?>
                    <?php if ($payment): ?>
                        <p class="muted mb-0">Recorded payment <?= e(money($payment['amount'])) ?> <?= e($payment['currency']) ?>.</p>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
