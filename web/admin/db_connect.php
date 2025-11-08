<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/database.php';

try {
    $pdo = echopress_database();
} catch (Throwable $e) {
    die('Database connection failed: ' . $e->getMessage());
}
