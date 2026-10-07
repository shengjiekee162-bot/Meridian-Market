<?php
declare(strict_types=1);

/**
 * Tiny environment loader. No Composer dependency.
 * Existing process environment variables win over the .env file.
 */
function load_env_file(string $path): void
{
    if (!is_file($path) || !is_readable($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) {
            continue;
        }
        $eq = strpos($line, '=');
        if ($eq === false) {
            continue;
        }
        $key = trim(substr($line, 0, $eq));
        $value = trim(substr($line, $eq + 1));
        if ($key === '' || !preg_match('/^[A-Z0-9_]+$/', $key)) {
            continue;
        }
        if (
            (str_starts_with($value, '"') && str_ends_with($value, '"'))
            || (str_starts_with($value, "'") && str_ends_with($value, "'"))
        ) {
            $value = substr($value, 1, -1);
        }
        if (getenv($key) !== false || array_key_exists($key, $_ENV)) {
            continue;
        }
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function env(string $key, ?string $default = null): ?string
{
    $value = $_ENV[$key] ?? getenv($key);
    if ($value === false || $value === null || $value === '') {
        return $default;
    }
    return (string) $value;
}

/**
 * Merge key/value pairs into the project .env file. Existing keys are updated; others are preserved.
 *
 * @param array<string, string> $pairs
 */
function write_env_values(array $pairs): void
{
    $path = APP_ROOT . '/.env';
    $lines = is_file($path) ? file($path, FILE_IGNORE_NEW_LINES) : [];
    if ($lines === false) {
        $lines = [];
    }
    $remaining = $pairs;
    $out = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed === '' || str_starts_with($trimmed, '#') || !str_contains($trimmed, '=')) {
            $out[] = $line;
            continue;
        }
        $eq = strpos($trimmed, '=');
        $key = trim(substr($trimmed, 0, $eq));
        if (array_key_exists($key, $remaining)) {
            $out[] = $key . '=' . env_export_value($remaining[$key]);
            unset($remaining[$key]);
        } else {
            $out[] = $line;
        }
    }
    foreach ($remaining as $key => $value) {
        if (!preg_match('/^[A-Z0-9_]+$/', $key)) {
            continue;
        }
        $out[] = $key . '=' . env_export_value($value);
    }
    $content = implode("\n", $out);
    if ($content !== '' && !str_ends_with($content, "\n")) {
        $content .= "\n";
    }
    if (file_put_contents($path, $content, LOCK_EX) === false) {
        throw new AppException('Could not write the .env file. Check folder permissions.');
    }
    foreach ($pairs as $key => $value) {
        $_ENV[$key] = $value;
        putenv($key . '=' . $value);
    }
}

function env_export_value(string $value): string
{
    if ($value === '' || preg_match('/[\s#\'"]/', $value)) {
        return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $value) . '"';
    }
    return $value;
}
