<?php
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/includes/utils.php';

$versionFile = __DIR__ . '/version.txt';
$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '0';

$albumParam = isset($_GET['album']) ? basename((string) $_GET['album']) : '';
$trackParam = $_GET['track'] ?? '';

/**
 * Extract the requested song slug from the query string.
 */
function resolve_song_slug(array $get, string $trackParam): string
{
    if (isset($get['slug'])) {
        return slugify((string) $get['slug']);
    }
    if (isset($get['song'])) {
        return slugify((string) $get['song']);
    }

    // Handle query strings of the form /song.php?song-title-slug (no key)
    if (!empty($get)) {
        $firstKey = array_key_first($get);
        if ($firstKey !== null && $firstKey !== 'album' && $firstKey !== 'track') {
            // When hitting /song.php?slug with an explicit value, prefer the value.
            if ($get[$firstKey] === '' || $get[$firstKey] === null) {
                return slugify($firstKey);
            }
            return slugify((string) $get[$firstKey]);
        }
    }

    $queryString = trim($_SERVER['QUERY_STRING'] ?? '');
    if ($queryString !== '' && strpos($queryString, '=') === false) {
        return slugify($queryString);
    }

    if ($trackParam !== '') {
        return slugify((string) $trackParam);
    }

    return '';
}

/**
 * Load an album manifest by slug.
 */
function load_album_manifest(string $albumSlug): ?array
{
    static $cache = [];
    if (isset($cache[$albumSlug])) {
        return $cache[$albumSlug];
    }
    $manifestPath = __DIR__ . '/discography/albums/' . $albumSlug . '/manifest.json';
    if (!is_file($manifestPath)) {
        $cache[$albumSlug] = null;
        return null;
    }
    $json = json_decode(file_get_contents($manifestPath), true);
    $cache[$albumSlug] = is_array($json) ? $json : null;
    return $cache[$albumSlug];
}

/**
 * Attempt to fetch a track from a specific album by number or slug.
 */
function find_track_in_album(string $albumSlug, string $trackParam): array
{
    $manifest = load_album_manifest($albumSlug);
    if (!$manifest) {
        return [null, null];
    }
    $tracks = $manifest['tracks'] ?? [];
    if ($trackParam === '') {
        return [null, $manifest];
    }

    $match = null;
    if (is_numeric($trackParam)) {
        $idx = max(0, (int) $trackParam - 1);
        if (isset($tracks[$idx])) {
            $match = $tracks[$idx];
        }
    }
    if (!$match) {
        $slug = slugify((string) $trackParam);
        foreach ($tracks as $track) {
            if (slugify($track['title'] ?? '') === $slug) {
                $match = $track;
                break;
            }
        }
    }

    if ($match) {
        $match['__albumSlug'] = $albumSlug;
        $match['__trackIndex'] = array_search($match, $tracks, true);
    }

    return [$match, $manifest];
}

/**
 * Find a track by slug across the entire catalog.
 */
function find_track_by_slug(string $songSlug, string $preferredAlbum = ''): array
{
    if ($songSlug === '') {
        return [null, null];
    }

    if ($preferredAlbum !== '') {
        [$track, $manifest] = find_track_in_album($preferredAlbum, $songSlug);
        if ($track) {
            return [$track, $manifest];
        }
    }

    $albumsDir = __DIR__ . '/discography/albums';
    $directories = glob($albumsDir . '/*', GLOB_ONLYDIR) ?: [];

    foreach ($directories as $dir) {
        $albumSlug = basename($dir);
        $manifest = load_album_manifest($albumSlug);
        if (!$manifest) {
            continue;
        }
        foreach ($manifest['tracks'] ?? [] as $index => $track) {
            if (slugify($track['title'] ?? '') === $songSlug) {
                $track['__albumSlug'] = $albumSlug;
                $track['__trackIndex'] = $index;
                return [$track, $manifest];
            }
        }
    }

    return [null, null];
}

$songSlug = resolve_song_slug($_GET, (string) $trackParam);
$selectedTrack = null;
$selectedManifest = null;

if ($albumParam !== '' && $trackParam !== '') {
    [$track, $manifest] = find_track_in_album($albumParam, (string) $trackParam);
    if ($track) {
        $selectedTrack = $track;
        $selectedManifest = $manifest;
        $songSlug = slugify($track['title'] ?? $songSlug);
    }
}

if (!$selectedTrack && $songSlug !== '') {
    [$track, $manifest] = find_track_by_slug($songSlug, $albumParam);
    $selectedTrack = $track;
    $selectedManifest = $manifest;
}

// Fallback to top_songs.json when nothing specific is requested
if (!$selectedTrack && $songSlug === '') {
    $topSongsPath = __DIR__ . '/top_songs.json';
    if (is_file($topSongsPath)) {
        $topSongs = json_decode(file_get_contents($topSongsPath), true);
        if (is_array($topSongs) && $topSongs) {
            $choice = $topSongs[array_rand($topSongs)];
            $albumChoice = basename($choice['album'] ?? '');
            $trackChoice = (string) ($choice['track'] ?? '');
            [$track, $manifest] = find_track_in_album($albumChoice, $trackChoice);
            if ($track) {
                $selectedTrack = $track;
                $selectedManifest = $manifest;
                $songSlug = slugify($track['title'] ?? '');
            }
        }
    }
}

