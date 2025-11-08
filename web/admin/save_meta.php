<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../includes/page_meta.php';
require_once __DIR__ . '/srcset.php';
if (empty($_SESSION['logged_in'])) { header('Location: login.php'); exit; }

$allowed = [
    '/index.php',
    '/blog/index.php',
    '/discography/index.php',
    '/contact/index.php'
];
$page = $_POST['page'] ?? '/index.php';
if (!in_array($page, $allowed, true)) {
    $page = '/index.php';
}

$title = trim($_POST['title'] ?? '');
$desc  = trim($_POST['description'] ?? '');
$keywords = trim($_POST['keywords'] ?? '');
$ogTitle = trim($_POST['og_title'] ?? '');
$ogDesc  = trim($_POST['og_description'] ?? '');
$existing = trim($_POST['existing_image'] ?? '');
$existingWebp = $_POST['existing_srcset_webp'] ?? '';
$existingJpg  = $_POST['existing_srcset_jpg'] ?? '';
$metaDir = __DIR__ . '/../profile/meta';
if (!is_dir($metaDir)) {
    mkdir($metaDir, 0777, true);
}
$imagePath = $existing;
$webpSet = $existingWebp;
$jpgSet  = $existingJpg;
if (!empty($_FILES['og_image']['tmp_name'])) {
    if ($existing) {
        $baseOld = pathinfo($existing, PATHINFO_FILENAME);
        foreach (glob("$metaDir/{$baseOld}*.{jpg,jpeg,png,gif,webp}", GLOB_BRACE) as $old) {
            if (is_file($old)) unlink($old);
        }
    }
    $base = preg_replace('/[^a-z0-9]+/i', '-', basename($page, '.php')) . '-' . time();
    list($imagePath, $webpSet, $jpgSet) = create_image_set(
        $_FILES['og_image']['tmp_name'],
        $metaDir,
        $base,
        $_FILES['og_image']['name'] ?? '',
        '/profile/meta/'
    );
}

$stmt = $pdo->prepare('SELECT id FROM page_meta WHERE page=?');
$stmt->execute([$page]);
$metaId = $stmt->fetchColumn();
if ($metaId) {
    $stmt = $pdo->prepare('UPDATE page_meta SET title=?, description=?, keywords=?, og_title=?, og_description=?, og_image=?, og_image_srcset_webp=?, og_image_srcset_jpg=? WHERE id=?');
    $stmt->execute([$title,$desc,$keywords,$ogTitle,$ogDesc,$imagePath,$webpSet,$jpgSet,$metaId]);
} else {
    $stmt = $pdo->prepare('INSERT INTO page_meta (page,title,description,keywords,og_title,og_description,og_image,og_image_srcset_webp,og_image_srcset_jpg) VALUES (?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$page,$title,$desc,$keywords,$ogTitle,$ogDesc,$imagePath,$webpSet,$jpgSet]);
}

header('Location: meta_edit.php?page=' . rawurlencode($page));

