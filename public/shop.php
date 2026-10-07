<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$filters = [
    'q' => query_string('q', 80),
    'category' => request_int('category', 'get'),
    'min_price' => query_string('min_price', 12),
    'max_price' => query_string('max_price', 12),
    'availability' => query_string('availability', 10),
    'rating' => request_int('rating', 'get'),
    'sort' => query_string('sort', 20),
    'page' => max(1, request_int('page', 'get')),
];
if (!in_array($filters['availability'], ['', 'in', 'out'], true)) {
    $filters['availability'] = '';
}
$result = query_products($filters);
$pageTitle = t('shop.title');
require APP_ROOT . '/includes/header.php';
?>
<main id="content" class="container py-4">
    <div class="section-head">
        <div>
            <p class="eyebrow"><?= te('shop.catalog') ?></p>
            <h1><?= te('shop.heading') ?></h1>
        </div>
        <p class="muted mb-0"><?= te('shop.products_count', ['count' => (string) $result['total']]) ?></p>
    </div>

    <form class="shop-search form-card" method="get" role="search">
        <label class="visually-hidden" for="q"><?= te('shop.search_label') ?></label>
        <div class="shop-search-row">
            <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
            <input class="form-control" id="q" name="q" type="search" value="<?= e($filters['q']) ?>" placeholder="<?= te('shop.search_placeholder') ?>" maxlength="80" autofocus>
            <button class="btn btn-accent" type="submit"><?= te('shop.search') ?></button>
            <?php if ($filters['q'] !== ''): ?>
                <a class="btn btn-quiet" href="<?= e(url('public/shop.php')) ?>"><?= te('shop.clear') ?></a>
            <?php endif; ?>
        </div>
        <?php if ($filters['category'] > 0): ?>
            <input type="hidden" name="category" value="<?= e((string) $filters['category']) ?>">
        <?php endif; ?>
        <?php if ($filters['sort'] !== ''): ?>
            <input type="hidden" name="sort" value="<?= e($filters['sort']) ?>">
        <?php endif; ?>
    </form>

    <?php if (!$result['items']): ?>
        <?php render_empty('fa-magnifying-glass', t('shop.empty_title'), t('shop.empty_text'), url('public/shop.php'), t('shop.clear_search')); ?>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($result['items'] as $product) {
                render_product_card($product);
            } ?>
        </div>
        <?= render_pagination($result['page'], $result['pages'], 'public/shop.php') ?>
    <?php endif; ?>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
