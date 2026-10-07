<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
$status = query_string('status', 20);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $id = request_int('review_id');
        $action = input_string('action', 20);
        if ($action === 'delete') {
            db()->prepare('DELETE FROM product_reviews WHERE id = ?')->execute([$id]);
            flash('success', 'Review deleted.');
        } elseif ($action === 'hide' || $action === 'publish') {
            db()->prepare('UPDATE product_reviews SET status = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$action === 'hide' ? 'hidden' : 'published', $id]);
            flash('success', 'Review updated.');
        } else {
            throw new AppException('That review action is not available.');
        }
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
    }
    redirect('admin/reviews.php' . ($status !== '' ? '?status=' . rawurlencode($status) : ''));
}

$where = '1=1';
$params = [];
if (in_array($status, ['published', 'hidden'], true)) {
    $where = 'r.status = ?';
    $params[] = $status;
}
$stmt = db()->prepare(
    "SELECT r.*, u.name AS customer_name, p.name AS product_name
     FROM product_reviews r
     INNER JOIN users u ON u.id = r.user_id
     INNER JOIN products p ON p.id = r.product_id
     WHERE $where
     ORDER BY r.created_at DESC"
);
$stmt->execute($params);
$reviews = $stmt->fetchAll();
$pageTitle = 'Reviews';
$activeMenu = 'reviews';
require APP_ROOT . '/includes/admin_header.php';
?>
<form class="d-flex gap-2 mb-3" method="get">
    <select class="form-select" style="max-width:220px" name="status">
        <option value="">All reviews</option>
        <option value="published" <?= $status === 'published' ? 'selected' : '' ?>>Published</option>
        <option value="hidden" <?= $status === 'hidden' ? 'selected' : '' ?>>Hidden</option>
    </select>
    <button class="btn btn-quiet" type="submit">Filter</button>
</form>
<?php foreach ($reviews as $review): ?>
    <article class="form-card mb-3">
        <div class="d-flex justify-content-between gap-3">
            <div>
                <strong><?= e($review['product_name']) ?></strong>
                <p class="mb-1"><?= e($review['customer_name']) ?> · <?= render_stars($review['rating']) ?></p>
                <p><?= nl2br(e($review['comment'])) ?></p>
            </div>
            <div class="text-nowrap">
                <span class="badge-status badge-<?= e($review['status']) ?>"><?= e($review['status']) ?></span>
                <form method="post" class="mt-2"><?= csrf_field() ?><input type="hidden" name="review_id" value="<?= e((string) $review['id']) ?>"><input type="hidden" name="action" value="<?= $review['status'] === 'published' ? 'hide' : 'publish' ?>"><button class="btn btn-quiet btn-sm" type="submit"><?= $review['status'] === 'published' ? 'Hide' : 'Publish' ?></button></form>
                <form method="post" data-confirm="Delete this review?"><?= csrf_field() ?><input type="hidden" name="review_id" value="<?= e((string) $review['id']) ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger-soft btn-sm mt-2" type="submit">Delete</button></form>
            </div>
        </div>
    </article>
<?php endforeach; ?>
<?php if (!$reviews): ?>
    <?php render_empty('fa-star', 'No reviews in this view.', 'Published and hidden reviews will appear here.'); ?>
<?php endif; ?>
<?php require APP_ROOT . '/includes/admin_footer.php'; ?>
