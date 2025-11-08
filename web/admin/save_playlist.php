<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
set_time_limit(0);
ignore_user_abort(true);

require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/srcset.php';
require_once __DIR__ . '/sitemap.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/app.php';
$defaultArtistName = echopress_artist_name();

function check_mime(string $tmp, array $allowed): bool
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    foreach ($allowed as $prefix) {
        if (strpos($mime, $prefix) === 0) {
            return true;
        }
    }
    return false;
}

function create_blur_image(string $src, string $dest): void
{
    if (!is_file($src)) {
        return;
    }
    if (extension_loaded('imagick')) {
        $img = new Imagick($src);
        $img->gaussianBlurImage(0, 25);
        $img->setImageFormat('jpeg');
        $img->setImageCompressionQuality(90);
        $img->writeImage($dest);
        $img->destroy();
        return;
    }
    if (function_exists('imagecreatetruecolor')) {
        $data = file_get_contents($src);
        if ($data === false) {
            return;
        }
        $img = imagecreatefromstring($data);
        if (!$img) {
            return;
        }
        for ($i = 0; $i < 3; $i++) {
            imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);
        }
        imagejpeg($img, $dest);
        imagedestroy($img);
        return;
    }
    copy($src, $dest);
}

$errors = [];

$playlistTitle = trim($_POST['playlistTitle'] ?? '');
$artist = trim($_POST['artist'] ?? $defaultArtistName);
$description = trim($_POST['description'] ?? '');
$displayOrder = isset($_POST['display_order']) ? (int) $_POST['display_order'] : 0;
$languages = isset($_POST['languages']) ? array_filter(array_map('trim', (array) $_POST['languages'])) : [];
$genre = trim($_POST['genre'] ?? '');

if ($playlistTitle === '') {
    $errors[] = 'Title is required.';
}

$rawTracks = $_POST['tracks'] ?? [];
$trackIds = [];
foreach ((array) $rawTracks as $tid) {
    $tid = (int) $tid;
    if ($tid > 0) {
        $trackIds[] = $tid;
    }
}
if (!$trackIds) {
    $errors[] = 'Select at least one track for the playlist.';
}

$original = trim($_POST['original_name'] ?? '');
$existingCover = trim($_POST['existing_cover'] ?? '');
$existingBackground = trim($_POST['existing_background'] ?? '');
$existingBackgroundImage = trim($_POST['existing_background_image'] ?? '');
$existingFont = trim($_POST['existing_font'] ?? '');

$slugSource = slugify($playlistTitle);
if ($slugSource === '') {
    $slugSource = 'playlist_' . time();
}
$folderSlug = $slugSource;
if ($original && $original !== $folderSlug) {
    $folderSlug = $slugSource;
}
if ($original === '') {
    $folderSlug = $slugSource;
}

$folder = $original ?: $folderSlug;

if ($original && $folderSlug !== $original) {
    $oldPath = $PLAYLISTS_DIR . '/' . $original;
    $newPath = $PLAYLISTS_DIR . '/' . $folderSlug;
    if (is_dir($oldPath)) {
        rename($oldPath, $newPath);
    }
    $folder = $folderSlug;
}

$playlistPath = $PLAYLISTS_DIR . '/' . $folder;
if (!is_dir($playlistPath)) {
    mkdir($playlistPath, 0777, true);
}
$assetDir = $playlistPath . '/assets';
if (!is_dir($assetDir)) {
    mkdir($assetDir, 0777, true);
}
$additionalDir = $assetDir . '/additional';
if (!is_dir($additionalDir)) {
    mkdir($additionalDir, 0777, true);
}
$cssDir = $playlistPath . '/css';
if (!is_dir($cssDir)) {
    mkdir($cssDir, 0777, true);
}

$manifestPath = $playlistPath . '/manifest.json';
$existingManifest = [];
if (is_file($manifestPath)) {
    $decoded = json_decode(file_get_contents($manifestPath), true);
    if (is_array($decoded)) {
        $existingManifest = $decoded;
    }
}

