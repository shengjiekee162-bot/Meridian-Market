<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

function cart_wants_json(): bool
{
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    $xhr = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return $xhr === 'xmlhttprequest' || str_contains($accept, 'application/json');
}

function cart_json(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $wantsJson = cart_wants_json();
    try {
        verify_csrf();
        $user = current_user();
        $action = input_string('action', 20);
        $productId = request_int('product_id');
        $quantity = request_int('quantity');
        if ($action === 'add') {
            $name = add_to_active_cart($productId, max(1, $quantity), false);
            $message = t('cart.added', ['name' => $name]);
            if ($wantsJson) {
                cart_json([
                    'ok' => true,
                    'message' => $message,
                    'cart_count' => active_cart_count(),
                    'product_name' => $name,
                ]);
            }
            flash('success', $message);
            redirect(safe_back_url('public/cart.php'));
        } elseif ($action === 'buy') {
            $name = add_to_active_cart($productId, max(1, $quantity), true);
            if (!$user) {
                $_SESSION['after_login'] = '/public/checkout.php';
                flash('success', t('cart.guest_buy', ['name' => $name]));
                redirect('public/login.php');
            }
            flash('success', t('cart.ready_checkout', ['name' => $name]));
            redirect('public/checkout.php');
        } elseif ($action === 'update') {
            update_active_cart_item(request_int('item_id'), $quantity);
            if ($wantsJson) {
                $cart = load_active_cart();
                $lines = [];
                foreach ($cart['items'] as $line) {
                    $lines[(string) $line['item_id']] = money(from_cents((int) $line['line_cents']));
                }
                cart_json([
                    'ok' => true,
                    'message' => t('cart.updated'),
                    'cart_count' => active_cart_count(),
                    'lines' => $lines,
                    'cart' => [
                        'subtotal' => money(from_cents((int) $cart['subtotal_cents'])),
                        'discount' => money(from_cents((int) $cart['discount_cents'])),
                        'shipping' => money(from_cents((int) $cart['shipping_cents'])),
                        'total' => money(from_cents((int) $cart['total_cents'])),
                        'purchasable_count' => (int) $cart['purchasable_count'],
                    ],
                ]);
            }
            flash('success', t('cart.updated'));
        } elseif ($action === 'remove') {
            remove_active_cart_item(request_int('item_id'));
            if ($wantsJson) {
                cart_json([
                    'ok' => true,
                    'message' => t('cart.removed'),
                    'cart_count' => active_cart_count(),
                    'removed' => true,
                ]);
            }
            flash('success', t('cart.removed'));
        } elseif ($action === 'coupon') {
            apply_coupon_to_active_cart(input_string('coupon', 20));
            flash('success', t('cart.coupon_ok'));
        } elseif ($action === 'remove_coupon') {
            remove_coupon();
            flash('success', t('cart.coupon_removed'));
        } else {
            throw new AppException(t('cart.action_missing'));
        }
    } catch (AppException $exception) {
        if ($wantsJson) {
            cart_json(['ok' => false, 'message' => $exception->getMessage()], 422);
        }
        flash('error', $exception->getMessage());
    } catch (Throwable $exception) {
        log_message('cart', $exception->getMessage());
        if ($wantsJson) {
            cart_json(['ok' => false, 'message' => t('cart.update_failed')], 500);
        }
        flash('error', t('cart.update_failed'));
    }
    redirect('public/cart.php');
}

