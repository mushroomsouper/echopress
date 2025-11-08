<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
if (empty($_SESSION['logged_in'])) { header('Location: login.php'); exit; }
$slug = basename($_POST['slug'] ?? '');
if ($slug) {
    $stmt = $pdo->prepare('DELETE FROM appearances WHERE slug=?');
    $stmt->execute([$slug]);
    $dir = __DIR__ . '/../discography/appearances/' . $slug;
    if (is_dir($dir)) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
        foreach ($it as $file) {
            if ($file->isDir()) rmdir($file->getRealPath()); else unlink($file->getRealPath());
        }
        rmdir($dir);
    }
}
header('Location: appearances.php');
