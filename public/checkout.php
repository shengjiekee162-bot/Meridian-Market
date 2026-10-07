<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$cart = load_cart((int) $user['id']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $method = input_string('payment_method', 20);
    try {
        verify_csrf();
        if ((int) $cart['purchasable_count'] < 1) {
            throw new AppException('Your cart has no items that can be purchased.');
        }
        $result = place_order((int) $user['id'], [
            'name' => input_string('name', 80),
            'email' => input_string('email', 120),
            'phone' => input_string('phone', 20),
            'address' => input_string('address', 255),
        ], $method);
        if ($method === 'paypal') {
            // Bank-style handoff: leave this site and open PayPal’s login / checkout page.
            redirect('public/paypal_go.php?order=' . rawurlencode($result['order_number']));
        }
        $message = 'Order ' . $result['order_number'] . ' is placed.';
        $message .= $result['emailed'] ? ' A confirmation email was sent.' : ' Mail is not configured, so no email was sent.';
        flash('success', $message);
        redirect('public/order_detail.php?order=' . rawurlencode($result['order_number']));
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
        set_old($_POST);
        $cart = load_cart((int) $user['id']);
    } catch (Throwable $exception) {
        log_message('checkout', $exception->getMessage());
        flash('error', 'Checkout could not be completed.');
        set_old($_POST);
    }
}

$pageTitle = 'Checkout';
require APP_ROOT . '/includes/header.php';
$paypalReady = paypal_is_configured();
$defaultMethod = old('payment_method', $paypalReady ? 'paypal' : 'cod');
?>
<main id="content" class="container py-4">
    <h1>Checkout</h1>
    <?php if ((int) $cart['purchasable_count'] < 1): ?>
        <?php render_empty('fa-cart-shopping', 'Nothing is ready to check out.', 'Update your cart before paying.', url('public/cart.php'), 'Return to cart'); ?>
    <?php else: ?>
        <form method="post" class="row g-4">
            <?= csrf_field() ?>
            <div class="col-lg-7">
                <section class="form-card">
                    <h2>Shipping</h2>
                    <div class="mb-3">
                        <label class="form-label" for="name">Full name</label>
                        <input class="form-control" id="name" name="name" required maxlength="80" value="<?= e(old('name', (string) $user['name'])) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="email">Email</label>
                        <input class="form-control" id="email" name="email" type="email" required maxlength="120" value="<?= e(old('email', (string) $user['email'])) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="phone">Phone</label>
                        <input class="form-control" id="phone" name="phone" required maxlength="20" value="<?= e(old('phone', (string) $user['phone'])) ?>">
                    </div>
                    <div class="mb-3">
                        <label class="form-label" for="address">Shipping address</label>
                        <textarea class="form-control" id="address" name="address" required maxlength="255"><?= e(old('address', (string) $user['address'])) ?></textarea>
                    </div>
                    <h2>Payment</h2>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment_method" id="pay-paypal" value="paypal" data-pay <?= $defaultMethod === 'paypal' ? 'checked' : '' ?> <?= $paypalReady ? '' : 'disabled' ?>>
                        <label class="form-check-label" for="pay-paypal">
                            PayPal
                            <?php if (!$paypalReady): ?>
                                <span class="muted">(disabled — admin must add Sandbox keys under Admin → PayPal)</span>
                            <?php else: ?>
                                <span class="muted">— opens paypal.com / sandbox.paypal.com</span>
                            <?php endif; ?>
                        </label>
                    </div>
                    <div class="form-check">
                        <input class="form-check-input" type="radio" name="payment_method" id="pay-cod" value="cod" data-pay <?= $defaultMethod === 'cod' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="pay-cod">Cash on delivery</label>
                    </div>
                    <div class="form-check mb-3">
                        <input class="form-check-input" type="radio" name="payment_method" id="pay-bank" value="bank" data-pay <?= $defaultMethod === 'bank' ? 'checked' : '' ?>>
                        <label class="form-check-label" for="pay-bank">Bank transfer</label>
                    </div>
                    <p data-pay-panel="paypal" <?= $defaultMethod === 'paypal' ? '' : 'hidden' ?>>
                        After you click Continue to PayPal, you leave this site and open <strong>PayPal’s login / payment page</strong>
                        (the same style as bank payment sites). When you finish, PayPal brings you back here.
                    </p>
                    <p data-pay-panel="cod" <?= $defaultMethod === 'cod' ? '' : 'hidden' ?>>Pay the courier when the parcel arrives. Stock is reserved now.</p>
                    <p data-pay-panel="bank" <?= $defaultMethod === 'bank' ? '' : 'hidden' ?>><?= e((string) config('app', 'bank_instructions')) ?></p>
                    <button class="btn btn-accent" type="submit" data-checkout-submit
                        data-label-paypal="Continue to PayPal"
                        data-label-other="Place order">
                        <?= $defaultMethod === 'paypal' ? 'Continue to PayPal' : 'Place order' ?>
                    </button>
                </section>
            </div>
            <div class="col-lg-5">
                <aside class="summary-card">
                    <h2>Order summary</h2>
                    <?php foreach ($cart['items'] as $item): ?>
                        <?php if (!$item['available']) { continue; } ?>
                        <div class="d-flex justify-content-between gap-3 mb-2">
                            <span><?= e($item['name']) ?> × <?= e((string) $item['quantity']) ?></span>
                            <span><?= e(money(from_cents((int) $item['line_cents']))) ?></span>
                        </div>
                    <?php endforeach; ?>
                    <div class="totals">
                        <div><span>Subtotal</span><span><?= e(money(from_cents((int) $cart['subtotal_cents']))) ?></span></div>
                        <div><span>Discount</span><span>-<?= e(money(from_cents((int) $cart['discount_cents']))) ?></span></div>
                        <div><span>Shipping</span><span><?= e(money(from_cents((int) $cart['shipping_cents']))) ?></span></div>
                        <div class="grand"><span>Total</span><span><?= e(money(from_cents((int) $cart['total_cents']))) ?></span></div>
                    </div>
                    <a href="<?= e(url('public/cart.php')) ?>">Edit cart</a>
                </aside>
            </div>
        </form>
    <?php endif; ?>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
