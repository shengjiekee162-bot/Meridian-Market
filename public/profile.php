<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('customer');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = input_string('action', 20);
    try {
        verify_csrf();
        if ($action === 'password') {
            $current = (string) ($_POST['current_password'] ?? '');
            $password = (string) ($_POST['password'] ?? '');
            $confirm = (string) ($_POST['password_confirmation'] ?? '');
            $stmt = db()->prepare('SELECT password FROM users WHERE id = ? LIMIT 1');
            $stmt->execute([(int) $user['id']]);
            $hash = (string) $stmt->fetchColumn();
            if (!password_verify($current, $hash)) {
                throw new AppException('The current password is incorrect.');
            }
            validate_password($password);
            if (!hash_equals($password, $confirm)) {
                throw new AppException('Password confirmation does not match.');
            }
            db()->prepare('UPDATE users SET password = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?')
                ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
            current_user(true);
            flash('success', 'Password updated.');
        } else {
            $name = validate_person_name(input_string('name', 80));
            $email = validate_email(input_string('email', 120));
            $phone = validate_phone(input_string('phone', 20));
            $address = validate_address(input_string('address', 255));
            $exists = db()->prepare('SELECT id FROM users WHERE email = ? AND id <> ? LIMIT 1');
            $exists->execute([$email, (int) $user['id']]);
            if ($exists->fetchColumn()) {
                throw new AppException('That email is already in use.');
            }
            $avatar = $user['avatar'];
            $uploads = normalize_uploads($_FILES['avatar'] ?? null);
            if ($uploads) {
                $avatar = store_uploaded_image($uploads[0], 'profiles', 100);
            }
            db()->prepare('UPDATE users SET name = ?, email = ?, phone = ?, address = ?, avatar = ?, updated_at = NOW() WHERE id = ?')
                ->execute([$name, $email, $phone, $address, $avatar, (int) $user['id']]);
            if ($avatar !== $user['avatar']) {
                delete_managed_upload($user['avatar']);
            }
            current_user(true);
            flash('success', 'Profile updated.');
        }
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
        set_old($_POST);
    } catch (Throwable $exception) {
        log_message('profile', $exception->getMessage());
        flash('error', 'The profile could not be saved.');
    }
    redirect('public/profile.php');
}

$pageTitle = 'Profile';
require APP_ROOT . '/includes/header.php';
?>
<main id="content" class="container py-4">
    <div class="row g-4">
        <div class="col-lg-7">
            <form method="post" enctype="multipart/form-data" class="form-card">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="profile">
                <h1>Profile</h1>
                <?php if (trim((string) $user['address']) === ''): ?>
                    <p class="text-danger">Add your shipping address here. You can also enter it when you check out.</p>
                <?php endif; ?>
                <?php if ($user['avatar']): ?><img src="<?= e(media_url($user['avatar'])) ?>" alt="" style="width:84px;height:84px;object-fit:cover;border-radius:50%"> <?php endif; ?>
                <div class="mb-3 mt-3">
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
                    <label class="form-label" for="address">Address</label>
                    <textarea class="form-control" id="address" name="address" required maxlength="255"><?= e(old('address', (string) $user['address'])) ?></textarea>
                </div>
                <div class="mb-3">
                    <label class="form-label" for="avatar">Profile photo</label>
                    <input class="form-control" id="avatar" type="file" name="avatar" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp">
                </div>
                <button class="btn btn-accent" type="submit">Save profile</button>
            </form>
        </div>
        <div class="col-lg-5">
            <?php require APP_ROOT . '/includes/password_form.php'; ?>
        </div>
    </div>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
