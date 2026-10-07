<?php
declare(strict_types=1);

const PRODUCT_CARD_SQL = <<<'SQL'
p.id, p.seller_id, p.category_id, p.name, p.slug, p.description, p.price, p.compare_price,
p.stock, p.sku, p.status, p.featured, p.created_at,
c.name AS category_name,
sp.shop_name,
(SELECT pi.image_path FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.display_order, pi.id LIMIT 1) AS image_path,
(SELECT ROUND(AVG(r.rating), 1) FROM product_reviews r WHERE r.product_id = p.id AND r.status = 'published') AS avg_rating,
(SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = p.id AND r.status = 'published') AS review_count,
(SELECT COALESCE(SUM(oi.quantity), 0)
   FROM order_items oi
   INNER JOIN orders o ON o.id = oi.order_id AND o.order_status <> 'cancelled'
   WHERE oi.product_id = p.id) AS units_sold
SQL;

function public_product_joins(): string
{
    return "INNER JOIN categories c ON c.id = p.category_id AND c.status = 'active'
            INNER JOIN users u ON u.id = p.seller_id AND u.status = 'active' AND u.role = 'seller'
            LEFT JOIN seller_profiles sp ON sp.user_id = p.seller_id";
}

function query_products(array $filters): array
{
    $where = ["p.status = 'active'"];
    $params = [];

    $search = trim((string) ($filters['q'] ?? ''));
    if ($search !== '') {
        $where[] = "(p.name LIKE ? ESCAPE '\\\\' OR p.description LIKE ? ESCAPE '\\\\' OR p.sku LIKE ? ESCAPE '\\\\')";
        $like = like_pattern($search);
        $params[] = $like;
        $params[] = $like;
        $params[] = $like;
    }

    $categoryId = (int) ($filters['category'] ?? 0);
    if ($categoryId > 0) {
        $where[] = 'p.category_id = ?';
        $params[] = $categoryId;
    }

    $minPrice = trim((string) ($filters['min_price'] ?? ''));
    if (preg_match('/^\d+(\.\d{1,2})?$/', $minPrice)) {
        $where[] = 'p.price >= ?';
        $params[] = number_format((float) $minPrice, 2, '.', '');
    }
    $maxPrice = trim((string) ($filters['max_price'] ?? ''));
    if (preg_match('/^\d+(\.\d{1,2})?$/', $maxPrice)) {
        $where[] = 'p.price <= ?';
        $params[] = number_format((float) $maxPrice, 2, '.', '');
    }

    $availability = (string) ($filters['availability'] ?? '');
    if ($availability === 'in') {
        $where[] = 'p.stock > 0';
    } elseif ($availability === 'out') {
        $where[] = 'p.stock = 0';
    }

    $sorts = [
        'newest' => 'created_at DESC',
        'price_asc' => 'price ASC, id DESC',
        'price_desc' => 'price DESC, id DESC',
        'rating' => 'avg_rating DESC, review_count DESC, id DESC',
        'popular' => 'units_sold DESC, created_at DESC',
    ];
    $sort = (string) ($filters['sort'] ?? 'newest');
    $orderBy = $sorts[$sort] ?? $sorts['newest'];

    $inner = 'SELECT ' . PRODUCT_CARD_SQL . ' FROM products p ' . public_product_joins()
        . ' WHERE ' . implode(' AND ', $where);
    $outerWhere = '1=1';
    $outerParams = $params;
    $minRating = (int) ($filters['rating'] ?? 0);
    if ($minRating > 0) {
        $outerWhere .= ' AND avg_rating >= ?';
        $outerParams[] = $minRating;
    }

    $countStmt = db()->prepare("SELECT COUNT(*) FROM ($inner) catalog WHERE $outerWhere");
    $countStmt->execute($outerParams);
    $total = (int) $countStmt->fetchColumn();

    $perPage = max(1, (int) config('app', 'products_per_page', 12));
    $pages = max(1, (int) ceil($total / $perPage));
    $page = (int) ($filters['page'] ?? 1);
    if ($page < 1) {
        $page = 1;
    }
    if ($page > $pages) {
        $page = $pages;
    }
    $offset = ($page - 1) * $perPage;

    $sql = "SELECT * FROM ($inner) catalog WHERE $outerWhere ORDER BY $orderBy LIMIT $perPage OFFSET $offset";
    $stmt = db()->prepare($sql);
    $stmt->execute($outerParams);

    return [
        'items' => $stmt->fetchAll(),
        'total' => $total,
        'page' => $page,
        'pages' => $pages,
        'per_page' => $perPage,
        'sort' => isset($sorts[$sort]) ? $sort : 'newest',
    ];
}

