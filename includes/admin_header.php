<?php
declare(strict_types=1);

$pageTitle = $pageTitle ?? 'Admin';
$panelUser = current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?> Admin</title>
    <link rel="icon" href="<?= e(asset('images/mark.svg')) ?>" type="image/svg+xml">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Fraunces:opsz,wght@9..144,560;9..144,680&family=Outfit:wght@380;480;580;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css" rel="stylesheet">
    <link href="<?= e(asset('css/style.css')) ?>" rel="stylesheet">
    <link href="<?= e(asset('css/admin.css')) ?>" rel="stylesheet">
</head>
<body class="panel-body">
<div class="panel-shell">
    <div class="d-none d-lg-block"><?php require APP_ROOT . '/includes/admin_navbar.php'; ?></div>
    <div class="panel-main">
        <header class="panel-top">
            <button class="btn btn-quiet btn-sm d-lg-none" type="button" data-bs-toggle="offcanvas" data-bs-target="#adminDrawer" aria-controls="adminDrawer">Menu</button>
            <div>
                <p class="eyebrow mb-0">Administration</p>
                <h1><?= e($pageTitle) ?></h1>
            </div>
            <span class="who"><?= e($panelUser['name'] ?? '') ?></span>
        </header>
        <div class="offcanvas offcanvas-start panel-drawer" tabindex="-1" id="adminDrawer">
            <div class="offcanvas-body">
                <?php require APP_ROOT . '/includes/admin_navbar.php'; ?>
            </div>
        </div>
        <div class="toast-stack" aria-live="polite">
            <?php foreach (get_flashes() as $flash): ?>
                <div class="toast-card toast-<?= e($flash['type']) ?>"><?= e($flash['message']) ?></div>
            <?php endforeach; ?>
        </div>
        <div class="panel-content">