$manifest = array_merge([
    'title' => '',
    'artist' => $defaultArtistName,
    'description' => '',
    'displayOrder' => 0,
    'live' => true,
    'comingSoon' => false,
    'themeColor' => '#333333',
    'textColor' => '#ffffff',
    'backgroundColor' => '#000000',
    'background' => '',
    'backgroundImage' => '',
    'cover' => '',
    'coverSrcsetWebp' => '',
    'coverSrcsetJpg' => '',
    'coverBlur' => '',
    'font' => '',
    'genre' => '',
    'languages' => [],
    'type' => 'playlist',
    'tracks' => []
], $existingManifest);

$manifest['title'] = $playlistTitle;
$manifest['artist'] = $artist !== '' ? $artist : $defaultArtistName;
$manifest['description'] = $description;
$manifest['displayOrder'] = $displayOrder;
$manifest['live'] = isset($_POST['live']);
$manifest['comingSoon'] = isset($_POST['comingSoon']);
$manifest['themeColor'] = $_POST['themeColor'] ?? '#333333';
$manifest['textColor'] = $_POST['textColor'] ?? '#ffffff';
$manifest['backgroundColor'] = $_POST['backgroundColor'] ?? '#000000';
$manifest['genre'] = $genre;
$manifest['languages'] = $languages;

if (!empty($_FILES['cover']['tmp_name'])) {
    if (!check_mime($_FILES['cover']['tmp_name'], ['image/'])) {
        $errors[] = 'Cover must be an image file.';
    } else {
        foreach (glob($assetDir . '/cover*') as $old) {
            if (is_file($old)) {
                unlink($old);
            }
        }
        list($fallback, $webpSet, $jpgSet) = create_image_set(
            $_FILES['cover']['tmp_name'],
            $assetDir,
            'cover',
            $_FILES['cover']['name'] ?? '',
            'assets/'
        );
        $manifest['cover'] = $fallback;
        $manifest['coverSrcsetWebp'] = $webpSet;
        $manifest['coverSrcsetJpg'] = $jpgSet;
        if ($fallback) {
            $ext = pathinfo($fallback, PATHINFO_EXTENSION);
            $blurName = 'cover_blur.' . $ext;
            create_blur_image($assetDir . '/' . basename($fallback), $assetDir . '/' . $blurName);
            $manifest['coverBlur'] = 'assets/' . $blurName;
        }
    }
} elseif ($existingCover) {
    $manifest['cover'] = $existingCover;
}

if (!empty($_FILES['background']['tmp_name'])) {
    if (!check_mime($_FILES['background']['tmp_name'], ['video/'])) {
        $errors[] = 'Background must be a video file.';
    } else {
        foreach (glob($assetDir . '/background.*') as $old) {
            if (is_file($old)) {
                unlink($old);
            }
        }
        $bgName = 'background.' . pathinfo($_FILES['background']['name'], PATHINFO_EXTENSION);
        move_uploaded_file($_FILES['background']['tmp_name'], $assetDir . '/' . $bgName);
        $manifest['background'] = 'assets/' . $bgName;
    }
} elseif ($existingBackground) {
    $manifest['background'] = $existingBackground;
}

if (!empty($_FILES['background_image']['tmp_name'])) {
    if (!check_mime($_FILES['background_image']['tmp_name'], ['image/'])) {
        $errors[] = 'Background image must be an image file.';
    } else {
        foreach (glob($assetDir . '/background_image.*') as $old) {
            if (is_file($old)) {
                unlink($old);
            }
        }
        $bgImageName = 'background_image.' . pathinfo($_FILES['background_image']['name'], PATHINFO_EXTENSION);
        move_uploaded_file($_FILES['background_image']['tmp_name'], $assetDir . '/' . $bgImageName);
        $manifest['backgroundImage'] = 'assets/' . $bgImageName;
    }
} elseif ($existingBackgroundImage) {
    $manifest['backgroundImage'] = $existingBackgroundImage;
}

