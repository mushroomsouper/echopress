<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/srcset.php';
require_once __DIR__ . '/../includes/app.php';
$defaultArtistName = echopress_artist_name();
if (empty($_SESSION['logged_in'])) { header('Location: login.php'); exit; }
$slug = basename($_GET['slug'] ?? '');
$video = [
    'title' => '',
    'artist' => $defaultArtistName,
    'releaseDate' => '',
    'url' => '',
    'platform' => 'vimeo',
    'video_order' => 0,
    'thumbnail' => '',
    'thumb_srcset_webp' => '',
    'thumb_srcset_jpg' => ''
];
if ($slug) {
    $stmt = $pdo->prepare('SELECT * FROM videos WHERE slug=?');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    if ($row) $video = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
    $pageTitle = ($slug ? 'Edit' : 'New') . ' Video';
    $versionFile = __DIR__ . '/../version.txt';
    $version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1';
    $versionParam = htmlspecialchars($version, ENT_QUOTES);
    $headExtras = '<link rel="stylesheet" href="/css/admin.css?v=' . $versionParam . '">' . "\n" .
                  '<script src="/js/admin-session.js?v=' . $versionParam . '" defer></script>';
?>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/css/style.css?v=<?= htmlspecialchars($version) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <?= $headExtras ?>
</head>
<body>
<h1><?= $slug ? 'Edit' : 'New' ?> Video</h1>
<form action="save_video.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="orig_slug" value="<?= htmlspecialchars($slug) ?>">
    <input type="hidden" name="existing_thumb" value="<?= htmlspecialchars($video['thumbnail']) ?>">
    <input type="hidden" name="existing_webp" value="<?= htmlspecialchars($video['thumb_srcset_webp']) ?>">
    <input type="hidden" name="existing_jpg" value="<?= htmlspecialchars($video['thumb_srcset_jpg']) ?>">
    <label>Title:<input type="text" name="title" required value="<?= htmlspecialchars($video['title']) ?>"></label><br>
    <label>Artist:<input type="text" name="artist" value="<?= htmlspecialchars($video['artist']) ?>"></label><br>
    <label>Release Date:<input type="date" name="releaseDate" value="<?= htmlspecialchars($video['releaseDate']) ?>"></label><br>
    <label>URL:<input type="url" name="url" value="<?= htmlspecialchars($video['url']) ?>"></label><br>
    <label>Platform:
        <select name="platform">
            <option value="vimeo"<?= $video['platform']==='vimeo'?' selected':'' ?>>Vimeo</option>
            <option value="youtube"<?= $video['platform']==='youtube'?' selected':'' ?>>YouTube</option>
        </select>
    </label><br>
    <label>Order:<input type="number" name="video_order" value="<?= htmlspecialchars($video['video_order']) ?>"></label><br>
    <label>Thumbnail:<input type="file" name="thumbnail"></label>
    <?php if ($video['thumbnail']): ?>
        <img src="<?= htmlspecialchars(cache_bust('/videos/' . $slug . '/' . $video['thumbnail'])) ?>" alt="thumb" height="50">
    <?php endif; ?><br>
    <button type="submit">Save</button>
</form>
</body>
</html>
