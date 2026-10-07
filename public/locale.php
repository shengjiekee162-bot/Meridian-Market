<?php
declare(strict_types=1);

require __DIR__ . '/../includes/bootstrap.php';

$lang = normalize_locale((string) ($_GET['lang'] ?? $_POST['lang'] ?? default_locale()));
set_locale($lang);
redirect(safe_back_url(''));
