<?php
$versionFile = __DIR__ . '/../../version.txt';
$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1';
require_once __DIR__ . '/../../includes/utils.php';

$playlists = [];
foreach (glob(__DIR__ . '/*', GLOB_ONLYDIR) as $dir) {
    $manifestPath = $dir . '/manifest.json';
    if (!file_exists($manifestPath)) {
        continue;
    }
    $data = json_decode(file_get_contents($manifestPath), true);
    if (!is_array($data)) {
        continue;
    }
    $live = isset($data['live']) ? (bool) $data['live'] : true;
    $comingSoon = isset($data['comingSoon']) ? (bool) $data['comingSoon'] : false;
    if (!$live && !$comingSoon) {
        continue;
    }
    $slug = basename($dir);
    $basePath = '/discography/playlists/' . $slug . '/';
    $cover = trim((string) ($data['cover'] ?? ''));
    $coverBlur = trim((string) ($data['coverBlur'] ?? ''));
    $srcsetWebp = trim((string) ($data['coverSrcsetWebp'] ?? ''));
    $srcsetJpg = trim((string) ($data['coverSrcsetJpg'] ?? ($data['coverSrcset'] ?? '')));
    $tracksData = is_array($data['tracks'] ?? null) ? $data['tracks'] : [];
    $trackCount = count($tracksData);
    $runtimeSeconds = 0;
    foreach ($tracksData as $track) {
        if (!empty($track['length']) && preg_match('/(\d+):(\d+)/', (string) $track['length'], $m)) {
            $runtimeSeconds += ((int) $m[1]) * 60 + (int) $m[2];
        }
    }
    $runtimeLabel = '';
    if ($runtimeSeconds > 0) {
        $minutes = floor($runtimeSeconds / 60);
        $seconds = $runtimeSeconds % 60;
        $runtimeLabel = sprintf('%d:%02d', $minutes, $seconds);
    }
    $playlists[] = [
        'slug' => $slug,
        'title' => $data['title'] ?? $slug,
        'description' => $data['description'] ?? '',
        'cover' => $cover ? $basePath . ltrim($cover, '/') : null,
        'coverBlur' => $coverBlur ? $basePath . ltrim($coverBlur, '/') : null,
        'srcsetWebp' => $srcsetWebp,
        'srcsetJpg' => $srcsetJpg,
        'trackCount' => $trackCount,
        'runtime' => $runtimeLabel,
        'comingSoon' => $comingSoon,
        'displayOrder' => $data['displayOrder'] ?? 0,
    ];
}
usort($playlists, function ($a, $b) {
    return $b['displayOrder'] <=> $a['displayOrder'];
});

$siteName = echopress_site_name();
$pageTitle = 'Playlists - ' . $siteName;
$pageDesc = 'Curated playlists from ' . $siteName . '.';
$baseUrl = echopress_base_url();
if ($baseUrl === '') {
    $baseUrl = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . ($_SERVER['HTTP_HOST'] ?? '');
}
$ogImage = rtrim($baseUrl, '/') . '/images/site-og-image.jpg';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title><?= htmlspecialchars($pageTitle) ?></title>
  <meta name="description" content="<?= htmlspecialchars($pageDesc) ?>" />
  <meta property="og:type" content="website" />
  <meta property="og:title" content="<?= htmlspecialchars($pageTitle) ?>" />
  <meta property="og:description" content="<?= htmlspecialchars($pageDesc) ?>" />
  <meta property="og:url" content="<?= htmlspecialchars(rtrim($baseUrl, '/') . '/discography/playlists/') ?>" />
  <meta property="og:image" content="<?= htmlspecialchars($ogImage) ?>" />
  <link rel="stylesheet" href="/css/style.css?v=<?= htmlspecialchars($version) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/analyticstracking.php'; ?>
</head>
<body class="discography-page">
  <?php include __DIR__ . '/../../includes/header.php'; ?>
  <main class="container">
    <section class="albums">
      <div class="section-header">
        <h2>Playlists</h2>
      </div>
      <ul class="album-list">
        <?php foreach ($playlists as $playlist): ?>
          <li class="album-item<?= $playlist['comingSoon'] ? ' coming-soon' : '' ?>">
            <a href="/discography/playlists/<?= urlencode($playlist['slug']) ?>/"<?= $playlist['comingSoon'] ? ' class="disabled"' : '' ?>>
              <?php if ($playlist['comingSoon'] && $playlist['coverBlur']): ?>
                <img src="<?= htmlspecialchars(cache_bust($playlist['coverBlur'])) ?>" alt="<?= htmlspecialchars($playlist['title']) ?> cover" width="160">
              <?php elseif ($playlist['cover']): ?>
                <picture>
                  <?php if (!empty($playlist['srcsetWebp'])): ?>
                    <source type="image/webp" srcset="<?= format_srcset($playlist['srcsetWebp'], '/discography/playlists/' . $playlist['slug'] . '/') ?>" sizes="160px">
                  <?php endif; ?>
                  <?php if (!empty($playlist['srcsetJpg'])): ?>
                    <source type="image/jpeg" srcset="<?= format_srcset($playlist['srcsetJpg'], '/discography/playlists/' . $playlist['slug'] . '/') ?>" sizes="160px">
                  <?php endif; ?>
                  <img src="<?= htmlspecialchars(cache_bust($playlist['cover'])) ?>" alt="<?= htmlspecialchars($playlist['title']) ?> cover" width="160">
                </picture>
              <?php else: ?>
                <div class="album-cover-placeholder"><i class="fa-solid fa-compact-disc"></i></div>
              <?php endif; ?>
              <div class="album-meta">
                <div class="album-title"><?= htmlspecialchars($playlist['title']) ?></div>
                <div class="album-info">
                  <?= $playlist['trackCount'] ?> <?= $playlist['trackCount'] === 1 ? 'track' : 'tracks' ?>
                  <?php if ($playlist['runtime']): ?> · <?= htmlspecialchars($playlist['runtime']) ?><?php endif; ?>
                </div>
              </div>
              <?php if ($playlist['comingSoon']): ?>
                <span class="coming-soon-overlay">Coming Soon</span>
              <?php endif; ?>
            </a>
          </li>
        <?php endforeach; ?>
        <?php if (!$playlists): ?>
          <li>No playlists available yet.</li>
        <?php endif; ?>
      </ul>
    </section>
  </main>
  <?php include __DIR__ . '/../../includes/footer.php'; ?>
</body>
</html>
