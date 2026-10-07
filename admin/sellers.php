<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$actor = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $name = validate_person_name(input_string('name', 80));
        $email = validate_email(input_string('email', 120));
        $phone = validate_phone(input_string('phone', 20));
        $address = validate_address(input_string('address', 255));
        $shop = clean_text(input_string('shop_name', 80), 80, 'Shop name');
        $bio = clean_text(input_string('bio', 500), 500, 'Bio');
        $location = clean_text(input_string('location', 120), 120, 'Location');
        $password = (string) ($_POST['password'] ?? '');
        validate_password($password);
        if (mb_strlen($shop) < 2 || mb_strlen($bio) < 10) {
            throw new AppException('Add a shop name and a short bio.');
        }
        $exists = db()->prepare('SELECT id FROM users WHERE email = ? LIMIT 1');
        $exists->execute([$email]);
        if ($exists->fetchColumn()) {
            throw new AppException('That email is already in use.');
        }
        $pdo = db();
        $pdo->beginTransaction();
        $pdo->prepare(
            'INSERT INTO users (name, email, password, phone, address, role, status, must_change_password, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, \'seller\', \'active\', 1, NOW(), NOW())'
        )->execute([$name, $email, password_hash($password, PASSWORD_DEFAULT), $phone, $address]);
        $sellerId = (int) $pdo->lastInsertId();
        $pdo->prepare(
            'INSERT INTO seller_profiles (user_id, shop_name, shop_slug, bio, location, created_at, updated_at)
             VALUES (?, ?, ?, ?, ?, NOW(), NOW())'
        )->execute([$sellerId, $shop, slugify($shop) . '-' . $sellerId, $bio, $location]);
        $pdo->commit();
        flash('success', 'Seller account created. They must change the starting password on first sign-in.');
        redirect('admin/sellers.php');
    } catch (AppException $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        flash('error', $exception->getMessage());
        set_old($_POST);
    } catch (Throwable $exception) {
        if (db()->inTransaction()) {
            db()->rollBack();
        }
        log_message('seller', $exception->getMessage());
        flash('error', 'The seller could not be created.');
        set_old($_POST);
    }
}

$sellers = db()->query(
    "SELECT u.id, u.name, u.email, u.status, sp.shop_name, sp.location,
            (SELECT COUNT(*) FROM products p WHERE p.seller_id = u.id) AS products
     FROM users u
     LEFT JOIN seller_profiles sp ON sp.user_id = u.id
     WHERE u.role = 'seller'
     ORDER BY u.id DESC"
)->fetchAll();
$pageTitle = 'Sellers';
$activeMenu = 'sellers';
require APP_ROOT . '/includes/admin_header.php';
?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="table-responsive form-card">
            <table class="table align-middle">
                <thead><tr><th>Studio</th><th>Contact</th><th>Products</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($sellers as $seller): ?>
                    <tr>
                        <td><?= e($seller['shop_name'] ?: $seller['name']) ?><div class="muted"><?= e($seller['location'] ?: '') ?></div></td>
                        <td><?= e($seller['email']) ?></td>
                        <td><?= e((string) $seller['products']) ?></td>
                        <td><span class="badge-status badge-<?= e($seller['status']) ?>"><?= e($seller['status']) ?></span></td>
                        <td><a href="<?= e(url('admin/user_view.php?id=' . (int) $seller['id'])) ?>">Manage</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-5">
        <form method="post" class="form-card">
            <?= csrf_field() ?>
            <h2>New seller</h2>
            <div class="mb-2"><label class="form-label" for="name">Contact name</label><input class="form-control" id="name" name="name" required value="<?= e(old('name')) ?>"></div>
            <div class="mb-2"><label class="form-label" for="email">Email</label><input class="form-control" id="email" type="email" name="email" required value="<?= e(old('email')) ?>"></div>
            <div class="mb-2"><label class="form-label" for="phone">Phone</label><input class="form-control" id="phone" name="phone" required value="<?= e(old('phone')) ?>"></div>
            <div class="mb-2"><label class="form-label" for="address">Address</label><textarea class="form-control" id="address" name="address" required><?= e(old('address')) ?></textarea></div>
            <div class="mb-2"><label class="form-label" for="shop_name">Shop name</label><input class="form-control" id="shop_name" name="shop_name" required value="<?= e(old('shop_name')) ?>"></div>
            <div class="mb-2"><label class="form-label" for="location">Location</label><input class="form-control" id="location" name="location" required value="<?= e(old('location')) ?>"></div>
            <div class="mb-2"><label class="form-label" for="bio">Bio</label><textarea class="form-control" id="bio" name="bio" required><?= e(old('bio')) ?></textarea></div>
            <div class="mb-3"><label class="form-label" for="password">Starting password</label><input class="form-control" id="password" name="password" type="password" required minlength="8" maxlength="72"></div>
            <button class="btn btn-accent" type="submit">Create seller</button>
        </form>
    </div>
</div>
<?php require APP_ROOT . '/includes/admin_footer.php'; ?>
