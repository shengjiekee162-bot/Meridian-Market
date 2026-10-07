<?php
declare(strict_types=1);

require __DIR__ . '/includes/bootstrap.php';
http_response_code(404);
$pageTitle = 'Page not found';
require APP_ROOT . '/includes/header.php';
?>
<main id="content" class="container py-5">
    <?php render_empty('fa-compass', 'That page is not on the map.', 'The link may be out of date. The shop is still open.', url('public/shop.php'), 'Browse the shop'); ?>
</main>
<?php require APP_ROOT . '/includes/footer.php'; ?>