function find_public_product(string $slug): ?array
{
    if (!preg_match('/^[a-z0-9-]{1,160}$/', $slug)) {
        return null;
    }
    $stmt = db()->prepare(
        'SELECT p.*, c.name AS category_name, c.slug AS category_slug, sp.shop_name, u.name AS seller_name,
                (SELECT ROUND(AVG(r.rating), 1) FROM product_reviews r WHERE r.product_id = p.id AND r.status = \'published\') AS avg_rating,
                (SELECT COUNT(*) FROM product_reviews r WHERE r.product_id = p.id AND r.status = \'published\') AS review_count
         FROM products p
         ' . public_product_joins() . '
         WHERE p.slug = ? AND p.status = \'active\'
         LIMIT 1'
    );
    $stmt->execute([$slug]);
    $product = $stmt->fetch();
    return $product ?: null;
}

function product_images(int $productId): array
{
    $stmt = db()->prepare(
        'SELECT id, image_path, display_order FROM product_images WHERE product_id = ? ORDER BY display_order, id'
    );
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function related_products(int $productId, int $categoryId, int $limit = 4): array
{
    $limit = max(1, min(8, $limit));
    $stmt = db()->prepare(
        'SELECT ' . PRODUCT_CARD_SQL . '
         FROM products p
         ' . public_product_joins() . '
         WHERE p.status = \'active\' AND p.category_id = ? AND p.id <> ?
         ORDER BY p.featured DESC, p.created_at DESC
         LIMIT ' . $limit
    );
    $stmt->execute([$categoryId, $productId]);
    return $stmt->fetchAll();
}

function published_reviews(int $productId): array
{
    $stmt = db()->prepare(
        'SELECT r.id, r.user_id, r.rating, r.comment, r.created_at, r.updated_at, u.name
         FROM product_reviews r
         INNER JOIN users u ON u.id = r.user_id
         WHERE r.product_id = ? AND r.status = \'published\'
         ORDER BY r.created_at DESC'
    );
    $stmt->execute([$productId]);
    return $stmt->fetchAll();
}

function rating_breakdown(int $productId): array
{
    $counts = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
    $stmt = db()->prepare(
        "SELECT rating, COUNT(*) AS total
         FROM product_reviews
         WHERE product_id = ? AND status = 'published'
         GROUP BY rating"
    );
    $stmt->execute([$productId]);
    foreach ($stmt->fetchAll() as $row) {
        $star = (int) $row['rating'];
        if (isset($counts[$star])) {
            $counts[$star] = (int) $row['total'];
        }
    }
    return $counts;
}

function purchase_order_for_product(int $userId, int $productId): ?int
{
    $stmt = db()->prepare(
        "SELECT o.id
         FROM orders o
         INNER JOIN order_items oi ON oi.order_id = o.id
         WHERE o.user_id = ? AND oi.product_id = ?
           AND o.order_status IN ('paid', 'processing', 'shipped', 'completed')
         ORDER BY o.id DESC
         LIMIT 1"
    );
    $stmt->execute([$userId, $productId]);
    $id = $stmt->fetchColumn();
    return $id ? (int) $id : null;
}

function own_review(int $userId, int $productId): ?array
{
    $stmt = db()->prepare(
        'SELECT * FROM product_reviews WHERE user_id = ? AND product_id = ? LIMIT 1'
    );
    $stmt->execute([$userId, $productId]);
    $row = $stmt->fetch();
    return $row ?: null;
}

function save_review(int $userId, int $productId, int $rating, string $comment): void
{
    if ($rating < 1 || $rating > 5) {
        throw new AppException('Choose a rating from 1 to 5.');
    }
    $comment = clean_text($comment, 1000, 'Review');
    if (mb_strlen($comment) < 10) {
        throw new AppException('Write at least 10 characters so other shoppers can use the review.');
    }
    $orderId = purchase_order_for_product($userId, $productId);
    if (!$orderId) {
        throw new AppException('Only customers who purchased this product can review it.');
    }
    $existing = own_review($userId, $productId);
    if ($existing) {
        $stmt = db()->prepare(
            'UPDATE product_reviews
             SET rating = ?, comment = ?, order_id = ?, updated_at = NOW()
             WHERE id = ? AND user_id = ?'
        );
        $stmt->execute([$rating, $comment, $orderId, (int) $existing['id'], $userId]);
        return;
    }
    $stmt = db()->prepare(
        'INSERT INTO product_reviews (product_id, user_id, order_id, rating, comment, status, created_at, updated_at)
         VALUES (?, ?, ?, ?, ?, \'published\', NOW(), NOW())'
    );
    $stmt->execute([$productId, $userId, $orderId, $rating, $comment]);
}

function active_categories(): array
{
    return db()->query(
        "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id AND p.status = 'active') AS product_count
         FROM categories c
         WHERE c.status = 'active'
         ORDER BY c.name"
    )->fetchAll();
}

function home_collections(): array
{
    $featured = db()->query(
        'SELECT ' . PRODUCT_CARD_SQL . ' FROM products p ' . public_product_joins() . "
         WHERE p.status = 'active' AND p.featured = 1
         ORDER BY p.created_at DESC
         LIMIT 8"
    )->fetchAll();
    $arrivals = db()->query(
        'SELECT ' . PRODUCT_CARD_SQL . ' FROM products p ' . public_product_joins() . "
         WHERE p.status = 'active'
         ORDER BY p.created_at DESC
         LIMIT 8"
    )->fetchAll();
    $popular = db()->query(
        'SELECT ' . PRODUCT_CARD_SQL . ' FROM products p ' . public_product_joins() . "
         WHERE p.status = 'active'
         ORDER BY units_sold DESC, p.created_at DESC
         LIMIT 8"
    )->fetchAll();
    return compact('featured', 'arrivals', 'popular');
}
