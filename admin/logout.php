<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require_login();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $pageTitle = 'Sign out';
    $activeMenu = '';
    require APP_ROOT . '/includes/admin_header.php';
    echo '<form method="post" class="form-card" style="max-width:420px">' . csrf_field() . '<h2>Sign out of the admin desk?</h2><button class="btn btn-accent" type="submit">Sign out</button></form>';
    require APP_ROOT . '/includes/admin_footer.php';
    exit;
}

try {
    verify_csrf();
} catch (AppException $exception) {
    flash('error', $exception->getMessage());
    redirect('admin/dashboard.php');
}
logout_user();
flash('success', 'You have signed out.');
redirect('public/login.php');
