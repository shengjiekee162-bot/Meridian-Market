<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('seller');
$orderId = request_int('id', 'get');
$stmt = db()->prepare(
    'SELECT o.* FROM orders o
     INNER JOIN order_items oi ON oi.order_id = o.id
     WHERE o.id = ? AND oi.seller_id = ?
     LIMIT 1'
);
$stmt->execute([$orderId, (int) $user['id']]);
$order = $stmt->fetch();
$pageTitle = $order ? (string) $order['order_number'] : 'Order';
$activeMenu = 'orders';
require APP_ROOT . '/includes/seller_header.php';
?>
<?php if (!$order): ?>
    <?php render_empty('fa-receipt', 'That sale was not found.', 'You can only open orders that contain your products.', url('seller/orders.php'), 'Back to sales'); ?>
<?php else: ?>
    <?php $items = order_items((int) $order['id'], (int) $user['id']); ?>
    <p><span class="badge-status badge-<?= e($order['order_status']) ?>"><?= e($order['order_status']) ?></span>
       <span class="badge-status badge-<?= e($order['payment_status']) ?>"><?= e($order['payment_status']) ?></span></p>
    <section class="form-card mb-3">
        <h2>Your items</h2>
        <?php foreach ($items as $item): ?>
            <p><?= e($item['product_name']) ?> · <?= e((string) $item['quantity']) ?> × <?= e(money($item['unit_price'])) ?> = <?= e(money($item['subtotal'])) ?></p>
        <?php endforeach; ?>
    </section>
    <section class="form-card">
        <h2>Ship to</h2>
        <p class="mb-1"><?= e($order['shipping_name']) ?></p>
        <p class="mb-1"><?= e($order['shipping_phone']) ?></p>
        <p><?= nl2br(e($order['shipping_address'])) ?></p>
        <p class="muted mb-0">Marketplace status is updated by an administrator so multi-studio orders stay consistent.</p>
    </section>
<?php endif; ?>
<?php require APP_ROOT . '/includes/seller_footer.php'; ?>