if (!empty($_FILES['font']['tmp_name'])) {
    if (!check_mime($_FILES['font']['tmp_name'], ['font/', 'application/font', 'application/x-font', 'application/octet-stream'])) {
        $errors[] = 'Invalid font file type.';
    } else {
        if ($existingFont) {
            $oldFont = $assetDir . '/' . basename($existingFont);
            if (is_file($oldFont)) {
                unlink($oldFont);
            }
        }
        $fontName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($_FILES['font']['name']));
        $fontDest = $assetDir . '/' . $fontName;
        move_uploaded_file($_FILES['font']['tmp_name'], $fontDest);
        $manifest['font'] = $fontName;
    }
} elseif ($existingFont) {
    $manifest['font'] = $existingFont;
}

$customCss = trim($_POST['custom_css'] ?? '');
$customHtml = trim($_POST['custom_html'] ?? '');

if ($errors) {
    $_SESSION['playlist_errors'] = $errors;
    $_SESSION['playlist_old_input'] = $_POST;
    $redirect = 'playlist_edit.php';
    if ($original) {
        $redirect .= '?playlist=' . urlencode($original);
    }
    header('Location: ' . $redirect);
    exit;
}

$placeholders = implode(',', array_fill(0, count($trackIds), '?'));
$trackStmt = $pdo->prepare(
    "SELECT at.id, at.track_number, at.title, at.length, at.file, at.lyrics, at.explicit, at.artist AS track_artist, at.year, at.genre AS track_genre, a.slug AS album_slug, a.albumTitle, a.artist AS album_artist
     FROM album_tracks at
     JOIN albums a ON at.album_id = a.id
     WHERE at.id IN ($placeholders)"
);
$trackStmt->execute($trackIds);
$tracksData = [];
while ($row = $trackStmt->fetch(PDO::FETCH_ASSOC)) {
    $tracksData[(int) $row['id']] = $row;
}

$albumManifestCache = [];
$tracksForManifest = [];
foreach ($trackIds as $index => $trackId) {
    if (!isset($tracksData[$trackId])) {
        continue;
    }
    $row = $tracksData[$trackId];
    $albumSlug = $row['album_slug'];
    if (!isset($albumManifestCache[$albumSlug])) {
        $albumManifestPath = __DIR__ . '/../discography/albums/' . $albumSlug . '/manifest.json';
        $albumData = [];
        if (is_file($albumManifestPath)) {
            $decodedAlbum = json_decode(file_get_contents($albumManifestPath), true);
            if (is_array($decodedAlbum)) {
                $albumData = $decodedAlbum;
            }
        }
        $albumManifestCache[$albumSlug] = $albumData;
    }
    $albumData = $albumManifestCache[$albumSlug];
    $albumFolder = '/discography/albums/' . $albumSlug . '/';
    $audioRel = ltrim($row['file'] ?? '', '/');
    $audioPath = $audioRel !== '' ? $albumFolder . $audioRel : '';
    $trackEntry = [
        'id' => $trackId,
        'title' => $row['title'],
        'length' => $row['length'],
        'explicit' => (bool) ($row['explicit'] ?? false),
        'lyrics' => $row['lyrics'] ?? '',
        'trackNumber' => (int) ($row['track_number'] ?? ($index + 1)),
        'album' => [
            'slug' => $albumSlug,
            'title' => $row['albumTitle'],
            'artist' => $row['album_artist'],
            'cover' => isset($albumData['cover']) && $albumData['cover']
                ? $albumFolder . $albumData['cover']
                : null,
            'coverBlur' => isset($albumData['coverBlur']) && $albumData['coverBlur']
                ? $albumFolder . $albumData['coverBlur']
                : null,
            'coverSrcsetWebp' => $albumData['coverSrcsetWebp'] ?? '',
            'coverSrcsetJpg' => $albumData['coverSrcsetJpg'] ?? '',
        ],
        'audio' => $audioPath,
        'position' => $index
    ];
    if (!empty($row['track_artist']) && $row['track_artist'] !== $row['album_artist']) {
        $trackEntry['artist'] = $row['track_artist'];
    }
    if (!empty($row['year'])) {
        $trackEntry['year'] = $row['year'];
    }
    if (!empty($row['track_genre'])) {
        $trackEntry['genre'] = $row['track_genre'];
    }
    $tracksForManifest[] = $trackEntry;
}

