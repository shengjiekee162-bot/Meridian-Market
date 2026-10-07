<?php
declare(strict_types=1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Database unavailable · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="plain-page">
    <main class="plain-card">
        <p class="eyebrow">Setup</p>
        <h1>The database is not reachable.</h1>
        <p>On XAMPP: start MySQL, then check <code>config/db.php</code> or <code>.env</code>.</p>
        <p>On cPanel: create the MySQL database and user, import <code>database/ecommerce_marketplace.sql</code> into that database, then put the cPanel host / name / user / password in the server <code>.env</code> file (not the local XAMPP values).</p>
        <p>The details were written to the server log and are not shown here.</p>
    </main>
</body>
</html>
