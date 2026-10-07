<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('seller');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $id = save_product(null, (int) $user['id'], false);
        flash('success', 'Product created.');
        redirect('seller/product_edit.php?id=' . $id);
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
        set_old($_POST);
    } catch (Throwable $exception) {
        log_message('product', $exception->getMessage());
        flash('error', 'The product could not be created.');
        set_old($_POST);
    }
}

$categories = db()->query('SELECT id, name FROM categories ORDER BY name')->fetchAll();
$product = null;
$images = [];
$asAdmin = false;
$pageTitle = 'Add product';
$activeMenu = 'products';
require APP_ROOT . '/includes/seller_header.php';
?>
<form method="post" enctype="multipart/form-data">
    <?= csrf_field() ?>
    <?php require APP_ROOT . '/includes/product_form.php'; ?>
</form>
<?php require APP_ROOT . '/includes/seller_footer.php'; ?>
