<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

if (current_user()) {
    redirect(home_for_role(current_user()['role']));
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        attempt_login(input_string('email', 120), (string) ($_POST['password'] ?? ''), isset($_POST['remember']));
        $user = current_user(true);
        $next = $_SESSION['after_login'] ?? '';
        unset($_SESSION['after_login']);
        flash('success', t('auth.welcome_back'));
        if (
            ($user['role'] ?? '') === 'customer'
            && is_string($next)
            && preg_match('#^/public/(payment_[a-z]+|checkout|wishlist|cart|orders|order_detail|profile|notifications|product|shop)\.php(\?|$)#', $next)
        ) {
            redirect($next);
        }
        redirect(home_for_role((string) ($user['role'] ?? 'customer')));
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
        set_old($_POST);
    }
}

$demoAccounts = [
    'admin' => [
        'email' => 'avery.quinn@meridianmarket.test',
        'password' => 'ChangeMe!Meridian2026',
    ],
    'user' => [
        'email' => 'elena.voss@example.com',
        'password' => 'Harbor#Lane88',
    ],
];

$pageTitle = t('auth.sign_in');
require APP_ROOT . '/includes/header.php';
?>
<main id="content" class="container">
    <div class="auth-wrap form-card">
        <p class="eyebrow"><?= te('auth.account') ?></p>
        <h1><?= te('auth.sign_in') ?></h1>
        <form method="post" id="loginForm">
            <?= csrf_field() ?>
            <div class="mb-3">
                <label class="form-label" for="email"><?= te('auth.email') ?></label>
                <input class="form-control" id="email" name="email" type="email" required maxlength="120" value="<?= e(old('email')) ?>" autocomplete="username">
            </div>
            <div class="mb-3">
                <label class="form-label" for="password"><?= te('auth.password') ?></label>
                <input class="form-control" id="password" name="password" type="password" required maxlength="72" autocomplete="current-password">
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" id="remember" name="remember" value="1">
                <label class="form-check-label" for="remember"><?= te('auth.remember') ?></label>
            </div>
            <button class="btn btn-accent w-100" type="submit"><?= te('auth.sign_in') ?></button>
        </form>

        <div class="demo-fill mt-3">
            <p class="muted small mb-2"><?= te('auth.demo_fill') ?></p>
            <div class="d-flex gap-2">
                <button class="btn btn-quiet btn-sm flex-fill" type="button"
                    data-fill-email="<?= e($demoAccounts['admin']['email']) ?>"
                    data-fill-password="<?= e($demoAccounts['admin']['password']) ?>">
                    <?= te('auth.demo_admin') ?>
                </button>
                <button class="btn btn-quiet btn-sm flex-fill" type="button"
                    data-fill-email="<?= e($demoAccounts['user']['email']) ?>"
                    data-fill-password="<?= e($demoAccounts['user']['password']) ?>">
                    <?= te('auth.demo_user') ?>
                </button>
            </div>
        </div>

        <p class="mt-3 mb-1"><a href="<?= e(url('public/forgot_password.php')) ?>"><?= te('auth.forgot') ?></a></p>
        <p class="mb-0"><?= te('auth.new_here') ?> <a href="<?= e(url('public/register.php')) ?>"><?= te('auth.create_customer') ?></a></p>
    </div>
</main>
<script src="<?= e(asset('js/login.js')) ?>"></script>
<?php require APP_ROOT . '/includes/footer.php'; ?>
