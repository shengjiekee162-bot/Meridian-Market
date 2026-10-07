<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$page = max(1, request_int('page', 'get'));
$perPage = 8;
$count = db()->prepare('SELECT COUNT(*) FROM orders WHERE user_id = ?');
$count->execute([(int) $user['id']]);
$total = (int) $count->fetchColumn();
$pages = max(1, (int) ceil($total / $perPage));
if ($page > $pages) {
    $page = $pages;
}
$offset = ($page - 1) * $perPage;
$stmt = db()->prepare(
    "SELECT * FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT $perPage OFFSET $offset"
);
$stmt->execute([(int) $user['id']]);
$orders = $stmt->fetchAll();
$pageTitle = 'My orders';
require APP_ROOT . '/includes/header.php';
?>
<main id="content" class="container py-4">
    <h1>My orders</h1>
    <?php if (!$orders): ?>
        <?php render_empty('fa-receipt', 'No orders yet.', 'When you check out, the order will appear here.', url('public/shop.php'), 'Start shopping'); ?>
    <?php else: ?>
        <?php foreach ($orders as $order): ?>
            <article class="order-card">
                <div>
                    <strong><?= e($order['order_number']) ?></strong>
                    <p class="muted mb-0"><?= e(date('M j, Y', strtotime((string) $order['created_at']))) ?></p>
                </div>
                <div>
                    <span class="badge-status badge-<?= e($order['order_status']) ?>"><?= e(order_status_label($order['order_status'])) ?></span>
                    <span class="badge-status badge-<?= e($order['payment_status']) ?>"><?= e(payment_status_label($order['payment_status'])) ?></span>
                    <p class="mb-0 mt-1"><?= e(money($order['total_amount'])) ?></p>
                </div>
                <a class="btn btn-quiet btn-sm" href="<?= e(url('public/order_detail.php?order=' . rawurlencode((string) $order['order_number']))) ?>">View</a>
            </article>
        <?php endforeach; ?>
        <?= render_pagination($page, $pages, 'public/orders.php') ?>
    <?php endif; ?>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
