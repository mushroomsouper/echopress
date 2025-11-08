<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
if (empty($_SESSION['logged_in'])) { header('Location: login.php'); exit; }
$slug = basename($_POST['slug'] ?? '');
$published = isset($_POST['published']);
if ($slug === '') { header('Location: blog.php'); exit; }
$postsDir = __DIR__ . '/../blog/posts';
$file = "$postsDir/$slug.json";
if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true) ?: [];
    $data['published'] = $published;
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT));
}
$stmt = $pdo->prepare('UPDATE blog_posts SET published=? WHERE slug=?');
$stmt->execute([$published ? 1 : 0, $slug]);
header('Location: blog.php');
