<?php
/* automatically generated playlist player */
require_once $_SERVER['DOCUMENT_ROOT'] . '/admin/session_secure.php';
session_start();
require_once dirname(__DIR__, 3) . '/includes/app.php';
$manifest = json_decode(file_get_contents(__DIR__ . '/manifest.json'), true);
$live = $manifest['live'] ?? true;
$comingSoon = $manifest['comingSoon'] ?? false;
if ((!$live || $comingSoon) && empty($_SESSION['logged_in'])) {
    include $_SERVER['DOCUMENT_ROOT'] . '/404.php';
    exit;
}
$playlistFolderPath = '/discography/playlists/bawlers-mix/';
$versionFile = $_SERVER['DOCUMENT_ROOT'] . '/version.txt';
$assetVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : 20250219183809;
$baseUrl = echopress_base_url();
if ($baseUrl === '') {
    $baseUrl = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
}
$playlistTitle = trim((string) ($manifest['title'] ?? 'Playlist'));
$curator = trim((string) ($manifest['artist'] ?? echopress_artist_name()));
$trackCount = is_array($manifest['tracks'] ?? null) ? count($manifest['tracks']) : 0;
$playlistMetaTitle = ($playlistTitle !== '' ? $playlistTitle : 'Playlist') . ' - ' . echopress_site_name();
$playlistMetaDescription = trim(($trackCount ? $trackCount . ' track ' : '') . 'playlist curated by ' . ($curator !== '' ? $curator : echopress_artist_name()));
if ($playlistMetaDescription === '') {
    $playlistMetaDescription = 'Curated playlist powered by EchoPress.';
}
$playlistMetaKeywords = ($playlistTitle !== '' ? $playlistTitle . ', ' : '') . ($curator !== '' ? $curator : echopress_site_name());
$coverFile = trim((string) ($manifest['cover'] ?? ''));
$playlistOgImage = $coverFile !== ''
    ? rtrim($baseUrl, '/') . $playlistFolderPath . ltrim($coverFile, '/')
    : rtrim($baseUrl, '/') . '/images/site-og-image.jpg';
$playlistOgUrl = rtrim($baseUrl, '/') . $playlistFolderPath;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($playlistMetaTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($playlistMetaDescription) ?>" />
  <meta name="keywords" content="<?= htmlspecialchars($playlistMetaKeywords) ?>" />
  <meta property="og:type" content="music.playlist" />
  <meta property="og:title" content="<?= htmlspecialchars($playlistMetaTitle) ?>" />
  <meta property="og:description" content="<?= htmlspecialchars($playlistMetaDescription) ?>" />
  <meta property="og:url" content="<?= htmlspecialchars($playlistOgUrl) ?>" />
  <meta property="og:image" content="<?= htmlspecialchars($playlistOgImage) ?>" />
  <link rel="stylesheet" href="/css/style.css?v=<?= htmlspecialchars($assetVersion) ?>">
  <link rel="stylesheet" href="/css/song-share.css?v=<?= htmlspecialchars($assetVersion) ?>">
  <link rel="stylesheet" href="/css/playlist-player.css?v=<?= htmlspecialchars($assetVersion) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <style>
    :root {
      --playlist-theme: #333333;
      --playlist-text: #ffffff;
      --playlist-base: #000000;
    }
  </style>
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/analyticstracking.php'; ?>
</head>
<body class="playlist-page">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/playlist-player.php'; ?>
<script src="/js/share-links.js?v=<?= htmlspecialchars($assetVersion) ?>" defer></script>
<script src="/js/playlist-player.js?v=<?= htmlspecialchars($assetVersion) ?>" defer></script>
</body>
</html>
