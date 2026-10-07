<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        db()->prepare('UPDATE notifications SET is_read = 1 WHERE user_id = ?')->execute([(int) $user['id']]);
        flash('success', 'Notifications marked read.');
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
    }
}
redirect(safe_back_url(''));
