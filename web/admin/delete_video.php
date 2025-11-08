<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
if (empty($_SESSION['logged_in'])) { header('Location: login.php'); exit; }
$slug = basename($_POST['slug'] ?? '');
if ($slug) {
    $stmt = $pdo->prepare('DELETE FROM videos WHERE slug=?');
    $stmt->execute([$slug]);
    $dir = __DIR__ . '/../videos/' . $slug;
    if (is_dir($dir)) {
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST) as $file) {
            if ($file->isFile()) unlink($file->getPathname());
            else rmdir($file->getPathname());
        }
        rmdir($dir);
    }
}
header('Location: videos.php');
