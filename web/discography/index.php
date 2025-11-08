<?php
$versionFile = __DIR__ . '/../version.txt';
$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '0';
require_once __DIR__ . '/../admin/db_connect.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/page_meta.php';


$releases = [];
foreach (glob(__DIR__ . '/albums/*', GLOB_ONLYDIR) as $dir) {
  $manifest = $dir . '/manifest.json';
  if (!file_exists($manifest))
    continue;
  $data = json_decode(file_get_contents($manifest), true);
  $cover = trim($data['cover'] ?? '');
  $coverBlur = trim($data['coverBlur'] ?? '');
  $srcsetWebp = trim($data['coverSrcsetWebp'] ?? '');
  $srcsetJpg = trim($data['coverSrcsetJpg'] ?? ($data['coverSrcset'] ?? ''));
  $live = isset($data['live']) ? (bool) $data['live'] : true;
  $comingSoon = isset($data['comingSoon']) ? (bool) $data['comingSoon'] : false;
  $year = '';
  if (!empty($data['releaseDate'])) {
    $ts = strtotime($data['releaseDate']);
    if ($ts !== false)
      $year = date('Y', $ts);
  } elseif (!empty($data['year'])) {
    $year = $data['year'];
  }
  $releases[] = [
    'folder' => basename($dir),
    'title' => $data['albumTitle'] ?? basename($dir),
    'volume' => isset($data['volume']) ? (int) $data['volume'] : 0,
    'year' => $year,
    'cover' => $cover !== '' ? "/discography/albums/" . basename($dir) . "/" . $cover : null,
    'coverBlur' => $coverBlur !== '' ? "/discography/albums/" . basename($dir) . "/" . $coverBlur : null,
    'srcsetWebp' => $srcsetWebp,
    'srcsetJpg' => $srcsetJpg,
    'background' => $data['background'] ?? '',
    'backgroundImage' => $data['backgroundImage'] ?? '',
    'backgroundColor' => $data['backgroundColor'] ?? '#000',
    'live' => $live,
    'comingSoon' => $comingSoon,
    'type' => $data['type'] ?? 'album',
    'explicit' => album_has_explicit($pdo, basename($dir))
  ];
}
$releases = array_filter($releases, fn($a) => $a['live'] || $a['comingSoon']);
$albums = array_values(array_filter($releases, fn($r) => ($r['type'] ?? 'album') === 'album'));
$singles = array_values(array_filter($releases, fn($r) => ($r['type'] ?? 'album') !== 'album'));
usort($albums, function ($a, $b) {
  return $b['volume'] <=> $a['volume'];
});

$playlists = [];
foreach (glob(__DIR__ . '/playlists/*', GLOB_ONLYDIR) as $dir) {
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
    'displayOrder' => $data['displayOrder'] ?? 0,
    'comingSoon' => $comingSoon,
    'live' => $live,
  ];
}
usort($playlists, function ($a, $b) {
  return $b['displayOrder'] <=> $a['displayOrder'];
});

$appearances = [];
$stmt = $pdo->query('SELECT * FROM appearances WHERE released=1 OR comingSoon=1 ORDER BY appearance_order DESC');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $year = '';
  if (!empty($row['releaseDate'])) {
    $ts = strtotime($row['releaseDate']);
    if ($ts !== false)
      $year = date('Y', $ts);
  }
  $appearances[] = [
    'slug' => $row['slug'],
    'title' => $row['title'],
    'artist' => $row['artist'],
    'year' => $year,
    'url' => $row['url'],
    'comingSoon' => (bool) $row['comingSoon'],
    'released' => (bool) $row['released'],
    'cover' => $row['cover'] ? '/discography/appearances/' . $row['slug'] . '/' . $row['cover'] : null,
    'srcsetWebp' => $row['cover_srcset_webp'],
    'srcsetJpg' => $row['cover_srcset_jpg']
  ];
}
$videos = [];
$stmt = $pdo->query('SELECT * FROM videos ORDER BY video_order DESC, releaseDate DESC');
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $year = '';
  if (!empty($row['releaseDate'])) {
    $ts = strtotime($row['releaseDate']);
    if ($ts !== false)
      $year = date('Y', $ts);
  }
  $videos[] = [
    'slug' => $row['slug'],
    'title' => $row['title'],
    'artist' => $row['artist'],
    'year' => $year,
    'url' => $row['url'],
    'platform' => $row['platform'],
    'thumbnail' => $row['thumbnail'] ? '/videos/' . $row['slug'] . '/' . $row['thumbnail'] : null,
    'srcsetWebp' => $row['thumb_srcset_webp'],
    'srcsetJpg' => $row['thumb_srcset_jpg']
  ];
}

