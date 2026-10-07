<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');

$counts = [
    'users' => (int) db()->query('SELECT COUNT(*) FROM users')->fetchColumn(),
    'sellers' => (int) db()->query("SELECT COUNT(*) FROM users WHERE role = 'seller'")->fetchColumn(),
    'products' => (int) db()->query('SELECT COUNT(*) FROM products')->fetchColumn(),
    'orders' => (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn(),
    'pending' => (int) db()->query("SELECT COUNT(*) FROM orders WHERE order_status = 'pending'")->fetchColumn(),
    'completed' => (int) db()->query("SELECT COUNT(*) FROM orders WHERE order_status = 'completed'")->fetchColumn(),
    'revenue' => (float) db()->query("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE payment_status = 'paid' AND order_status <> 'cancelled'")->fetchColumn(),
];
$recent = db()->query(
    'SELECT o.*, u.name AS customer_name
     FROM orders o INNER JOIN users u ON u.id = o.user_id
     ORDER BY o.id DESC LIMIT 8'
)->fetchAll();
$statusRows = db()->query('SELECT order_status, COUNT(*) AS total FROM orders GROUP BY order_status')->fetchAll();
$categoryRows = db()->query(
    "SELECT c.name, COUNT(p.id) AS total
     FROM categories c LEFT JOIN products p ON p.category_id = c.id
     GROUP BY c.id, c.name ORDER BY total DESC"
)->fetchAll();
$monthRows = db()->query(
    "SELECT DATE_FORMAT(created_at, '%Y-%m') AS ym, COALESCE(SUM(total_amount),0) AS total
     FROM orders
     WHERE payment_status = 'paid' AND order_status <> 'cancelled'
       AND created_at >= DATE_SUB(CURDATE(), INTERVAL 5 MONTH)
     GROUP BY DATE_FORMAT(created_at, '%Y-%m') ORDER BY ym"
)->fetchAll();
$byMonth = [];
foreach ($monthRows as $row) {
    $byMonth[$row['ym']] = (float) $row['total'];
}
$series = [];
$cursor = new DateTimeImmutable('first day of this month');
$cursor = $cursor->modify('-5 months');
for ($i = 0; $i < 6; $i++) {
    $key = $cursor->format('Y-m');
    $series[$key] = $byMonth[$key] ?? 0;
    $cursor = $cursor->modify('+1 month');
}
$maxMonth = max(1, max($series));
$pageTitle = 'Dashboard';
$activeMenu = 'dashboard';
require APP_ROOT . '/includes/admin_header.php';
?>
<div class="stat-grid">
    <article class="stat-card"><span>Users</span><strong><?= e((string) $counts['users']) ?></strong></article>
    <article class="stat-card"><span>Sellers</span><strong><?= e((string) $counts['sellers']) ?></strong></article>
    <article class="stat-card"><span>Products</span><strong><?= e((string) $counts['products']) ?></strong></article>
    <article class="stat-card"><span>Orders</span><strong><?= e((string) $counts['orders']) ?></strong></article>
    <article class="stat-card"><span>Pending orders</span><strong><?= e((string) $counts['pending']) ?></strong></article>
    <article class="stat-card"><span>Completed</span><strong><?= e((string) $counts['completed']) ?></strong></article>
    <article class="stat-card"><span>Paid revenue</span><strong><?= e(money($counts['revenue'])) ?></strong></article>
</div>
<div class="row g-4 mt-1">
    <div class="col-lg-7">
        <section class="form-card">
            <h2>Paid sales by month</h2>
            <div class="chart-bars">
                <?php foreach ($series as $label => $total): ?>
                    <div style="height: <?= e((string) max(8, (int) round(($total / $maxMonth) * 160))) ?>px" title="<?= e(money($total)) ?>"><span><?= e(substr($label, 5)) ?></span></div>
                <?php endforeach; ?>
            </div>
        </section>
    </div>
    <div class="col-lg-5">
        <section class="form-card">
            <h2>Orders by status</h2>
            <?php foreach ($statusRows as $row): ?>
                <p class="d-flex justify-content-between"><span><?= e($row['order_status']) ?></span><strong><?= e((string) $row['total']) ?></strong></p>
            <?php endforeach; ?>
            <h2 class="h5 mt-3">Products by category</h2>
            <?php foreach ($categoryRows as $row): ?>
                <p class="d-flex justify-content-between mb-1"><span><?= e($row['name']) ?></span><strong><?= e((string) $row['total']) ?></strong></p>
            <?php endforeach; ?>
        </section>
    </div>
</div>
<section class="form-card mt-4">
    <h2>Recent orders</h2>
    <div class="table-responsive">
        <table class="table align-middle">
            <thead><tr><th>Order</th><th>Customer</th><th>Amount</th><th>Payment</th><th>Status</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($recent as $order): ?>
                <tr>
                    <td><?= e($order['order_number']) ?></td>
                    <td><?= e($order['customer_name']) ?></td>
                    <td><?= e(money($order['total_amount'])) ?></td>
                    <td><span class="badge-status badge-<?= e($order['payment_status']) ?>"><?= e($order['payment_status']) ?></span></td>
                    <td><span class="badge-status badge-<?= e($order['order_status']) ?>"><?= e($order['order_status']) ?></span></td>
                    <td><?= e(date('M j, Y', strtotime((string) $order['created_at']))) ?></td>
                    <td><a class="btn btn-quiet btn-sm" href="<?= e(url('admin/order_view.php?id=' . (int) $order['id'])) ?>">View</a></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php require APP_ROOT . '/includes/admin_footer.php'; ?>
