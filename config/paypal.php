<?php
declare(strict_types=1);

$mode = strtolower((string) env('PAYPAL_MODE', 'sandbox'));
if (!in_array($mode, ['sandbox', 'live'], true)) {
    $mode = 'sandbox';
}

$defaultBase = $mode === 'live'
    ? 'https://api-m.paypal.com'
    : 'https://api-m.sandbox.paypal.com';

return [
    'mode' => $mode,
    'client_id' => (string) env('PAYPAL_CLIENT_ID', ''),
    'client_secret' => (string) env('PAYPAL_CLIENT_SECRET', ''),
    'currency' => strtoupper((string) env('PAYPAL_CURRENCY', 'MYR')),
    'business_email' => trim((string) env('PAYPAL_BUSINESS_EMAIL', '')),
    'api_base' => rtrim((string) env('PAYPAL_API_BASE', $defaultBase), '/'),
];