$pageMeta = get_page_meta($pdo, '/discography/index.php');
$siteName = echopress_site_name();
$metaTitle = $pageMeta['title'] ?? ('Discography - ' . $siteName);
$metaDesc = $pageMeta['description'] ?? ('Discography of ' . $siteName . '.');
$metaKeywords = $pageMeta['keywords'] ?? '';
$ogTitle = $pageMeta['og_title'] ?? $metaTitle;
$ogDesc = $pageMeta['og_description'] ?? $metaDesc;
$ogImage = $pageMeta['og_image'] ?? '/images/abahcprofileultrawide.jpg';
?>
<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="description" content="<?= htmlspecialchars($metaDesc) ?>" />
  <?php if ($metaKeywords !== ''): ?>
    <meta name="keywords" content="<?= htmlspecialchars($metaKeywords) ?>" />
  <?php endif; ?>
  <title><?= htmlspecialchars($metaTitle) ?></title>
  <?php
  $host = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https') . '://' . $_SERVER['HTTP_HOST'];
  ?>
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= htmlspecialchars($siteName) ?>">
  <meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($ogDesc) ?>">
  <meta property="og:url" content="<?= htmlspecialchars($host . '/discography/') ?>">
  <meta property="og:image" content="<?= htmlspecialchars($host . $ogImage) ?>">
  <link rel="stylesheet" href="/css/style.css?v=<?php echo $version; ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/analyticstracking.php'; ?>
</head>

