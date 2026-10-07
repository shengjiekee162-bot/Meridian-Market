<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
require_role('admin');
$editId = request_int('id', 'get');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        verify_csrf();
        $action = input_string('action', 20);
        if ($action === 'deactivate') {
            db()->prepare("UPDATE coupons SET status = 'inactive', updated_at = NOW() WHERE id = ?")->execute([request_int('coupon_id')]);
            flash('success', 'Coupon deactivated.');
        } elseif ($action === 'activate') {
            db()->prepare("UPDATE coupons SET status = 'active', updated_at = NOW() WHERE id = ?")->execute([request_int('coupon_id')]);
            flash('success', 'Coupon activated.');
        } else {
            $code = strtoupper(clean_text(input_string('code', 20), 20, 'Code'));
            if (!preg_match('/^[A-Z0-9]{4,20}$/', $code)) {
                throw new AppException('Coupon codes use 4 to 20 letters or numbers.');
            }
            $type = input_string('discount_type', 20) === 'fixed' ? 'fixed' : 'percent';
            $valueRaw = input_string('discount_value', 12);
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $valueRaw)) {
                throw new AppException('Enter a discount value.');
            }
            $valueCents = to_cents($valueRaw);
            if ($type === 'percent' && ($valueCents < 100 || $valueCents > 9000)) {
                throw new AppException('Percentage discounts must be between 1 and 90.');
            }
            if ($type === 'fixed' && $valueCents <= 0) {
                throw new AppException('Enter a fixed discount greater than zero.');
            }
            $minimumRaw = input_string('minimum_amount', 12);
            if ($minimumRaw === '') {
                $minimumRaw = '0';
            }
            if (!preg_match('/^\d+(\.\d{1,2})?$/', $minimumRaw)) {
                throw new AppException('Enter a valid minimum amount.');
            }
            $maxRaw = input_string('maximum_discount', 12);
            $maximum = null;
            if ($maxRaw !== '') {
                if (!preg_match('/^\d+(\.\d{1,2})?$/', $maxRaw)) {
                    throw new AppException('Enter a valid maximum discount.');
                }
                $maximum = number_format((float) $maxRaw, 2, '.', '');
            }
            $limitRaw = input_string('usage_limit', 8);
            $limit = null;
            if ($limitRaw !== '') {
                if (!preg_match('/^\d+$/', $limitRaw) || (int) $limitRaw < 1) {
                    throw new AppException('Usage limit must be a positive number.');
                }
                $limit = (int) $limitRaw;
            }
            $start = input_string('start_date', 10);
            $expiry = input_string('expiry_date', 10);
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $start) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $expiry) || $expiry < $start) {
                throw new AppException('Enter a valid start and expiry date.');
            }
            $status = input_string('status', 20) === 'inactive' ? 'inactive' : 'active';
            $id = request_int('coupon_id');
            $dup = db()->prepare('SELECT id FROM coupons WHERE code = ? AND (? = 0 OR id <> ?) LIMIT 1');
            $dup->execute([$code, $id, $id]);
            if ($dup->fetchColumn()) {
                throw new AppException('That coupon code already exists.');
            }
            $value = number_format((float) $valueRaw, 2, '.', '');
            $minimum = number_format((float) $minimumRaw, 2, '.', '');
            if ($id > 0) {
                db()->prepare(
                    'UPDATE coupons SET code=?, discount_type=?, discount_value=?, minimum_amount=?, maximum_discount=?, usage_limit=?, start_date=?, expiry_date=?, status=?, updated_at=NOW() WHERE id=?'
                )->execute([$code, $type, $value, $minimum, $maximum, $limit, $start, $expiry, $status, $id]);
                flash('success', 'Coupon updated.');
            } else {
                db()->prepare(
                    'INSERT INTO coupons (code, discount_type, discount_value, minimum_amount, maximum_discount, usage_limit, used_count, start_date, expiry_date, status, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?, 0, ?, ?, ?, NOW(), NOW())'
                )->execute([$code, $type, $value, $minimum, $maximum, $limit, $start, $expiry, $status]);
                flash('success', 'Coupon created.');
            }
        }
        redirect('admin/coupons.php');
    } catch (AppException $exception) {
        flash('error', $exception->getMessage());
        set_old($_POST);
    }
}

