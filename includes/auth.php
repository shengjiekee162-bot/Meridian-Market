<?php
declare(strict_types=1);

function current_user(bool $refresh = false): ?array
{
    static $loaded = false;
    static $cached = null;
    if ($refresh) {
        $loaded = false;
        $cached = null;
    }
    if ($loaded) {
        return $cached;
    }
    $loaded = true;
    $userId = $_SESSION['user_id'] ?? null;
    if (!is_int($userId) && !(is_string($userId) && ctype_digit($userId))) {
        $cached = null;
        return null;
    }
    $fingerprint = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'cli'));
    $known = $_SESSION['fingerprint'] ?? '';
    if (!is_string($known) || $known === '' || !hash_equals($known, $fingerprint)) {
        unset($_SESSION['user_id'], $_SESSION['fingerprint']);
        $cached = null;
        return null;
    }
    $stmt = db()->prepare(
        'SELECT id, name, email, phone, address, avatar, role, status, must_change_password, created_at
         FROM users WHERE id = ? LIMIT 1'
    );
    $stmt->execute([(int) $userId]);
    $user = $stmt->fetch();
    if (!$user || $user['status'] !== 'active') {
        unset($_SESSION['user_id'], $_SESSION['fingerprint']);
        if ($user && $user['status'] !== 'active') {
            flash('error', 'This account is unavailable.');
        }
        $cached = null;
        return null;
    }
    $cached = $user;
    return $cached;
}

function session_login(array $user, bool $remember): void
{
    $guestCart = guest_cart_bag();
    $couponCode = $_SESSION['coupon_code'] ?? null;
    session_regenerate_id(true);
    $_SESSION['user_id'] = (int) $user['id'];
    $_SESSION['fingerprint'] = hash('sha256', (string) ($_SERVER['HTTP_USER_AGENT'] ?? 'cli'));
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    unset($_SESSION['login_attempts'], $_SESSION['login_locked_until']);
    if (is_string($couponCode) && $couponCode !== '') {
        $_SESSION['coupon_code'] = $couponCode;
    }
    if ($guestCart !== []) {
        $_SESSION['guest_cart'] = $guestCart;
    }
    if ($remember && PHP_SAPI !== 'cli') {
        $params = session_get_cookie_params();
        setcookie(session_name(), session_id(), [
            'expires' => time() + 60 * 60 * 24 * 14,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => 'Lax',
        ]);
    }
    current_user(true);
    reset_locale_to_english();
    if (($user['role'] ?? '') === 'customer') {
        merge_guest_cart_into_user((int) $user['id']);
    } else {
        unset($_SESSION['guest_cart']);
    }
}

function logout_user(): void
{
    $_SESSION = [];
    if (session_status() === PHP_SESSION_ACTIVE) {
        session_destroy();
    }
    if (PHP_SAPI !== 'cli') {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', [
            'expires' => time() - 42000,
            'path' => $params['path'],
            'domain' => $params['domain'],
            'secure' => (bool) $params['secure'],
            'httponly' => (bool) $params['httponly'],
            'samesite' => 'Lax',
        ]);
        session_start();
        session_regenerate_id(true);
    }
}

function home_for_role(string $role): string
{
    return match ($role) {
        'admin' => 'admin/dashboard.php',
        'seller' => 'seller/dashboard.php',
        default => 'public/shop.php',
    };
}

function password_page_for(array $user): string
{
    return match ($user['role']) {
        'admin' => 'admin/account.php',
        'seller' => 'seller/profile.php',
        default => 'public/profile.php',
    };
}

function attempt_login(string $email, string $password, bool $remember): void
{
    $lockedUntil = (int) ($_SESSION['login_locked_until'] ?? 0);
    if ($lockedUntil > time()) {
        throw new AppException('Too many sign-in attempts. Try again in a few minutes.');
    }
    try {
        $email = validate_email($email);
    } catch (AppException) {
        throw new AppException('Email or password is incorrect.');
    }
    $stmt = db()->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    $dummy = '$2y$12$gY3n8GUl5eqfwAEYZyaTk.OUQHXtcrFxEMUdWUEa4pXhOypNmwDoC';
    $hash = $user['password'] ?? $dummy;
    $valid = password_verify($password, $hash);
    if (!$user || !$valid) {
        $attempts = (int) ($_SESSION['login_attempts'] ?? 0) + 1;
        $_SESSION['login_attempts'] = $attempts;
        if ($attempts >= 5) {
            $_SESSION['login_locked_until'] = time() + 600;
            $_SESSION['login_attempts'] = 0;
        }
        log_message('auth', 'Failed sign-in for ' . $email . ' from ' . client_ip());
        throw new AppException('Email or password is incorrect.');
    }
    if ($user['status'] !== 'active') {
        log_message('auth', 'Suspended sign-in attempt for user ' . (int) $user['id']);
        throw new AppException('This account is unavailable. Contact the marketplace if you need help.');
    }
    session_login($user, $remember);
    if (password_needs_rehash($user['password'], PASSWORD_DEFAULT)) {
        $update = db()->prepare('UPDATE users SET password = ? WHERE id = ?');
        $update->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
    }
}

function require_login(): array
{
    $user = current_user();
    if (!$user) {
        $request = (string) ($_SERVER['REQUEST_URI'] ?? '');
        if (APP_BASE_PATH !== '' && str_starts_with($request, APP_BASE_PATH)) {
            $request = substr($request, strlen(APP_BASE_PATH)) ?: '/';
        }
        if (
            preg_match('#^/public/(payment_[a-z]+|checkout|wishlist|cart|orders|order_detail|profile|notifications)\.php(\?|$)#', $request)
        ) {
            $_SESSION['after_login'] = $request;
        } elseif (
            ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'
            && preg_match('#^https?://[^/]+' . preg_quote(APP_BASE_PATH, '#') . '(/public/[^?#]+)#i', (string) ($_SERVER['HTTP_REFERER'] ?? ''), $matches)
        ) {
            $_SESSION['after_login'] = $matches[1];
        }
        flash('error', 'Please sign in to continue.');
        redirect('public/login.php');
    }
    return $user;
}

function require_role(string $role): array
{
    $user = require_login();
    if ($user['role'] !== $role) {
        http_response_code(403);
        $pageTitle = 'Access denied';
        require APP_ROOT . '/includes/header.php';
        echo '<main class="container py-5"><div class="empty-panel"><h1>You do not have access to that area.</h1><p>Your account can continue from its own dashboard.</p><a class="btn btn-accent" href="' . e(url(home_for_role($user['role']))) . '">Go to my dashboard</a></div></main>';
        require APP_ROOT . '/includes/footer.php';
        exit;
    }
    return $user;
}

function enforce_password_change(): void
{
    if (PHP_SAPI === 'cli') {
        return;
    }
    $user = current_user();
    if (!$user || (int) $user['must_change_password'] !== 1) {
        return;
    }
    $script = basename((string) ($_SERVER['SCRIPT_NAME'] ?? ''));
    if (in_array($script, ['profile.php', 'account.php', 'logout.php'], true)) {
        return;
    }
    flash('error', 'Change the default password before using the marketplace.');
    redirect(password_page_for($user));
}
