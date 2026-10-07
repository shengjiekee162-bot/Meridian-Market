<?php
declare(strict_types=1);

function next_order_number(PDO $pdo): string
{
    $day = date('Ymd');
    $pdo->prepare(
        'INSERT INTO order_counters (day_key, seq_value) VALUES (?, LAST_INSERT_ID(1))
         ON DUPLICATE KEY UPDATE seq_value = LAST_INSERT_ID(seq_value + 1)'
    )->execute([$day]);
    $sequence = (int) $pdo->lastInsertId();
    if ($sequence < 1) {
        throw new AppException('Could not assign an order number.');
    }
    return 'ORD-' . $day . '-' . str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
}

function record_order_event(PDO $pdo, int $orderId, string $status, string $note, ?int $actorId): void
{
    $stmt = $pdo->prepare(
        'INSERT INTO order_status_events (order_id, status, note, actor_id, created_at)
         VALUES (?, ?, ?, ?, NOW())'
    );
    $stmt->execute([$orderId, $status, $note !== '' ? $note : null, $actorId]);
}

function place_order(int $userId, array $shipping, string $paymentMethod): array
{
    $methods = ['paypal', 'cod', 'bank'];
    if (!in_array($paymentMethod, $methods, true)) {
        throw new AppException('Choose a payment method.');
    }
    if ($paymentMethod === 'paypal' && !paypal_is_configured()) {
        throw new AppException('PayPal is not configured yet. Choose another payment method or add sandbox credentials.');
    }
    $name = validate_person_name($shipping['name'] ?? '');
    $email = validate_email($shipping['email'] ?? '');
    $phone = validate_phone($shipping['phone'] ?? '');
    $address = validate_address($shipping['address'] ?? '');

    $pdo = db();
    $pdo->beginTransaction();
    try {
        $cartId = cart_id_for($userId, false, true);
        if (!$cartId) {
            throw new AppException('Your cart is empty.');
        }
        $itemStmt = $pdo->prepare('SELECT product_id, quantity FROM cart_items WHERE cart_id = ?');
        $itemStmt->execute([$cartId]);
        $cartItems = $itemStmt->fetchAll();
        if (!$cartItems) {
            throw new AppException('Your cart is empty.');
        }
        $ids = [];
        $qtyByProduct = [];
        foreach ($cartItems as $item) {
            $productId = (int) $item['product_id'];
            $ids[] = $productId;
            $qtyByProduct[$productId] = (int) $item['quantity'];
        }
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $lock = $pdo->prepare(
            "SELECT p.id, p.seller_id, p.name, p.price, p.stock, p.status, p.sku,
                    c.status AS category_status, u.status AS seller_status, u.role AS seller_role
             FROM products p
             INNER JOIN categories c ON c.id = p.category_id
             INNER JOIN users u ON u.id = p.seller_id
             WHERE p.id IN ($placeholders)
             FOR UPDATE"
        );
        $lock->execute($ids);
        $products = $lock->fetchAll();
        if (count($products) !== count($ids)) {
            throw new AppException('A product in your cart is no longer available.');
        }

        $subtotal = 0;
        $lines = [];
        foreach ($products as $product) {
            $qty = $qtyByProduct[(int) $product['id']] ?? 0;
            if (
                $product['status'] !== 'active'
                || $product['category_status'] !== 'active'
                || $product['seller_status'] !== 'active'
                || $product['seller_role'] !== 'seller'
            ) {
                throw new AppException($product['name'] . ' is no longer available. Update your cart and try again.');
            }
            if ($qty < 1 || $qty > 20 || $qty > (int) $product['stock']) {
                throw new AppException('Not enough stock for ' . $product['name'] . '.');
            }
            $unit = to_cents($product['price']);
            if ($unit <= 0) {
                throw new AppException('A product price could not be confirmed.');
            }
            $line = $unit * $qty;
            $subtotal += $line;
            $lines[] = [
                'product_id' => (int) $product['id'],
                'seller_id' => (int) $product['seller_id'],
                'name' => (string) $product['name'],
                'quantity' => $qty,
                'unit_cents' => $unit,
                'line_cents' => $line,
            ];
        }

        $coupon = null;
        $code = $_SESSION['coupon_code'] ?? '';
        if (is_string($code) && $code !== '') {
            $coupon = coupon_by_code($code, true);
            $assessment = assess_coupon($coupon, $subtotal);
            if (!$assessment['ok']) {
                throw new AppException($assessment['message']);
            }
        }
        $totals = calculate_totals($subtotal, $coupon);
        if ($coupon && !$totals['coupon_ok']) {
            throw new AppException($totals['coupon_message'] ?: 'The coupon could not be applied.');
        }

        $orderNumber = next_order_number($pdo);
        $insertOrder = $pdo->prepare(
            'INSERT INTO orders (
                order_number, user_id, subtotal, discount_amount, shipping_fee, total_amount, coupon_id,
                payment_method, payment_status, transaction_id, order_status,
                shipping_name, shipping_email, shipping_phone, shipping_address,
                stock_restored, created_at, updated_at
             ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, \'unpaid\', NULL, \'pending\', ?, ?, ?, ?, 0, NOW(), NOW())'
        );
        $insertOrder->execute([
            $orderNumber,
            $userId,
            from_cents($subtotal),
            from_cents($totals['discount_cents']),
            from_cents($totals['shipping_cents']),
            from_cents($totals['total_cents']),
            $coupon ? (int) $coupon['id'] : null,
            $paymentMethod,
            $name,
            $email,
            $phone,
            $address,
        ]);
        $orderId = (int) $pdo->lastInsertId();

        $insertItem = $pdo->prepare(
            'INSERT INTO order_items (order_id, product_id, seller_id, product_name, quantity, unit_price, subtotal, created_at)
             VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );
        $stock = $pdo->prepare(
            "UPDATE products SET stock = stock - ?, updated_at = NOW()
             WHERE id = ? AND stock >= ? AND status = 'active'"
        );
        foreach ($lines as $line) {
            $insertItem->execute([
                $orderId,
                $line['product_id'],
                $line['seller_id'],
                $line['name'],
                $line['quantity'],
                from_cents($line['unit_cents']),
                from_cents($line['line_cents']),
            ]);
            $stock->execute([$line['quantity'], $line['product_id'], $line['quantity']]);
            if ($stock->rowCount() !== 1) {
                throw new AppException('Stock changed while the order was being placed. Please try again.');
            }
        }

        $insertPayment = $pdo->prepare(
            'INSERT INTO payments (order_id, payment_method, transaction_id, provider_reference, amount, currency, status, response_data, created_at, updated_at)
             VALUES (?, ?, NULL, NULL, ?, ?, \'unpaid\', NULL, NOW(), NOW())'
        );
        $insertPayment->execute([
            $orderId,
            $paymentMethod,
            from_cents($totals['total_cents']),
            (string) config('app', 'currency', 'USD'),
        ]);

        if ($coupon) {
            $burn = $pdo->prepare(
                'UPDATE coupons
                 SET used_count = used_count + 1, updated_at = NOW()
                 WHERE id = ? AND status = \'active\' AND (usage_limit IS NULL OR used_count < usage_limit)'
            );
            $burn->execute([(int) $coupon['id']]);
            if ($burn->rowCount() !== 1) {
                throw new AppException('This coupon has reached its usage limit.');
            }
        }

        record_order_event($pdo, $orderId, 'pending', 'Order placed', $userId);
        $pdo->prepare('DELETE FROM cart_items WHERE cart_id = ?')->execute([$cartId]);

        notify($userId, 'Order placed', 'Order ' . $orderNumber . ' has been received.', 'order');
        $sellers = array_unique(array_column($lines, 'seller_id'));
        foreach ($sellers as $sellerId) {
            notify((int) $sellerId, 'New sale', 'You have a new item in order ' . $orderNumber . '.', 'order');
        }
        $admins = $pdo->query("SELECT id FROM users WHERE role = 'admin' AND status = 'active'")->fetchAll();
        foreach ($admins as $admin) {
            notify((int) $admin['id'], 'New marketplace order', $orderNumber . ' was just placed.', 'order');
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof AppException) {
            throw $exception;
        }
        log_message('order', $exception->getMessage());
        throw new AppException('We could not place the order. Nothing was charged and your cart is unchanged.');
    }

    unset($_SESSION['coupon_code']);
    $emailed = send_order_email($userId, $orderNumber, $paymentMethod, from_cents($totals['total_cents']));
    return [
        'order_id' => $orderId,
        'order_number' => $orderNumber,
        'total' => from_cents($totals['total_cents']),
        'emailed' => $emailed,
    ];
}

function fetch_order_for_user(string $orderNumber, int $userId): ?array
{
    if (!preg_match('/^ORD-\d{8}-\d{6}$/', $orderNumber)) {
        return null;
    }
    $stmt = db()->prepare('SELECT * FROM orders WHERE order_number = ? AND user_id = ? LIMIT 1');
    $stmt->execute([$orderNumber, $userId]);
    $order = $stmt->fetch();
    return $order ?: null;
}

function fetch_order_by_id(int $orderId): ?array
{
    $stmt = db()->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    return $order ?: null;
}

function order_items(int $orderId, ?int $sellerId = null): array
{
    $sql = 'SELECT oi.*, sp.shop_name
            FROM order_items oi
            LEFT JOIN seller_profiles sp ON sp.user_id = oi.seller_id
            WHERE oi.order_id = ?';
    $params = [$orderId];
    if ($sellerId !== null) {
        $sql .= ' AND oi.seller_id = ?';
        $params[] = $sellerId;
    }
    $sql .= ' ORDER BY oi.id';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function order_events(int $orderId): array
{
    $stmt = db()->prepare(
        'SELECT e.*, u.name AS actor_name
         FROM order_status_events e
         LEFT JOIN users u ON u.id = e.actor_id
         WHERE e.order_id = ?
         ORDER BY e.id ASC'
    );
    $stmt->execute([$orderId]);
    return $stmt->fetchAll();
}

function order_payment(int $orderId): ?array
{
    $stmt = db()->prepare('SELECT * FROM payments WHERE order_id = ? LIMIT 1');
    $stmt->execute([$orderId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function cancel_order(int $orderId, int $actorId, string $reason, bool $asAdmin): void
{
    $reason = clean_text($reason, 255, 'Cancellation note');
    if (mb_strlen($reason) < 3) {
        throw new AppException('Add a short reason for the cancellation.');
    }
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new AppException('That order was not found.');
        }
        if (!$asAdmin && (int) $order['user_id'] !== $actorId) {
            throw new AppException('You cannot cancel this order.');
        }
        if ($order['order_status'] === 'cancelled') {
            throw new AppException('This order is already cancelled.');
        }
        $allowed = $asAdmin ? ['pending', 'paid', 'processing'] : ['pending', 'paid'];
        if (!in_array($order['order_status'], $allowed, true)) {
            throw new AppException('This order can no longer be cancelled.');
        }
        if ((int) $order['stock_restored'] === 1) {
            throw new AppException('Inventory for this order was already restored.');
        }

        $items = $pdo->prepare('SELECT product_id, quantity FROM order_items WHERE order_id = ?');
        $items->execute([$orderId]);
        $restore = $pdo->prepare('UPDATE products SET stock = stock + ?, updated_at = NOW() WHERE id = ?');
        foreach ($items->fetchAll() as $item) {
            $restore->execute([(int) $item['quantity'], (int) $item['product_id']]);
        }

        $paymentStatus = $order['payment_status'] === 'paid' ? 'refunded' : 'failed';
        $update = $pdo->prepare(
            'UPDATE orders
             SET order_status = \'cancelled\', payment_status = ?, cancellation_reason = ?, cancelled_at = NOW(),
                 stock_restored = 1, updated_at = NOW()
             WHERE id = ?'
        );
        $update->execute([$paymentStatus, $reason, $orderId]);
        $pdo->prepare(
            'UPDATE payments SET status = ?, updated_at = NOW() WHERE order_id = ?'
        )->execute([$paymentStatus, $orderId]);

        if ($order['coupon_id']) {
            $pdo->prepare(
                'UPDATE coupons SET used_count = GREATEST(used_count - 1, 0), updated_at = NOW() WHERE id = ?'
            )->execute([(int) $order['coupon_id']]);
        }
        record_order_event($pdo, $orderId, 'cancelled', $reason, $actorId);
        notify((int) $order['user_id'], 'Order cancelled', 'Order ' . $order['order_number'] . ' was cancelled.', 'order');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof AppException) {
            throw $exception;
        }
        log_message('order', 'Cancel failed: ' . $exception->getMessage());
        throw new AppException('The order could not be cancelled.');
    }

    send_status_email((int) $order['user_id'], (string) $order['order_number'], 'cancelled');
}

function change_order_status(int $orderId, string $newStatus, int $adminId, string $note): void
{
    $allowed = ['pending', 'paid', 'processing', 'shipped', 'completed', 'cancelled'];
    if (!in_array($newStatus, $allowed, true)) {
        throw new AppException('Choose a valid order status.');
    }
    if ($newStatus === 'cancelled') {
        cancel_order($orderId, $adminId, $note !== '' ? $note : 'Cancelled by an administrator', true);
        return;
    }
    $note = clean_text($note, 255, 'Status note');
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new AppException('That order was not found.');
        }
        if ($order['order_status'] === $newStatus) {
            throw new AppException('The order already has that status.');
        }
        $transitions = [
            'pending' => ['paid', 'processing'],
            'paid' => ['processing'],
            'processing' => ['shipped'],
            'shipped' => ['completed'],
            'completed' => [],
            'cancelled' => [],
        ];
        if (!in_array($newStatus, $transitions[$order['order_status']] ?? [], true)) {
            throw new AppException('That status change is not allowed from ' . $order['order_status'] . '.');
        }
        if ($newStatus === 'paid' && !in_array($order['payment_method'], ['cod', 'bank'], true)) {
            throw new AppException('PayPal orders are marked paid only after the payment is verified.');
        }
        if (
            $order['payment_method'] === 'paypal'
            && $order['payment_status'] !== 'paid'
            && in_array($newStatus, ['processing', 'shipped', 'completed'], true)
        ) {
            throw new AppException('This PayPal order is not paid yet.');
        }

        $paymentStatus = $order['payment_status'];
        if ($newStatus === 'paid') {
            $paymentStatus = 'paid';
        }
        $update = $pdo->prepare(
            'UPDATE orders SET order_status = ?, payment_status = ?, updated_at = NOW() WHERE id = ?'
        );
        $update->execute([$newStatus, $paymentStatus, $orderId]);
        if ($paymentStatus !== $order['payment_status']) {
            $pdo->prepare('UPDATE payments SET status = ?, updated_at = NOW() WHERE order_id = ?')
                ->execute([$paymentStatus, $orderId]);
        }
        record_order_event($pdo, $orderId, $newStatus, $note, $adminId);
        notify((int) $order['user_id'], 'Order update', 'Order ' . $order['order_number'] . ' is now ' . $newStatus . '.', 'order');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof AppException) {
            throw $exception;
        }
        log_message('order', 'Status change failed: ' . $exception->getMessage());
        throw new AppException('The order status could not be updated.');
    }
    send_status_email((int) $order['user_id'], (string) $order['order_number'], $newStatus);
}

