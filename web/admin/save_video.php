<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/srcset.php';
require_once __DIR__ . '/../includes/app.php';
$defaultArtistName = echopress_artist_name();
if (empty($_SESSION['logged_in'])) { header('Location: login.php'); exit; }
$origSlug = trim($_POST['orig_slug'] ?? '');
$title = trim($_POST['title'] ?? '');
$artist = trim($_POST['artist'] ?? $defaultArtistName);
$releaseDate = trim($_POST['releaseDate'] ?? '');
$url = trim($_POST['url'] ?? '');
$platform = trim($_POST['platform'] ?? 'vimeo');
$order = (int)($_POST['video_order'] ?? 0);
$existingThumb = $_POST['existing_thumb'] ?? '';
$existingWebp = $_POST['existing_webp'] ?? '';
$existingJpg = $_POST['existing_jpg'] ?? '';
$slug = preg_replace('/[^a-z0-9]+/i', '_', strtolower($title));
$slug = trim($slug, '_');
if (!$slug) $slug = 'video_' . time();
if ($origSlug && $origSlug !== $slug) {
    $oldDir = __DIR__ . '/../videos/' . $origSlug;
    $newDir = __DIR__ . '/../videos/' . $slug;
    if (is_dir($oldDir)) rename($oldDir, $newDir);
}
$dir = __DIR__ . '/../videos/' . $slug . '/assets';
if (!is_dir($dir)) mkdir($dir, 0777, true);
$thumb = $existingThumb;
$webpSet = $existingWebp;
$jpgSet = $existingJpg;
if (!empty($_FILES['thumbnail']['tmp_name'])) {
    if ($existingThumb) {
        $baseOld = pathinfo($existingThumb, PATHINFO_FILENAME);
        foreach (glob("$dir/{$baseOld}*.{jpg,jpeg,png,gif,webp}", GLOB_BRACE) as $old) {
            if (is_file($old)) unlink($old);
        }
    }
    $base = 'thumb';
    list($thumb, $webpSet, $jpgSet) = create_image_set(
        $_FILES['thumbnail']['tmp_name'],
        $dir,
        $base,
        $_FILES['thumbnail']['name'] ?? '',
        'assets/'
    );
}
$stmt = $pdo->prepare('SELECT id FROM videos WHERE slug=?');
$stmt->execute([$slug]);
$id = $stmt->fetchColumn();
if ($id) {
    $stmt = $pdo->prepare('UPDATE videos SET title=?, artist=?, releaseDate=?, url=?, platform=?, thumbnail=?, thumb_srcset_webp=?, thumb_srcset_jpg=?, video_order=?, slug=? WHERE id=?');
    $stmt->execute([$title,$artist,$releaseDate?:null,$url,$platform,$thumb,$webpSet,$jpgSet,$order,$slug,$id]);
} else {
    $stmt = $pdo->prepare('INSERT INTO videos (slug,title,artist,releaseDate,url,platform,thumbnail,thumb_srcset_webp,thumb_srcset_jpg,video_order) VALUES (?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([$slug,$title,$artist,$releaseDate?:null,$url,$platform,$thumb,$webpSet,$jpgSet,$order]);
}
header('Location: videos.php');
