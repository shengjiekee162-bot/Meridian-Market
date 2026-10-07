<?php
declare(strict_types=1);

define('APP_ROOT', dirname(__DIR__));

require_once APP_ROOT . '/config/env.php';
load_env_file(APP_ROOT . '/.env');

$appConfig = require APP_ROOT . '/config/app.php';
$dbConfig = require APP_ROOT . '/config/db.php';
$paypalConfig = require APP_ROOT . '/config/paypal.php';
$mailConfig = require APP_ROOT . '/config/mail.php';

$GLOBALS['mm_config'] = [
    'app' => $appConfig,
    'db' => $dbConfig,
    'paypal' => $paypalConfig,
    'mail' => $mailConfig,
];

date_default_timezone_set((string) ($appConfig['timezone'] ?? 'UTC'));

define('APP_NAME', (string) $appConfig['name']);
define('APP_ENV', (string) (env('APP_ENV', 'development') ?? 'development'));

function detect_base_path(): string
{
    $configured = env('APP_BASE_PATH');
    if ($configured !== null && $configured !== '') {
        $configured = '/' . trim(str_replace('\\', '/', $configured), '/');
        return $configured === '/' ? '' : $configured;
    }
    $docRoot = realpath((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
    $appRoot = realpath(APP_ROOT);
    if ($docRoot && $appRoot) {
        $docNorm = str_replace('\\', '/', $docRoot);
        $appNorm = str_replace('\\', '/', $appRoot);
        if (str_starts_with(strtolower($appNorm), strtolower($docNorm))) {
            return rtrim(substr($appNorm, strlen($docNorm)), '/');
        }
    }
    return '';
}

define('APP_BASE_PATH', detect_base_path());

$https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || ((string) ($_SERVER['SERVER_PORT'] ?? '') === '443');
$host = (string) ($_SERVER['HTTP_HOST'] ?? 'localhost');
if (!preg_match('/^[A-Za-z0-9.:-]+$/', $host)) {
    $host = 'localhost';
}
$configuredUrl = env('APP_URL');
if ($configuredUrl) {
    define('APP_URL', rtrim($configuredUrl, '/'));
} elseif (PHP_SAPI === 'cli') {
    define('APP_URL', 'http://localhost' . APP_BASE_PATH);
} else {
    define('APP_URL', ($https ? 'https' : 'http') . '://' . $host . APP_BASE_PATH);
}

ini_set('display_errors', '0');
ini_set('log_errors', '1');
error_reporting(E_ALL);

require_once APP_ROOT . '/includes/functions.php';
require_once APP_ROOT . '/includes/lang.php';
require_once APP_ROOT . '/includes/csrf.php';
require_once APP_ROOT . '/includes/auth.php';
require_once APP_ROOT . '/includes/coupon_service.php';
require_once APP_ROOT . '/includes/upload.php';
require_once APP_ROOT . '/includes/mail_service.php';
require_once APP_ROOT . '/includes/catalog.php';
require_once APP_ROOT . '/includes/cart_service.php';
require_once APP_ROOT . '/includes/product_service.php';
require_once APP_ROOT . '/includes/order_service.php';
require_once APP_ROOT . '/includes/paypal_service.php';
require_once APP_ROOT . '/includes/ui.php';

set_exception_handler(static function (Throwable $exception): void {
    try {
        if (!$exception instanceof AppException) {
            log_message('error', $exception->getMessage());
        }
    } catch (Throwable) {
    }
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, ($exception instanceof AppException ? $exception->getMessage() : 'Application error') . PHP_EOL);
        exit(1);
    }
    if (!headers_sent()) {
        http_response_code(500);
    }
    $safeMessage = $exception instanceof AppException
        ? $exception->getMessage()
        : 'Something went wrong. Please try again in a moment.';
    require APP_ROOT . '/includes/error_page.php';
    exit;
});

if (PHP_SAPI === 'cli') {
    if (session_status() !== PHP_SESSION_ACTIVE) {
        session_start();
    }
} else {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.gc_maxlifetime', (string) (60 * 60 * 24 * 14));
    session_name('MERIDIANSESS');
    session_set_cookie_params([
        'lifetime' => 0,
        'path' => APP_BASE_PATH === '' ? '/' : APP_BASE_PATH,
        'secure' => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
    header('X-Frame-Options: SAMEORIGIN');
    header('X-Content-Type-Options: nosniff');
    header('Referrer-Policy: strict-origin-when-cross-origin');
    header('Permissions-Policy: geolocation=(), microphone=(), camera=()');
    header("Content-Security-Policy: default-src 'self'; img-src 'self' data:; style-src 'self' https://cdn.jsdelivr.net https://cdnjs.cloudflare.com https://fonts.googleapis.com 'unsafe-inline'; font-src 'self' https://fonts.gstatic.com https://cdnjs.cloudflare.com data:; script-src 'self' https://cdn.jsdelivr.net; base-uri 'self'; form-action 'self' https://www.paypal.com https://www.sandbox.paypal.com https://paypal.com https://sandbox.paypal.com; frame-ancestors 'self'");
    header('Cache-Control: no-store, private');
}

if (!schema_ready()) {
    if (PHP_SAPI === 'cli') {
        fwrite(STDERR, "Database schema is not installed.\n");
        exit(1);
    }
    http_response_code(503);
    require APP_ROOT . '/includes/setup_required.php';
    exit;
}

if (PHP_SAPI !== 'cli') {
    enforce_password_change();
    csrf_token();
}
