<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

try {
    handle_product_list_action((int) $user['id'], true);
    if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
        redirect('admin/products.php');
    }
} catch (AppException $exception) {
    flash('error', $exception->getMessage());
    redirect('admin/products.php');
}

$search = query_string('q', 80);
$status = query_string('status', 20);
$where = ['1=1'];
$params = [];
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
    'SELECT p.*, c.name AS category_name, sp.shop_name
     FROM products p
     INNER JOIN categories c ON c.id = p.category_id
     LEFT JOIN seller_profiles sp ON sp.user_id = p.seller_id
     WHERE ' . implode(' AND ', $where) . '
     ORDER BY p.id DESC'
);
$stmt->execute($params);
$products = $stmt->fetchAll();
$pageTitle = 'Products';
$activeMenu = 'products';
require APP_ROOT . '/includes/admin_header.php';
?>
<div class="d-flex justify-content-between mb-3">
    <form class="d-flex gap-2" method="get">
        <input class="form-control" name="q" value="<?= e($search) ?>" placeholder="Name or SKU">
        <select class="form-select" name="status">
            <option value="">All</option>
            <?php foreach (['active', 'inactive', 'archived'] as $option): ?>
                <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e($option) ?></option>
            <?php endforeach; ?>
        </select>
        <button class="btn btn-quiet" type="submit">Filter</button>
    </form>
    <a class="btn btn-accent" href="<?= e(url('admin/product_add.php')) ?>">Add product</a>
</div>
<div class="table-responsive form-card">
    <table class="table align-middle">
        <thead><tr><th>Product</th><th>Seller</th><th>Price</th><th>Stock</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($products as $product): ?>
            <tr>
                <td><strong><?= e($product['name']) ?></strong><div class="muted"><?= e($product['sku']) ?> · <?= e($product['category_name']) ?></div></td>
                <td><?= e($product['shop_name'] ?: 'Seller') ?></td>
                <td><?= e(money($product['price'])) ?></td>
                <td>
                    <form method="post" class="d-flex gap-2"><?= csrf_field() ?>
                        <input type="hidden" name="action" value="stock">
                        <input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>">
                        <input class="form-control" style="width:5rem" type="number" min="0" name="stock" value="<?= e((string) $product['stock']) ?>">
                        <button class="btn btn-quiet btn-sm" type="submit">Save</button>
                    </form>
                </td>
                <td><?= e($product['status']) ?><?= (int) $product['featured'] === 1 ? ' · featured' : '' ?></td>
                <td class="text-nowrap">
                    <a href="<?= e(url('admin/product_edit.php?id=' . (int) $product['id'])) ?>">Edit</a>
                    <?php if ($product['status'] !== 'archived'): ?>
                        <form class="d-inline" method="post"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>"><input type="hidden" name="action" value="<?= (int) $product['featured'] === 1 ? 'unfeature' : 'feature' ?>"><button class="btn btn-link btn-sm" type="submit"><?= (int) $product['featured'] === 1 ? 'Unfeature' : 'Feature' ?></button></form>
                        <form class="d-inline" method="post"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>"><input type="hidden" name="action" value="<?= $product['status'] === 'active' ? 'deactivate' : 'activate' ?>"><button class="btn btn-link btn-sm" type="submit"><?= $product['status'] === 'active' ? 'Hide' : 'Show' ?></button></form>
                        <form class="d-inline" method="post" data-confirm="Archive this product?"><?= csrf_field() ?><input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>"><input type="hidden" name="action" value="archive"><button class="btn btn-link btn-sm" type="submit">Archive</button></form>
                    <?php endif; ?>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require APP_ROOT . '/includes/admin_footer.php'; ?>
