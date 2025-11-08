<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/sitemap.php';
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}
$album = $_POST['album'] ?? '';
$live = isset($_POST['live']);
if ($album === '') {
    header('Location: index.php');
    exit;
}
$manifestPath = "$ALBUMS_DIR/$album/manifest.json";
if (file_exists($manifestPath)) {
    $data = json_decode(file_get_contents($manifestPath), true) ?: [];
    $data['live'] = $live;
    file_put_contents($manifestPath, json_encode($data, JSON_PRETTY_PRINT));
}
$stmt = $pdo->prepare('UPDATE albums SET live=? WHERE slug=?');
$stmt->execute([$live ? 1 : 0, $album]);
regenerate_sitemap($pdo);
header('Location: index.php');
