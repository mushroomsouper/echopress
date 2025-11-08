<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

$requestUri = $_SERVER['REQUEST_URI'] ?? '';
if (PHP_SAPI !== 'cli' && !echopress_is_installed() && strpos($requestUri, '/install') !== 0) {
    header('Location: /install/');
    exit;
}

date_default_timezone_set(echopress_timezone());
