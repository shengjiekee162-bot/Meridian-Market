<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';
$user = require_login();
$number = query_string('order', 24);

try {
    if (!preg_match('/^ORD-\d{8}-\d{6}$/', $number)) {
        throw new AppException('The PayPal handoff is missing an order number.');
    }
    $handoff = build_paypal_handoff((int) $user['id'], $number);
} catch (AppException $exception) {
    flash('error', $exception->getMessage());
    redirect('public/order_detail.php?order=' . rawurlencode($number));
}

$pageTitle = 'Redirecting to PayPal';
$isRedirect = ($handoff['type'] ?? '') === 'redirect';
?>
<!DOCTYPE html>
<html lang="<?= e(current_locale() === 'zh' ? 'zh-Hans' : 'en') ?>">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
    <link href="<?= e(asset('css/style.css')) ?>" rel="stylesheet">
    <?php if ($isRedirect): ?>
        <meta http-equiv="refresh" content="0;url=<?= e((string) $handoff['url']) ?>">
    <?php endif; ?>
</head>
<body class="store-body">
<main class="container py-5">
    <div class="form-card auth-wrap text-center">
        <p class="eyebrow">PayPal</p>
        <h1>Opening PayPal…</h1>
        <p class="muted">Like online banking, you will leave this site and sign in on PayPal’s website to pay for <strong><?= e($number) ?></strong>.</p>
        <?php if ($isRedirect): ?>
            <p><a class="btn btn-accent" data-paypal-continue href="<?= e((string) $handoff['url']) ?>">Continue to PayPal</a></p>
        <?php else: ?>
            <form id="paypalHandoff" method="post" action="<?= e((string) ($handoff['action'] ?? '')) ?>">
                <?php foreach (($handoff['fields'] ?? []) as $name => $value): ?>
                    <input type="hidden" name="<?= e((string) $name) ?>" value="<?= e((string) $value) ?>">
                <?php endforeach; ?>
                <button class="btn btn-accent" type="submit">Continue to PayPal</button>
            </form>
        <?php endif; ?>
    </div>
</main>
<script src="<?= e(asset('js/paypal_go.js')) ?>"></script>
</body>
</html>
