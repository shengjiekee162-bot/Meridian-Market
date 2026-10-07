<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$token = query_string('token', 80);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $token = input_string('token', 80);
}

$valid = false;
$row = null;
if (preg_match('/^[a-f0-9]{64}$/', $token)) {
    $hash = hash('sha256', $token);
    $stmt = db()->prepare(
        'SELECT * FROM password_resets WHERE token_hash = ? AND used_at IS NULL AND expires_at > NOW() LIMIT 1'
    );
    $stmt->execute([$hash]);
    $row = $stmt->fetch() ?: null;
    if ($row && hash_equals((string) $row['token_hash'], $hash)) {
        $valid = true;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        if (!$valid || !$row) {
            throw new AppException('This reset link is invalid or has expired.');
        }
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');
        validate_password($password);
        if (!hash_equals($password, $confirm)) {
            throw new AppException('Password confirmation does not match.');
        }
        db()->prepare('UPDATE users SET password = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $row['user_id']]);
        db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE id = ?')->execute([(int) $row['id']]);
        flash('success', 'Password updated. Sign in with the new password.');
        redirect('public/login.php');
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
    }
}

$pageTitle = 'Choose a new password';
require APP_ROOT . '/includes/header.php';
?>
<main id="content" class="container">
    <div class="auth-wrap form-card">
        <h1>Choose a new password</h1>
        <?php if (!$valid): ?>
            <p>This reset link is invalid or has expired.</p>
            <a href="<?= e(url('public/forgot_password.php')) ?>">Request a new link</a>
        <?php else: ?>
            <form method="post">
                <?= csrf_field() ?>
                <input type="hidden" name="token" value="<?= e($token) ?>">
                <div class="mb-3">
                    <label class="form-label" for="password">New password</label>
                    <input class="form-control" id="password" name="password" type="password" required minlength="8" maxlength="72" pattern="(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,72}">
                </div>
                <div class="mb-3">
                    <label class="form-label" for="password_confirmation">Confirm password</label>
                    <input class="form-control" id="password_confirmation" name="password_confirmation" type="password" required maxlength="72">
                </div>
                <button class="btn btn-accent" type="submit">Update password</button>
            </form>
        <?php endif; ?>
    </div>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
