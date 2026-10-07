<?php
declare(strict_types=1);

$activeMenu = $activeMenu ?? '';
$adminLinks = [
    ['dashboard', 'Dashboard', 'admin/dashboard.php', 'fa-chart-line'],
    ['orders', 'Orders', 'admin/orders.php', 'fa-receipt'],
    ['products', 'Products', 'admin/products.php', 'fa-box'],
    ['categories', 'Categories', 'admin/categories.php', 'fa-shapes'],
    ['coupons', 'Coupons', 'admin/coupons.php', 'fa-ticket'],
    ['reviews', 'Reviews', 'admin/reviews.php', 'fa-star'],
    ['users', 'Customers', 'admin/users.php', 'fa-users'],
    ['sellers', 'Sellers', 'admin/sellers.php', 'fa-store'],
    ['paypal', 'PayPal', 'admin/paypal.php', 'fa-brands fa-paypal'],
    ['account', 'Security', 'admin/account.php', 'fa-key'],
];
?>
<aside class="panel-side">
    <a class="brand brand-light" href="<?= e(url('admin/dashboard.php')) ?>">
        <span class="brand-mark" aria-hidden="true"></span>
        <span><strong>Meridian</strong> <small>Admin</small></span>
    </a>
    <nav class="side-nav">
        <?php foreach ($adminLinks as [$key, $label, $href, $icon]): ?>
            <a class="<?= $activeMenu === $key ? 'is-active' : '' ?>" href="<?= e(url($href)) ?>">
                <i class="<?= str_starts_with($icon, 'fa-brands') ? e($icon) : 'fa-solid ' . e($icon) ?>"></i> <?= e($label) ?>
            </a>
        <?php endforeach; ?>
        <a href="<?= e(url('')) ?>"><i class="fa-solid fa-arrow-up-right-from-square"></i> View store</a>
        <form method="post" action="<?= e(url('admin/logout.php')) ?>">
            <?= csrf_field() ?>
            <button type="submit"><i class="fa-solid fa-right-from-bracket"></i> Sign out</button>
        </form>
    </nav>
</aside>
