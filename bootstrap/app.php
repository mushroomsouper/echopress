<?php
declare(strict_types=1);

/**
 * Global bootstrap for EchoPress.
 * - Loads environment variables from .env
 * - Exposes helper functions for paths, config, and metadata
 */

if (!defined('ECHOPRESS_ROOT')) {
    define('ECHOPRESS_ROOT', dirname(__DIR__));
}

// Load .env variables once so config/app.php can rely on getenv().
(function (): void {
    $envFile = ECHOPRESS_ROOT . '/.env';
    if (!is_file($envFile)) {
        return;
    }

    $lines = @file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || $line[0] === '#') {
            continue;
        }
        $separatorPos = strpos($line, '=');
        if ($separatorPos === false) {
            continue;
        }
        $key = trim(substr($line, 0, $separatorPos));
        $value = trim(substr($line, $separatorPos + 1));
        if ($key === '') {
            continue;
        }
        if (!array_key_exists($key, $_ENV)) {
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
    }
})();

if (!function_exists('echopress_path')) {
    function echopress_path(string $path = ''): string
    {
        $base = ECHOPRESS_ROOT;
        if ($path === '') {
            return $base;
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('echopress_storage_path')) {
    function echopress_storage_path(string $path = ''): string
    {
        $base = echopress_path('storage');
        if ($path === '') {
            return $base;
        }
        return $base . '/' . ltrim($path, '/');
    }
}

if (!function_exists('echopress_config')) {
    function echopress_config(?string $key = null, $default = null)
    {
        if (!array_key_exists('echopress_config_cache', $GLOBALS) || $GLOBALS['echopress_config_cache'] === null) {
            $configFile = echopress_path('config/app.php');
            if (!is_file($configFile)) {
                throw new RuntimeException('Missing config/app.php. Run the EchoPress installer.');
            }
            $loaded = require $configFile;
            if (!is_array($loaded)) {
                throw new RuntimeException('config/app.php must return an array.');
            }
            $GLOBALS['echopress_config_cache'] = $loaded;
        }

        $config = $GLOBALS['echopress_config_cache'];

        if ($key === null) {
            return $config;
        }

        $segments = explode('.', $key);
        $value = $config;
        foreach ($segments as $segment) {
            if (!is_array($value) || !array_key_exists($segment, $value)) {
                return $default;
            }
            $value = $value[$segment];
        }

        return $value;
    }
}

if (!function_exists('echopress_config_reload')) {
    function echopress_config_reload(): void
    {
        $GLOBALS['echopress_config_cache'] = null;
    }
}

// Simple hooks API (actions/filters)
if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10): void
    {
        $GLOBALS['echopress_actions'][$hook][$priority][] = $callback;
    }
}
if (!function_exists('do_action')) {
    function do_action(string $hook, ...$args): void
    {
        if (empty($GLOBALS['echopress_actions'][$hook])) return;
        ksort($GLOBALS['echopress_actions'][$hook]);
        foreach ($GLOBALS['echopress_actions'][$hook] as $list) {
            foreach ($list as $cb) {
                try { $cb(...$args); } catch (\Throwable $e) { /* noop */ }
            }
        }
    }
}
if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10): void
    {
        $GLOBALS['echopress_filters'][$hook][$priority][] = $callback;
    }
}
if (!function_exists('apply_filters')) {
    function apply_filters(string $hook, $value, ...$args)
    {
        if (empty($GLOBALS['echopress_filters'][$hook])) return $value;
        ksort($GLOBALS['echopress_filters'][$hook]);
        foreach ($GLOBALS['echopress_filters'][$hook] as $list) {
            foreach ($list as $cb) {
                try { $value = $cb($value, ...$args); } catch (\Throwable $e) { /* noop */ }
            }
        }
        return $value;
    }
}

// Feature toggles
if (!function_exists('echopress_feature_enabled')) {
    function echopress_feature_enabled(string $feature, bool $default = true): bool
    {
        $val = echopress_config('features.' . $feature, $default);
        return (bool) $val;
    }
}

