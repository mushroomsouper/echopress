<?php
require_once __DIR__ . '/session_secure.php';
session_start();
if (empty($_SESSION['logged_in'])) {
    http_response_code(403);
    echo "Not authorized";
    exit;
}
$versionFile = __DIR__ . '/../version.txt';
$version = time();
file_put_contents($versionFile, $version);
echo "ok";
?>
