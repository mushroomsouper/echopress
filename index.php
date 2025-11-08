<?php
// EchoPress root front controller fallback
// Allows running the app even if the hosting docroot cannot be set to /web

$webRoot = __DIR__ . '/web';

// If /web doesn't exist, bail.
if (!is_dir($webRoot)) {
    http_response_code(500);
    echo 'EchoPress error: missing web/ directory.';
    exit;
}

// Normalize request path
$uri = $_SERVER['REQUEST_URI'] ?? '/';
$path = parse_url($uri, PHP_URL_PATH);
if (!is_string($path)) {
    $path = '/';
}

// Map to a file within /web
$candidate = realpath($webRoot . $path);
// Ensure the candidate stays inside /web to avoid traversal
if ($candidate && strpos($candidate, realpath($webRoot)) === 0 && is_file($candidate)) {
    // Serve static file with basic content-type
    $ext = strtolower(pathinfo($candidate, PATHINFO_EXTENSION));
    $types = [
        'css' => 'text/css',
        'js'  => 'application/javascript',
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg'=> 'image/jpeg',
        'webp'=> 'image/webp',
        'svg' => 'image/svg+xml',
        'ico' => 'image/x-icon',
        'json'=> 'application/json',
        'xml' => 'application/xml',
        'html'=> 'text/html'
    ];
    if (isset($types[$ext])) {
        header('Content-Type: ' . $types[$ext]);
    }
    readfile($candidate);
    exit;
}

// Otherwise, route through the app index in /web
// Adjust DOCUMENT_ROOT and CWD so relative paths work as expected
$_SERVER['DOCUMENT_ROOT'] = realpath($webRoot);
chdir($_SERVER['DOCUMENT_ROOT']);
require $_SERVER['DOCUMENT_ROOT'] . '/index.php';

