<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('seller');
$status = query_string('status', 20);
$where = 'oi.seller_id = ?';
$params = [(int) $user['id']];
if (in_array($status, ['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled'], true)) {
    $where .= ' AND o.order_status = ?';
    $params[] = $status;
}
$stmt = db()->prepare(
    "SELECT o.id, o.order_number, o.order_status, o.payment_status, o.created_at,
            SUM(oi.subtotal) AS seller_subtotal, SUM(oi.quantity) AS units
     FROM orders o
     INNER JOIN order_items oi ON oi.order_id = o.id
     WHERE $where
     GROUP BY o.id, o.order_number, o.order_status, o.payment_status, o.created_at
     ORDER BY o.id DESC"
);
$stmt->execute($params);
$orders = $stmt->fetchAll();
$pageTitle = 'Sales';
$activeMenu = 'orders';
require APP_ROOT . '/includes/seller_header.php';
?>
<form class="d-flex gap-2 mb-3" method="get">
    <select class="form-select" style="max-width:220px" name="status">
        <option value="">All statuses</option>
        <?php foreach (['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled'] as $option): ?>
            <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-quiet" type="submit">Filter</button>
</form>
<div class="table-responsive form-card">
    <table class="table align-middle">
        <thead><tr><th>Order</th><th>Your items</th><th>Your subtotal</th><th>Status</th><th>Payment</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td><?= e($order['order_number']) ?><div class="muted"><?= e(date('M j, Y', strtotime((string) $order['created_at']))) ?></div></td>
                <td><?= e((string) $order['units']) ?></td>
                <td><?= e(money($order['seller_subtotal'])) ?></td>
                <td><span class="badge-status badge-<?= e($order['order_status']) ?>"><?= e($order['order_status']) ?></span></td>
                <td><?= e($order['payment_status']) ?></td>
                <td><a href="<?= e(url('seller/order_view.php?id=' . (int) $order['id'])) ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php if (!$orders): ?>
    <?php render_empty('fa-receipt', 'No sales match this filter.', 'Orders that include your products will show up here.'); ?>
<?php endif; ?>
<?php require APP_ROOT . '/includes/seller_footer.php'; ?>