$user = current_user();
$cart = load_active_cart();
$itemCount = 0;
foreach ($cart['items'] as $line) {
    $itemCount += (int) $line['quantity'];
}
$pageTitle = t('cart.title');
require APP_ROOT . '/includes/header.php';
?>
<main id="content" class="container py-4">
    <div class="section-head">
        <div>
            <p class="eyebrow"><?= te('cart.eyebrow') ?></p>
            <h1><?= te('cart.heading') ?></h1>
        </div>
        <a href="<?= e(url('public/shop.php')) ?>"><?= te('cart.continue') ?></a>
    </div>

    <?php if (!$cart['items']): ?>
        <?php render_empty('fa-bag-shopping', t('cart.empty_title'), t('cart.empty_text'), url('public/shop.php'), t('cart.browse')); ?>
    <?php else: ?>
        <?php if (!$user): ?>
            <p class="muted mb-3"><?= te('cart.guest_note', ['count' => (string) $itemCount]) ?> <a href="<?= e(url('public/login.php')) ?>"><?= te('cart.guest_sign_in') ?></a></p>
        <?php else: ?>
            <p class="muted mb-3"><?= te('cart.item_count', ['count' => (string) $itemCount]) ?></p>
        <?php endif; ?>
        <div class="row g-4">
            <div class="col-lg-8">
                <?php foreach ($cart['items'] as $item): ?>
                    <article class="cart-line" data-cart-line data-item-id="<?= e((string) $item['item_id']) ?>">
                        <a href="<?= e(url('public/product.php?slug=' . rawurlencode((string) $item['slug']))) ?>">
                            <img src="<?= e(media_url($item['image_path'] ?? null)) ?>" alt="<?= e($item['name']) ?>">
                        </a>
                        <div>
                            <h2 class="h5 mb-1"><a href="<?= e(url('public/product.php?slug=' . rawurlencode((string) $item['slug']))) ?>"><?= e($item['name']) ?></a></h2>
                            <p class="muted mb-2"><?= e($item['shop_name'] ?: '') ?> · <?= e(money($item['price'])) ?> <?= te('cart.each') ?></p>
                            <?php if (!$item['available']): ?>
                                <p class="text-danger mb-2"><?= te('cart.unavailable') ?></p>
                            <?php endif; ?>
                            <form method="post" class="d-flex gap-2 align-items-center" data-cart-update>
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="item_id" value="<?= e((string) $item['item_id']) ?>">
                                <div class="qty" data-qty>
                                    <button type="button" data-qty-minus aria-label="<?= te('product.decrease_qty') ?>">−</button>
                                    <input class="form-control" type="number" name="quantity" min="1" max="<?= e((string) max(1, (int) $item['stock'])) ?>" value="<?= e((string) $item['quantity']) ?>" data-line-qty required>
                                    <button type="button" data-qty-plus aria-label="<?= te('product.increase_qty') ?>">+</button>
                                </div>
                                <button class="btn btn-quiet btn-sm" type="submit"><?= te('cart.update') ?></button>
                            </form>
                        </div>
                        <div class="text-end">
                            <strong data-line-total><?= e(money(from_cents((int) $item['line_cents']))) ?></strong>
                            <form method="post" class="mt-2" data-cart-remove>
                                <?= csrf_field() ?>
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="item_id" value="<?= e((string) $item['item_id']) ?>">
                                <button class="btn btn-danger-soft btn-sm" type="submit"><?= te('cart.remove') ?></button>
                            </form>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="col-lg-4">
                <aside class="summary-card" data-cart-summary>
                    <h2><?= te('cart.summary') ?></h2>
                    <?php if (!empty($cart['coupon_message']) && !$cart['coupon']): ?>
                        <p class="text-danger"><?= e($cart['coupon_message']) ?></p>
                    <?php endif; ?>
                    <div class="totals">
                        <div><span><?= te('cart.subtotal') ?></span><span data-cart-subtotal><?= e(money(from_cents((int) $cart['subtotal_cents']))) ?></span></div>
                        <div><span><?= te('cart.coupon') ?></span><span data-cart-discount>-<?= e(money(from_cents((int) $cart['discount_cents']))) ?></span></div>
                        <div><span><?= te('cart.shipping') ?></span><span data-cart-shipping><?= e(money(from_cents((int) $cart['shipping_cents']))) ?></span></div>
                        <div class="grand"><span><?= te('cart.total') ?></span><span data-cart-total><?= e(money(from_cents((int) $cart['total_cents']))) ?></span></div>
                    </div>
                    <p class="muted"><?= te('cart.prices_note', ['amount' => money(config('app', 'free_shipping_threshold'))]) ?></p>
                    <?php if ($cart['coupon']): ?>
                        <p><?= te('cart.coupon_applied', ['code' => (string) $cart['coupon']['code']]) ?></p>
                        <form method="post">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="remove_coupon">
                            <button class="btn btn-quiet btn-sm" type="submit"><?= te('cart.remove_coupon') ?></button>
                        </form>
                    <?php else: ?>
                        <form method="post" class="mt-3">
                            <?= csrf_field() ?>
                            <input type="hidden" name="action" value="coupon">
                            <label class="form-label" for="coupon"><?= te('cart.coupon_code') ?></label>
                            <div class="d-flex gap-2">
                                <input class="form-control" id="coupon" name="coupon" maxlength="20" required placeholder="e.g. WELCOME10">
                                <button class="btn btn-accent" type="submit"><?= te('cart.apply') ?></button>
                            </div>
                        </form>
                    <?php endif; ?>
                    <?php if ((int) $cart['purchasable_count'] > 0): ?>
                        <a class="btn btn-accent w-100 mt-3" href="<?= e(url('public/checkout.php')) ?>"><?= $user ? te('cart.checkout') : te('cart.sign_in_checkout') ?></a>
                    <?php else: ?>
                        <button class="btn btn-quiet w-100 mt-3" type="button" disabled><?= te('cart.nothing_ready') ?></button>
                    <?php endif; ?>
                </aside>
            </div>
        </div>
    <?php endif; ?>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
