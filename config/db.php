<?php
declare(strict_types=1);

/**
 * Database settings.
 * XAMPP defaults work immediately. Override with a .env file or real environment variables.
 */
return [
    'host' => env('DB_HOST', '127.0.0.1'),
    'port' => env('DB_PORT', '3306'),
    'name' => env('DB_NAME', 'meridian_market'),
    'user' => env('DB_USER', 'root'),
    'pass' => env('DB_PASS', ''),
    'charset' => 'utf8mb4',
];
