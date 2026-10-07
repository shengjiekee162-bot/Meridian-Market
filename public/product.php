<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$slug = query_string('slug', 160);
$product = find_public_product($slug);
if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product not found';
    require APP_ROOT . '/includes/header.php';
    echo '<main id="content" class="container py-5">';
    render_empty('fa-box-open', 'This product is not available.', 'It may be archived or the link is incorrect.', url('public/shop.php'), 'Back to the shop');
    echo '</main>';
    require APP_ROOT . '/includes/footer.php';
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $user = require_login();
        save_review((int) $user['id'], (int) $product['id'], request_int('rating'), input_string('comment', 1000));
        flash('success', 'Your review has been saved.');
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
        set_old($_POST);
    }
    redirect('public/product.php?slug=' . rawurlencode($slug));
}

$images = product_images((int) $product['id']);
$related = related_products((int) $product['id'], (int) $product['category_id']);
$reviews = published_reviews((int) $product['id']);
$breakdown = rating_breakdown((int) $product['id']);
$user = current_user();
$ownedReview = $user ? own_review((int) $user['id'], (int) $product['id']) : null;
$canReview = $user && purchase_order_for_product((int) $user['id'], (int) $product['id']);
$wished = $user ? wishlist_has((int) $user['id'], (int) $product['id']) : false;
$inStock = (int) $product['stock'] > 0;
$percent = discount_percent($product);
$pageTitle = (string) $product['name'];
require APP_ROOT . '/includes/header.php';
?>
<main id="content" class="container py-4">
    <div class="row g-4">
        <div class="col-lg-7">
            <div class="gallery" data-gallery>
                <div class="thumbs">
                    <?php foreach ($images as $image): ?>
                        <button type="button" data-gallery-src="<?= e(media_url($image['image_path'])) ?>" aria-label="Show image">
                            <img src="<?= e(media_url($image['image_path'])) ?>" alt="">
                        </button>
                    <?php endforeach; ?>
                </div>
                <div class="gallery-main">
                    <img data-gallery-main src="<?= e(media_url($images[0]['image_path'] ?? null)) ?>" alt="<?= e($product['name']) ?>">
                </div>
            </div>
        </div>
        <div class="col-lg-5">
            <p class="product-kicker"><?= e($product['category_name']) ?> · <?= e($product['shop_name'] ?: $product['seller_name']) ?></p>
            <h1><?= e($product['name']) ?></h1>
            <p class="muted">SKU <?= e($product['sku']) ?></p>
            <div class="price-row mb-2">
                <strong class="fs-3"><?= e(money($product['price'])) ?></strong>
                <?php if ($percent): ?><s><?= e(money($product['compare_price'])) ?></s><span class="flag flag-sale position-static"><?= e((string) $percent) ?>% off</span><?php endif; ?>
            </div>
            <div class="meta-row mb-3">
                <?php if ((int) $product['review_count'] > 0): ?>
                    <?= render_stars($product['avg_rating']) ?>
                    <span><?= e(number_format((float) $product['avg_rating'], 1)) ?> (<?= e((string) $product['review_count']) ?>)</span>
                <?php else: ?>
                    <span><?= te('product.no_reviews') ?></span>
                <?php endif; ?>
            </div>
            <p><?= nl2br(e($product['description'])) ?></p>
            <p><strong><?= $inStock ? te('product.in_stock', ['count' => (string) $product['stock']]) : te('product.out_of_stock') ?></strong></p>
            <div class="d-flex gap-2 align-items-center">
                <?php if ($inStock): ?>
                    <form method="post" action="<?= e(url('public/cart.php')) ?>" class="d-flex gap-2 align-items-center" data-product-cart>
                        <?= csrf_field() ?>
                        <input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>">
                        <div class="qty" data-qty>
                            <button type="button" data-qty-minus aria-label="<?= te('product.decrease_qty') ?>">−</button>
                            <input type="number" name="quantity" min="1" max="<?= e((string) min(20, (int) $product['stock'])) ?>" value="1" required>
                            <button type="button" data-qty-plus aria-label="<?= te('product.increase_qty') ?>">+</button>
                        </div>
                        <button class="btn btn-quiet" name="action" value="add" type="submit" data-add-cart-btn><?= te('product.add_to_cart') ?></button>
                        <button class="btn btn-accent" name="action" value="buy" type="submit"><?= te('product.buy_now') ?></button>
                    </form>
                <?php else: ?>
                    <button class="btn btn-quiet" type="button" disabled><?= te('product.out_of_stock') ?></button>
                <?php endif; ?>
                <form method="post" action="<?= e(url('public/wishlist.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="<?= $wished ? 'remove' : 'add' ?>">
                    <input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>">
                    <button class="icon-btn" type="submit" aria-label="<?= te('product.wishlist') ?>"><i class="<?= $wished ? 'fa-solid' : 'fa-regular' ?> fa-heart"></i></button>
                </form>
            </div>
        </div>
    </div>

    <section class="section">
        <div class="row g-4">
            <div class="col-lg-4">
                <h2><?= te('product.reviews') ?></h2>
                <?php $totalReviews = array_sum($breakdown); ?>
                <?php foreach ([5, 4, 3, 2, 1] as $star): ?>
                    <?php $width = $totalReviews > 0 ? (int) round(($breakdown[$star] / $totalReviews) * 100) : 0; ?>
                    <div class="bar-row">
                        <span><?= e((string) $star) ?> star</span>
                        <div class="bar"><span style="width: <?= e((string) $width) ?>%"></span></div>
                        <span><?= e((string) $breakdown[$star]) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
            <div class="col-lg-8">
                <?php if ($canReview): ?>
                    <form method="post" class="form-card mb-4">
                        <?= csrf_field() ?>
                        <h3><?= $ownedReview ? 'Edit your review' : 'Write a review' ?></h3>
                        <?php if ($ownedReview && $ownedReview['status'] === 'hidden'): ?>
                            <p class="muted">A moderator has hidden this review. You can still update the text.</p>
                        <?php endif; ?>
                        <label class="form-label" for="rating">Rating</label>
                        <select class="form-select mb-3" id="rating" name="rating" required>
                            <?php for ($star = 5; $star >= 1; $star--): ?>
                                <option value="<?= e((string) $star) ?>" <?= (int) old('rating', (string) ($ownedReview['rating'] ?? 5)) === $star ? 'selected' : '' ?>><?= e((string) $star) ?></option>
                            <?php endfor; ?>
                        </select>
                        <label class="form-label" for="comment">Comment</label>
                        <textarea class="form-control mb-3" id="comment" name="comment" required minlength="10" maxlength="1000"><?= e(old('comment', (string) ($ownedReview['comment'] ?? ''))) ?></textarea>
                        <button class="btn btn-accent" type="submit"><?= $ownedReview ? 'Update review' : 'Publish review' ?></button>
                    </form>
                <?php elseif (!$user): ?>
                    <p><a href="<?= e(url('public/login.php')) ?>">Sign in</a> after purchasing this item to leave a review.</p>
                <?php else: ?>
                    <p class="muted">Reviews are open after you purchase this product and payment is confirmed.</p>
                <?php endif; ?>
                <?php if (!$reviews): ?>
                    <p>No published reviews yet.</p>
                <?php endif; ?>
                <?php foreach ($reviews as $review): ?>
                    <article class="mb-3">
                        <strong><?= e($review['name']) ?></strong>
                        <?= render_stars($review['rating']) ?>
                        <p class="muted mb-1"><?= e(date('M j, Y', strtotime((string) $review['created_at']))) ?></p>
                        <p><?= nl2br(e($review['comment'])) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php if ($related): ?>
        <section class="section">
            <h2>Related products</h2>
            <div class="product-grid">
                <?php foreach ($related as $item) {
                    render_product_card($item);
                } ?>
            </div>
        </section>
    <?php endif; ?>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
