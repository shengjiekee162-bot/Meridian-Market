<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$admin = require_role('admin');
$orderId = request_int('id', 'get');
$order = $orderId > 0 ? fetch_order_by_id($orderId) : null;

if ($order && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = input_string('action', 20);
        if ($action === 'mark_paid') {
            mark_manual_payment((int) $order['id'], (int) $admin['id']);
            flash('success', 'Payment marked as paid.');
        } else {
            change_order_status((int) $order['id'], input_string('order_status', 20), (int) $admin['id'], input_string('note', 255));
            flash('success', 'Order status updated.');
        }
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
    }
    redirect('admin/order_view.php?id=' . (int) $order['id']);
}

$pageTitle = $order ? (string) $order['order_number'] : 'Order';
$activeMenu = 'orders';
require APP_ROOT . '/includes/admin_header.php';
?>
<?php if (!$order): ?>
    <?php render_empty('fa-receipt', 'Order not found.', 'Return to the order list.', url('admin/orders.php'), 'All orders'); ?>
<?php else: ?>
    <?php
    $items = order_items((int) $order['id']);
    $events = order_events((int) $order['id']);
    $payment = order_payment((int) $order['id']);
    $customer = db()->prepare('SELECT name, email FROM users WHERE id = ?');
    $customer->execute([(int) $order['user_id']]);
    $buyer = $customer->fetch();
    ?>
    <div class="row g-4">
        <div class="col-lg-7">
            <section class="form-card">
                <h2>Items</h2>
                <?php foreach ($items as $item): ?>
                    <p><?= e($item['product_name']) ?> · <?= e($item['shop_name'] ?: 'Seller') ?> · <?= e((string) $item['quantity']) ?> × <?= e(money($item['unit_price'])) ?></p>
                <?php endforeach; ?>
                <p>Subtotal <?= e(money($order['subtotal'])) ?> · Discount <?= e(money($order['discount_amount'])) ?> · Shipping <?= e(money($order['shipping_fee'])) ?></p>
                <p><strong>Total <?= e(money($order['total_amount'])) ?></strong></p>
            </section>
            <section class="form-card mt-3">
                <h2>Customer</h2>
                <p><?= e($buyer['name'] ?? '') ?> · <?= e($buyer['email'] ?? '') ?></p>
                <p><?= e($order['shipping_name']) ?>, <?= e($order['shipping_phone']) ?></p>
                <p><?= nl2br(e($order['shipping_address'])) ?></p>
            </section>
        </div>
        <div class="col-lg-5">
            <section class="form-card">
                <h2>Payment</h2>
                <p><?= e($order['payment_method']) ?> · <?= e($order['payment_status']) ?></p>
                <p>Transaction: <?= e($order['transaction_id'] ?: 'None yet') ?></p>
                <?php if ($payment): ?>
                    <p>Provider reference: <?= e($payment['provider_reference'] ?: 'None') ?></p>
                    <p>Recorded amount: <?= e(money($payment['amount'])) ?> <?= e($payment['currency']) ?></p>
                <?php endif; ?>
                <?php if (in_array($order['payment_method'], ['cod', 'bank'], true) && $order['payment_status'] !== 'paid' && $order['order_status'] !== 'cancelled'): ?>
                    <form method="post" data-confirm="Mark this payment as received?"><?= csrf_field() ?><input type="hidden" name="action" value="mark_paid"><button class="btn btn-accent btn-sm" type="submit">Mark paid</button></form>
                <?php endif; ?>
            </section>
            <form method="post" class="form-card mt-3">
                <?= csrf_field() ?>
                <h2>Update status</h2>
                <label class="form-label" for="order_status">Status</label>
                <select class="form-select mb-3" id="order_status" name="order_status" data-status-select>
                    <?php foreach (['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled'] as $option): ?>
                        <option value="<?= e($option) ?>" <?= $order['order_status'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
                <label class="form-label" for="note">Note visible to the customer</label>
                <textarea class="form-control mb-3" id="note" name="note" maxlength="255" data-status-note></textarea>
                <button class="btn btn-accent" type="submit">Save status</button>
            </form>
            <section class="form-card mt-3">
                <h2>Timeline</h2>
                <?php foreach ($events as $event): ?>
                    <p class="mb-1"><strong><?= e($event['status']) ?></strong> · <?= e(date('M j, g:i a', strtotime((string) $event['created_at']))) ?><?php if ($event['note']): ?> · <?= e($event['note']) ?><?php endif; ?></p>
                <?php endforeach; ?>
            </section>
        </div>
    </div>
<?php endif; ?>
<?php require APP_ROOT . '/includes/admin_footer.php'; ?>
