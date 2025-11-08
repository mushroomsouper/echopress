<?php
require_once __DIR__ . '/session_secure.php';
session_start();
if (empty($_SESSION['logged_in'])) {
    http_response_code(403);
    echo "Not authorized";
    exit;
}
$cmd = 'php ' . escapeshellarg(__DIR__ . '/../send_blog_newsletters.php') . ' 2>&1';
header('Content-Type: text/plain');
passthru($cmd);

