<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/app.php';
$manifestName = echopress_site_name();
$manifestShort = substr(preg_replace('/[^A-Za-z0-9 ]/', '', $manifestName), 0, 12);
if ($manifestShort === '') {
    $manifestShort = 'EchoPress';
}
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}
$error = '';
$manifestPath = __DIR__ . '/../profile/favicon/site.webmanifest';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!empty($_FILES['favicon']['tmp_name'])) {
        $tmp = $_FILES['favicon']['tmp_name'];
        $mime = mime_content_type($tmp);
        if (strpos($mime, 'image') === 0) {
            if (extension_loaded('imagick')) {
                try {
                    $img = new Imagick($tmp);
                    $img->setImageColorspace(Imagick::COLORSPACE_RGB);
                    $square = min($img->getImageWidth(), $img->getImageHeight());
                    $x = intdiv($img->getImageWidth() - $square, 2);
                    $y = intdiv($img->getImageHeight() - $square, 2);
                    $img->cropImage($square, $square, $x, $y);

                    $sizes = [
                        512 => 'favicon-512x512.png',
                        192 => 'android-chrome-192x192.png',
                        180 => 'apple-touch-icon.png',
                        32  => 'favicon-32x32.png',
                        16  => 'favicon-16x16.png'
                    ];
                    foreach ($sizes as $size => $name) {
                        $tmpImg = clone $img;
                        $tmpImg->resizeImage($size, $size, Imagick::FILTER_LANCZOS, 1);
                        $tmpImg->writeImage(__DIR__ . '/../profile/favicon/' . $name);
                        $tmpImg->destroy();
                    }

                    // ICO with common sizes
                    $ico = new Imagick();
                    foreach ([16,32,48] as $s) {
                        $icon = clone $img;
                        $icon->resizeImage($s, $s, Imagick::FILTER_LANCZOS, 1);
                        $ico->addImage($icon);
                    }
                    $ico->setImageFormat('ico');
                    $ico->writeImage(__DIR__ . '/../profile/favicon/favicon.ico');
                    $ico->destroy();
                    $img->destroy();

                    $manifest = [
                        'name' => $manifestName,
                        'short_name' => $manifestShort,
                        'start_url' => '/',
                        'display' => 'standalone',
                        'background_color' => '#ffffff',
                        'theme_color' => '#000000',
                        'icons' => [
                            ['src' => '/profile/favicon/android-chrome-192x192.png', 'sizes' => '192x192', 'type' => 'image/png'],
                            ['src' => '/profile/favicon/favicon-512x512.png', 'sizes' => '512x512', 'type' => 'image/png']
                        ]
                    ];
                    file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
                } catch (Exception $e) {
                    $error = 'Imagick failed to process image';
                }
            } else {
                move_uploaded_file($tmp, __DIR__ . '/../profile/favicon/favicon.ico');
                $manifest = [
                    'name' => $manifestName,
                    'short_name' => $manifestShort,
                    'start_url' => '/',
                    'display' => 'standalone',
                    'background_color' => '#ffffff',
                    'theme_color' => '#000000',
                    'icons' => [
                        ['src' => '/profile/favicon/favicon.ico', 'sizes' => 'any', 'type' => 'image/x-icon']
                    ]
                ];
                file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
            }
        } else {
            $error = 'Invalid file type';
        }
    }
}
$exists = file_exists(__DIR__ . '/../profile/favicon/favicon.ico');
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
$pageTitle = 'Manage Favicon';
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
<h1>Favicon</h1>
<?php if ($error): ?><p style="color:red"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<?php if ($exists): ?>
<p>Current favicon:</p>
<img src="<?= htmlspecialchars(cache_bust('/profile/favicon/favicon.ico')) ?>" alt="favicon" height="32">
<?php endif; ?>
<form method="post" enctype="multipart/form-data">
<input type="file" name="favicon" accept="image/*">
<button type="submit">Upload</button>
</form>
<p><a href="index.php">Back</a></p>
</body>
</html>
