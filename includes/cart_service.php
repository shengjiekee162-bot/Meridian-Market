<?php
declare(strict_types=1);

function guest_cart_bag(): array
{
    $bag = $_SESSION['guest_cart'] ?? [];
    if (!is_array($bag)) {
        return [];
    }
    $clean = [];
    foreach ($bag as $productId => $quantity) {
        if (!is_int($productId) && !(is_string($productId) && ctype_digit($productId))) {
            continue;
        }
        $qty = (int) $quantity;
        if ($qty < 1) {
            continue;
        }
        $clean[(int) $productId] = min(20, $qty);
    }
    return $clean;
}

function save_guest_cart_bag(array $bag): void
{
    $clean = [];
    foreach ($bag as $productId => $quantity) {
        $pid = (int) $productId;
        $qty = (int) $quantity;
        if ($pid > 0 && $qty > 0) {
            $clean[$pid] = min(20, $qty);
        }
    }
    if ($clean === []) {
        unset($_SESSION['guest_cart']);
        return;
    }
    $_SESSION['guest_cart'] = $clean;
}

function guest_cart_count(): int
{
    return array_sum(guest_cart_bag());
}

function active_cart_count(): int
{
    $user = current_user();
    if ($user) {
        return cart_count((int) $user['id']);
    }
    return guest_cart_count();
}

function fetch_cart_product(int $productId): ?array
{
    $stmt = db()->prepare(
        'SELECT p.id, p.name, p.slug, p.price, p.compare_price, p.stock, p.sku, p.status,
                c.name AS category_name, c.status AS category_status,
                u.status AS seller_status, u.role AS seller_role, sp.shop_name,
                (SELECT pi.image_path FROM product_images pi
                  WHERE pi.product_id = p.id ORDER BY pi.display_order, pi.id LIMIT 1) AS image_path
         FROM products p
         INNER JOIN categories c ON c.id = p.category_id
         INNER JOIN users u ON u.id = p.seller_id
         LEFT JOIN seller_profiles sp ON sp.user_id = p.seller_id
         WHERE p.id = ? LIMIT 1'
    );
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    return $product ?: null;
}

function product_is_purchasable(?array $product): bool
{
    return $product
        && $product['status'] === 'active'
        && $product['category_status'] === 'active'
        && $product['seller_status'] === 'active'
        && $product['seller_role'] === 'seller';
}

function cart_id_for(int $userId, bool $create = false, bool $lock = false): ?int
{
    $sql = 'SELECT id FROM carts WHERE user_id = ? LIMIT 1' . ($lock ? ' FOR UPDATE' : '');
    $stmt = db()->prepare($sql);
    $stmt->execute([$userId]);
    $id = $stmt->fetchColumn();
    if ($id) {
        return (int) $id;
    }
    if (!$create) {
        return null;
    }
    try {
        $insert = db()->prepare('INSERT INTO carts (user_id, created_at, updated_at) VALUES (?, NOW(), NOW())');
        $insert->execute([$userId]);
        return (int) db()->lastInsertId();
    } catch (PDOException) {
        $stmt->execute([$userId]);
        $id = $stmt->fetchColumn();
        return $id ? (int) $id : null;
    }
}

