<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('seller');
$sellerId = (int) $user['id'];

$productCount = db()->prepare('SELECT COUNT(*) FROM products WHERE seller_id = ?');
$productCount->execute([$sellerId]);
$activeCount = db()->prepare("SELECT COUNT(*) FROM products WHERE seller_id = ? AND status = 'active'");
$activeCount->execute([$sellerId]);
$stats = [
    'products' => (int) $productCount->fetchColumn(),
    'active' => (int) $activeCount->fetchColumn(),
];
$orderStats = db()->prepare(
    "SELECT
        COUNT(DISTINCT o.id) AS total_orders,
        COUNT(DISTINCT CASE WHEN o.order_status = 'pending' THEN o.id END) AS pending_orders,
        COUNT(DISTINCT CASE WHEN o.order_status = 'completed' THEN o.id END) AS completed_orders,
        COALESCE(SUM(CASE WHEN o.order_status <> 'cancelled' THEN oi.subtotal END), 0) AS sales
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE oi.seller_id = ?"
);
$orderStats->execute([$sellerId]);
$orders = $orderStats->fetch() ?: [];
$recent = db()->prepare(
    'SELECT DISTINCT o.order_number, o.total_amount, o.order_status, o.payment_status, o.created_at, o.id
     FROM orders o
     INNER JOIN order_items oi ON oi.order_id = o.id
     WHERE oi.seller_id = ?
     ORDER BY o.id DESC
     LIMIT 6'
);
$recent->execute([$sellerId]);
$pageTitle = 'Overview';
$activeMenu = 'dashboard';
require APP_ROOT . '/includes/seller_header.php';
?>
<div class="stat-grid">
    <article class="stat-card"><span>Products</span><strong><?= e((string) $stats['products']) ?></strong></article>
    <article class="stat-card"><span>Active listings</span><strong><?= e((string) $stats['active']) ?></strong></article>
    <article class="stat-card"><span>Orders</span><strong><?= e((string) ($orders['total_orders'] ?? 0)) ?></strong></article>
    <article class="stat-card"><span>Pending</span><strong><?= e((string) ($orders['pending_orders'] ?? 0)) ?></strong></article>
    <article class="stat-card"><span>Completed</span><strong><?= e((string) ($orders['completed_orders'] ?? 0)) ?></strong></article>
    <article class="stat-card"><span>Item sales</span><strong><?= e(money($orders['sales'] ?? 0)) ?></strong></article>
</div>
<section class="form-card mt-4">
    <h2>Recent sales</h2>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Order</th><th>Status</th><th>Payment</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($recent->fetchAll() as $order): ?>
                <tr>
                    <td><?= e($order['order_number']) ?></td>
                    <td><span class="badge-status badge-<?= e($order['order_status']) ?>"><?= e($order['order_status']) ?></span></td>
                    <td><?= e($order['payment_status']) ?></td>
                    <td><?= e(date('M j, Y', strtotime((string) $order['created_at']))) ?></td>
                    <td><a href="<?= e(url('seller/order_view.php?id=' . (int) $order['id'])) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require APP_ROOT . '/includes/seller_footer.php'; ?>
