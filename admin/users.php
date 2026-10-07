<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$actor = require_role('admin');
$q = query_string('q', 80);
$role = query_string('role', 20);
$page = max(1, request_int('page', 'get'));
$where = ['1=1'];
$params = [];
if ($q !== '') {
    $where[] = "(name LIKE ? ESCAPE '\\\\' OR email LIKE ? ESCAPE '\\\\')";
    $like = like_pattern($q);
    $params[] = $like;
    $params[] = $like;
}
if (in_array($role, ['customer', 'seller', 'admin'], true)) {
    $where[] = 'role = ?';
    $params[] = $role;
}
$sqlWhere = implode(' AND ', $where);
$count = db()->prepare("SELECT COUNT(*) FROM users WHERE $sqlWhere");
$count->execute($params);
$total = (int) $count->fetchColumn();
$perPage = 12;
$pages = max(1, (int) ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$stmt = db()->prepare("SELECT id, name, email, role, status, created_at FROM users WHERE $sqlWhere ORDER BY id DESC LIMIT $perPage OFFSET $offset");
$stmt->execute($params);
$users = $stmt->fetchAll();
$pageTitle = 'Customers';
$activeMenu = 'users';
require APP_ROOT . '/includes/admin_header.php';
?>
<form class="d-flex gap-2 mb-3" method="get">
    <input class="form-control" name="q" value="<?= e($q) ?>" placeholder="Name or email">
    <select class="form-select" style="max-width:180px" name="role">
        <option value="">All roles</option>
        <?php foreach (['customer', 'seller', 'admin'] as $option): ?>
            <option value="<?= e($option) ?>" <?= $role === $option ? 'selected' : '' ?>><?= e($option) ?></option>
        <?php endforeach; ?>
    </select>
    <button class="btn btn-quiet" type="submit">Search</button>
</form>
<div class="table-responsive form-card">
    <table class="table align-middle">
        <thead><tr><th>Name</th><th>Email</th><th>Role</th><th>Status</th><th>Joined</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($users as $person): ?>
            <tr>
                <td><?= e($person['name']) ?></td>
                <td><?= e($person['email']) ?></td>
                <td><?= e($person['role']) ?></td>
                <td><span class="badge-status badge-<?= e($person['status']) ?>"><?= e($person['status']) ?></span></td>
                <td><?= e(date('M j, Y', strtotime((string) $person['created_at']))) ?></td>
                <td><a href="<?= e(url('admin/user_view.php?id=' . (int) $person['id'])) ?>">View</a></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?= render_pagination($page, $pages, 'admin/users.php') ?>
<?php require APP_ROOT . '/includes/admin_footer.php'; ?>
