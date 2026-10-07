<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $user = require_login();
        $action = input_string('action', 20);
        $productId = request_int('product_id');
        if ($action === 'add') {
            add_wishlist((int) $user['id'], $productId);
            flash('success', 'Saved to your wishlist.');
            redirect(safe_back_url('public/wishlist.php'));
        } elseif ($action === 'remove') {
            remove_wishlist((int) $user['id'], $productId);
            flash('success', 'Removed from your wishlist.');
        } elseif ($action === 'move') {
            $name = move_wishlist_to_cart((int) $user['id'], $productId);
            flash('success', $name . ' moved to your cart.');
        } else {
            throw new AppException('That wishlist action is not available.');
        }
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
    }
    redirect('public/wishlist.php');
}

$user = current_user();
$pageTitle = 'Wishlist';
require APP_ROOT . '/includes/header.php';
?>
<main id="content" class="container py-4">
    <h1>Wishlist</h1>
    <?php if (!$user): ?>
        <?php render_empty('fa-heart', 'Sign in to save products.', 'Browse freely without an account. Sign in when you want a wishlist that stays with you.', url('public/login.php'), 'Sign in'); ?>
    <?php else: ?>
        <?php
        $stmt = db()->prepare(
            'SELECT p.id, p.name, p.slug, p.price, p.compare_price, p.stock, p.status, c.name AS category_name, c.status AS category_status,
                    u.status AS seller_status, sp.shop_name,
                    (SELECT image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY display_order, id LIMIT 1) AS image_path
             FROM wishlists w
             INNER JOIN products p ON p.id = w.product_id
             INNER JOIN categories c ON c.id = p.category_id
             INNER JOIN users u ON u.id = p.seller_id
             LEFT JOIN seller_profiles sp ON sp.user_id = p.seller_id
             WHERE w.user_id = ?
             ORDER BY w.created_at DESC'
        );
        $stmt->execute([(int) $user['id']]);
        $items = $stmt->fetchAll();
        ?>
        <?php if (!$items): ?>
            <?php render_empty('fa-heart', 'Nothing saved yet.', 'Tap the heart on a product to keep it here.', url('public/shop.php'), 'Browse products'); ?>
        <?php else: ?>
            <?php foreach ($items as $item): ?>
                <?php $available = $item['status'] === 'active' && $item['category_status'] === 'active' && $item['seller_status'] === 'active' && (int) $item['stock'] > 0; ?>
                <article class="cart-line">
                    <img src="<?= e(media_url($item['image_path'] ?? null)) ?>" alt="">
                    <div>
                        <h2 class="h5"><a href="<?= e(url('public/product.php?slug=' . rawurlencode((string) $item['slug']))) ?>"><?= e($item['name']) ?></a></h2>
                        <p class="muted mb-1"><?= e($item['category_name']) ?> · <?= e(money($item['price'])) ?></p>
                        <?php if (!$available): ?><p class="text-danger">Currently unavailable</p><?php endif; ?>
                    </div>
                    <div class="d-flex flex-column gap-2">
                        <?php if ($available): ?>
                            <form method="post">
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="move">
                                <input type="hidden" name="product_id" value="<?= e((string) $item['id']) ?>">
                                <button class="btn btn-accent btn-sm" type="submit">Move to cart</button>
                            </form>
                        <?php endif; ?>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove">
                            <input type="hidden" name="product_id" value="<?= e((string) $item['id']) ?>">
                            <button class="btn btn-quiet btn-sm" type="submit">Remove</button>
                        </form>
                    </div>
                </article>
            <?php endforeach; ?>
        <?php endif; ?>
    <?php endif; ?>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