$coupons = db()->query('SELECT * FROM coupons ORDER BY id DESC')->fetchAll();
$editing = null;
foreach ($coupons as $coupon) {
    if ((int) $coupon['id'] === $editId) {
        $editing = $coupon;
    }
}
$pageTitle = 'Coupons';
$activeMenu = 'coupons';
require APP_ROOT . '/includes/admin_header.php';
?>
<div class="table-responsive form-card mb-4">
    <table class="table align-middle">
        <thead><tr><th>Code</th><th>Type</th><th>Value</th><th>Used</th><th>Limit</th><th>Expiry</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php foreach ($coupons as $coupon): ?>
            <tr>
                <td><?= e($coupon['code']) ?></td>
                <td><?= e($coupon['discount_type']) ?></td>
                <td><?= e($coupon['discount_type'] === 'percent' ? rtrim(rtrim((string) $coupon['discount_value'], '0'), '.') . '%' : money($coupon['discount_value'])) ?></td>
                <td><?= e((string) $coupon['used_count']) ?></td>
                <td><?= e($coupon['usage_limit'] === null ? 'None' : (string) $coupon['usage_limit']) ?></td>
                <td><?= e($coupon['expiry_date']) ?></td>
                <td><span class="badge-status badge-<?= e($coupon['status']) ?>"><?= e($coupon['status']) ?></span></td>
                <td class="text-nowrap">
                    <a href="<?= e(url('admin/coupons.php?id=' . (int) $coupon['id'])) ?>">Edit</a>
                    <form method="post" class="d-inline"><?= csrf_field() ?>
                        <input type="hidden" name="coupon_id" value="<?= e((string) $coupon['id']) ?>">
                        <input type="hidden" name="action" value="<?= $coupon['status'] === 'active' ? 'deactivate' : 'activate' ?>">
                        <button class="btn btn-link btn-sm" type="submit"><?= $coupon['status'] === 'active' ? 'Deactivate' : 'Activate' ?></button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</div>
<form method="post" class="form-card">
    <?= csrf_field() ?>
    <input type="hidden" name="coupon_id" value="<?= e((string) ($editing['id'] ?? 0)) ?>">
    <h2><?= $editing ? 'Edit coupon' : 'Create coupon' ?></h2>
    <div class="row g-3">
        <div class="col-md-3"><label class="form-label" for="code">Code</label><input class="form-control" id="code" name="code" required maxlength="20" value="<?= e(old('code', (string) ($editing['code'] ?? ''))) ?>"></div>
        <div class="col-md-3"><label class="form-label" for="discount_type">Type</label>
            <?php $type = old('discount_type', (string) ($editing['discount_type'] ?? 'percent')); ?>
            <select class="form-select" id="discount_type" name="discount_type"><option value="percent" <?= $type === 'percent' ? 'selected' : '' ?>>Percentage</option><option value="fixed" <?= $type === 'fixed' ? 'selected' : '' ?>>Fixed amount</option></select>
        </div>
        <div class="col-md-2"><label class="form-label" for="discount_value">Value</label><input class="form-control" id="discount_value" name="discount_value" required value="<?= e(old('discount_value', isset($editing['discount_value']) ? number_format((float) $editing['discount_value'], 2, '.', '') : '')) ?>"></div>
        <div class="col-md-2"><label class="form-label" for="minimum_amount">Minimum</label><input class="form-control" id="minimum_amount" name="minimum_amount" value="<?= e(old('minimum_amount', isset($editing['minimum_amount']) ? number_format((float) $editing['minimum_amount'], 2, '.', '') : '0.00')) ?>"></div>
        <div class="col-md-2"><label class="form-label" for="maximum_discount">Max discount</label><input class="form-control" id="maximum_discount" name="maximum_discount" value="<?= e(old('maximum_discount', isset($editing['maximum_discount']) && $editing['maximum_discount'] !== null ? number_format((float) $editing['maximum_discount'], 2, '.', '') : '')) ?>"></div>
        <div class="col-md-2"><label class="form-label" for="usage_limit">Usage limit</label><input class="form-control" id="usage_limit" name="usage_limit" value="<?= e(old('usage_limit', (string) ($editing['usage_limit'] ?? ''))) ?>"></div>
        <div class="col-md-3"><label class="form-label" for="start_date">Starts</label><input class="form-control" id="start_date" type="date" name="start_date" required value="<?= e(old('start_date', (string) ($editing['start_date'] ?? date('Y-m-d')))) ?>"></div>
        <div class="col-md-3"><label class="form-label" for="expiry_date">Expires</label><input class="form-control" id="expiry_date" type="date" name="expiry_date" required value="<?= e(old('expiry_date', (string) ($editing['expiry_date'] ?? date('Y-12-31')))) ?>"></div>
        <div class="col-md-2"><label class="form-label" for="status">Status</label>
            <?php $status = old('status', (string) ($editing['status'] ?? 'active')); ?>
            <select class="form-select" id="status" name="status"><option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option></select>
        </div>
    </div>
    <button class="btn btn-accent mt-3" type="submit"><?= $editing ? 'Save coupon' : 'Create coupon' ?></button>
</form>
<?php require APP_ROOT . '/includes/admin_footer.php'; ?>
