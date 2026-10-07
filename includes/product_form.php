<?php
declare(strict_types=1);

/** @var array|null $product */
/** @var array $categories */
/** @var array $images */
/** @var array $sellers */
/** @var bool $asAdmin */
$product = $product ?? null;
$images = $images ?? [];
$sellers = $sellers ?? [];
$asAdmin = $asAdmin ?? false;
?>
<div class="row g-4">
    <div class="col-lg-8">
        <section class="form-card">
            <div class="row g-3">
                <div class="col-md-8">
                    <label class="form-label" for="name">Product name</label>
                    <input class="form-control" id="name" name="name" required maxlength="160" value="<?= e(old('name', (string) ($product['name'] ?? ''))) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="sku">SKU</label>
                    <input class="form-control" id="sku" name="sku" required maxlength="40" value="<?= e(old('sku', (string) ($product['sku'] ?? ''))) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="category_id">Category</label>
                    <select class="form-select" id="category_id" name="category_id" required>
                        <option value="">Choose</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= e((string) $category['id']) ?>" <?= (string) old('category_id', (string) ($product['category_id'] ?? '')) === (string) $category['id'] ? 'selected' : '' ?>><?= e($category['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <?php if ($asAdmin): ?>
                    <div class="col-md-6">
                        <label class="form-label" for="seller_id">Seller</label>
                        <select class="form-select" id="seller_id" name="seller_id" required>
                            <option value="">Choose</option>
                            <?php foreach ($sellers as $seller): ?>
                                <option value="<?= e((string) $seller['id']) ?>" <?= (string) old('seller_id', (string) ($product['seller_id'] ?? '')) === (string) $seller['id'] ? 'selected' : '' ?>><?= e($seller['shop_name'] ?: $seller['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                <?php endif; ?>
                <div class="col-md-4">
                    <label class="form-label" for="price">Price (USD)</label>
                    <input class="form-control" id="price" name="price" required inputmode="decimal" value="<?= e(old('price', isset($product['price']) ? number_format((float) $product['price'], 2, '.', '') : '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="compare_price">Compare price</label>
                    <input class="form-control" id="compare_price" name="compare_price" inputmode="decimal" value="<?= e(old('compare_price', isset($product['compare_price']) && $product['compare_price'] !== null ? number_format((float) $product['compare_price'], 2, '.', '') : '')) ?>">
                </div>
                <div class="col-md-4">
                    <label class="form-label" for="stock">Stock</label>
                    <input class="form-control" id="stock" name="stock" type="number" min="0" max="100000" required value="<?= e(old('stock', (string) ($product['stock'] ?? '0'))) ?>">
                </div>
                <div class="col-md-6">
                    <label class="form-label" for="status">Visibility</label>
                    <select class="form-select" id="status" name="status">
                        <?php $status = old('status', (string) ($product['status'] ?? 'active')); ?>
                        <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                        <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                <?php if ($asAdmin): ?>
                    <div class="col-md-6 d-flex align-items-end">
                        <?php
                        $featuredOn = (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST')
                            ? isset($_POST['featured'])
                            : ((int) ($product['featured'] ?? 0) === 1);
                        ?>
                        <div class="form-check mb-2">
                            <input class="form-check-input" type="checkbox" id="featured" name="featured" value="1" <?= $featuredOn ? 'checked' : '' ?>>
                            <label class="form-check-label" for="featured">Feature on the home page</label>
                        </div>
                    </div>
                <?php endif; ?>
                <div class="col-12">
                    <label class="form-label" for="description">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="6" required maxlength="5000"><?= e(old('description', (string) ($product['description'] ?? ''))) ?></textarea>
                </div>
            </div>
        </section>
    </div>
    <div class="col-lg-4">
        <section class="form-card">
            <h2>Images</h2>
            <p class="muted">JPG, PNG, or WEBP. At least 200×200, up to 2 MB, maximum five images.</p>
            <?php foreach ($images as $image): ?>
                <label class="image-check">
                    <img src="<?= e(media_url($image['image_path'])) ?>" alt="">
                    <span><input type="checkbox" name="remove_images[]" value="<?= e((string) $image['id']) ?>"> Remove</span>
                </label>
            <?php endforeach; ?>
            <label class="form-label" for="images">Upload images</label>
            <input class="form-control" id="images" type="file" name="images[]" accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp" multiple>
            <button class="btn btn-accent w-100 mt-3" type="submit"><?= e($product ? 'Save product' : 'Create product') ?></button>
        </section>
    </div>
</div>