$manifest['tracks'] = $tracksForManifest;

file_put_contents($manifestPath, json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

$cssContent = '';
if (!empty($manifest['font'])) {
    $fontFamily = $folder . 'Font';
    $cssContent .= "@font-face {\n" .
        "    font-family: '{$fontFamily}';\n" .
        "    src: url('../assets/{$manifest['font']}') format('woff2');\n" .
        "    font-weight: normal;\n" .
        "    font-style: normal;\n" .
        "    font-display: swap;\n" .
        "}\n\n" .
        "body {\n    font-family: '{$fontFamily}', serif;\n}\n";
}
if ($customCss !== '') {
    $cssContent .= "\n" . $customCss . "\n";
}
$cssPath = $cssDir . '/style.css';
if ($cssContent !== '') {
    file_put_contents($cssPath, $cssContent);
} elseif (is_file($cssPath)) {
    unlink($cssPath);
}

$customHtmlPath = $playlistPath . '/custom.html';
if ($customHtml !== '') {
    file_put_contents($customHtmlPath, $customHtml);
} elseif (is_file($customHtmlPath)) {
    unlink($customHtmlPath);
}

$pdo->beginTransaction();
try {
    $languagesCsv = $languages ? implode(',', $languages) : null;
    $background = $manifest['background'] ?: null;
    $backgroundImage = $manifest['backgroundImage'] ?: null;
    $cover = $manifest['cover'] ?: null;
    $coverSrcsetWebp = $manifest['coverSrcsetWebp'] ?: null;
    $coverSrcsetJpg = $manifest['coverSrcsetJpg'] ?: null;
    $coverBlur = $manifest['coverBlur'] ?: null;
    $font = $manifest['font'] ?: null;

    $stmt = $pdo->prepare('SELECT id FROM playlists WHERE slug = ?');
    $stmt->execute([$original ?: $folder]);
    $playlistId = $stmt->fetchColumn();

    if ($playlistId) {
        $update = $pdo->prepare('UPDATE playlists SET slug=?, title=?, artist=?, description=?, display_order=?, live=?, comingSoon=?, themeColor=?, textColor=?, backgroundColor=?, background=?, backgroundImage=?, cover=?, cover_srcset_webp=?, cover_srcset_jpg=?, cover_blur=?, font=?, genre=?, languages=? WHERE id=?');
        $update->execute([
            $folder,
            $manifest['title'],
            $manifest['artist'],
            $description,
            $displayOrder,
            $manifest['live'] ? 1 : 0,
            $manifest['comingSoon'] ? 1 : 0,
            $manifest['themeColor'],
            $manifest['textColor'],
            $manifest['backgroundColor'],
            $background,
            $backgroundImage,
            $cover,
            $coverSrcsetWebp,
            $coverSrcsetJpg,
            $coverBlur,
            $font,
            $genre,
            $languagesCsv,
            $playlistId
        ]);
        $pdo->prepare('DELETE FROM playlist_tracks WHERE playlist_id = ?')->execute([$playlistId]);
    } else {
        $insert = $pdo->prepare('INSERT INTO playlists (slug, title, artist, description, display_order, live, comingSoon, themeColor, textColor, backgroundColor, background, backgroundImage, cover, cover_srcset_webp, cover_srcset_jpg, cover_blur, font, genre, languages) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
        $insert->execute([
            $folder,
            $manifest['title'],
            $manifest['artist'],
            $description,
            $displayOrder,
            $manifest['live'] ? 1 : 0,
            $manifest['comingSoon'] ? 1 : 0,
            $manifest['themeColor'],
            $manifest['textColor'],
            $manifest['backgroundColor'],
            $background,
            $backgroundImage,
            $cover,
            $coverSrcsetWebp,
            $coverSrcsetJpg,
            $coverBlur,
            $font,
            $genre,
            $languagesCsv
        ]);
        $playlistId = $pdo->lastInsertId();
    }

    $trackInsert = $pdo->prepare('INSERT INTO playlist_tracks (playlist_id, track_id, position) VALUES (?,?,?)');
    foreach ($trackIds as $pos => $trackId) {
        $trackInsert->execute([$playlistId, $trackId, $pos]);
    }

    $additionalUploaded = 0;
    if (!empty($_FILES['additional_assets']) && is_array($_FILES['additional_assets']['tmp_name'])) {
        foreach ($_FILES['additional_assets']['tmp_name'] as $idx => $tmp) {
            if (!$tmp) {
                continue;
            }
            $origName = $_FILES['additional_assets']['name'][$idx] ?? '';
            $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($origName));
            $dest = $additionalDir . '/' . $cleanName;
            if (!move_uploaded_file($tmp, $dest)) {
                continue;
            }
            $additionalUploaded++;
            $pdo->prepare('INSERT INTO playlist_assets (playlist_id, filename) VALUES (?, ?)')->execute([
                $playlistId,
                $cleanName
            ]);
        }
    }

    $pdo->commit();
} catch (Throwable $e) {
    $pdo->rollBack();
    $_SESSION['playlist_errors'] = ['Failed to save playlist: ' . $e->getMessage()];
    $_SESSION['playlist_old_input'] = $_POST;
    $redirect = 'playlist_edit.php';
    if ($original) {
        $redirect .= '?playlist=' . urlencode($original);
    }
    header('Location: ' . $redirect);
    exit;
}

$trackCount = count($tracksForManifest);
$runtimeSeconds = 0;
$hasExplicitTrack = false;
foreach ($tracksForManifest as $trackMeta) {
    if (!empty($trackMeta['length']) && preg_match('/(\d+):(\d+)/', (string) $trackMeta['length'], $m)) {
        $runtimeSeconds += ((int) $m[1]) * 60 + (int) $m[2];
    }
    if (!$hasExplicitTrack && !empty($trackMeta['explicit'])) {
        $hasExplicitTrack = true;
    }
}
$runtimeMinutes = $runtimeSeconds > 0 ? floor($runtimeSeconds / 60) : 0;
$runtimeRemainder = $runtimeSeconds > 0 ? $runtimeSeconds % 60 : 0;
$runtimeLabel = sprintf('%d:%02d', $runtimeMinutes, $runtimeRemainder);

$versionFilePublic = $_SERVER['DOCUMENT_ROOT'] . '/version.txt';
$assetVersionPublic = file_exists($versionFilePublic) ? trim(file_get_contents($versionFilePublic)) : time();
$playlistWebPath = '/discography/playlists/' . $folder . '/';
$pagePath = $playlistPath . '/index.php';

$liveSite = echopress_primary_url();
if ($liveSite === '') {
    $liveSite = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
}
$coverPathForOg = $manifest['cover'] ? $playlistWebPath . ltrim($manifest['cover'], '/') : '';
$ogImageVersion = '';
if ($coverPathForOg) {
    $coverFileForOg = $_SERVER['DOCUMENT_ROOT'] . $coverPathForOg;
    if (file_exists($coverFileForOg)) {
        $mt = filemtime($coverFileForOg);
        if ($mt !== false) {
            $ogImageVersion = '?v=' . $mt;
        }
    }
}
$ogImage = $coverPathForOg
    ? rtrim($liveSite, '/') . $coverPathForOg . $ogImageVersion
    : rtrim($liveSite, '/') . '/images/site-og-image.jpg';

$pageTitleText = trim(($manifest['title'] ?? 'Untitled Playlist') . ' - ' . ($manifest['artist'] ?? $defaultArtistName));
$metaDescription = $description !== ''
    ? $description
    : trim(($trackCount ?: '0') . ' track playlist curated by ' . ($manifest['artist'] ?? $defaultArtistName));
if ($runtimeSeconds > 0) {
    $metaDescription .= ' · ' . $runtimeLabel;
}

$metaKeywords = ($manifest['title'] ?? 'playlist') . ', ' . ($manifest['artist'] ?? $defaultArtistName);
$themeColor = $manifest['themeColor'] ?? '#333333';
$textColor = $manifest['textColor'] ?? '#ffffff';
$baseColor = $manifest['backgroundColor'] ?? '#05070f';
$pageTitleExport = addslashes($pageTitleText);
$metaDescriptionExport = addslashes($metaDescription);
$metaKeywordsExport = addslashes($metaKeywords);
$ogImageExport = addslashes($ogImage);
$ogUrlExport = addslashes(rtrim($liveSite, '/') . $playlistWebPath);

$pageContent = <<<PHP
<?php
/* automatically generated playlist player */
require_once \$_SERVER['DOCUMENT_ROOT'] . '/admin/session_secure.php';
session_start();
\$manifest = json_decode(file_get_contents(__DIR__ . '/manifest.json'), true);
\$live = \$manifest['live'] ?? true;
\$comingSoon = \$manifest['comingSoon'] ?? false;
if ((!\$live || \$comingSoon) && empty(\$_SESSION['logged_in'])) {
    include \$_SERVER['DOCUMENT_ROOT'] . '/404.php';
    exit;
}
\$playlistFolderPath = '{$playlistWebPath}';
\$versionFile = \$_SERVER['DOCUMENT_ROOT'] . '/version.txt';
\$assetVersion = file_exists(\$versionFile) ? trim(file_get_contents(\$versionFile)) : {$assetVersionPublic};
\$playlistMetaTitle = '{$pageTitleExport}';
\$playlistMetaDescription = '{$metaDescriptionExport}';
\$playlistMetaKeywords = '{$metaKeywordsExport}';
\$playlistOgImage = '{$ogImageExport}';
\$playlistOgUrl = '{$ogUrlExport}';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars(\$playlistMetaTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars(\$playlistMetaDescription) ?>" />
  <meta name="keywords" content="<?= htmlspecialchars(\$playlistMetaKeywords) ?>" />
  <meta property="og:type" content="music.playlist" />
  <meta property="og:title" content="<?= htmlspecialchars(\$playlistMetaTitle) ?>" />
  <meta property="og:description" content="<?= htmlspecialchars(\$playlistMetaDescription) ?>" />
  <meta property="og:url" content="<?= htmlspecialchars(\$playlistOgUrl) ?>" />
  <meta property="og:image" content="<?= htmlspecialchars(\$playlistOgImage) ?>" />
  <link rel="stylesheet" href="/css/style.css?v=<?= htmlspecialchars(\$assetVersion) ?>">
  <link rel="stylesheet" href="/css/song-share.css?v=<?= htmlspecialchars(\$assetVersion) ?>">
  <link rel="stylesheet" href="/css/playlist-player.css?v=<?= htmlspecialchars(\$assetVersion) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <style>
    :root {
      --playlist-theme: {$themeColor};
      --playlist-text: {$textColor};
      --playlist-base: {$baseColor};
    }
  </style>
  <?php include \$_SERVER['DOCUMENT_ROOT'] . '/includes/analyticstracking.php'; ?>
</head>
<body class="playlist-page">
<?php include \$_SERVER['DOCUMENT_ROOT'] . '/includes/playlist-player.php'; ?>
<script src="/js/share-links.js?v=<?= htmlspecialchars(\$assetVersion) ?>" defer></script>
<script src="/js/playlist-player.js?v=<?= htmlspecialchars(\$assetVersion) ?>" defer></script>
</body>
</html>
PHP;

file_put_contents($pagePath, $pageContent);
regenerate_sitemap($pdo);

$_SESSION['playlist_success'] = 'Playlist saved successfully.';
header('Location: playlists.php');
exit;
