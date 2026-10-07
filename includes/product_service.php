<?php
declare(strict_types=1);

function product_input_from_post(bool $asAdmin): array
{
    $name = clean_text(input_string('name', 160), 160, 'Product name');
    if (mb_strlen($name) < 3) {
        throw new AppException('Enter a product name of at least 3 characters.');
    }
    $description = clean_text(input_string('description', 5000), 5000, 'Description');
    if (mb_strlen($description) < 20) {
        throw new AppException('Write a description of at least 20 characters.');
    }
    $priceRaw = input_string('price', 12);
    if (!preg_match('/^\d+(\.\d{1,2})?$/', $priceRaw) || to_cents($priceRaw) <= 0 || to_cents($priceRaw) > 10000000) {
        throw new AppException('Enter a valid price.');
    }
    $compareRaw = input_string('compare_price', 12);
    $compare = null;
    if ($compareRaw !== '') {
        if (!preg_match('/^\d+(\.\d{1,2})?$/', $compareRaw) || to_cents($compareRaw) <= to_cents($priceRaw)) {
            throw new AppException('The compare price must be higher than the selling price.');
        }
        $compare = number_format((float) $compareRaw, 2, '.', '');
    }
    $stock = request_int('stock');
    if ($stock < 0 || $stock > 100000) {
        throw new AppException('Enter a stock quantity from 0 to 100000.');
    }
    $sku = strtoupper(clean_text(input_string('sku', 40), 40, 'SKU'));
    if (!preg_match('/^[A-Z0-9-]{3,40}$/', $sku)) {
        throw new AppException('SKU must be 3 to 40 letters, numbers, or hyphens.');
    }
    $categoryId = request_int('category_id');
    $category = db()->prepare('SELECT id FROM categories WHERE id = ? LIMIT 1');
    $category->execute([$categoryId]);
    if (!$category->fetchColumn()) {
        throw new AppException('Choose a category.');
    }
    $status = input_string('status', 20);
    if (!in_array($status, ['active', 'inactive'], true)) {
        $status = 'inactive';
    }
    $sellerId = request_int('seller_id');
    $featured = $asAdmin && isset($_POST['featured']) ? 1 : 0;
    return [
        'name' => $name,
        'description' => $description,
        'price' => number_format((float) $priceRaw, 2, '.', ''),
        'compare_price' => $compare,
        'stock' => $stock,
        'sku' => $sku,
        'category_id' => $categoryId,
        'status' => $status,
        'seller_id' => $sellerId,
        'featured' => $featured,
    ];
}

function assert_unique_product_fields(string $sku, string $slug, ?int $ignoreId): void
{
    $skuStmt = db()->prepare('SELECT id FROM products WHERE sku = ? AND (? IS NULL OR id <> ?) LIMIT 1');
    $skuStmt->execute([$sku, $ignoreId, $ignoreId]);
    if ($skuStmt->fetchColumn()) {
        throw new AppException('That SKU is already in use.');
    }
    $slugStmt = db()->prepare('SELECT id FROM products WHERE slug = ? AND (? IS NULL OR id <> ?) LIMIT 1');
    $slugStmt->execute([$slug, $ignoreId, $ignoreId]);
    if ($slugStmt->fetchColumn()) {
        throw new AppException('A product with a similar name already exists. Adjust the name slightly.');
    }
}

