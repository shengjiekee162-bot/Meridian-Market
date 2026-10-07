<?php
declare(strict_types=1);

function available_locales(): array
{
    return [
        'en' => 'English',
        'zh' => '华文',
    ];
}

function default_locale(): string
{
    return 'en';
}

function normalize_locale(?string $locale): string
{
    $locale = strtolower(trim((string) $locale));
    return array_key_exists($locale, available_locales()) ? $locale : default_locale();
}

function current_locale(): string
{
    if (!empty($_SESSION['locale']) && is_string($_SESSION['locale'])) {
        return normalize_locale($_SESSION['locale']);
    }
    return default_locale();
}

function set_locale(string $locale): void
{
    $_SESSION['locale'] = normalize_locale($locale);
}

function reset_locale_to_english(): void
{
    $_SESSION['locale'] = 'en';
}

function load_translations(string $locale): array
{
    static $cache = [];
    $locale = normalize_locale($locale);
    if (isset($cache[$locale])) {
        return $cache[$locale];
    }
    $path = APP_ROOT . '/lang/' . $locale . '.php';
    $fallbackPath = APP_ROOT . '/lang/en.php';
    $fallback = is_file($fallbackPath) ? require $fallbackPath : [];
    $strings = is_file($path) ? require $path : [];
    if (!is_array($fallback)) {
        $fallback = [];
    }
    if (!is_array($strings)) {
        $strings = [];
    }
    $cache[$locale] = $locale === 'en' ? $fallback : array_merge($fallback, $strings);
    return $cache[$locale];
}

/**
 * Translate a key. Supports {name} placeholders.
 */
function t(string $key, array $replace = []): string
{
    $strings = load_translations(current_locale());
    $text = $strings[$key] ?? load_translations('en')[$key] ?? $key;
    if ($replace === []) {
        return $text;
    }
    $search = [];
    $values = [];
    foreach ($replace as $name => $value) {
        $search[] = '{' . $name . '}';
        $values[] = (string) $value;
    }
    return str_replace($search, $values, $text);
}

function te(string $key, array $replace = []): string
{
    return e(t($key, $replace));
}
