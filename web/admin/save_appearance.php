<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/srcset.php';
if (empty($_SESSION['logged_in'])) { header('Location: login.php'); exit; }
$origSlug = trim($_POST['orig_slug'] ?? '');
$title = trim($_POST['title'] ?? '');
$artist = trim($_POST['artist'] ?? '');
$releaseDate = trim($_POST['releaseDate'] ?? '');
$url = trim($_POST['url'] ?? '');
$comingSoon = isset($_POST['comingSoon']);
$released = isset($_POST['released']);
$order = (int)($_POST['appearance_order'] ?? 0);
$existingCover = $_POST['existing_cover'] ?? '';
$existingWebp = $_POST['existing_srcset_webp'] ?? '';
$existingJpg = $_POST['existing_srcset_jpg'] ?? '';
$slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($title));
$slug = trim($slug, '_');
if (!$slug) $slug = 'appearance_' . time();
if ($origSlug && $origSlug !== $slug) {
    // rename dir
    $oldDir = __DIR__ . '/../discography/appearances/' . $origSlug;
    $newDir = __DIR__ . '/../discography/appearances/' . $slug;
    if (is_dir($oldDir)) rename($oldDir, $newDir);
}
$dir = __DIR__ . '/../discography/appearances/' . $slug . '/assets';
if (!is_dir($dir)) mkdir($dir, 0777, true);
$cover = $existingCover;
$webpSet = $existingWebp;
$jpgSet = $existingJpg;
if (!empty($_FILES['cover']['tmp_name'])) {
    if ($existingCover) {
        $baseOld = pathinfo($existingCover, PATHINFO_FILENAME);
        foreach (glob("$dir/{$baseOld}*.{jpg,jpeg,png,gif,webp}", GLOB_BRACE) as $old) {
            if (is_file($old)) unlink($old);
        }
    }
    $base = 'cover';
    list($cover, $webpSet, $jpgSet) = create_image_set(
        $_FILES['cover']['tmp_name'],
        $dir,
        $base,
        $_FILES['cover']['name'] ?? '',
        'assets/'
    );
}
$stmt = $pdo->prepare('SELECT id FROM appearances WHERE slug=?');
$stmt->execute([$slug]);
$id = $stmt->fetchColumn();
if ($id) {
    $stmt = $pdo->prepare('UPDATE appearances SET title=?, artist=?, releaseDate=?, url=?, comingSoon=?, released=?, cover=?, cover_srcset_webp=?, cover_srcset_jpg=?, appearance_order=?, slug=? WHERE id=?');
    $stmt->execute([$title,$artist,$releaseDate?:null,$url,$comingSoon?1:0,$released?1:0,$cover,$webpSet,$jpgSet,$order,$slug,$id]);
} else {
    $stmt = $pdo->prepare('INSERT INTO appearances (slug,title,artist,releaseDate,url,comingSoon,released,cover,cover_srcset_webp,cover_srcset_jpg,appearance_order) VALUES (?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$slug,$title,$artist,$releaseDate?:null,$url,$comingSoon?1:0,$released?1:0,$cover,$webpSet,$jpgSet,$order]);
}
header('Location: appearances.php');
