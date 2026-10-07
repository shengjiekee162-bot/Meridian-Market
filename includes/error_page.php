<?php
declare(strict_types=1);

http_response_code(500);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Something went wrong · <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(asset('css/style.css')) ?>">
</head>
<body class="plain-page">
    <main class="plain-card">
        <p class="eyebrow">Meridian Market</p>
        <h1>We hit a snag.</h1>
        <p><?= e($safeMessage ?? 'Please try again in a moment.') ?></p>
        <a class="btn btn-accent" href="<?= e(url('')) ?>">Back to the market</a>
    </main>
</body>
</html>
