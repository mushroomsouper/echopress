<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/sitemap.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$playlist = basename($_POST['playlist'] ?? '');
$live = isset($_POST['live']);

if ($playlist === '') {
    header('Location: playlists.php');
    exit;
}

$manifestPath = $PLAYLISTS_DIR . '/' . $playlist . '/manifest.json';
if (file_exists($manifestPath)) {
    $data = json_decode(file_get_contents($manifestPath), true) ?: [];
    $data['live'] = $live;
    file_put_contents($manifestPath, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

$stmt = $pdo->prepare('UPDATE playlists SET live = ? WHERE slug = ?');
$stmt->execute([$live ? 1 : 0, $playlist]);

regenerate_sitemap($pdo);

header('Location: playlists.php');
exit;
