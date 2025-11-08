<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
if (empty($_SESSION['logged_in'])) { header('Location: login.php'); exit; }
$slug = basename($_POST['slug'] ?? '');
$stmt = $pdo->prepare('SELECT id FROM blog_posts WHERE slug=?');
$stmt->execute([$slug]);
$postId = $stmt->fetchColumn();
if ($postId) {
    $pdo->prepare('DELETE FROM blog_posts WHERE id=?')->execute([$postId]);
    $pdo->prepare('DELETE FROM blog_post_categories WHERE post_id=?')->execute([$postId]);
}
$postsDir = __DIR__ . '/../blog/posts';
$file = "$postsDir/$slug.json";
if (preg_match('/^[a-z0-9-]+$/', $slug) && file_exists($file)) {
    unlink($file);
}
header('Location: blog.php');
