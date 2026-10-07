<?php
declare(strict_types=1);

function render_product_card(array $product): void
{
    $href = url('public/product.php?slug=' . rawurlencode((string) $product['slug']));
    $percent = discount_percent($product);
    $inStock = (int) $product['stock'] > 0;
    ?>
    <article class="product-card reveal">
        <a class="product-media" href="<?= e($href) ?>">
            <img src="<?= e(media_url($product['image_path'] ?? null)) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
            <?php if ($percent): ?>
                <span class="flag flag-sale"><?= e((string) $percent) ?>%</span>
            <?php endif; ?>
            <?php if (!$inStock): ?>
                <span class="flag flag-stock"><?= te('product.sold_out') ?></span>
            <?php elseif ((int) $product['stock'] <= 5): ?>
                <span class="flag flag-low"><?= te('product.low_stock') ?></span>
            <?php endif; ?>
        </a>
        <div class="product-body">
            <p class="product-kicker"><?= e($product['category_name'] ?? t('common.market')) ?><?php if (!empty($product['shop_name'])): ?> · <?= e($product['shop_name']) ?><?php endif; ?></p>
            <h3><a href="<?= e($href) ?>"><?= e($product['name']) ?></a></h3>
            <div class="price-row">
                <strong><?= e(money($product['price'])) ?></strong>
                <?php if ($percent): ?>
                    <s><?= e(money($product['compare_price'])) ?></s>
                <?php endif; ?>
            </div>
            <div class="meta-row">
                <?php if ((int) ($product['review_count'] ?? 0) > 0): ?>
                    <?= render_stars($product['avg_rating']) ?>
                    <span><?= e(number_format((float) $product['avg_rating'], 1)) ?></span>
                <?php else: ?>
                    <span class="muted"><?= te('product.new') ?></span>
                <?php endif; ?>
            </div>
            <div class="card-actions">
                <form method="post" action="<?= e(url('public/wishlist.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>">
                    <button class="icon-btn" type="submit" aria-label="<?= te('common.save_wishlist', ['name' => (string) $product['name']]) ?>"><i class="fa-regular fa-heart"></i></button>
                </form>
                <?php if ($inStock): ?>
                    <div class="buy-actions">
                        <form method="post" action="<?= e(url('public/cart.php')) ?>" data-add-cart>
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button class="btn btn-quiet btn-sm" type="submit"><?= te('product.add_to_cart') ?></button>
                        </form>
                        <form method="post" action="<?= e(url('public/cart.php')) ?>">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="buy">
                            <input type="hidden" name="product_id" value="<?= e((string) $product['id']) ?>">
                            <input type="hidden" name="quantity" value="1">
                            <button class="btn btn-accent btn-sm" type="submit"><?= te('product.buy_now') ?></button>
                        </form>
                    </div>
                <?php else: ?>
                    <button class="btn btn-quiet btn-sm" type="button" disabled><?= te('product.sold_out') ?></button>
                <?php endif; ?>
            </div>
        </div>
    </article>
    <?php
}

function render_empty(string $icon, string $title, string $text, ?string $href = null, ?string $label = null): void
{
    ?>
    <div class="empty-panel">
        <i class="fa-solid <?= e($icon) ?>"></i>
        <h2><?= e($title) ?></h2>
        <p><?= e($text) ?></p>
        <?php if ($href && $label): ?>
            <a class="btn btn-accent" href="<?= e($href) ?>"><?= e($label) ?></a>
        <?php endif; ?>
    </div>
    <?php
}

function unread_notifications(int $userId): array
{
    $stmt = db()->prepare(
        'SELECT id, title, message, is_read, created_at
         FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 6'
    );
    $stmt->execute([$userId]);
    return $stmt->fetchAll();
}

function unread_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}
