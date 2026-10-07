<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Install Meridian Market</title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="plain-page">
    <main class="plain-card">
        <p class="eyebrow">Installation</p>
        <h1>Import the marketplace database to continue.</h1>
        <ol>
            <li>Start Apache and MySQL in XAMPP.</li>
            <li>Open phpMyAdmin and import <code>database/ecommerce_marketplace.sql</code>.</li>
            <li>Leave the XAMPP defaults in <code>config/db.php</code>, or copy <code>.env.example</code> to <code>.env</code> and edit it.</li>
            <li>Refresh this page.</li>
        </ol>
        <p>The SQL file creates the <code>meridian_market</code> database, tables, and sample catalog.</p>
    </main>
</body>
</html>