// Theme resolution
if (!function_exists('echopress_theme_active')) {
    function echopress_theme_active(): string
    {
        $active = (string) echopress_config('themes.active', '');
        return $active;
    }
}
if (!function_exists('echopress_template')) {
    function echopress_template(string $relative): string
    {
        $relative = ltrim($relative, '/');
        $theme = echopress_theme_active();
        if ($theme !== '') {
            $themePath = echopress_path('web/themes/' . $theme . '/' . $relative);
            if (is_file($themePath)) return $themePath;
        }
        return echopress_path('web/' . $relative);
    }
}

// Plugin loader
if (!function_exists('echopress_load_plugins')) {
    function echopress_load_plugins(): void
    {
        $enabled = (array) echopress_config('plugins.enabled', []);
        foreach ($enabled as $slug) {
            $slug = trim((string) $slug);
            if ($slug === '') continue;
            $main = echopress_path('web/plugins/' . $slug . '/' . $slug . '.php');
            if (is_file($main)) {
                try { require_once $main; } catch (\Throwable $e) { /* ignore plugin load failure */ }
            }
        }
    }
}

// Initialize plugins and fire init hook
echopress_load_plugins();
do_action('init');
if (!function_exists('echopress_site_name')) {
    function echopress_site_name(): string
    {
        $name = echopress_config('site.name', 'EchoPress Artist');
        return is_string($name) && $name !== '' ? $name : 'EchoPress Artist';
    }
}

if (!function_exists('echopress_site_tagline')) {
    function echopress_site_tagline(): string
    {
        $tagline = echopress_config('site.tagline', 'Independent artist powered by EchoPress');
        return is_string($tagline) ? $tagline : '';
    }
}

if (!function_exists('echopress_analytics_embed')) {
    function echopress_analytics_embed(): string
    {
        static $cached;
        if ($cached !== null) {
            return $cached;
        }
        $embed = (string) echopress_config('analytics.embed_code', '');
        if ($embed !== '') {
            $cached = $embed;
            return $embed;
        }
        $file = echopress_storage_path('analytics/embed.html');
        if (is_file($file)) {
            $cached = (string) file_get_contents($file);
            return $cached;
        }
        $cached = '';
        return '';
    }
}

if (!function_exists('echopress_primary_url')) {
    function echopress_primary_url(): string
    {
        $url = (string) echopress_config('site.url', '');
        $url = trim($url);
        if ($url !== '') {
            return rtrim($url, '/');
        }
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return '';
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $host;
    }
}

if (!function_exists('echopress_artist_name')) {
    function echopress_artist_name(): string
    {
        $name = echopress_config('artist.name', 'EchoPress Artist');
        return is_string($name) && $name !== '' ? $name : echopress_site_name();
    }
}

if (!function_exists('echopress_timezone')) {
    function echopress_timezone(): string
    {
        $tz = echopress_config('site.timezone', 'UTC');
        return is_string($tz) && $tz !== '' ? $tz : 'UTC';
    }
}

if (!function_exists('echopress_support_email')) {
    function echopress_support_email(): string
    {
        $email = echopress_config('contact.email', 'hello@example.com');
        return is_string($email) && $email !== '' ? $email : 'hello@example.com';
    }
}

if (!function_exists('echopress_base_url')) {
    function echopress_base_url(): string
    {
        $url = echopress_primary_url();
        if ($url !== '') {
            return $url;
        }
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        $host = $_SERVER['HTTP_HOST'] ?? '';
        if ($host === '') {
            return '';
        }
        return $scheme . '://' . $host;
    }
}

if (!function_exists('echopress_asset_version')) {
    function echopress_asset_version(): string
    {
        $versionFile = echopress_path('web/version.txt');
        if (is_file($versionFile)) {
            return trim((string) file_get_contents($versionFile)) ?: '1';
        }
        return '1';
    }
}

if (!function_exists('echopress_install_lock_path')) {
    function echopress_install_lock_path(): string
    {
        $lock = echopress_config('installer.lock_file', echopress_path('storage/installer.lock'));
        return $lock ?: echopress_path('storage/installer.lock');
    }
}

if (!function_exists('echopress_is_installed')) {
    function echopress_is_installed(): bool
    {
        return is_file(echopress_install_lock_path());
    }
}

require_once __DIR__ . '/tools.php';
?>
