<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
$status = query_string('status', 20);
$q = query_string('q', 40);
$where = ['1=1'];
$params = [];
if (in_array($status, ['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled'], true)) {
    $where[] = 'o.order_status = ?';
    $params[] = $status;
}
if ($q !== '') {
    $where[] = 'o.order_number LIKE ? ESCAPE \'\\\\\'';
    $params[] = like_pattern($q);
}
$stmt = db()->prepare(
    'SELECT o.*, u.name AS customer_name
     FROM orders o INNER JOIN users u ON u.id = o.user_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY o.id DESC LIMIT 50'
);
$stmt->execute($params);
$orders = $stmt->fetchAll();
$pageTitle = 'Orders';
$activeMenu = 'orders';
require APP_ROOT . '/includes/admin_header.php';
?>
<form class="d-flex gap-2 mb-3" method="get">
    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Order number">
    <select class="form-select" name="status">
        <option value="">All statuses</option>
        <?php foreach (['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled'] as $option): ?>
            <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-quiet" type="submit">Filter</button>
</form>
<div class="table-responsive form-card">
    <table class="table align-middle">
        <thead><tr><th>Order</th><th>Customer</th><th>Total</th><th>Payment</th><th>Status</th><th>Transaction</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($orders as $order): ?>
            <tr>
                <td><?= e($order['order_number']) ?><div class="muted"><?= e(date('M j, Y', strtotime((string) $order['created_at']))) ?></div></td>
                <td><?= e($order['customer_name']) ?></td>
                <td><?= e(money($order['total_amount'])) ?></td>
                <td><?= e($order['payment_method']) ?> · <?= e($order['payment_status']) ?></td>
                <td><?= e($order['order_status']) ?></td>
                <td><?= e($order['transaction_id'] ?: '—') ?></td>
                <td><a href="<?= e(url('admin/order_view.php?id=' . (int) $order['id'])) ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require APP_ROOT . '/includes/admin_footer.php'; ?>
