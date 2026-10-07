<?php
declare(strict_types=1);
?>
<footer class="site-footer">
    <div class="container">
        <div class="row g-4">
            <div class="col-md-5">
                <a class="brand brand-light" href="<?= e(url('')) ?>">
                    <span class="brand-mark" aria-hidden="true"></span>
                    <span><strong>Meridian</strong> <small>Market</small></span>
                </a>
                <p><?= te('footer.blurb') ?></p>
            </div>
            <div class="col-6 col-md-2">
                <h2><?= te('footer.shop') ?></h2>
                <a href="<?= e(url('public/shop.php')) ?>"><?= te('footer.all_products') ?></a>
                <a href="<?= e(url('public/shop.php?sort=popular')) ?>"><?= te('footer.popular') ?></a>
                <a href="<?= e(url('public/shop.php?sort=newest')) ?>"><?= te('footer.new_arrivals') ?></a>
            </div>
            <div class="col-6 col-md-2">
                <h2><?= te('footer.account') ?></h2>
                <?php if (!empty($currentUser)): ?>
                    <a href="<?= e(url('public/orders.php')) ?>"><?= te('footer.orders') ?></a>
                    <a href="<?= e(url('public/wishlist.php')) ?>"><?= te('footer.wishlist') ?></a>
                    <a href="<?= e(url('public/profile.php')) ?>"><?= te('footer.profile') ?></a>
                <?php else: ?>
                    <a href="<?= e(url('public/login.php')) ?>"><?= te('footer.sign_in') ?></a>
                    <a href="<?= e(url('public/register.php')) ?>"><?= te('footer.create_account') ?></a>
                    <a href="<?= e(url('public/orders.php')) ?>"><?= te('footer.orders') ?></a>
                <?php endif; ?>
            </div>
            <div class="col-md-3">
                <h2><?= te('footer.studios') ?></h2>
                <p><?= te('footer.studios_blurb') ?></p>
                <p class="mb-0">hello@meridianmarket.test</p>
            </div>
        </div>
        <div class="footer-base">
            <span>&copy; <?= e(date('Y')) ?> Meridian Market</span>
            <span><?= te('footer.shipping', [
                'fee' => money(config('app', 'shipping_fee')),
                'threshold' => money(config('app', 'free_shipping_threshold')),
            ]) ?></span>
        </div>
    </div>
</footer>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="<?= e(asset('js/main.js')) ?>"></script>
<script src="<?= e(asset('js/cart.js')) ?>"></script>
<script src="<?= e(asset('js/checkout.js')) ?>"></script>
<?php clear_old(); ?>
</body>
</html>
