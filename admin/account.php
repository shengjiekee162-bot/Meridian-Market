<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_role('admin');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $current = (string) ($_POST['current_password'] ?? '');
        $password = (string) ($_POST['password'] ?? '');
        $confirm = (string) ($_POST['password_confirmation'] ?? '');
        $stmt = db()->prepare('SELECT password FROM users WHERE id = ?');
        $stmt->execute([(int) $user['id']]);
        if (!password_verify($current, (string) $stmt->fetchColumn())) {
            throw new AppException('The current password is incorrect.');
        }
        validate_password($password);
        if (!hash_equals($password, $confirm)) {
            throw new AppException('Password confirmation does not match.');
        }
        db()->prepare('UPDATE users SET password = ?, must_change_password = 0, updated_at = NOW() WHERE id = ?')
            ->execute([password_hash($password, PASSWORD_DEFAULT), (int) $user['id']]);
        current_user(true);
        flash('success', 'Administrator password updated.');
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
    }
    redirect('admin/account.php');
}

$pageTitle = 'Security';
$activeMenu = 'account';
require APP_ROOT . '/includes/admin_header.php';
?>
<div class="row"><div class="col-lg-6"><?php require APP_ROOT . '/includes/password_form.php'; ?></div></div>
<?php require APP_ROOT . '/includes/admin_footer.php'; ?>
