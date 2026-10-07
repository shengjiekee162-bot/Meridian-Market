<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('seller');
$productId = request_int('id', 'get');

try {
    $product = load_owned_product($productId, (int) $user['id'], false);
} catch (AppException $exception) {
    flash('error', $exception->getMessage());
    redirect('seller/products.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        save_product($productId, (int) $user['id'], false);
        flash('success', 'Product saved.');
        redirect('seller/product_edit.php?id=' . $productId);
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
        set_old($_POST);
    } catch (Throwable $exception) {
        log_message('product', $exception->getMessage());
        flash('error', 'The product could not be saved.');
        set_old($_POST);
    }
    $product = load_owned_product($productId, (int) $user['id'], false);
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$images = product_images($productId);
$asAdmin = false;
$pageTitle = 'Edit product';
$activeMenu = 'products';
require APP_ROOT . '/includes/seller_header.php';
?>
<form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php require APP_ROOT . '/includes/product_form.php'; ?>
</form>
<?php require APP_ROOT . '/includes/seller_footer.php'; ?>