function save_product(?int $productId, int $actorId, bool $asAdmin): int
{
    $input = product_input_from_post($asAdmin);
    $pdo = db();
    $pdo->beginTransaction();
    $stored = [];
    $freshUploads = [];
    try {
        if ($asAdmin) {
            $seller = $pdo->prepare("SELECT id FROM users WHERE id = ? AND role = 'seller' AND status = 'active' LIMIT 1");
            $seller->execute([$input['seller_id']]);
            if (!$seller->fetchColumn()) {
                throw new AppException('Choose an active seller for this product.');
            }
            $sellerId = $input['seller_id'];
        } else {
            $sellerId = $actorId;
        }

        $currentImages = [];
        if ($productId) {
            $existingStmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1 FOR UPDATE');
            $existingStmt->execute([$productId]);
            $existing = $existingStmt->fetch();
            if (!$existing || (!$asAdmin && (int) $existing['seller_id'] !== $actorId)) {
                throw new AppException('You cannot edit that product.');
            }
            $slug = (string) $existing['slug'];
            if (slugify($input['name']) !== slugify((string) $existing['name'])) {
                $slug = unique_slug(slugify($input['name']), $productId);
            }
            assert_unique_product_fields($input['sku'], $slug, $productId);
            $featured = $asAdmin ? $input['featured'] : (int) $existing['featured'];
            $update = $pdo->prepare(
                'UPDATE products
                 SET seller_id = ?, category_id = ?, name = ?, slug = ?, description = ?, price = ?, compare_price = ?,
                     stock = ?, sku = ?, status = ?, featured = ?, updated_at = NOW()
                 WHERE id = ?'
            );
            $update->execute([
                $sellerId, $input['category_id'], $input['name'], $slug, $input['description'], $input['price'],
                $input['compare_price'], $input['stock'], $input['sku'], $input['status'], $featured, $productId,
            ]);
            $imageStmt = $pdo->prepare('SELECT id, image_path FROM product_images WHERE product_id = ?');
            $imageStmt->execute([$productId]);
            $currentImages = $imageStmt->fetchAll();
        } else {
            $slug = unique_slug(slugify($input['name']), null);
            assert_unique_product_fields($input['sku'], $slug, null);
            $insert = $pdo->prepare(
                'INSERT INTO products (
                    seller_id, category_id, name, slug, description, price, compare_price, stock, sku, status, featured, created_at, updated_at
                 ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())'
            );
            $insert->execute([
                $sellerId, $input['category_id'], $input['name'], $slug, $input['description'], $input['price'],
                $input['compare_price'], $input['stock'], $input['sku'], $input['status'], $asAdmin ? $input['featured'] : 0,
            ]);
            $productId = (int) $pdo->lastInsertId();
        }

        $removeIds = $_POST['remove_images'] ?? [];
        if (!is_array($removeIds)) {
            $removeIds = [];
        }
        $remaining = count($currentImages);
        foreach ($currentImages as $image) {
            if (in_array((string) $image['id'], array_map('strval', $removeIds), true)) {
                $pdo->prepare('DELETE FROM product_images WHERE id = ? AND product_id = ?')->execute([(int) $image['id'], $productId]);
                $stored[] = (string) $image['image_path'];
                $remaining--;
            }
        }

        $uploads = normalize_uploads($_FILES['images'] ?? null);
        if (count($uploads) + max(0, $remaining) > 5) {
            throw new AppException('A product can have up to 5 images.');
        }
        $orderStmt = $pdo->prepare('SELECT COALESCE(MAX(display_order), -1) FROM product_images WHERE product_id = ?');
        $orderStmt->execute([$productId]);
        $order = (int) $orderStmt->fetchColumn();
        $insertImage = $pdo->prepare(
            'INSERT INTO product_images (product_id, image_path, display_order, created_at) VALUES (?, ?, ?, NOW())'
        );
        foreach ($uploads as $file) {
            $path = store_uploaded_image($file, 'products', 200);
            $freshUploads[] = $path;
            $order++;
            $insertImage->execute([$productId, $path, $order]);
            $remaining++;
        }
        if ($remaining < 1) {
            throw new AppException('Add at least one product image.');
        }

        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        foreach ($freshUploads as $path) {
            delete_managed_upload($path);
        }
        if ($exception instanceof AppException) {
            throw $exception;
        }
        log_message('upload', $exception->getMessage());
        throw new AppException('The product could not be saved.');
    }

    foreach ($stored as $path) {
        delete_managed_upload($path);
    }
    return (int) $productId;
}

function unique_slug(string $base, ?int $ignoreId): string
{
    $slug = $base;
    $suffix = 2;
    while (true) {
        $stmt = db()->prepare('SELECT id FROM products WHERE slug = ? AND (? IS NULL OR id <> ?) LIMIT 1');
        $stmt->execute([$slug, $ignoreId, $ignoreId]);
        if (!$stmt->fetchColumn()) {
            return $slug;
        }
        $slug = substr($base, 0, 140) . '-' . $suffix;
        $suffix++;
        if ($suffix > 200) {
            throw new AppException('Choose a more specific product name.');
        }
    }
}