function mark_manual_payment(int $orderId, int $adminId): void
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $stmt = $pdo->prepare('SELECT * FROM orders WHERE id = ? LIMIT 1 FOR UPDATE');
        $stmt->execute([$orderId]);
        $order = $stmt->fetch();
        if (!$order) {
            throw new AppException('That order was not found.');
        }
        if (!in_array($order['payment_method'], ['cod', 'bank'], true)) {
            throw new AppException('Only cash and bank orders can be marked paid by hand.');
        }
        if ($order['order_status'] === 'cancelled') {
            throw new AppException('Cancelled orders cannot be marked paid.');
        }
        if ($order['payment_status'] === 'paid') {
            throw new AppException('This payment is already marked paid.');
        }
        $nextStatus = $order['order_status'] === 'pending' ? 'paid' : $order['order_status'];
        $txn = 'MANUAL-' . $order['order_number'];
        $pdo->prepare(
            'UPDATE orders SET payment_status = \'paid\', order_status = ?, transaction_id = ?, updated_at = NOW() WHERE id = ?'
        )->execute([$nextStatus, $txn, $orderId]);
        $pdo->prepare(
            'UPDATE payments SET status = \'paid\', transaction_id = ?, updated_at = NOW() WHERE order_id = ?'
        )->execute([$txn, $orderId]);
        record_order_event($pdo, $orderId, 'paid', 'Payment confirmed by an administrator', $adminId);
        notify((int) $order['user_id'], 'Payment confirmed', 'Payment for ' . $order['order_number'] . ' was confirmed.', 'order');
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        if ($exception instanceof AppException) {
            throw $exception;
        }
        log_message('order', 'Manual payment failed: ' . $exception->getMessage());
        throw new AppException('The payment could not be updated.');
    }
}
