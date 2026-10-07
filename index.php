<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';

$categories = active_categories();
$collections = home_collections();
$promo = coupon_by_code('WELCOME10');
$makers = db()->query(
    "SELECT sp.shop_name, sp.bio, sp.location, sp.shop_slug
     FROM seller_profiles sp
     INNER JOIN users u ON u.id = sp.user_id
     WHERE u.status = 'active' AND u.role = 'seller'
     ORDER BY sp.shop_name"
)->fetchAll();
$heroItems = $collections['featured'] ?: $collections['arrivals'];
$heroImage = $heroItems[0]['image_path'] ?? null;
$heroAlt = (string) ($heroItems[0]['name'] ?? APP_NAME);

$pageTitle = t('home.title');
require APP_ROOT . '/includes/header.php';
?>
<main id="content">
    <section class="hero">
        <div class="hero-media" aria-hidden="true">
            <img src="<?= e(media_url($heroImage)) ?>" alt="">
        </div>
        <div class="hero-shade" aria-hidden="true"></div>
        <div class="container hero-inner">
            <p class="brand-lockup"><span aria-hidden="true"></span> Meridian Market</p>
            <h1><?= te('home.headline') ?></h1>
            <p class="hero-copy"><?= e((string) config('app', 'tagline')) ?></p>
            <div class="hero-actions">
                <a class="btn btn-accent" href="<?= e(url('public/shop.php')) ?>"><?= te('home.shop_now') ?></a>
                <a class="btn btn-quiet" href="#categories"><?= te('home.browse_categories') ?></a>
            </div>
        </div>
    </section>

    <?php if ($promo && $promo['status'] === 'active' && $promo['expiry_date'] >= date('Y-m-d')): ?>
        <section class="section pt-0 pb-0">
            <div class="promo-strip">
                <div>
                    <p class="eyebrow mb-1"><?= te('home.welcome_offer') ?></p>
                    <h2><?= e($promo['code']) ?> · <?= e($promo['discount_type'] === 'percent' ? rtrim(rtrim(number_format((float) $promo['discount_value'], 2), '0'), '.') . '% off' : money($promo['discount_value']) . ' off') ?></h2>
                    <p class="mb-0 muted"><?= te('home.cart_minimum', ['amount' => money($promo['minimum_amount'])]) ?></p>
                </div>
                <a class="btn btn-accent" href="<?= e(url('public/shop.php')) ?>"><?= te('home.start_shopping') ?></a>
            </div>
        </section>
    <?php endif; ?>

    <section class="section" id="categories">
        <div class="container">
            <div class="section-head reveal">
                <h2><?= te('home.shop_by_category') ?></h2>
                <a href="<?= e(url('public/shop.php')) ?>"><?= te('home.view_all') ?></a>
            </div>
            <div class="category-grid">
                <?php foreach ($categories as $category): ?>
                    <a class="category-tile reveal" href="<?= e(url('public/shop.php?category=' . (int) $category['id'])) ?>">
                        <img src="<?= e(media_url($category['image'])) ?>" alt="<?= e($category['name']) ?>">
                        <span>
                            <strong><?= e($category['name']) ?></strong>
                            <small><?= te('home.pieces', ['count' => (string) $category['product_count']]) ?></small>
                        </span>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    </section>

    <?php
    $blocks = [
        'featured' => [t('home.featured'), t('home.featured_text')],
        'arrivals' => [t('home.arrivals'), t('home.arrivals_text')],
        'popular' => [t('home.popular'), t('home.popular_text')],
    ];
    foreach ($blocks as $key => [$title, $text]):
        if (empty($collections[$key])) {
            continue;
        }
    ?>
        <section class="section">
            <div class="container">
                <div class="section-head reveal">
                    <div>
                        <h2><?= e($title) ?></h2>
                        <p class="muted mb-0"><?= e($text) ?></p>
                    </div>
                    <a href="<?= e(url('public/shop.php' . ($key === 'popular' ? '?sort=popular' : ($key === 'arrivals' ? '?sort=newest' : '')))) ?>"><?= te('home.view_all') ?></a>
                </div>
                <div class="product-grid">
                    <?php foreach ($collections[$key] as $product) {
                        render_product_card($product);
                    } ?>
                </div>
            </div>
        </section>
    <?php endforeach; ?>

    <section class="section" id="makers">
        <div class="container">
            <div class="section-head reveal">
                <div>
                    <h2><?= te('home.makers') ?></h2>
                    <p class="muted mb-0"><?= te('home.makers_text') ?></p>
                </div>
            </div>
            <div class="maker-grid">
                <?php foreach ($makers as $maker): ?>
                    <article class="maker-card reveal">
                        <p class="eyebrow"><?= e($maker['location']) ?></p>
                        <h3><?= e($maker['shop_name']) ?></h3>
                        <p><?= e($maker['bio']) ?></p>
                    </article>
                <?php endforeach; ?>
            </div>
        </div>
    </section>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