function retire_product(int $productId, int $actorId, bool $asAdmin): string
{
    $pdo = db();
    $stmt = $pdo->prepare('SELECT * FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product || (!$asAdmin && (int) $product['seller_id'] !== $actorId)) {
        throw new AppException('You cannot change that product.');
    }
    $orders = $pdo->prepare('SELECT COUNT(*) FROM order_items WHERE product_id = ?');
    $orders->execute([$productId]);
    if ((int) $orders->fetchColumn() > 0 || $product['status'] === 'archived') {
        $pdo->prepare("UPDATE products SET status = 'archived', featured = 0, updated_at = NOW() WHERE id = ?")->execute([$productId]);
        return 'archived';
    }
    $images = product_images($productId);
    $pdo->beginTransaction();
    try {
        $pdo->prepare('DELETE FROM product_reviews WHERE product_id = ?')->execute([$productId]);
        $pdo->prepare('DELETE FROM wishlists WHERE product_id = ?')->execute([$productId]);
        $pdo->prepare('DELETE FROM cart_items WHERE product_id = ?')->execute([$productId]);
        $pdo->prepare('DELETE FROM product_images WHERE product_id = ?')->execute([$productId]);
        $pdo->prepare('DELETE FROM products WHERE id = ?')->execute([$productId]);
        $pdo->commit();
    } catch (Throwable $exception) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        $pdo->prepare("UPDATE products SET status = 'archived', featured = 0, updated_at = NOW() WHERE id = ?")->execute([$productId]);
        log_message('product', 'Hard delete blocked: ' . $exception->getMessage());
        return 'archived';
    }
    foreach ($images as $image) {
        delete_managed_upload($image['image_path']);
    }
    return 'deleted';
}

function set_product_state(int $productId, int $actorId, bool $asAdmin, string $status): void
{
    if (!in_array($status, ['active', 'inactive'], true)) {
        throw new AppException('Choose a valid product status.');
    }
    $stmt = db()->prepare('SELECT seller_id, status FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product || (!$asAdmin && (int) $product['seller_id'] !== $actorId)) {
        throw new AppException('You cannot change that product.');
    }
    if ($product['status'] === 'archived') {
        throw new AppException('Archived products stay archived so past orders remain intact.');
    }
    db()->prepare('UPDATE products SET status = ?, updated_at = NOW() WHERE id = ?')->execute([$status, $productId]);
}

function set_featured(int $productId, bool $featured): void
{
    $stmt = db()->prepare("UPDATE products SET featured = ?, updated_at = NOW() WHERE id = ? AND status <> 'archived'");
    $stmt->execute([$featured ? 1 : 0, $productId]);
    if ($stmt->rowCount() < 1) {
        $exists = db()->prepare('SELECT id FROM products WHERE id = ?');
        $exists->execute([$productId]);
        if (!$exists->fetchColumn()) {
            throw new AppException('That product was not found.');
        }
    }
}

function update_stock_only(int $productId, int $actorId, bool $asAdmin, int $stock): void
{
    if ($stock < 0 || $stock > 100000) {
        throw new AppException('Enter a stock quantity from 0 to 100000.');
    }
    $stmt = db()->prepare('SELECT seller_id FROM products WHERE id = ? LIMIT 1');
    $stmt->execute([$productId]);
    $sellerId = $stmt->fetchColumn();
    if (!$sellerId || (!$asAdmin && (int) $sellerId !== $actorId)) {
        throw new AppException('You cannot change that product.');
    }
    db()->prepare('UPDATE products SET stock = ?, updated_at = NOW() WHERE id = ?')->execute([$stock, $productId]);
}

function load_owned_product(int $productId, int $actorId, bool $asAdmin): array
{
    $stmt = db()->prepare(
        'SELECT p.*, sp.shop_name
         FROM products p
         LEFT JOIN seller_profiles sp ON sp.user_id = p.seller_id
         WHERE p.id = ? LIMIT 1'
    );
    $stmt->execute([$productId]);
    $product = $stmt->fetch();
    if (!$product || (!$asAdmin && (int) $product['seller_id'] !== $actorId)) {
        throw new AppException('That product was not found.');
    }
    return $product;
}

function handle_product_list_action(int $actorId, bool $asAdmin): void
{
    if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
        return;
    }
    verify_csrf();
    $action = input_string('action', 20);
    $productId = request_int('product_id');
    if ($action === 'stock') {
        update_stock_only($productId, $actorId, $asAdmin, request_int('stock'));
        flash('success', 'Stock updated.');
    } elseif ($action === 'activate' || $action === 'deactivate') {
        set_product_state($productId, $actorId, $asAdmin, $action === 'activate' ? 'active' : 'inactive');
        flash('success', 'Product visibility updated.');
    } elseif ($action === 'archive') {
        $result = retire_product($productId, $actorId, $asAdmin);
        flash('success', $result === 'deleted' ? 'Product deleted.' : 'Product archived so existing orders stay intact.');
    } elseif ($asAdmin && ($action === 'feature' || $action === 'unfeature')) {
        set_featured($productId, $action === 'feature');
        flash('success', 'Featured status updated.');
    } else {
        throw new AppException('That product action is not available.');
    }
}
