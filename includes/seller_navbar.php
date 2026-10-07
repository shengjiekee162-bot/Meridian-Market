<?php
declare(strict_types=1);

$activeMenu = $activeMenu ?? '';
$sellerLinks = [
    ['dashboard', 'Overview', 'seller/dashboard.php', 'fa-gauge'],
    ['products', 'Products', 'seller/products.php', 'fa-box'],
    ['orders', 'Sales', 'seller/orders.php', 'fa-receipt'],
    ['profile', 'Studio profile', 'seller/profile.php', 'fa-store'],
];
?>
<aside class="panel-side seller-side">
    <a class="brand brand-light" href="<?= e(url('seller/dashboard.php')) ?>">
        <span class="brand-mark" aria-hidden="true"></span>
        <span><strong>Meridian</strong> <small>Studio</small></span>
    </a>
    <nav class="side-nav">
        <?php foreach ($sellerLinks as [$key, $label, $href, $icon]): ?>
            <a class="<?= $activeMenu === $key ? 'is-active' : '' ?>" href="<?= e(url($href)) ?>">
                <i class="fa-solid <?= e($icon) ?>"></i> <?= e($label) ?>
            </a>
        <?php endforeach; ?>
        <a href="<?= e(url('public/orders.php')) ?>"><i class="fa-solid fa-bag-shopping"></i> My purchases</a>
        <a href="<?= e(url('')) ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i> View store</a>
        <form method="post" action="<?= e(url('public/logout.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit"><i class="fa-solid fa-right-from-bracket"></i> Sign out</button>
        </form>
    </nav>
</aside>
