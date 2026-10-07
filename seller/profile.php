<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('seller');
$profileStmt = db()->prepare('SELECT * FROM seller_profiles WHERE user_id = ? LIMIT 1');
$profileStmt->execute([(int) $user['id']]);
$profile = $profileStmt->fetch() ?: ['shop_name' => '', 'bio' => '', 'location' => ''];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        if (input_string('action', 20) === 'password') {
            $current = (string) ($_POST['current_password'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['password_confirmation'] ?? '');
            $hashStmt = db()->prepare('SELECT password FROM users WHERE id = ?');
            $hashStmt->execute([(int) $user['id']]);
            if (!password_verify($current, (string) $hashStmt->fetchColumn())) {
                throw new AppException('The current password is incorrect.');
            }
            validate_password($password);
            if (!hash_equals($password, $confirm)) {
                throw new AppException('Password confirmation does not match.');
            }
            db()->prepare('UPDATE users SET password = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
            flash('success', 'Password updated.');
        } else {
            $name = validate_person_name(input_string('name', 80));
            $phone = validate_phone(input_string('phone', 20));
            $address = validate_address(input_string('address', 255));
            $shop = clean_text(input_string('shop_name', 80), 80, 'Shop name');
            $bio = clean_text(input_string('bio', 500), 500, 'Bio');
            $location = clean_text(input_string('location', 120), 120, 'Location');
            if (mb_strlen($shop) < 2 || mb_strlen($bio) < 10 || mb_strlen($location) < 2) {
                throw new AppException('Add a shop name, location, and a short bio.');
            }
            db()->prepare('UPDATE users SET name = ?, phone = ?, address = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$name, $phone, $address, (int) $user['id']]);
            db()->prepare(
                'INSERT INTO seller_profiles (user_id, shop_name, shop_slug, bio, location, created_at, updated_at)
                 VALUES (?, ?, ?, ?, ?, NOW(), NOW())
                 ON DUPLICATE KEY UPDATE shop_name = VALUES(shop_name), bio = VALUES(bio), location = VALUES(location), updated_at = NOW()'
            )->execute([(int) $user['id'], $shop, slugify($shop) . '-' . (int) $user['id'], $bio, $location]);
            flash('success', 'Studio profile saved.');
        }
        current_user(true);
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
        set_old($_POST);
    }
    redirect('seller/profile.php');
}

$user = current_user(true) ?? $user;
$profileStmt->execute([(int) $user['id']]);
$profile = $profileStmt->fetch() ?: $profile;
$pageTitle = 'Studio profile';
$activeMenu = 'profile';
require APP_ROOT . '/includes/seller_header.php';
?>
<div class="row g-4">
    <div class="col-lg-7">
        <form method="post" class="form-card">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="profile">
            <div class="mb-3"><label class="form-label" for="shop_name">Shop name</label><input class="form-control" id="shop_name" name="shop_name" required maxlength="80" value="<?= e(old('shop_name', (string) $profile['shop_name'])) ?>"></div>
            <div class="mb-3"><label class="form-label" for="location">Location</label><input class="form-control" id="location" name="location" required maxlength="120" value="<?= e(old('location', (string) $profile['location'])) ?>"></div>
            <div class="mb-3"><label class="form-label" for="bio">Bio</label><textarea class="form-control" id="bio" name="bio" required maxlength="500"><?= e(old('bio', (string) $profile['bio'])) ?></textarea></div>
            <div class="mb-3"><label class="form-label" for="name">Contact name</label><input class="form-control" id="name" name="name" required maxlength="80" value="<?= e(old('name', (string) $user['name'])) ?>"></div>
            <div class="mb-3"><label class="form-label" for="phone">Phone</label><input class="form-control" id="phone" name="phone" required maxlength="20" value="<?= e(old('phone', (string) $user['phone'])) ?>"></div>
            <div class="mb-3"><label class="form-label" for="address">Address</label><textarea class="form-control" id="address" name="address" required maxlength="255"><?= e(old('address', (string) $user['address'])) ?></textarea></div>
            <button class="btn btn-accent" type="submit">Save studio</button>
        </form>
    </div>
    <div class="col-lg-5"><?php require APP_ROOT . '/includes/password_form.php'; ?></div>
</div>
<?php require APP_ROOT . '/includes/seller_footer.php'; ?>
