<?php
require_once __DIR__ . '/session_secure.php';
session_start();
date_default_timezone_set('America/Regina');
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/srcset.php';
if (empty($_SESSION['logged_in'])) { header('Location: login.php'); exit; }
$postsDir = __DIR__ . '/../blog/posts';
$imgDir = __DIR__ . '/../blog/images';
$title = trim($_POST['title'] ?? '');
$dateInput  = trim($_POST['date'] ?? date('Y-m-d H:i'));
if (strpos($dateInput, 'T') !== false) {
    $dateInput = str_replace('T', ' ', $dateInput);
}
$date = $dateInput;
$orig  = trim($_POST['orig_slug'] ?? '');
$body  = $_POST['body'] ?? '';
$origImage = trim($_POST['orig_image'] ?? '');
$existingWebp = $_POST['existing_srcset_webp'] ?? '';
$existingJpg  = $_POST['existing_srcset_jpg'] ?? '';
$published = isset($_POST['published']);
$categories = isset($_POST['categories']) ? array_filter(array_map('trim', $_POST['categories'])) : [];
// slug derived from title; ensure uniqueness by appending a numeric suffix if needed
$slug = preg_replace('/[^a-z0-9]+/i', '-', strtolower($title));
$slug = trim($slug, '-');
if (!$slug) {
    die('Invalid slug');
}
if (!$orig) {
    $baseSlug = $slug;
    $suffix = 1;
    while (true) {
        $stmt = $pdo->prepare('SELECT id FROM blog_posts WHERE slug=?');
        $stmt->execute([$slug]);
        if (!$stmt->fetchColumn()) {
            break;
        }
        $suffix++;
        $slug = $baseSlug . '-' . $suffix;
    }
} else {
    $slug = $orig;
}
// handle image upload
if (!is_dir($imgDir)) {
    mkdir($imgDir, 0777, true);
}
$imagePath = $origImage;
$webpSet = $existingWebp;
$jpgSet  = $existingJpg;
if (!empty($_FILES['image']['tmp_name'])) {
    if ($origImage) {
        $baseOld = pathinfo($origImage, PATHINFO_FILENAME);
        foreach (glob("$imgDir/{$baseOld}*.{jpg,jpeg,png,gif,webp}", GLOB_BRACE) as $old) {
            if (is_file($old)) unlink($old);
        }
    }
    $base = $slug . '-' . time();
    list($imagePath, $webpSet, $jpgSet) = create_image_set(
        $_FILES['image']['tmp_name'],
        $imgDir,
        $base,
        $_FILES['image']['name'] ?? '',
        '/blog/images/'
    );
}

$data = [
    'title' => $title,
    'date'  => $date,
    'categories' => $categories,
    'body'  => $body,
    'image' => $imagePath,
    'srcsetWebp' => $webpSet,
    'srcsetJpg'  => $jpgSet,
    'published' => $published
];

// insert/update blog post in DB
$stmt = $pdo->prepare('SELECT id FROM blog_posts WHERE slug=?');
$stmt->execute([$slug]);
$postId = $stmt->fetchColumn();
if ($postId) {
    $stmt = $pdo->prepare('UPDATE blog_posts SET title=?, body=?, image=?, image_srcset_webp=?, image_srcset_jpg=?, published=?, post_date=? WHERE id=?');
    $stmt->execute([$title, $body, $imagePath, $webpSet, $jpgSet, $published ? 1 : 0, $date, $postId]);
} else {
    $stmt = $pdo->prepare('INSERT INTO blog_posts (slug,title,body,image,image_srcset_webp,image_srcset_jpg,published,post_date) VALUES (?,?,?,?,?,?,?,?)');
    $stmt->execute([$slug,$title,$body,$imagePath,$webpSet,$jpgSet,$published ? 1 : 0,$date]);
    $postId = $pdo->lastInsertId();
}

// categories
$catIds = [];
foreach ($categories as $cat) {
    $stmt = $pdo->prepare('SELECT id FROM blog_categories WHERE name=?');
    $stmt->execute([$cat]);
    $cid = $stmt->fetchColumn();
    if (!$cid) {
        $stmt = $pdo->prepare('INSERT INTO blog_categories (name) VALUES (?)');
        $stmt->execute([$cat]);
        $cid = $pdo->lastInsertId();
    }
    $catIds[] = $cid;
}
$pdo->prepare('DELETE FROM blog_post_categories WHERE post_id=?')->execute([$postId]);
foreach ($catIds as $cid) {
    $pdo->prepare('INSERT INTO blog_post_categories (post_id, category_id) VALUES (?,?)')->execute([$postId,$cid]);
}

// maintain JSON files for public site
$catFile = __DIR__ . '/../blog/data/categories.json';
$allCats = file_exists($catFile) ? json_decode(file_get_contents($catFile), true) : [];
$allCats = array_unique(array_merge($allCats, $categories));
file_put_contents($catFile, json_encode(array_values($allCats), JSON_PRETTY_PRINT));
if (!is_dir($postsDir)) mkdir($postsDir, 0777, true);
file_put_contents("$postsDir/$slug.json", json_encode($data, JSON_PRETTY_PRINT));
if ($orig && $orig !== $slug) {
    $old = "$postsDir/$orig.json";
    if (file_exists($old)) unlink($old);
}
header('Location: blog.php');
