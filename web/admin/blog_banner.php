<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/srcset.php';
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$bannerFile = __DIR__ . '/../blog/data/banner.json';
$imgDir = __DIR__ . '/../images';
if (!is_dir($imgDir)) {
    mkdir($imgDir, 0777, true);
}
$data = ['image' => '/images/blog_banner.jpg', 'srcsetWebp' => '', 'srcsetJpg' => ''];
if (file_exists($bannerFile)) {
    $json = json_decode(file_get_contents($bannerFile), true);
    if ($json) $data = array_merge($data, $json);
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['banner']['tmp_name'])) {
        foreach (glob("$imgDir/blog_banner*.{jpg,jpeg,png,gif,webp}", GLOB_BRACE) as $old) {
            if (is_file($old)) unlink($old);
        }
        list($path, $webp, $jpg) = create_image_set(
            $_FILES['banner']['tmp_name'],
            $imgDir,
            'blog_banner',
            $_FILES['banner']['name'] ?? '',
            '/images/'
        );
        if ($path) {
            $data['image'] = $path;
            $data['srcsetWebp'] = $webp;
            $data['srcsetJpg']  = $jpg;
            file_put_contents($bannerFile, json_encode($data, JSON_PRETTY_PRINT));
        }
    }
    header('Location: blog_banner.php');
    exit;
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
$pageTitle = 'Blog Banner';
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
    <link rel="apple-touch-icon" sizes="180x180" href="/profile/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/profile/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/profile/favicon/favicon-16x16.png">
    <link rel="manifest" href="/profile/favicon/site.webmanifest">
    <link rel="icon" href="/profile/favicon/favicon.ico">
</head>
<body>
<h1>Blog Banner</h1>
<?php if ($data['image']): ?>
    <p>Current banner:</p>
    <img src="<?= htmlspecialchars(cache_bust($data['image'])) ?>" alt="banner" style="max-width:100%;height:auto">
<?php endif; ?>
<form method="post" enctype="multipart/form-data">
    <label>Upload New Banner: <input type="file" name="banner" accept="image/*"></label>
    <button type="submit">Save</button>
</form>
<p><a href="blog.php">Back</a></p>
</body>
</html>
