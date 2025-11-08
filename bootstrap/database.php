<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

if (!function_exists('echopress_database')) {
    function echopress_database(): PDO
    {
        static $pdo;
        if ($pdo instanceof PDO) {
            return $pdo;
        }

        $config = echopress_config('database', []);
        $default = $config['default'] ?? 'sqlite';
        $connections = $config['connections'] ?? [];
        if (!isset($connections[$default])) {
            throw new RuntimeException("Database connection '{$default}' is not configured.");
        }

        $connection = $connections[$default];
        $driver = $connection['driver'] ?? $default;
        $options = [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ];

        if ($driver === 'sqlite') {
            $databasePath = $connection['database'] ?? echopress_storage_path('database/echopress.sqlite');
            $directory = dirname($databasePath);
            if (!is_dir($directory)) {
                mkdir($directory, 0775, true);
            }
            $pdo = new PDO('sqlite:' . $databasePath, null, null, $options);
            if (!empty($connection['enforce_foreign_keys'])) {
                $pdo->exec('PRAGMA foreign_keys = ON');
            }
            return $pdo;
        }

        if ($driver === 'mysql' || $driver === 'mariadb') {
            $host = $connection['host'] ?? '127.0.0.1';
            $port = $connection['port'] ?? 3306;
            $dbname = $connection['database'] ?? 'echopress';
            $charset = $connection['charset'] ?? 'utf8mb4';
            $collation = $connection['collation'] ?? 'utf8mb4_unicode_ci';
            $charset = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $charset) ?: 'utf8mb4';
            $collation = preg_replace('/[^a-zA-Z0-9_]/', '', (string) $collation) ?: 'utf8mb4_unicode_ci';
            $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $host, $port, $dbname, $charset);
            $pdo = new PDO($dsn, $connection['username'] ?? 'root', $connection['password'] ?? '', $options);
            if ($collation !== '') {
                $pdo->exec(sprintf('SET NAMES %s COLLATE %s', $charset, $collation));
            }
            return $pdo;
        }

        throw new InvalidArgumentException("Unsupported database driver: {$driver}");
    }
}

if (!function_exists('echopress_database_driver')) {
    function echopress_database_driver(): string
    {
        $config = echopress_config('database', []);
        $default = $config['default'] ?? 'sqlite';
        $connection = $config['connections'][$default] ?? [];
        return (string) ($connection['driver'] ?? $default);
    }
}
?>
