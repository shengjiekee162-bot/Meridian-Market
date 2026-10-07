<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('seller');

try {
    handle_product_list_action((int) $user['id'], false);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        redirect('seller/products.php');
    }
} catch (AppException $exception) {
    flash('error', $exception->getMessage());
    redirect('seller/products.php');
}

$search = query_string('q', 80);
$status = query_string('status', 20);
$where = ['p.seller_id = ?'];
$params = [(int) $user['id']];
if ($search !== '') {
    $where[] = "(p.name LIKE ? ESCAPE '\\\\' OR p.sku LIKE ? ESCAPE '\\\\')";
    $like = like_pattern($search);
    $params[] = $like;
    $params[] = $like;
}
if (in_array($status, ['active', 'inactive', 'archived'], true)) {
    $where[] = 'p.status = ?';
    $params[] = $status;
}
$stmt = db()->prepare(
    'SELECT p.*, c.name AS category_name,
            (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY display_order, id LIMIT 1) AS image_path
     FROM products p
     INNER JOIN categories c ON c.id = p.category_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY p.updated_at DESC'
);
$stmt->execute($params);
$products = $stmt->fetchAll();
$pageTitle = 'Products';
$activeMenu = 'products';
require APP_ROOT . '/includes/seller_header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <form class="d-flex gap-2" method="get">
        <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Name or SKU">
        <select class="form-select" name="status">
            <option value="">All statuses</option>
            <?php foreach (['active', 'inactive', 'archived'] as $option): ?>
                <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-quiet" type="submit">Filter</button>
    </form>
    <a class="btn btn-accent" href="<?= e(url('seller/product_add.php')) ?>">Add product</a>
</div>
<div class="table-responsive form-card">
    <table class="table align-middle">
        <thead><tr><th>Product</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($products as $product): ?>
            <tr>
                <td>
                    <strong><?= e($product['name']) ?></strong>
                    <div class="muted"><?= e($product['sku']) ?> · <?= e($product['category_name']) ?></div>
                </td>
                <td><?= e(money($product['price'])) ?></td>
                <td>
                    <form method="post" class="d-flex gap-2">
                        <?= csrf_field() ?>
                        <input type="hidden" name="action" value="stock">
                        <input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>">
                        <input class="form-control" style="width:5.5rem" type="number" name="stock" min="0" value="<?= e((string) $product['stock']) ?>">
                        <button class="btn btn-quiet btn-sm" type="submit">Save</button>
                    </form>
                </td>
                <td><span class="badge-status badge-<?= e($product['status']) ?>"><?= e($product['status']) ?></span></td>
                <td class="text-nowrap">
                    <a href="<?= e(url('seller/product_edit.php?id=' . (int) $product['id'])) ?>">Edit</a>
                    <?php if ($product['status'] === 'active'): ?>
                        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="deactivate"><input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>"><button class="btn btn-link btn-sm" type="submit">Hide</button></form>
                    <?php elseif ($product['status'] === 'inactive'): ?>
                        <form method="post" class="d-inline"><?= csrf_field() ?><input type="hidden" name="action" value="activate"><input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>"><button class="btn btn-link btn-sm" type="submit">Show</button></form>
                    <?php endif; ?>
                    <?php if ($product['status'] !== 'archived'): ?>
                        <form method="post" class="d-inline" data-confirm="Archive this product?"><?= csrf_field() ?><input type="hidden" name="action" value="archive"><input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>"><button class="btn btn-link btn-sm" type="submit">Archive</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require APP_ROOT . '/includes/seller_footer.php'; ?>
