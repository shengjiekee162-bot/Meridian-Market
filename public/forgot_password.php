<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $email = validate_email(input_string('email', 120));
        $stmt = db()->prepare('SELECT id, name, email FROM users WHERE email = ? AND status = \'active\' LIMIT 1');
        $stmt->execute([$email]);
        $user = $stmt->fetch();
        if ($user) {
            $token = bin2hex(random_bytes(32));
            $hash = hash('sha256', $token);
            db()->prepare('UPDATE password_resets SET used_at = NOW() WHERE user_id = ? AND used_at IS NULL')->execute([(int) $user['id']]);
            db()->prepare(
                'INSERT INTO password_resets (user_id, email, token_hash, expires_at, created_at)
                 VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 1 HOUR), NOW())'
            )->execute([(int) $user['id'], $user['email'], $hash]);
            $link = APP_URL . '/public/reset_password.php?token=' . rawurlencode($token);
            send_reset_email($user['email'], $user['name'], $link);
        }
        flash('success', 'If an account exists for that email, password reset instructions are on the way.');
        redirect('public/login.php');
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
        set_old($_POST);
    }
}

$pageTitle = 'Forgot password';
require APP_ROOT . '/includes/header.php';
?>
<main id="content" class="container">
    <div class="auth-wrap form-card">
        <h1>Reset a password</h1>
        <p class="muted">We will send a one-hour link if the email belongs to an active account. When mail is not configured locally, the link is written to the development log.</p>
        <form method="post">
            <?= csrf_field() ?>
            <label class="form-label" for="email">Email</label>
            <input class="form-control mb-3" id="email" name="email" type="email" required maxlength="120" value="<?= e(old('email')) ?>">
            <button class="btn btn-accent" type="submit">Send reset link</button>
        </form>
    </div>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
