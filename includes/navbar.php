<?php
declare(strict_types=1);

$searchValue = query_string('q', 80);
$locale = current_locale();
$localeSwitchBase = url('public/locale.php');
?>
<header class="site-header">
    <nav class="navbar navbar-expand-lg">
        <div class="container">
            <a class="brand" href="<?= e(url('')) ?>">
                <span class="brand-mark" aria-hidden="true"></span>
                <span>
                    <strong>Meridian</strong>
                    <small>Market</small>
                </span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#storeNav" aria-controls="storeNav" aria-expanded="false" aria-label="<?= te('nav.open_menu') ?>">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="storeNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('public/shop.php')) ?>"><?= te('nav.shop') ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('')) ?>#categories"><?= te('nav.categories') ?></a></li>
                    <li class="nav-item"><a class="nav-link" href="<?= e(url('')) ?>#makers"><?= te('nav.makers') ?></a></li>
                </ul>
                <form class="search-form" method="get" action="<?= e(url('public/shop.php')) ?>" role="search">
                    <label class="visually-hidden" for="navSearch"><?= te('nav.search_label') ?></label>
                    <i class="fa-solid fa-magnifying-glass"></i>
                    <input id="navSearch" type="search" name="q" value="<?= e($searchValue) ?>" placeholder="<?= te('nav.search') ?>" maxlength="80">
                </form>
                <div class="nav-tools">
                    <div class="lang-switch" role="group" aria-label="<?= te('nav.lang') ?>">
                        <a class="lang-btn<?= $locale === 'en' ? ' is-active' : '' ?>" href="<?= e($localeSwitchBase . '?lang=en') ?>" hreflang="en" lang="en"><?= te('nav.lang_en') ?></a>
                        <a class="lang-btn<?= $locale === 'zh' ? ' is-active' : '' ?>" href="<?= e($localeSwitchBase . '?lang=zh') ?>" hreflang="zh" lang="zh-Hans"><?= te('nav.lang_zh') ?></a>
                    </div>
                    <?php if ($currentUser): ?>
                        <div class="dropdown">
                            <button class="icon-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" aria-label="<?= te('nav.notifications') ?>">
                                <i class="fa-regular fa-bell"></i>
                                <?php if ($noteCount > 0): ?><span class="count"><?= e((string) $noteCount) ?></span><?php endif; ?>
                            </button>
                            <div class="dropdown-menu dropdown-menu-end note-menu">
                                <?php if (!$notes): ?>
                                    <p class="muted px-3 py-2 mb-0"><?= te('nav.no_notifications') ?></p>
                                <?php else: ?>
                                    <?php foreach ($notes as $note): ?>
                                        <div class="note-item <?= (int) $note['is_read'] === 0 ? 'is-new' : '' ?>">
                                            <strong><?= e($note['title']) ?></strong>
                                            <p><?= e($note['message']) ?></p>
                                        </div>
                                    <?php endforeach; ?>
                                    <form method="post" action="<?= e(url('public/notifications.php')) ?>">
                                        <?= csrf_field() ?>
                                        <button class="btn btn-quiet btn-sm w-100" type="submit"><?= te('nav.mark_read') ?></button>
                                    </form>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endif; ?>
                    <a class="icon-btn" href="<?= e(url('public/wishlist.php')) ?>" aria-label="<?= te('nav.wishlist') ?>">
                        <i class="fa-regular fa-heart"></i>
                        <?php if ($wishCount > 0): ?><span class="count"><?= e((string) $wishCount) ?></span><?php endif; ?>
                    </a>
                    <a class="icon-btn cart-link" href="<?= e(url('public/cart.php')) ?>" aria-label="<?= te('nav.cart') ?>">
                        <i class="fa-solid fa-bag-shopping"></i>
                        <span class="cart-count-badge<?= $cartCount > 0 ? '' : ' is-empty' ?>" data-cart-count><?php if ($cartCount > 0): ?><?= e((string) $cartCount) ?><?php endif; ?></span>
                    </a>
                    <?php if (!$currentUser): ?>
                        <a class="btn btn-quiet btn-sm" href="<?= e(url('public/login.php')) ?>"><?= te('nav.sign_in') ?></a>
                        <a class="btn btn-accent btn-sm" href="<?= e(url('public/register.php')) ?>"><?= te('nav.join') ?></a>
                    <?php else: ?>
                        <div class="dropdown">
                            <button class="btn btn-quiet btn-sm dropdown-toggle" type="button" data-bs-toggle="dropdown" aria-expanded="false">
                                <?= e($currentUser['name']) ?>
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end">
                                <?php if ($currentUser['role'] === 'admin'): ?>
                                    <li><a class="dropdown-item" href="<?= e(url('admin/dashboard.php')) ?>"><?= te('nav.admin') ?></a></li>
                                    <li><a class="dropdown-item" href="<?= e(url('admin/account.php')) ?>"><?= te('nav.account_security') ?></a></li>
                                <?php elseif ($currentUser['role'] === 'seller'): ?>
                                    <li><a class="dropdown-item" href="<?= e(url('seller/dashboard.php')) ?>"><?= te('nav.seller_studio') ?></a></li>
                                    <li><a class="dropdown-item" href="<?= e(url('seller/profile.php')) ?>"><?= te('nav.shop_profile') ?></a></li>
                                    <li><a class="dropdown-item" href="<?= e(url('public/orders.php')) ?>"><?= te('nav.my_purchases') ?></a></li>
                                <?php else: ?>
                                    <li><a class="dropdown-item" href="<?= e(url('public/orders.php')) ?>"><?= te('nav.my_orders') ?></a></li>
                                    <li><a class="dropdown-item" href="<?= e(url('public/profile.php')) ?>"><?= te('nav.profile') ?></a></li>
                                <?php endif; ?>
                                <li>
                                    <form method="post" action="<?= e(url('public/logout.php')) ?>">
                                        <?= csrf_field() ?>
                                        <button class="dropdown-item" type="submit"><?= te('nav.sign_out') ?></button>
                                    </form>
                                </li>
                            </ul>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </nav>
</header>
