<?php
declare(strict_types=1);

require dirname(__DIR__) . '/includes/bootstrap.php';

$failures = 0;

function check(bool $condition, string $message): void
{
    global $failures;
    if ($condition) {
        echo "OK  {$message}\n";
        return;
    }
    $failures++;
    echo "FAIL {$message}\n";
}

check(e('<script>') === '&lt;script&gt;', 'output escaping');
$known = 'abc';
check(hash_equals($known, 'abc') && !hash_equals($known, 'abd'), 'hash comparison');

$admin = db()->prepare('SELECT password, must_change_password FROM users WHERE email = ?');
$admin->execute(['avery.quinn@meridianmarket.test']);
$adminRow = $admin->fetch();
check($adminRow && password_verify('ChangeMe!Meridian2026', $adminRow['password']) && (int) $adminRow['must_change_password'] === 1, 'admin password hash');

$welcome = coupon_by_code('WELCOME10');
$deal = assess_coupon($welcome, 5800);
check($deal['ok'] && $deal['discount_cents'] === 580, 'percent coupon math');
$tooSmall = assess_coupon($welcome, 1000);
check(!$tooSmall['ok'], 'coupon minimum');
$expired = assess_coupon(coupon_by_code('ARCHIVE20'), 10000);
check($expired && !$expired['ok'], 'inactive coupon rejected');
check(shipping_cents_for(15000) === 0 && shipping_cents_for(14900) === 800, 'shipping threshold');

$counts = [
    'users' => 9,
    'products' => 30,
    'categories' => 10,
    'orders' => 10,
];
foreach ($counts as $table => $expected) {
    $actual = (int) db()->query('SELECT COUNT(*) FROM ' . $table)->fetchColumn();
    check($actual === $expected, "{$table} count is {$actual}");
}

$shop = query_products(['q' => 'journal', 'page' => 1]);
check($shop['total'] === 1, 'search finds journal');
$injection = query_products(['q' => "' OR 1=1 --", 'page' => 1]);
check($injection['total'] === 0, 'search does not treat raw SQL as logic');

$sellerBlocked = false;
try {
    load_owned_product(1, 3, false);
} catch (AppException) {
    $sellerBlocked = true;
}
check($sellerBlocked, 'seller cannot open another studio product');

$stockStmt = db()->prepare('SELECT stock FROM products WHERE id = 21');
$stockStmt->execute();
$before = (int) $stockStmt->fetchColumn();
$orderId = 0;
$orderNumber = '';
try {
    add_to_cart(5, 21, 2, true);
    $placed = place_order(5, [
        'name' => 'Elena Voss',
        'email' => 'elena.voss@example.com',
        'phone' => '+1 555 2011',
        'address' => '402 Cedar Avenue, Apt 3, Burlington, Vermont',
    ], 'cod');
    $orderNumber = (string) $placed['order_number'];
    $order = fetch_order_for_user($orderNumber, 5);
    $orderId = (int) ($order['id'] ?? 0);
    $stockStmt->execute();
    $mid = (int) $stockStmt->fetchColumn();
    check($mid === $before - 2, 'stock reserved at checkout');
    check(to_cents($order['total_amount'] ?? 0) === (18 * 2 * 100) + 800, 'order total uses database price');
    $blocked = false;
    try {
        cancel_order(2, 6, 'Should not cancel a shipped order', false);
    } catch (AppException) {
        $blocked = true;
    }
    check($blocked, 'shipped order cannot be cancelled by its customer');
    cancel_order($orderId, 5, 'Self-check cleanup cancellation', false);
    $stockStmt->execute();
    check((int) $stockStmt->fetchColumn() === $before, 'stock restored after cancellation');
} catch (Throwable $exception) {
    check(false, 'order cycle: ' . $exception->getMessage());
} finally {
    if ($orderId > 0) {
        $pdo = db();
        if ($orderNumber !== '') {
            $pdo->prepare('DELETE FROM notifications WHERE message LIKE ?')->execute(['%' . $orderNumber . '%']);
        }
        $pdo->prepare('DELETE FROM order_status_events WHERE order_id = ?')->execute([$orderId]);
        $pdo->prepare('DELETE FROM payments WHERE order_id = ?')->execute([$orderId]);
        $pdo->prepare('DELETE FROM order_items WHERE order_id = ?')->execute([$orderId]);
        $pdo->prepare('DELETE FROM orders WHERE id = ?')->execute([$orderId]);
    }
    $cart = db()->prepare('DELETE ci FROM cart_items ci INNER JOIN carts c ON c.id = ci.cart_id WHERE c.user_id = 5');
    $cart->execute();
}

$stockStmt->execute();
check((int) $stockStmt->fetchColumn() === $before, 'sample stock unchanged after self-check');
$orderCount = (int) db()->query('SELECT COUNT(*) FROM orders')->fetchColumn();
check($orderCount === 10, 'sample orders unchanged');

echo $failures === 0 ? "Self-check passed\n" : "Self-check failed ({$failures})\n";
exit($failures === 0 ? 0 : 1);