if (!$selectedTrack || !$selectedManifest) {
    http_response_code(404);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Song Not Found</title>
        <link rel="stylesheet" href="/css/song-share.css?v=<?= htmlspecialchars($version) ?>" />
    </head>
    <body class="song-share song-share--error">
      <div class="song-share__background song-share__background--fallback"></div>
      <div class="song-share__scrim"></div>
      <main class="song-share__content">
        <div class="song-card">
          <h1 class="song-card__title">Song Not Found</h1>
          <p class="song-card__subtitle">That share link doesn’t match any track in the catalog.</p>
          <div class="song-card__actions">
            <a class="song-card__button" href="/">Return Home</a>
            <a class="song-card__button song-card__button--ghost" href="/discography/">Browse Discography</a>
          </div>
        </div>
      </main>
    </body>
    </html>
    <?php
    exit;
}

$albumSlug = $selectedTrack['__albumSlug'];
$albumFolder = '/discography/albums/' . $albumSlug . '/';
$albumTitle = $selectedManifest['albumTitle'] ?? $albumSlug;
$artistName = $selectedManifest['artist'] ?? echopress_artist_name();
$coverPath = $selectedManifest['cover'] ?? '';
$backgroundImage = $selectedManifest['backgroundImage'] ?? $coverPath;
$audioPath = $selectedTrack['file'] ?? '';
$lyrics = trim((string) ($selectedTrack['lyrics'] ?? ''));
$explicit = !empty($selectedTrack['explicit']);
$duration = $selectedTrack['length'] ?? '';
$releaseDate = $selectedManifest['releaseDate'] ?? '';

$coverUrl = $coverPath ? $albumFolder . ltrim($coverPath, '/') : '';
$backgroundUrl = $backgroundImage ? $albumFolder . ltrim($backgroundImage, '/') : $coverUrl;
$audioUrl = $audioPath ? $albumFolder . ltrim($audioPath, '/') : '';
$albumUrl = $albumFolder;
$homeUrl = '/';

$trackTitle = $selectedTrack['title'] ?? 'Untitled';
$pageTitle = $trackTitle . ' – ' . $albumTitle;
$description = 'Listen to “' . $trackTitle . '” from the album “' . $albumTitle . '” by ' . $artistName . '.';
$canonicalSlug = slugify($trackTitle);
$canonicalUrl = $canonicalSlug !== '' ? '/song.php?' . rawurlencode($canonicalSlug) : $_SERVER['REQUEST_URI'];

$shareData = [
    'title' => $trackTitle,
    'albumTitle' => $albumTitle,
    'artist' => $artistName,
    'albumUrl' => $albumUrl,
    'homeUrl' => $homeUrl,
    'audioUrl' => $audioUrl,
    'coverUrl' => $coverUrl,
    'backgroundUrl' => $backgroundUrl,
    'lyrics' => $lyrics,
    'duration' => $duration,
    'explicit' => $explicit,
    'releaseDate' => $releaseDate,
    'slug' => $canonicalSlug,
];

$baseSongUrl = echopress_base_url();
if ($baseSongUrl === '') {
    $baseSongUrl = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
}
$fullBackground = $backgroundUrl ? rtrim($baseSongUrl, '/') . $backgroundUrl : '';
$fullCover = $coverUrl ? rtrim($baseSongUrl, '/') . $coverUrl : '';
$fullCanonical = rtrim($baseSongUrl, '/') . $canonicalUrl;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($description) ?>">
  <link rel="canonical" href="<?= htmlspecialchars($fullCanonical) ?>">

  <meta property="og:type" content="music.song">
  <meta property="og:site_name" content="<?= htmlspecialchars(echopress_site_name()) ?>">
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($description) ?>">
  <meta property="og:url" content="<?= htmlspecialchars($fullCanonical) ?>">
  <?php if ($fullCover): ?>
    <meta property="og:image" content="<?= htmlspecialchars($fullCover) ?>">
  <?php endif; ?>

  <meta name="twitter:card" content="summary_large_image">
  <meta name="twitter:title" content="<?= htmlspecialchars($pageTitle) ?>">
  <meta name="twitter:description" content="<?= htmlspecialchars($description) ?>">
  <?php if ($fullCover): ?>
    <meta name="twitter:image" content="<?= htmlspecialchars($fullCover) ?>">
  <?php endif; ?>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <link rel="stylesheet" href="/css/song-share.css?v=<?= htmlspecialchars($version) ?>">
  <link rel="apple-touch-icon" sizes="180x180" href="/profile/favicon/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/profile/favicon/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/profile/favicon/favicon-16x16.png">
  <link rel="manifest" href="/profile/favicon/site.webmanifest">
  <link rel="icon" href="/profile/favicon/favicon.ico">
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/analyticstracking.php'; ?>
</head>
<body class="song-share">
<?php
  $shareData['fullCover'] = $fullCover;
  $shareData['fullBackground'] = $fullBackground;
  include __DIR__ . '/includes/song-player.php';
?>
<script src="/js/song-share.js?v=<?= htmlspecialchars($version) ?>" defer></script>
</body>
</html>
