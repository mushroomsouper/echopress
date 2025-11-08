<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../includes/page_meta.php';
require_once __DIR__ . '/srcset.php';
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$allowed = [
    '/index.php',
    '/blog/index.php',
    '/discography/index.php',
    '/contact/index.php'
];
$page = $_GET['page'] ?? '/index.php';
if (!in_array($page, $allowed, true)) {
    $page = '/index.php';
}

$meta = get_page_meta($pdo, $page);
$defaults = [
    'title' => '',
    'description' => '',
    'keywords' => '',
    'og_title' => '',
    'og_description' => '',
    'og_image' => '',
    'og_image_srcset_webp' => '',
    'og_image_srcset_jpg' => ''
];
$meta = array_merge($defaults, $meta);
$metaDir = __DIR__ . '/../profile/meta';
if (!is_dir($metaDir)) {
    mkdir($metaDir, 0777, true);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
    $pageTitle = 'Page Meta';
    $versionFile = __DIR__ . '/../version.txt';
    $version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1';
    $versionParam = htmlspecialchars($version, ENT_QUOTES);
    $headExtras = '<link rel="stylesheet" href="/css/admin.css?v=' . $versionParam . '">' . "\n" .
                  '<script src="/js/admin-session.js?v=' . $versionParam . '" defer></script>';
    $pageDescription = $pageDescription ?? '';
    $pageKeywords = $pageKeywords ?? '';
?>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
<?php if ($pageDescription !== ''): ?>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>" />
<?php endif; ?>
<?php if ($pageKeywords !== ''): ?>
    <meta name="keywords" content="<?= htmlspecialchars($pageKeywords) ?>" />
<?php endif; ?>
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/css/style.css?v=<?= htmlspecialchars($version) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <?= $headExtras ?>
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/favicon-16x16.png">
    <link rel="manifest" href="/site.webmanifest">
    <link rel="icon" href="/favicon.ico">
</head>
<body>
<h1>Page Meta</h1>
<form action="save_meta.php" method="post" enctype="multipart/form-data">
    <input type="hidden" name="page" value="<?= htmlspecialchars($page) ?>">
    <label>Title: <input type="text" name="title" value="<?= htmlspecialchars($meta['title']) ?>"></label><br>
    <label>Description:<br><textarea name="description" rows="3" cols="60"><?= htmlspecialchars($meta['description']) ?></textarea></label><br>
    <label>Keywords: <input type="text" name="keywords" value="<?= htmlspecialchars($meta['keywords']) ?>"></label><br>
    <label>OG Title: <input type="text" name="og_title" value="<?= htmlspecialchars($meta['og_title']) ?>"></label><br>
    <label>OG Description:<br><textarea name="og_description" rows="3" cols="60"><?= htmlspecialchars($meta['og_description']) ?></textarea></label><br>
    <label>OG Image: <input type="file" name="og_image"></label>
    <?php if ($meta['og_image']): ?>
        <img src="<?= htmlspecialchars(cache_bust($meta['og_image'])) ?>" alt="og" height="80">
    <?php endif; ?>
    <input type="hidden" name="existing_image" value="<?= htmlspecialchars($meta['og_image']) ?>">
    <input type="hidden" name="existing_srcset_webp" value="<?= htmlspecialchars($meta['og_image_srcset_webp']) ?>">
    <input type="hidden" name="existing_srcset_jpg" value="<?= htmlspecialchars($meta['og_image_srcset_jpg']) ?>">
    <button type="submit">Save</button>
</form>
<p>Edit page:
<?php foreach ($allowed as $p): ?>
    <a href="meta_edit.php?page=<?= urlencode($p) ?>"<?= $p === $page ? ' style="font-weight:bold"' : '' ?>><?= htmlspecialchars($p) ?></a>
<?php endforeach; ?>
</p>
<p><a href="index.php">Back</a></p>
</body>
</html>

