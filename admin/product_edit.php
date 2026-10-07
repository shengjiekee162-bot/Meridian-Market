<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');
$productId = request_int('id', 'get');
try {
    $product = load_owned_product($productId, (int) $user['id'], true);
} catch (AppException $exception) {
    flash('error', $exception->getMessage());
    redirect('admin/products.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        save_product($productId, (int) $user['id'], true);
        flash('success', 'Product saved.');
        redirect('admin/product_edit.php?id=' . $productId);
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
        set_old($_POST);
    } catch (Throwable $exception) {
        log_message('product', $exception->getMessage());
        flash('error', 'The product could not be saved.');
        set_old($_POST);
    }
    $product = load_owned_product($productId, (int) $user['id'], true);
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$sellers = db()->query("SELECT u.id, u.name, sp.shop_name FROM users u LEFT JOIN seller_profiles sp ON sp.user_id = u.id WHERE u.role = 'seller' ORDER BY sp.shop_name")->fetchAll();
$images = product_images($productId);
$asAdmin = true;
$pageTitle = 'Edit product';
$activeMenu = 'products';
require APP_ROOT . '/includes/admin_header.php';
?>
<form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php require APP_ROOT . '/includes/product_form.php'; ?>
</form>
<?php require APP_ROOT . '/includes/admin_footer.php'; ?>