function cart_count(int $userId): int
{
    $stmt = db()->prepare(
        'SELECT COALESCE(SUM(ci.quantity), 0)
         FROM carts c
         INNER JOIN cart_items ci ON ci.cart_id = c.id
         WHERE c.user_id = ?'
    );
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function wishlist_count(int $userId): int
{
    $stmt = db()->prepare('SELECT COUNT(*) FROM wishlists WHERE user_id = ?');
    $stmt->execute([$userId]);
    return (int) $stmt->fetchColumn();
}

function wishlist_has(int $userId, int $productId): bool
{
    $stmt = db()->prepare('SELECT 1 FROM wishlists WHERE user_id = ? AND product_id = ? LIMIT 1');
    $stmt->execute([$userId, $productId]);
    return (bool) $stmt->fetchColumn();
}

function finalize_cart_totals(array $items, ?int $cartId = null): array
{
    $subtotal = 0;
    $purchasable = 0;
    foreach ($items as &$item) {
        $qty = max(0, (int) $item['quantity']);
        $available = !empty($item['available']) && $qty > 0;
        $item['available'] = $available;
        $item['line_cents'] = $available ? to_cents($item['price']) * $qty : 0;
        if ($available) {
            $subtotal += $item['line_cents'];
            $purchasable++;
        }
    }
    unset($item);

    $coupon = null;
    $code = $_SESSION['coupon_code'] ?? '';
    if (is_string($code) && $code !== '') {
        $coupon = coupon_by_code($code, false);
    }
    $totals = calculate_totals($subtotal, $coupon);
    if ($code !== '' && !$totals['coupon_ok']) {
        $removedMessage = $totals['coupon_message'] !== ''
            ? $totals['coupon_message']
            : 'The coupon was removed because it no longer applies.';
        unset($_SESSION['coupon_code']);
        $coupon = null;
        $totals = calculate_totals($subtotal, null);
        $totals['coupon_message'] = $removedMessage;
    }

    return [
        'cart_id' => $cartId,
        'items' => $items,
        'subtotal_cents' => $subtotal,
        'purchasable_count' => $purchasable,
        'coupon' => $totals['coupon_ok'] ? $coupon : null,
        'coupon_message' => $totals['coupon_message'],
        'discount_cents' => $totals['discount_cents'],
        'shipping_cents' => $totals['shipping_cents'],
        'total_cents' => $totals['total_cents'],
    ];
}

function load_guest_cart(): array
{
    $bag = guest_cart_bag();
    $items = [];
    foreach ($bag as $productId => $quantity) {
        $product = fetch_cart_product($productId);
        if (!$product) {
            continue;
        }
        $qty = (int) $quantity;
        $available = product_is_purchasable($product) && (int) $product['stock'] >= $qty && $qty > 0;
        $items[] = [
            'item_id' => $productId,
            'quantity' => $qty,
            'saved_price' => $product['price'],
            'product_id' => $productId,
            'name' => $product['name'],
            'slug' => $product['slug'],
            'price' => $product['price'],
            'compare_price' => $product['compare_price'],
            'stock' => $product['stock'],
            'sku' => $product['sku'],
            'status' => $product['status'],
            'category_name' => $product['category_name'],
            'category_status' => $product['category_status'],
            'seller_status' => $product['seller_status'],
            'seller_role' => $product['seller_role'],
            'shop_name' => $product['shop_name'],
            'image_path' => $product['image_path'],
            'available' => $available,
        ];
    }
    return finalize_cart_totals($items, null);
}

function load_cart(int $userId, bool $persistPrices = true): array
{
    $cartId = cart_id_for($userId, false, false);
    $items = [];
    if ($cartId) {
        $stmt = db()->prepare(
            'SELECT ci.id AS item_id, ci.quantity, ci.price AS saved_price,
                    p.id AS product_id, p.name, p.slug, p.price, p.compare_price, p.stock, p.sku, p.status,
                    c.name AS category_name, c.status AS category_status,
                    u.status AS seller_status, u.role AS seller_role, sp.shop_name,
                    (SELECT pi.image_path FROM product_images pi
                      WHERE pi.product_id = p.id ORDER BY pi.display_order, pi.id LIMIT 1) AS image_path
             FROM cart_items ci
             INNER JOIN products p ON p.id = ci.product_id
             INNER JOIN categories c ON c.id = p.category_id
             INNER JOIN users u ON u.id = p.seller_id
             LEFT JOIN seller_profiles sp ON sp.user_id = p.seller_id
             WHERE ci.cart_id = ?
             ORDER BY ci.id ASC'
        );
        $stmt->execute([$cartId]);
        $items = $stmt->fetchAll();
    }

    foreach ($items as &$item) {
        $qty = max(0, (int) $item['quantity']);
        $available = $item['status'] === 'active'
            && $item['category_status'] === 'active'
            && $item['seller_status'] === 'active'
            && $item['seller_role'] === 'seller'
            && (int) $item['stock'] >= $qty
            && $qty > 0;
        $item['available'] = $available;
        if ($persistPrices && $cartId && to_cents($item['saved_price']) !== to_cents($item['price'])) {
            $update = db()->prepare('UPDATE cart_items SET price = ?, updated_at = NOW() WHERE id = ?');
            $update->execute([$item['price'], (int) $item['item_id']]);
        }
    }
    unset($item);

    return finalize_cart_totals($items, $cartId);
}

function load_active_cart(bool $persistPrices = true): array
{
    $user = current_user();
    if ($user) {
        return load_cart((int) $user['id'], $persistPrices);
    }
    return load_guest_cart();
}

function guest_add_to_cart(int $productId, int $quantity, bool $replace = false): string
{
    if ($quantity < 1 || $quantity > 20) {
        throw new AppException('Choose a quantity between 1 and 20.');
    }
    $product = fetch_cart_product($productId);
    if (!product_is_purchasable($product)) {
        throw new AppException('That product is not available.');
    }
    if ((int) $product['stock'] < 1) {
        throw new AppException('That product is out of stock.');
    }
    $bag = guest_cart_bag();
    $existing = $bag[$productId] ?? 0;
    $newQty = $replace || $existing < 1 ? $quantity : ($existing + $quantity);
    if ($newQty > 20) {
        $newQty = 20;
    }
    if ($newQty > (int) $product['stock']) {
        throw new AppException('Only ' . (int) $product['stock'] . ' are available for ' . $product['name'] . '.');
    }
    $bag[$productId] = $newQty;
    save_guest_cart_bag($bag);
    return (string) $product['name'];
}

function add_to_cart(int $userId, int $productId, int $quantity, bool $replace = false): string
{
    if ($quantity < 1 || $quantity > 20) {
        throw new AppException('Choose a quantity between 1 and 20.');
    }
    $product = fetch_cart_product($productId);
    if (!product_is_purchasable($product)) {
        throw new AppException('That product is not available.');
    }
    if ((int) $product['stock'] < 1) {
        throw new AppException('That product is out of stock.');
    }
    $cartId = cart_id_for($userId, true, false);
    $existing = db()->prepare('SELECT id, quantity FROM cart_items WHERE cart_id = ? AND product_id = ? LIMIT 1');
    $existing->execute([$cartId, $productId]);
    $row = $existing->fetch();
    $newQty = $replace || !$row ? $quantity : ((int) $row['quantity'] + $quantity);
    if ($newQty > 20) {
        $newQty = 20;
    }
    if ($newQty > (int) $product['stock']) {
        throw new AppException('Only ' . (int) $product['stock'] . ' are available for ' . $product['name'] . '.');
    }
    if ($row) {
        $update = db()->prepare('UPDATE cart_items SET quantity = ?, price = ?, updated_at = NOW() WHERE id = ?');
        $update->execute([$newQty, $product['price'], (int) $row['id']]);
    } else {
        $insert = db()->prepare(
            'INSERT INTO cart_items (cart_id, product_id, quantity, price, created_at, updated_at)
             VALUES (?, ?, ?, ?, NOW(), NOW())'
        );
        $insert->execute([$cartId, $productId, $newQty, $product['price']]);
    }
    db()->prepare('UPDATE carts SET updated_at = NOW() WHERE id = ?')->execute([$cartId]);
    return (string) $product['name'];
}

function add_to_active_cart(int $productId, int $quantity, bool $replace = false): string
{
    $user = current_user();
    if ($user) {
        return add_to_cart((int) $user['id'], $productId, $quantity, $replace);
    }
    return guest_add_to_cart($productId, $quantity, $replace);
}

function guest_update_cart_item(int $productId, int $quantity): void
{
    $bag = guest_cart_bag();
    if (!isset($bag[$productId])) {
        throw new AppException('That cart item was not found.');
    }
    if ($quantity <= 0) {
        unset($bag[$productId]);
        save_guest_cart_bag($bag);
        return;
    }
    if ($quantity > 20) {
        throw new AppException('Quantity cannot be higher than 20.');
    }
    $product = fetch_cart_product($productId);
    if (!$product) {
        throw new AppException('That cart item was not found.');
    }
    if ($product['status'] === 'active' && $quantity > (int) $product['stock']) {
        throw new AppException('Only ' . (int) $product['stock'] . ' are available for ' . $product['name'] . '.');
    }
    $bag[$productId] = $quantity;
    save_guest_cart_bag($bag);
}

function guest_remove_cart_item(int $productId): void
{
    $bag = guest_cart_bag();
    if (!isset($bag[$productId])) {
        throw new AppException('That cart item was not found.');
    }
    unset($bag[$productId]);
    save_guest_cart_bag($bag);
}

function update_cart_item(int $userId, int $itemId, int $quantity): void
{
    $stmt = db()->prepare(
        'SELECT ci.id, ci.product_id, p.name, p.stock, p.status
         FROM cart_items ci
         INNER JOIN carts c ON c.id = ci.cart_id
         INNER JOIN products p ON p.id = ci.product_id
         WHERE ci.id = ? AND c.user_id = ? LIMIT 1'
    );
    $stmt->execute([$itemId, $userId]);
    $item = $stmt->fetch();
    if (!$item) {
        throw new AppException('That cart item was not found.');
    }
    if ($quantity <= 0) {
        remove_cart_item($userId, $itemId);
        return;
    }
    if ($quantity > 20) {
        throw new AppException('Quantity cannot be higher than 20.');
    }
    if ($item['status'] === 'active' && $quantity > (int) $item['stock']) {
        throw new AppException('Only ' . (int) $item['stock'] . ' are available for ' . $item['name'] . '.');
    }
    $update = db()->prepare('UPDATE cart_items SET quantity = ?, updated_at = NOW() WHERE id = ?');
    $update->execute([$quantity, $itemId]);
}

function remove_cart_item(int $userId, int $itemId): void
{
    $stmt = db()->prepare(
        'DELETE ci FROM cart_items ci
         INNER JOIN carts c ON c.id = ci.cart_id
         WHERE ci.id = ? AND c.user_id = ?'
    );
    $stmt->execute([$itemId, $userId]);
    if ($stmt->rowCount() < 1) {
        throw new AppException('That cart item was not found.');
    }
}

function update_active_cart_item(int $itemId, int $quantity): void
{
    $user = current_user();
    if ($user) {
        update_cart_item((int) $user['id'], $itemId, $quantity);
        return;
    }
    guest_update_cart_item($itemId, $quantity);
}

function remove_active_cart_item(int $itemId): void
{
    $user = current_user();
    if ($user) {
        remove_cart_item((int) $user['id'], $itemId);
        return;
    }
    guest_remove_cart_item($itemId);
}

function clear_cart(int $userId): void
{
    $cartId = cart_id_for($userId, false, false);
    if (!$cartId) {
        return;
    }
    db()->prepare('DELETE FROM cart_items WHERE cart_id = ?')->execute([$cartId]);
}

function apply_coupon_to_active_cart(string $code): void
{
    $cart = load_active_cart(false);
    if ($cart['subtotal_cents'] <= 0) {
        throw new AppException('Add an available product before applying a coupon.');
    }
    $coupon = coupon_by_code($code, false);
    $assessment = assess_coupon($coupon, $cart['subtotal_cents']);
    if (!$assessment['ok']) {
        throw new AppException($assessment['message']);
    }
    $_SESSION['coupon_code'] = strtoupper(trim($code));
}

function apply_coupon(int $userId, string $code): void
{
    $cart = load_cart($userId, false);
    if ($cart['subtotal_cents'] <= 0) {
        throw new AppException('Add an available product before applying a coupon.');
    }
    $coupon = coupon_by_code($code, false);
    $assessment = assess_coupon($coupon, $cart['subtotal_cents']);
    if (!$assessment['ok']) {
        throw new AppException($assessment['message']);
    }
    $_SESSION['coupon_code'] = strtoupper(trim($code));
}

function remove_coupon(): void
{
    unset($_SESSION['coupon_code']);
}

function merge_guest_cart_into_user(int $userId): void
{
    $bag = guest_cart_bag();
    if ($bag === []) {
        return;
    }
    foreach ($bag as $productId => $quantity) {
        try {
            add_to_cart($userId, (int) $productId, (int) $quantity, false);
        } catch (AppException) {
            // Skip items that became unavailable; keep the rest.
        }
    }
    unset($_SESSION['guest_cart']);
}

function add_wishlist(int $userId, int $productId): void
{
    $stmt = db()->prepare("SELECT id FROM products WHERE id = ? AND status = 'active' LIMIT 1");
    $stmt->execute([$productId]);
    if (!$stmt->fetchColumn()) {
        throw new AppException('That product cannot be saved.');
    }
    try {
        $insert = db()->prepare('INSERT INTO wishlists (user_id, product_id, created_at) VALUES (?, ?, NOW())');
        $insert->execute([$userId, $productId]);
    } catch (PDOException $exception) {
        if ((string) $exception->getCode() !== '23000') {
            throw $exception;
        }
    }
}

function remove_wishlist(int $userId, int $productId): void
{
    $stmt = db()->prepare('DELETE FROM wishlists WHERE user_id = ? AND product_id = ?');
    $stmt->execute([$userId, $productId]);
}

function move_wishlist_to_cart(int $userId, int $productId): string
{
    $name = add_to_cart($userId, $productId, 1, false);
    remove_wishlist($userId, $productId);
    return $name;
}
