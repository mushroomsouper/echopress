<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/utils.php';

$versionFile = __DIR__ . '/version.txt';
$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '0';

$album = isset($_GET['album']) ? basename($_GET['album']) : '';
$trackParam = $_GET['track'] ?? '';

if (!$album || $trackParam === '') {
    // Fallback: pick a song from top_songs.json
    $fallback = __DIR__ . '/top_songs.json';
    if (file_exists($fallback)) {
        $top = json_decode(file_get_contents($fallback), true);
        if (is_array($top) && $top) {
            $choice = $top[array_rand($top)];
            $album = basename($choice['album'] ?? '');
            $trackParam = (string)($choice['track'] ?? '1');
        }
    }
}

if (!$album || $trackParam === '') {
    echo '<p>Missing album or track parameter.</p>';
    return;
}

$albumFolderPath = '/discography/albums/' . $album . '/';
$manifestPath = __DIR__ . $albumFolderPath . 'manifest.json';
if (!file_exists($manifestPath)) {
    echo '<p>Album not found.</p>';
    return;
}
$manifest = json_decode(file_get_contents($manifestPath), true);
$tracks = $manifest['tracks'] ?? [];
$artist = $manifest['artist'] ?? 'A Boy And His Computer';

$trackIndex = null;
if (is_numeric($trackParam)) {
    $idx = (int)$trackParam - 1;
    if (isset($tracks[$idx])) $trackIndex = $idx;
}
if ($trackIndex === null) {
    $slug = slugify($trackParam);
    foreach ($tracks as $i => $t) {
        if (slugify($t['title'] ?? '') === $slug) {
            $trackIndex = $i;
            break;
        }
    }
}
if ($trackIndex === null) {

    echo '<p>Track not found.</p>';
    return;
}
$selected = $tracks[$trackIndex];
$selected['albumTitle'] = $manifest['albumTitle'] ?? '';
$selected['albumSlug'] = $album;
$selected['albumFolder'] = $albumFolderPath;
$selected['albumCover'] = $manifest['cover'] ?? '';
$selected['artist'] = $artist;

$playlist = [$selected];

// Build a list of all tracks from every album
$albums = [];
foreach (glob(__DIR__ . '/discography/albums/*', GLOB_ONLYDIR) as $dir) {
    $mPath = $dir . '/manifest.json';
    if (!file_exists($mPath)) continue;
    $m = json_decode(file_get_contents($mPath), true);
    if (!is_array($m)) continue;
    $albums[] = [
        'slug' => basename($dir),
        'volume' => isset($m['volume']) ? (int)$m['volume'] : 0,
        'cover' => $m['cover'] ?? '',
        'title' => $m['albumTitle'] ?? basename($dir),
        'artist' => $m['artist'] ?? 'A Boy And His Computer',
        'tracks' => $m['tracks'] ?? []
    ];
}

usort($albums, function ($a, $b) {
    return $a['volume'] <=> $b['volume'];
});

foreach ($albums as $al) {
    $folder = '/discography/albums/' . $al['slug'] . '/';
    foreach ($al['tracks'] as $idx => $tr) {
        if ($al['slug'] === $album && $idx === $trackIndex) continue; // skip duplicate
        $tr['albumTitle'] = $al['title'];
        $tr['albumSlug'] = $al['slug'];
        $tr['albumFolder'] = $folder;
        $tr['albumCover'] = $al['cover'];
        $tr['artist'] = $al['artist'];
        $playlist[] = $tr;
    }
}


$pageTitle = ($selected['albumTitle'] ?? '')
    ? ($selected['title'] ?? 'Song') . ' - ' . $selected['albumTitle']
    : ($selected['title'] ?? 'Song');
$description = 'Listen to ' . ($selected['title'] ?? 'this song') .
    ' from the album ' . ($selected['albumTitle'] ?? '');

$host = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . $_SERVER['HTTP_HOST'];
$coverImage = $host . $albumFolderPath . ($manifest['cover'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($description) ?>">
  <meta property="og:type" content="music.song">
  <meta property="og:site_name" content="A Boy And His Computer">
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($description) ?>">
  <meta property="og:url" content="<?= htmlspecialchars($host . $_SERVER['REQUEST_URI']) ?>">
  <meta property="og:image" content="<?= htmlspecialchars($coverImage) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <link rel="stylesheet" href="/css/album-player.css?v=<?php echo $version; ?>" />
  <link rel="apple-touch-icon" sizes="180x180" href="/profile/favicon/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/profile/favicon/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/profile/favicon/favicon-16x16.png">
  <link rel="manifest" href="/profile/favicon/site.webmanifest">
  <link rel="icon" href="/profile/favicon/favicon.ico">
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/analyticstracking.php'; ?>
</head>
<body>
<?php
  include __DIR__ . '/includes/song-player.php';
?>
</body>
</html>

