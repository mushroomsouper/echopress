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

$validPlaylists = [];
if (is_dir($PLAYLISTS_DIR)) {
    foreach (scandir($PLAYLISTS_DIR) as $entry) {
        if ($entry === '.' || $entry === '..') {
            continue;
        }
        if (is_dir($PLAYLISTS_DIR . '/' . $entry)) {
            $validPlaylists[] = $entry;
        }
    }
}

if ($playlist === '' || !in_array($playlist, $validPlaylists, true)) {
    http_response_code(400);
    echo 'Invalid playlist';
    exit;
}

$playlistPath = $PLAYLISTS_DIR . '/' . $playlist;

function rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    foreach (array_diff(scandir($dir), ['.', '..']) as $item) {
        $path = $dir . '/' . $item;
        if (is_dir($path)) {
            rrmdir($path);
        } else {
            unlink($path);
        }
    }
    rmdir($dir);
}

if (is_dir($playlistPath)) {
    rrmdir($playlistPath);
}

$stmt = $pdo->prepare('DELETE FROM playlists WHERE slug = ?');
$stmt->execute([$playlist]);

regenerate_sitemap($pdo);
$_SESSION['playlist_success'] = 'Playlist deleted.';
header('Location: playlists.php');
exit;
