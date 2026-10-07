<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
$editId = request_int('id', 'get');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = input_string('action', 20);
        if ($action === 'delete') {
            $id = request_int('category_id');
            $used = db()->prepare('SELECT COUNT(*) FROM products WHERE category_id = ?');
            $used->execute([$id]);
            if ((int) $used->fetchColumn() > 0) {
                throw new AppException('Move or archive the products in this category before deleting it.');
            }
            $row = db()->prepare('SELECT image FROM categories WHERE id = ?');
            $row->execute([$id]);
            $image = $row->fetchColumn();
            db()->prepare('DELETE FROM categories WHERE id = ?')->execute([$id]);
            delete_managed_upload(is_string($image) ? $image : null);
            flash('success', 'Category deleted.');
        } else {
            $name = clean_text(input_string('name', 60), 60, 'Category name');
            $description = clean_text(input_string('description', 255), 255, 'Description');
            $status = input_string('status', 20) === 'inactive' ? 'inactive' : 'active';
            if (mb_strlen($name) < 2) {
                throw new AppException('Enter a category name.');
            }
            $slug = slugify($name);
            $id = request_int('category_id');
            $uploads = normalize_uploads($_FILES['image'] ?? null);
            $imagePath = null;
            if ($uploads) {
                $imagePath = store_uploaded_image($uploads[0], 'categories', 100);
            }
            if ($id > 0) {
                $current = db()->prepare('SELECT id, image FROM categories WHERE id = ?');
                $current->execute([$id]);
                $existing = $current->fetch();
                if (!$existing) {
                    throw new AppException('That category was not found.');
                }
                $existingImage = $existing['image'];
                $dup = db()->prepare('SELECT id FROM categories WHERE slug = ? AND id <> ?');
                $dup->execute([$slug, $id]);
                if ($dup->fetchColumn()) {
                    $slug .= '-' . $id;
                }
                db()->prepare('UPDATE categories SET name = ?, slug = ?, description = ?, status = ?, image = ?, updated_at = NOW() WHERE id = ?')
                    ->execute([$name, $slug, $description, $status, $imagePath ?: $existingImage, $id]);
                if ($imagePath && is_string($existingImage)) {
                    delete_managed_upload($existingImage);
                }
                flash('success', 'Category updated.');
            } else {
                $dup = db()->prepare('SELECT id FROM categories WHERE slug = ?');
                $dup->execute([$slug]);
                if ($dup->fetchColumn()) {
                    $slug .= '-' . time();
                }
                db()->prepare('INSERT INTO categories (name, slug, description, image, status, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())')
                    ->execute([$name, $slug, $description, $imagePath, $status]);
                flash('success', 'Category created.');
            }
        }
        redirect('admin/categories.php');
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
        set_old($_POST);
    } catch (Throwable $exception) {
        log_message('category', $exception->getMessage());
        flash('error', 'The category could not be saved.');
    }
}

$categories = db()->query(
    "SELECT c.*, (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
     FROM categories c ORDER BY c.name"
)->fetchAll();
$editing = null;
foreach ($categories as $category) {
    if ((int) $category['id'] === $editId) {
        $editing = $category;
    }
}
$pageTitle = 'Categories';
$activeMenu = 'categories';
require APP_ROOT . '/includes/admin_header.php';
?>
<div class="row g-4">
    <div class="col-lg-7">
        <div class="table-responsive form-card">
            <table class="table align-middle">
                <thead><tr><th>Category</th><th>Products</th><th>Status</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($categories as $category): ?>
                    <tr>
                        <td><?= e($category['name']) ?><div class="muted"><?= e($category['description']) ?></div></td>
                        <td><?= e((string) $category['product_count']) ?></td>
                        <td><?= e($category['status']) ?></td>
                        <td class="text-nowrap">
                            <a href="<?= e(url('admin/categories.php?id=' . (int) $category['id'])) ?>">Edit</a>
                            <form method="post" class="d-inline" data-confirm="Delete this category?"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="category_id" value="<?= e((string) $category['id']) ?>"><button class="btn btn-link btn-sm" type="submit">Delete</button></form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
    <div class="col-lg-5">
        <form method="post" enctype="multipart/form-data" class="form-card">
            <?= csrf_field() ?>
            <input type="hidden" name="category_id" value="<?= e((string) ($editing['id'] ?? 0)) ?>">
            <h2><?= $editing ? 'Edit category' : 'Add category' ?></h2>
            <div class="mb-2"><label class="form-label" for="name">Name</label><input class="form-control" id="name" name="name" required maxlength="60" value="<?= e(old('name', (string) ($editing['name'] ?? ''))) ?>"></div>
            <div class="mb-2"><label class="form-label" for="description">Description</label><textarea class="form-control" id="description" name="description" maxlength="255"><?= e(old('description', (string) ($editing['description'] ?? ''))) ?></textarea></div>
            <div class="mb-2"><label class="form-label" for="status">Status</label>
                <select class="form-select" id="status" name="status">
                    <?php $selected = old('status', (string) ($editing['status'] ?? 'active')); ?>
                    <option value="active" <?= $selected === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $selected === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="mb-3"><label class="form-label" for="image">Image</label><input class="form-control" id="image" type="file" name="image" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"></div>
            <button class="btn btn-accent" type="submit"><?= $editing ? 'Save category' : 'Add category' ?></button>
        </form>
    </div>
</div>
<?php require APP_ROOT . '/includes/admin_footer.php'; ?>
