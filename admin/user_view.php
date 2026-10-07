<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$actor = require_role('admin');
$userId = request_int('id', 'get');
$stmt = db()->prepare('SELECT * FROM users WHERE id = ? LIMIT 1');
$stmt->execute([$userId]);
$person = $stmt->fetch();

if ($person && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        if ((int) $person['id'] === (int) $actor['id']) {
            throw new AppException('Update another administrator rather than your own access from this screen.');
        }
        $role = input_string('role', 20);
        $status = input_string('status', 20);
        if (!in_array($role, ['customer', 'seller', 'admin'], true) || !in_array($status, ['active', 'suspended'], true)) {
            throw new AppException('Choose a valid role and status.');
        }
        guard_last_admin($person, $role, $status);
        db()->prepare('UPDATE users SET role = ?, status = ?, updated_at = NOW() WHERE id = ?')->execute([$role, $status, (int) $person['id']]);
        if ($role === 'seller') {
            db()->prepare(
                'INSERT INTO seller_profiles (user_id, shop_name, shop_slug, bio, location, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE updated_at = updated_at'
            )->execute([
                (int) $person['id'],
                $person['name'] . ' Studio',
                slugify($person['name']) . '-' . (int) $person['id'],
                'Studio profile created by an administrator.',
                'Not specified',
            ]);
        }
        flash('success', 'Account access updated.');
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
    }
    redirect('admin/user_view.php?id=' . (int) $person['id']);
}

$orders = [];
if ($person) {
    $orderStmt = db()->prepare('SELECT order_number, total_amount, order_status, created_at FROM orders WHERE user_id = ? ORDER BY id DESC LIMIT 8');
    $orderStmt->execute([(int) $person['id']]);
    $orders = $orderStmt->fetchAll();
}
$pageTitle = $person ? (string) $person['name'] : 'User';
$activeMenu = 'users';
require APP_ROOT . '/includes/admin_header.php';
?>
<?php if (!$person): ?>
    <?php render_empty('fa-user', 'User not found.', 'Return to the directory.', url('admin/users.php'), 'All users'); ?>
<?php else: ?>
    <section class="form-card mb-4">
        <p><?= e($person['email']) ?></p>
        <p><?= e($person['phone']) ?></p>
        <p><?= e($person['address']) ?></p>
        <p>Joined <?= e(date('M j, Y', strtotime((string) $person['created_at']))) ?></p>
        <form method="post" class="row g-3" data-confirm="Update this account?">
            <?= csrf_field() ?>
            <div class="col-md-4">
                <label class="form-label" for="role">Role</label>
                <select class="form-select" id="role" name="role">
                    <?php foreach (['customer', 'seller', 'admin'] as $option): ?>
                        <option value="<?= e($option) ?>" <?= $person['role'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4">
                <label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <?php foreach (['active', 'suspended'] as $option): ?>
                        <option value="<?= e($option) ?>" <?= $person['status'] === $option ? 'selected' : '' ?>><?= e($option) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <button class="btn btn-accent" type="submit">Save access</button>
            </div>
        </form>
    </section>
    <section class="form-card">
        <h2>Recent orders</h2>
        <?php if (!$orders): ?><p class="mb-0">No orders yet.</p><?php endif; ?>
        <?php foreach ($orders as $order): ?>
            <p><?= e($order['order_number']) ?> · <?= e(money($order['total_amount'])) ?> · <?= e($order['order_status']) ?></p>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php require APP_ROOT . '/includes/admin_footer.php'; ?>