<body class="discography-page">

  <?php include __DIR__ . '/../includes/header.php'; ?>

  <main class="container">
    <section class="albums">
      <div class="section-header">
        <h2>Albums <i class="fas fa-plus accordion-icon"></i></h2>
        <div class="scroll-arrows">
          <button class="scroll-btn left" aria-label="Scroll left"><i class="fas fa-chevron-left"></i></button>
          <button class="scroll-btn right" aria-label="Scroll right"><i class="fas fa-chevron-right"></i></button>
        </div>
      </div>
      <ul class="album-list">
        <?php foreach ($albums as $album): ?>
          <li class="album-item<?= $album['comingSoon'] ? ' coming-soon' : '' ?>">
            <a href="/discography/albums/<?= urlencode($album['folder']) ?>/" <?= $album['comingSoon'] ? ' class="disabled"' : '' ?>>
              <?php if ($album['comingSoon'] && $album['coverBlur']): ?>
                <img src="<?= htmlspecialchars(cache_bust($album['coverBlur'])) ?>"
                  alt="<?= htmlspecialchars($album['title']) ?> cover" width="150">
              <?php elseif ($album['cover']): ?>
                <picture>
                  <?php if (!empty($album['srcsetWebp'])): ?>
                    <source type="image/webp"
                      srcset="<?= format_srcset($album['srcsetWebp'], '/discography/albums/' . $album['folder'] . '/') ?>"
                      sizes="150px">
                  <?php endif; ?>
                  <?php if (!empty($album['srcsetJpg'])): ?>
                    <source type="image/jpeg"
                      srcset="<?= format_srcset($album['srcsetJpg'], '/discography/albums/' . $album['folder'] . '/') ?>"
                      sizes="150px">
                  <?php endif; ?>
                  <img src="<?= htmlspecialchars(cache_bust($album['cover'])) ?>"
                    alt="<?= htmlspecialchars($album['title']) ?> cover" width="150">
                </picture>
              <?php endif; ?>
              <div class="album-meta">
                <div class="album-title"><?= htmlspecialchars($album['title']) ?></div>
                <div class="album-info"><?php if ($album['explicit']): ?><span class="explicit_icon" title="Explicit"><i
                        class="fa-solid fa-e"></i></span><?php endif; ?>Vol.
                  <?= $album['volume'] ?>   <?= $album['year'] ? ' · ' . $album['year'] : '' ?>
                </div>
              </div>
              <?php if ($album['comingSoon']): ?>
                <span class="coming-soon-overlay">Coming Soon</span>
              <?php endif; ?>
            </a>
          </li>

        <?php endforeach; ?>
        <li class="flex-holder"></li>
        <li class="flex-holder"></li>
      </ul>
    </section>
    <?php if ($singles): ?>
      <section class="singles">
        <div class="section-header">
          <h2>EPs &amp; Singles <i class="fas fa-chevron-down accordion-icon"></i></h2>
          <div class="scroll-arrows">
            <button class="scroll-btn left" aria-label="Scroll left"><i class="fas fa-chevron-left"></i></button>
            <button class="scroll-btn right" aria-label="Scroll right"><i class="fas fa-chevron-right"></i></button>
          </div>
        </div>
        <ul class="album-list">
          <?php foreach ($singles as $single): ?>
            <li class="album-item<?= $single['comingSoon'] ? ' coming-soon' : '' ?>">
              <?php if ($single['comingSoon'] && $single['coverBlur']): ?>
                <img src="<?= htmlspecialchars(cache_bust($single['coverBlur'])) ?>"
                  alt="<?= htmlspecialchars($single['title']) ?> cover" width="150">
              <?php elseif ($single['cover']): ?>
                <picture>
                  <?php if (!empty($single['srcsetWebp'])): ?>
                    <source type="image/webp"
                      srcset="<?= format_srcset($single['srcsetWebp'], '/discography/albums/' . $single['folder'] . '/') ?>"
                      sizes="150px">
                  <?php endif; ?>
                  <?php if (!empty($single['srcsetJpg'])): ?>
                    <source type="image/jpeg"
                      srcset="<?= format_srcset($single['srcsetJpg'], '/discography/albums/' . $single['folder'] . '/') ?>"
                      sizes="150px">
                  <?php endif; ?>
                  <img src="<?= htmlspecialchars(cache_bust($single['cover'])) ?>"
                    alt="<?= htmlspecialchars($single['title']) ?> cover" width="150">
                </picture>
              <?php endif; ?>
              <div class="album-meta">
                <a href="/discography/albums/<?= urlencode($single['folder']) ?>/"
                  class="album-title<?= $single['comingSoon'] ? ' disabled' : '' ?>">
                  <?= htmlspecialchars($single['title']) ?></a>
                <div class="album-info"><?php if ($single['explicit']): ?><span class="explicit_icon" title="Explicit"><i
                        class="fa-solid fa-e"></i></span><?php endif; ?><?= $single['year'] ?></div>
              </div>
              <?php if ($single['comingSoon']): ?>
                <span class="coming-soon-overlay">Coming Soon</span>
              <?php endif; ?>
            </li>
          <?php endforeach; ?>
          <li class="flex-holder"></li>
          <li class="flex-holder"></li>
        </ul>
      </section>
    <?php endif; ?>


    <?php if ($videos): ?>
      <section class="videos">
        <div class="section-header">
          <h2>Videos <i class="fas fa-plus accordion-icon"></i></h2>
          <div class="scroll-arrows">
            <button class="scroll-btn left" aria-label="Scroll left"><i class="fas fa-chevron-left"></i></button>
            <button class="scroll-btn right" aria-label="Scroll right"><i class="fas fa-chevron-right"></i></button>
          </div>
        </div>
        <ul class="album-list">
          <?php foreach ($videos as $v): ?>
            <li class="album-item video-item" data-url="<?= htmlspecialchars($v['url']) ?>"
              data-platform="<?= htmlspecialchars($v['platform']) ?>">
              <?php if ($v['thumbnail']): ?>
                <picture>
                  <?php if (!empty($v['srcsetWebp'])): ?>
                    <source type="image/webp" srcset="<?= format_srcset($v['srcsetWebp'], '/videos/' . $v['slug'] . '/') ?>"
                      sizes="150px">
                  <?php endif; ?>
                  <?php if (!empty($v['srcsetJpg'])): ?>
                    <source type="image/jpeg" srcset="<?= format_srcset($v['srcsetJpg'], '/videos/' . $v['slug'] . '/') ?>"
                      sizes="150px">
                  <?php endif; ?>
                  <img src="<?= htmlspecialchars(cache_bust($v['thumbnail'])) ?>"
                    alt="<?= htmlspecialchars($v['title']) ?> thumbnail" width="150">
                </picture>
              <?php endif; ?>
              <div class="album-meta">
                <div class="album-title"><?= htmlspecialchars($v['title']) ?></div>
                <div class="album-info"><?= htmlspecialchars($v['artist']) ?><?= $v['year'] ? ' - ' . $v['year'] : '' ?>
                </div>
              </div>
            </li>
          <?php endforeach; ?>
          <li class="flex-holder"></li>
          <li class="flex-holder"></li>
        </ul>
      </section>
    <?php endif; ?>
    <?php if ($appearances): ?>
      <section class="appearances">
        <div class="section-header">
          <h2>Appears On <i class="fas fa-plus accordion-icon"></i></h2>
        </div>
        <ul class="album-list">
          <?php foreach ($appearances as $app): ?>
            <li class="album-item<?= $app['comingSoon'] ? ' coming-soon' : '' ?>">
              <a href="<?= htmlspecialchars($app['url']) ?>" target="_blank" <?= $app['comingSoon'] ? ' class="disabled"' : '' ?>>
                <?php if ($app['cover']): ?>
                  <picture>
                    <?php if (!empty($app['srcsetWebp'])): ?>
                      <source type="image/webp"
                        srcset="<?= format_srcset($app['srcsetWebp'], '/discography/appearances/' . $app['slug'] . '/') ?>"
                        sizes="150px">
                    <?php endif; ?>
                    <?php if (!empty($app['srcsetJpg'])): ?>
                      <source type="image/jpeg"
                        srcset="<?= format_srcset($app['srcsetJpg'], '/discography/appearances/' . $app['slug'] . '/') ?>"
                        sizes="150px">
                    <?php endif; ?>
                    <img src="<?= htmlspecialchars(cache_bust($app['cover'])) ?>"
                      alt="<?= htmlspecialchars($app['title']) ?> cover" width="150">
                  </picture>
                <?php endif; ?>
                <span class="external-link-icon"><i class="fas fa-up-right-from-square"></i></span>
                <div class="album-meta">
                  <div class="album-title">"<?= htmlspecialchars($app['title']) ?>"</div>
                  <div class="album-info">
                    <?= htmlspecialchars($app['artist']) ?>     <?= $app['year'] ? ' - ' . $app['year'] : '' ?>
                  </div>
                </div>
                <?php if ($app['comingSoon']): ?>
                  <span class="coming-soon-overlay">Coming Soon</span>
                <?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
          <li class="flex-holder"></li>
          <li class="flex-holder"></li>
        </ul>
      </section>
    <?php endif; ?>


    <?php if ($playlists): ?>
      <section class="playlists">
        <div class="section-header">
          <h2>Playlists <i class="fas fa-plus accordion-icon"></i></h2>
          <div class="scroll-arrows">
            <button class="scroll-btn left" aria-label="Scroll left"><i class="fas fa-chevron-left"></i></button>
            <button class="scroll-btn right" aria-label="Scroll right"><i class="fas fa-chevron-right"></i></button>
          </div>
        </div>
        <ul class="album-list">
          <?php foreach ($playlists as $playlist): ?>
            <li class="album-item<?= $playlist['comingSoon'] ? ' coming-soon' : '' ?>">
              <a href="/discography/playlists/<?= urlencode($playlist['slug']) ?>/" <?= $playlist['comingSoon'] ? ' class="disabled"' : '' ?>>
                <?php if ($playlist['comingSoon'] && $playlist['coverBlur']): ?>
                  <img src="<?= htmlspecialchars(cache_bust($playlist['coverBlur'])) ?>"
                    alt="<?= htmlspecialchars($playlist['title']) ?> cover" width="150">
                <?php elseif ($playlist['cover']): ?>
                  <picture>
                    <?php if (!empty($playlist['srcsetWebp'])): ?>
                      <source type="image/webp"
                        srcset="<?= format_srcset($playlist['srcsetWebp'], '/discography/playlists/' . $playlist['slug'] . '/') ?>"
                        sizes="150px">
                    <?php endif; ?>
                    <?php if (!empty($playlist['srcsetJpg'])): ?>
                      <source type="image/jpeg"
                        srcset="<?= format_srcset($playlist['srcsetJpg'], '/discography/playlists/' . $playlist['slug'] . '/') ?>"
                        sizes="150px">
                    <?php endif; ?>
                    <img src="<?= htmlspecialchars(cache_bust($playlist['cover'])) ?>"
                      alt="<?= htmlspecialchars($playlist['title']) ?> cover" width="150">
                  </picture>
                <?php else: ?>
                  <div class="album-cover-placeholder"><i class="fa-solid fa-compact-disc"></i></div>
                <?php endif; ?>
                <div class="album-meta">
                  <div class="album-title"><?= htmlspecialchars($playlist['title']) ?></div>
                  <div class="album-info">
                    <?= $playlist['trackCount'] ?>     <?= $playlist['trackCount'] === 1 ? 'track' : 'tracks' ?>
                    <?php if ($playlist['runtime']): ?> · <?= htmlspecialchars($playlist['runtime']) ?><?php endif; ?>
                  </div>
                </div>
                <?php if ($playlist['comingSoon']): ?>
                  <span class="coming-soon-overlay">Coming Soon</span>
                <?php endif; ?>
              </a>
            </li>
          <?php endforeach; ?>
          <li class="flex-holder"></li>
          <li class="flex-holder"></li>
        </ul>
      </section>
    <?php endif; ?>

  </main>
  <script src="/js/scroll-controls.js?v=<?php echo $version; ?>"></script>
  <script src="/js/video-overlay.js"></script>
  <script src="/js/discography.js?v=<?php echo $version; ?>"></script>
</body>

</html>
