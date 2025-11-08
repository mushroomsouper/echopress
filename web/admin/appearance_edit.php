<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/srcset.php';
if (empty($_SESSION['logged_in'])) { header('Location: login.php'); exit; }
$slug = basename($_GET['slug'] ?? '');
$appearance = [
    'title' => '',
    'artist' => '',
    'releaseDate' => '',
    'url' => '',
    'comingSoon' => 0,
    'released' => 1,
    'appearance_order' => 0,
    'cover' => '',
    'cover_srcset_webp' => '',
    'cover_srcset_jpg' => ''
];
if ($slug) {
    $stmt = $pdo->prepare('SELECT * FROM appearances WHERE slug=?');
    $stmt->execute([$slug]);
    $row = $stmt->fetch();
    if ($row) $appearance = $row;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
    $pageTitle = ($slug ? 'Edit' : 'New') . ' Appearance';
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
<h1><?= $slug ? 'Edit' : 'New' ?> Appearance</h1>
<form action="save_appearance.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="orig_slug" value="<?= htmlspecialchars($slug) ?>">
    <input type="hidden" name="existing_cover" value="<?= htmlspecialchars($appearance['cover']) ?>">
    <input type="hidden" name="existing_srcset_webp" value="<?= htmlspecialchars($appearance['cover_srcset_webp']) ?>">
    <input type="hidden" name="existing_srcset_jpg" value="<?= htmlspecialchars($appearance['cover_srcset_jpg']) ?>">
    <label>Title:<input type="text" name="title" required value="<?= htmlspecialchars($appearance['title']) ?>"></label><br>
    <label>Artist:<input type="text" name="artist" required value="<?= htmlspecialchars($appearance['artist']) ?>"></label><br>
    <label>Release Date:<input type="date" name="releaseDate" value="<?= htmlspecialchars($appearance['releaseDate']) ?>"></label><br>
    <label>URL:<input type="url" name="url" value="<?= htmlspecialchars($appearance['url']) ?>"></label><br>
    <label>Coming Soon:<input type="checkbox" name="comingSoon" value="1" <?= $appearance['comingSoon'] ? 'checked' : '' ?>></label><br>
    <label>Released:<input type="checkbox" name="released" value="1" <?= $appearance['released'] ? 'checked' : '' ?>></label><br>
    <label>Order:<input type="number" name="appearance_order" value="<?= htmlspecialchars($appearance['appearance_order']) ?>"></label><br>
    <label>Cover:<input type="file" name="cover"></label>
    <?php if ($appearance['cover']): ?>
        <img src="<?= htmlspecialchars(cache_bust('/discography/appearances/' . $slug . '/' . $appearance['cover'])) ?>" alt="cover" height="50">
    <?php endif; ?><br>
    <button type="submit">Save</button>
</form>
</body>
</html>
