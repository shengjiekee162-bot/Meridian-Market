<?php
declare(strict_types=1);

final class AppException extends RuntimeException
{
}

function config(string $group, ?string $key = null, mixed $default = null): mixed
{
    $bag = $GLOBALS['mm_config'][$group] ?? [];
    if ($key === null) {
        return $bag;
    }
    return $bag[$key] ?? $default;
}

function e(mixed $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function log_message(string $channel, string $message): void
{
    $dir = APP_ROOT . '/logs';
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    $line = sprintf("[%s] [%s] %s\n", date('Y-m-d H:i:s'), $channel, str_replace(["\r", "\n"], ' ', $message));
    file_put_contents($dir . '/app.log', $line, FILE_APPEND | LOCK_EX);
}

function client_ip(): string
{
    $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

function url(string $path = ''): string
{
    $base = APP_BASE_PATH;
    $path = ltrim($path, '/');
    if ($path === '') {
        return ($base === '' ? '' : $base) . '/';
    }
    $hash = '';
    $query = '';
    if (str_contains($path, '#')) {
        [$path, $hash] = explode('#', $path, 2);
        $hash = '#' . $hash;
    }
    if (str_contains($path, '?')) {
        [$path, $query] = explode('?', $path, 2);
        $query = '?' . $query;
    }
    return ($base === '' ? '' : $base) . '/' . ltrim($path, '/') . $query . $hash;
}

function asset(string $path): string
{
    $relative = ltrim($path, '/');
    $full = APP_ROOT . '/assets/' . $relative;
    $version = is_file($full) ? (string) filemtime($full) : '1';
    return url('assets/' . $relative) . '?v=' . $version;
}

function media_url(?string $path): string
{
    if ($path === null || $path === '') {
        return asset('images/placeholder.svg');
    }
    $path = str_replace('\\', '/', $path);
    if (preg_match('#^(https?:)?//#i', $path) || str_contains($path, '..')) {
        return asset('images/placeholder.svg');
    }
    return url(ltrim($path, '/'));
}

function redirect(string $path): never
{
    if (preg_match('#^https?://#i', $path)) {
        $allowed = rtrim(APP_URL, '/');
        if (!str_starts_with($path, $allowed . '/') && $path !== $allowed) {
            $path = url('');
        }
        header('Location: ' . $path);
        exit;
    }
    if (!str_starts_with($path, '/')) {
        $path = url($path);
    } elseif (APP_BASE_PATH !== '' && !str_starts_with($path, APP_BASE_PATH . '/') && $path !== APP_BASE_PATH) {
        $path = url(ltrim($path, '/'));
    }
    header('Location: ' . $path);
    exit;
}

function redirect_external_paypal(string $target): never
{
    $parts = parse_url($target);
    $host = strtolower((string) ($parts['host'] ?? ''));
    $scheme = strtolower((string) ($parts['scheme'] ?? ''));
    $isPayPalHost = $host === 'paypal.com'
        || $host === 'sandbox.paypal.com'
        || str_ends_with($host, '.paypal.com');
    if ($scheme !== 'https' || !$isPayPalHost) {
        throw new AppException('The payment service returned an unexpected address.');
    }
    header('Location: ' . $target);
    exit;
}

function safe_back_url(string $fallback): string
{
    $referer = (string) ($_SERVER['HTTP_REFERER'] ?? '');
    $parts = parse_url($referer);
    if ($parts === false || empty($parts['path'])) {
        return $fallback;
    }
    $host = strtolower((string) ($parts['host'] ?? ''));
    $currentHost = strtolower((string) ($_SERVER['HTTP_HOST'] ?? ''));
    if ($host !== '' && $host !== $currentHost) {
        return $fallback;
    }
    $path = (string) $parts['path'];
    if (str_starts_with($path, '//')) {
        return $fallback;
    }
    if (APP_BASE_PATH !== '') {
        if ($path !== APP_BASE_PATH && !str_starts_with($path, APP_BASE_PATH . '/')) {
            return $fallback;
        }
        $path = substr($path, strlen(APP_BASE_PATH)) ?: '/';
    }
    if (!str_starts_with($path, '/')) {
        return $fallback;
    }
    $query = isset($parts['query']) ? '?' . $parts['query'] : '';
    return $path . $query;
}

function flash(string $type, string $message): void
{
    $_SESSION['flash'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array
{
    $items = $_SESSION['flash'] ?? [];
    unset($_SESSION['flash']);
    return is_array($items) ? $items : [];
}

function set_old(array $data): void
{
    unset($data['password'], $data['password_confirmation'], $data['current_password'], $data['csrf_token']);
    $_SESSION['old'] = $data;
}

function old(string $key, string $default = ''): string
{
    $value = $_SESSION['old'][$key] ?? $default;
    return is_string($value) ? $value : $default;
}

function clear_old(): void
{
    unset($_SESSION['old']);
}

function to_cents(mixed $amount): int
{
    $value = trim((string) $amount);
    if (!preg_match('/^-?\d+(\.\d+)?$/', $value)) {
        return 0;
    }
    $negative = str_starts_with($value, '-');
    $value = ltrim($value, '-');
    [$dollars, $cents] = array_pad(explode('.', $value, 2), 2, '0');
    $cents = substr(str_pad($cents, 2, '0'), 0, 2);
    $total = ((int) $dollars) * 100 + (int) $cents;
    return $negative ? -$total : $total;
}

function from_cents(int $cents): string
{
    $negative = $cents < 0;
    $cents = abs($cents);
    return ($negative ? '-' : '') . intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

function money(mixed $amount): string
{
    $cents = to_cents($amount);
    $negative = $cents < 0;
    $cents = abs($cents);
    $formatted = number_format($cents / 100, 2);
    $symbol = (string) config('app', 'currency_symbol', 'RM');
    $prefix = $symbol === '' ? '' : $symbol . ' ';
    return ($negative ? '-' : '') . $prefix . $formatted;
}

function clean_text(string $value, int $max, string $label = 'This field'): string
{
    $value = trim(preg_replace("/\r\n?/", "\n", $value) ?? '');
    $value = strip_tags($value);
    if (mb_strlen($value) > $max) {
        throw new AppException($label . ' is too long.');
    }
    return $value;
}

function input_string(string $key, int $max = 5000): string
{
    $value = $_POST[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    if (mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }
    return $value;
}

function request_int(string $key, string $source = 'post'): int
{
    $bag = $source === 'get' ? $_GET : $_POST;
    $value = $bag[$key] ?? '0';
    if (is_array($value) || !preg_match('/^-?\d+$/', (string) $value)) {
        return 0;
    }
    return (int) $value;
}

function query_string(string $key, int $max = 80): string
{
    $value = $_GET[$key] ?? '';
    if (!is_string($value)) {
        return '';
    }
    $value = trim($value);
    if (mb_strlen($value) > $max) {
        $value = mb_substr($value, 0, $max);
    }
    return $value;
}

function validate_email(string $email): string
{
    $email = strtolower(trim($email));
    if (mb_strlen($email) > 120 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        throw new AppException('Enter a valid email address.');
    }
    return $email;
}

function validate_person_name(string $name): string
{
    $name = clean_text($name, 80, 'Name');
    if (!preg_match("/^[\p{L}\p{M}][\p{L}\p{M}\s.'-]{1,79}$/u", $name)) {
        throw new AppException('Enter a name using letters, spaces, apostrophes, or hyphens.');
    }
    return $name;
}

function validate_phone(string $phone): string
{
    $phone = clean_text($phone, 20, 'Phone');
    if (!preg_match('/^[0-9+().\-\s]{7,20}$/', $phone)) {
        throw new AppException('Enter a phone number with 7 to 20 digits.');
    }
    return $phone;
}

function validate_address(string $address): string
{
    $address = clean_text($address, 255, 'Address');
    if (mb_strlen($address) < 8) {
        throw new AppException('Enter a complete shipping address.');
    }
    return $address;
}

function validate_password(string $password): void
{
    if (strlen($password) < 8 || strlen($password) > 72) {
        throw new AppException('Use a password between 8 and 72 characters.');
    }
    if (!preg_match('/[a-z]/', $password) || !preg_match('/[A-Z]/', $password)) {
        throw new AppException('Include both uppercase and lowercase letters in the password.');
    }
    if (!preg_match('/\d/', $password) || !preg_match('/[^A-Za-z0-9]/', $password)) {
        throw new AppException('Include a number and a symbol in the password.');
    }
}

function slugify(string $text): string
{
    $text = strtolower(trim($text));
    $text = preg_replace('/[^a-z0-9]+/', '-', $text) ?? '';
    $text = trim($text, '-');
    $text = substr($text, 0, 140);
    return $text !== '' ? $text : 'item';
}

function like_pattern(string $term): string
{
    $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
    return '%' . $term . '%';
}

function pagination_query(array $extra = []): string
{
    $params = $_GET;
    unset($params['page']);
    foreach ($extra as $key => $value) {
        $params[$key] = $value;
    }
    $filtered = [];
    foreach ($params as $key => $value) {
        if (!is_string($key) || is_array($value)) {
            continue;
        }
        $filtered[$key] = (string) $value;
    }
    return http_build_query($filtered);
}

function render_pagination(int $page, int $pages, string $basePath): string
{
    if ($pages <= 1) {
        return '';
    }
    $html = '<nav class="mm-pagination" aria-label="Pagination"><ul class="pagination">';
    $windowStart = max(1, $page - 2);
    $windowEnd = min($pages, $page + 2);
    $link = static function (int $target, string $label, bool $current = false, bool $disabled = false) use ($basePath): string {
        $qs = pagination_query(['page' => (string) $target]);
        $href = url($basePath) . ($qs !== '' ? '?' . $qs : '');
        if ($disabled) {
            return '<li class="page-item disabled"><span class="page-link">' . e($label) . '</span></li>';
        }
        if ($current) {
            return '<li class="page-item active"><span class="page-link" aria-current="page">' . e($label) . '</span></li>';
        }
        return '<li class="page-item"><a class="page-link" href="' . e($href) . '">' . e($label) . '</a></li>';
    };
    $html .= $link(max(1, $page - 1), 'Prev', false, $page <= 1);
    for ($i = $windowStart; $i <= $windowEnd; $i++) {
        $html .= $link($i, (string) $i, $i === $page);
    }
    $html .= $link(min($pages, $page + 1), 'Next', false, $page >= $pages);
    $html .= '</ul></nav>';
    return $html;
}

function discount_percent(array $product): ?int
{
    if (!isset($product['compare_price']) || $product['compare_price'] === null || $product['compare_price'] === '') {
        return null;
    }
    $price = to_cents($product['price']);
    $compare = to_cents($product['compare_price']);
    if ($compare <= $price || $compare === 0) {
        return null;
    }
    return (int) round((($compare - $price) * 100) / $compare);
}

function render_stars(mixed $rating): string
{
    $value = (float) $rating;
    $rounded = (int) round($value);
    $html = '<span class="stars" aria-label="' . e(number_format($value, 1)) . ' out of 5 stars">';
    for ($i = 1; $i <= 5; $i++) {
        $html .= '<i class="fa-solid fa-star' . ($i <= $rounded ? '' : ' is-empty') . '"></i>';
    }
    $html .= '</span>';
    return $html;
}

function order_status_label(string $status): string
{
    return ucfirst($status);
}

function payment_status_label(string $status): string
{
    return ucfirst($status);
}

function customer_can_cancel(array $order): bool
{
    return in_array((string) $order['order_status'], ['pending', 'paid'], true);
}

function order_timeline(array $order): array
{
    $status = (string) $order['order_status'];
    $paid = (string) $order['payment_status'] === 'paid'
        || in_array($status, ['paid', 'processing', 'shipped', 'completed'], true);
    if ($status === 'cancelled') {
        return [
            ['key' => 'placed', 'label' => 'Order placed', 'state' => 'done'],
            ['key' => 'cancelled', 'label' => 'Cancelled', 'state' => 'current'],
        ];
    }
    $rank = [
        'pending' => 1,
        'paid' => 2,
        'processing' => 3,
        'shipped' => 4,
        'completed' => 5,
    ];
    $current = $rank[$status] ?? 1;
    $steps = [
        ['key' => 'placed', 'label' => 'Order placed', 'rank' => 1],
        ['key' => 'paid', 'label' => 'Payment confirmed', 'rank' => 2],
        ['key' => 'processing', 'label' => 'Processing', 'rank' => 3],
        ['key' => 'shipped', 'label' => 'Shipped', 'rank' => 4],
        ['key' => 'completed', 'label' => 'Completed', 'rank' => 5],
    ];
    foreach ($steps as &$step) {
        if ($step['key'] === 'paid' && !$paid && $current < 2) {
            $step['state'] = 'upcoming';
        } elseif ($step['rank'] < $current || ($step['key'] === 'paid' && $paid && $current > 2)) {
            $step['state'] = 'done';
        } elseif ($step['rank'] === $current || ($step['key'] === 'paid' && $paid && $current === 2)) {
            $step['state'] = 'current';
        } else {
            $step['state'] = 'upcoming';
        }
        if ($status === 'completed') {
            $step['state'] = 'done';
        }
    }
    unset($step);
    return $steps;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }
    $cfg = config('db');
    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=%s',
        $cfg['host'],
        $cfg['port'],
        $cfg['name'],
        $cfg['charset']
    );
    try {
        $pdo = new PDO($dsn, (string) $cfg['user'], (string) $cfg['pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);
        $pdo->exec("SET time_zone = '+08:00'");
    } catch (PDOException $exception) {
        log_message('database', 'Connection failed: ' . $exception->getMessage());
        if (PHP_SAPI === 'cli') {
            fwrite(STDERR, "Database connection failed. See logs/app.log\n");
            exit(1);
        }
        http_response_code(500);
        require APP_ROOT . '/includes/db_error.php';
        exit;
    }
    return $pdo;
}

function schema_ready(): bool
{
    try {
        db()->query('SELECT 1 FROM users LIMIT 1');
        return true;
    } catch (Throwable $exception) {
        log_message('database', 'Schema check failed: ' . $exception->getMessage());
        return false;
    }
}

function notify(int $userId, string $title, string $message, string $type = 'info'): void
{
    $stmt = db()->prepare(
        'INSERT INTO notifications (user_id, title, message, type, is_read, created_at)
         VALUES (?, ?, ?, ?, 0, NOW())'
    );
    $stmt->execute([$userId, mb_substr($title, 0, 120), mb_substr($message, 0, 500), mb_substr($type, 0, 30)]);
}

function active_admin_count(int $exceptId = 0): int
{
    $stmt = db()->prepare(
        "SELECT COUNT(*) FROM users WHERE role = 'admin' AND status = 'active' AND id <> ?"
    );
    $stmt->execute([$exceptId]);
    return (int) $stmt->fetchColumn();
}

function guard_last_admin(array $user, string $newRole, string $newStatus): void
{
    if (($user['role'] ?? '') !== 'admin') {
        return;
    }
    $remainsActive = $newRole === 'admin' && $newStatus === 'active';
    if ($remainsActive) {
        return;
    }
    if (active_admin_count((int) $user['id']) < 1) {
        throw new AppException('The marketplace needs at least one active administrator.');
    }
}
