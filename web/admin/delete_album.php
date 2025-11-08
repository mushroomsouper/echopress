<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/sitemap.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$album = basename($_POST['album'] ?? '');

// Build whitelist of available album directories
$validAlbums = [];
if (is_dir($ALBUMS_DIR)) {
    foreach (scandir($ALBUMS_DIR) as $entry) {
        if ($entry === '.' || $entry === '..') continue;
        if (is_dir("$ALBUMS_DIR/$entry")) {
            $validAlbums[] = $entry;
        }
    }
}

// Reject invalid album names with a 400 response
if (!$album || !in_array($album, $validAlbums, true)) {
    http_response_code(400);
    echo 'Invalid album';
    exit;
}

$albumPath = "$ALBUMS_DIR/$album";
$discogPath = __DIR__ . '/../discography/albums/' . $album;
$cssPath = __DIR__ . '/../discography/albums/' . $album . '/css/style.css';

function rrmdir($dir) {
    if (!is_dir($dir)) {
        return;
    }
    $items = array_diff(scandir($dir), ['.', '..']);
    foreach ($items as $item) {
        $path = "$dir/$item";
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

if (is_dir($albumPath)) {
    rrmdir($albumPath);
}
if (is_dir($discogPath)) {
    rrmdir($discogPath);
}
if (file_exists($cssPath)) {
    unlink($cssPath);
}

$stmt = $pdo->prepare('SELECT id FROM albums WHERE slug=?');
$stmt->execute([$album]);
$albumId = $stmt->fetchColumn();
if ($albumId) {
    $pdo->prepare('DELETE FROM albums WHERE id=?')->execute([$albumId]);
    $pdo->prepare('DELETE FROM album_tracks WHERE album_id=?')->execute([$albumId]);
}

regenerate_sitemap($pdo);

header('Location: index.php');
exit;
