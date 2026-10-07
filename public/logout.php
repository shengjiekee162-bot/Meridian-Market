<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $pageTitle = 'Sign out';
    require APP_ROOT . '/includes/header.php';
    echo '<main id="content" class="container py-5"><div class="form-card auth-wrap"><h1>Sign out?</h1><form method="post">' . csrf_field() . '<button class="btn btn-accent" type="submit">Sign out</button></form></div></main>';
    require APP_ROOT . '/includes/footer.php';
    exit;
}

try {
    verify_csrf();
} catch (AppException $exception) {
    flash('error', $exception->getMessage());
    redirect('public/login.php');
}
logout_user();
flash('success', 'You have signed out.');
redirect('public/login.php');
