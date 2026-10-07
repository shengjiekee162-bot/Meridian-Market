<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? APP_NAME;
$bodyClass = $bodyClass ?? '';
$currentUser = current_user();
$cartCount = active_cart_count();
$wishCount = $currentUser ? wishlist_count((int) $currentUser['id']) : 0;
$notes = $currentUser ? unread_notifications((int) $currentUser['id']) : [];
$noteCount = $currentUser ? unread_count((int) $currentUser['id']) : 0;
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale() === 'zh' ? 'zh-Hans' : 'en') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <meta name="description" content="<?= e((string) config('app', 'tagline')) ?>">
    <meta name="csrf-token" content="<?= e(csrf_token()) ?>">
    <meta name="app-base" content="<?= e(APP_BASE_PATH) ?>">
    <meta name="app-locale" content="<?= e(current_locale()) ?>">
    <link rel="icon" href="<?= e(asset('images/mark.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Libre+Franklin:wght@500;600;700;800&family=Nunito+Sans:opsz,wght@6..12,420;6..12,600;6..12,700;6..12,800&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/style.css')) ?>" rel="stylesheet">
</head>
<body class="store-body <?= e($bodyClass) ?>">
<a class="skip-link" href="#content"><?= te('common.skip') ?></a>
<?php require APP_ROOT . '/includes/navbar.php'; ?>
<div class="toast-stack" aria-live="polite">
    <?php foreach (get_flashes() as $flash): ?>
        <div class="toast-card toast-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
    <?php endforeach; ?>
</div>
