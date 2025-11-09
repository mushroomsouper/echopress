<?php
require_once __DIR__ . '/includes/app.php';

function format_blog_date($str)
{
  $ts = strtotime(str_replace('T', ' ', $str));
  return $ts !== false ? date('F j, Y g:i a', $ts) : $str;
}
$versionFile = __DIR__ . '/version.txt';
$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '0';
require_once __DIR__ . "/includes/utils.php";
require_once __DIR__ . '/admin/db_connect.php';

// Provide multibyte string fallbacks for environments without the mbstring
// extension to avoid fatal errors on the homepage.
// Use loose function signatures for compatibility with older PHP versions that
// don't support type hints.
if (!function_exists('mb_substr')) {
  function mb_substr($string, $start, $length = null)
  {
    return $length === null ? substr($string, $start) : substr($string, $start, $length);
  }
}
if (!function_exists('mb_strlen')) {
  function mb_strlen($string)
  {
    return strlen($string);
  }
}

// Build release list from manifests
$releases = [];
foreach (glob(__DIR__ . '/discography/albums/*', GLOB_ONLYDIR) as $dir) {
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
$liveAlbums = array_values(array_filter($albums, fn($a) => $a['live']));
$latest = $liveAlbums ? $liveAlbums[0] : null;

$homepagePlaylists = [];
foreach (glob(__DIR__ . '/discography/playlists/*', GLOB_ONLYDIR) as $dir) {
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
  $homepagePlaylists[] = [
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
    'live' => $live
  ];
}
usort($homepagePlaylists, function ($a, $b) {
  return ($b['displayOrder'] ?? 0) <=> ($a['displayOrder'] ?? 0);
});

// Load bio content
$bioFile = __DIR__ . '/profile/bio/bio.json';
$bio = ['text' => '', 'image' => '', 'srcsetWebp' => '', 'srcsetJpg' => ''];
if (file_exists($bioFile)) {
  $bio = json_decode(file_get_contents($bioFile), true) ?: $bio;
}

// ——— Load blog posts from the database ———
require_once __DIR__ . '/includes/page_meta.php';

$posts = [];
$stmt = $pdo->prepare(
  'SELECT
        p.slug,
        p.title,
        p.body,
        p.image,
        p.image_srcset_webp AS srcsetWebp,
        p.image_srcset_jpg  AS srcsetJpg,
        p.post_date,
        GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ",") AS cats
     FROM blog_posts p
     LEFT JOIN blog_post_categories pc ON p.id = pc.post_id
     LEFT JOIN blog_categories c ON pc.category_id = c.id
     WHERE p.published = 1
       AND p.post_date <= NOW()
     GROUP BY p.id
     ORDER BY p.post_date DESC'
);
$stmt->execute();
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  // build a short excerpt
  $desc = html_excerpt($row['body']);

  $posts[] = [
    'slug' => $row['slug'],
    'title' => $row['title'],
    'date' => $row['post_date'],
    'link' => '/blog/post.php?article=' . rawurlencode($row['slug']),
    'body' => $row['body'],
    'image' => trim($row['image'] ?? ''),
    'srcsetWebp' => trim($row['srcsetWebp'] ?? ''),
    'srcsetJpg' => trim($row['srcsetJpg'] ?? ''),
    'desc' => $desc,
    'categories' => $row['cats'] ? explode(',', $row['cats']) : []
  ];
}

$blogSlides = array_slice($posts, 0, 4);

// Load external appearances
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
$stmt = $pdo->query("SELECT * FROM videos ORDER BY video_order DESC, releaseDate DESC");
while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
  $year = "";
  if (!empty($row["releaseDate"])) {
    $ts = strtotime($row["releaseDate"]);
    if ($ts !== false)
      $year = date("Y", $ts);
  }
  $videos[] = [
    "slug" => $row["slug"],
    "title" => $row["title"],
    "artist" => $row["artist"],
    "year" => $year,
    "url" => $row["url"],
    "platform" => $row["platform"],
    "thumbnail" => $row["thumbnail"] ? "/videos/" . $row["slug"] . "/" . $row["thumbnail"] : null,
    "srcsetWebp" => $row["thumb_srcset_webp"],
    "srcsetJpg" => $row["thumb_srcset_jpg"]
  ];
}


$pageMeta = get_page_meta($pdo, '/index.php');
$metaTitle = $pageMeta['title'] ?? echopress_site_name();
$metaDesc = $pageMeta['description'] ?? echopress_config('site.description', echopress_site_tagline());
$metaKeywords = $pageMeta['keywords'] ?? '';
$ogTitle = $pageMeta['og_title'] ?? $metaTitle;
$ogDesc = $pageMeta['og_description'] ?? $metaDesc;
$ogImage = $pageMeta['og_image'] ?? '/images/site-og-image.jpg';
$ogWebp = $pageMeta['og_image_srcset_webp'] ?? '';
$ogJpg = $pageMeta['og_image_srcset_jpg'] ?? '';
$baseUrl = echopress_base_url();
$ogSiteName = $pageMeta['og_site_name'] ?? echopress_site_name();
$ogUrl = $pageMeta['og_url'] ?? ($baseUrl ? $baseUrl . '/' : '/');
$structuredSameAs = [];
foreach ((array) echopress_config('artist.social', []) as $socialItem) {
  $url = is_array($socialItem) ? trim((string) ($socialItem['url'] ?? '')) : trim((string) $socialItem);
  if ($url !== '') {
    $structuredSameAs[] = $url;
  }
}
// ——— end DB loader ———
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
  <meta property="og:type" content="website">
  <meta property="og:site_name" content="<?= htmlspecialchars($ogSiteName) ?>">
  <meta property="og:title" content="<?= htmlspecialchars($ogTitle) ?>">
  <meta property="og:description" content="<?= htmlspecialchars($ogDesc) ?>">
  <meta property="og:url" content="<?= htmlspecialchars($ogUrl) ?>">
  <meta property="og:image" content="<?= htmlspecialchars(($baseUrl ?: '') . $ogImage) ?>">
  <link rel="stylesheet" href="/css/style.css?v=<?php echo $version; ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <link rel="apple-touch-icon" sizes="180x180" href="/profile/favicon/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/profile/favicon/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/profile/favicon/favicon-16x16.png">
  <link rel="manifest" href="/profile/favicon/site.webmanifest">
  <link rel="icon" href="/profile/favicon/favicon.ico">
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/analyticstracking.php'; ?>
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/theme_vars.php'; ?>

  <script type="application/ld+json">
{
  "@context": "https://schema.org",
  "@type": "MusicGroup",
  "name": "<?= addslashes(echopress_artist_name()) ?>",
  "url": "<?= addslashes($baseUrl) ?>",
  "sameAs": <?= json_encode($structuredSameAs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: '[]' ?>
}
</script>

</head>

<body class="homepage">

  <?php include __DIR__ . '/includes/header.php'; ?>

  <?php if ($latest): ?>

    <section class="hero<?php if ($latest['comingSoon'])
      echo ' comingsoon'; ?>" style="background-color: <?= htmlspecialchars($latest['backgroundColor']) ?>;">
      <?php if ($latest['background']): ?>
        <video<?php if (!$latest['comingSoon'])
          echo ' autoplay loop'; ?> muted playsinline<?php if ($latest['backgroundImage'])
               echo ' poster="' . htmlspecialchars('/discography/albums/' . $latest['folder'] . '/' . $latest['backgroundImage']) . '"'; ?>>
          <source src="<?= htmlspecialchars('/discography/albums/' . $latest['folder'] . '/' . $latest['background']) ?>"
            type="video/mp4">
          </video>
        <?php elseif ($latest['backgroundImage']): ?>
          <img
            src="<?= htmlspecialchars(cache_bust('/discography/albums/' . $latest['folder'] . '/' . $latest['backgroundImage'])) ?>"
            alt="">
        <?php endif; ?>
        <div class="hero-info">
          <?php if ($latest['comingSoon'] && $latest['coverBlur']): ?>
            <img class="hero-cover" src="<?= htmlspecialchars(cache_bust($latest['coverBlur'])) ?>" width="170" alt="cover">
          <?php elseif ($latest['cover']): ?>
            <picture>
              <?php if (!empty($latest['srcsetWebp'])): ?>
                <source type="image/webp"
                  srcset="<?= format_srcset($latest['srcsetWebp'], '/discography/albums/' . $latest['folder'] . '/') ?>"
                  sizes="170px">
              <?php endif; ?>
              <?php if (!empty($latest['srcsetJpg'])): ?>
                <source type="image/jpeg"
                  srcset="<?= format_srcset($latest['srcsetJpg'], '/discography/albums/' . $latest['folder'] . '/') ?>"
                  sizes="170px">
              <?php endif; ?>
              <img class="hero-cover" src="<?= htmlspecialchars(cache_bust($latest['cover'])) ?>" width="170" alt="cover">
            </picture>
          <?php endif; ?>
          <div class="hero-details">
            <div class="hero-text">
              <div class="hero-latest">
                <?= $latest['comingSoon'] ? 'Coming Soon' : 'Latest Album' ?>
              </div>
              <div class="hero-title"><?= htmlspecialchars($latest['title']) ?></div>
              <div class="hero-volume"><?php if ($latest['explicit']): ?><span class="explicit_icon" title="Explicit"><i
                      class="fa-solid fa-e"></i></span><?php endif; ?>Vol. <?= $latest['volume'] ?></div>
            </div>

            <?php if (!$latest['comingSoon']): ?>
              <a class="listen-link" href="/discography/albums/<?= urlencode($latest['folder']) ?>/">Listen now
                <!-- fontawesome arrow right -->
                <i class="fas fa-arrow-right"></i>
              </a>
            <?php endif; ?>
          </div>
        </div>
    </section>
  <?php endif; ?>
  <main class="container">

    <section class="discography">
      <div class="section-header">
        <h2>Albums</h2>
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
      </ul>
    </section>
    <?php if ($singles): ?>
      <section class="singles">
        <div class="section-header">
          <h2>EPs &amp; Singles</h2>
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
        </ul>
      </section>
    <?php endif; ?>


    <?php if ($videos): ?>
      <section class="videos">
        <div class="section-header">
          <h2>Videos</h2>
          <div class="scroll-arrows">
            <button class="scroll-btn left" aria-label="Scroll left"><i class="fas fa-chevron-left"></i></button>
            <button class="scroll-btn right" aria-label="Scroll right"><i class="fas fa-chevron-right"></i></button>
          </div>
        </div>
        <ul class="album-list">
          <?php foreach ($videos as $v): ?>
            <li class="album-item video-item" data-url="<?= htmlspecialchars($v['url']) ?>"
              data-platform="<?= htmlspecialchars($v['platform']) ?>">
              <a><?php if ($v['thumbnail']): ?>
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
              </a>
            </li>
          <?php endforeach; ?>
        </ul>
      </section>
    <?php endif; ?>
    <?php if ($appearances): ?>
      <section class="appearances">
        <div class="section-header">
          <h2>Appears On</h2>
          <div class="scroll-arrows">
            <button class="scroll-btn left" aria-label="Scroll left"><i class="fas fa-chevron-left"></i></button>
            <button class="scroll-btn right" aria-label="Scroll right"><i class="fas fa-chevron-right"></i></button>
          </div>
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
                  <div class="album-title"><?= htmlspecialchars($app['title']) ?></div>
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
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($homepagePlaylists): ?>
      <section class="playlists">
        <div class="section-header">
          <h2>Playlists</h2>
          <div class="scroll-arrows">
            <button class="scroll-btn left" aria-label="Scroll left"><i class="fas fa-chevron-left"></i></button>
            <button class="scroll-btn right" aria-label="Scroll right"><i class="fas fa-chevron-right"></i></button>
          </div>
        </div>
        <ul class="album-list">
          <?php foreach ($homepagePlaylists as $playlist): ?>
            <li class="album-item<?= $playlist['comingSoon'] ? ' coming-soon' : '' ?>">
              <a href="/discography/playlists/<?= urlencode($playlist['slug']) ?>/"<?= $playlist['comingSoon'] ? ' class="disabled"' : '' ?>>
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
          <li class="flex-holder"></li>
          <li class="flex-holder"></li>
        </ul>
      </section>
    <?php endif; ?>

    <?php if ($blogSlides): ?>
      <section class="blog-slider" data-autoheight>
        <div class="section-header">
          <h2>Articles</h2>
          <div class="scroll-arrows">
            <button class="scroll-btn left" aria-label="Scroll left"><i class="fas fa-chevron-left"></i></button>
            <button class="scroll-btn right" aria-label="Scroll right"><i class="fas fa-chevron-right"></i></button>
          </div>
        </div>
        <ul class="album-list blog-list">
          <?php foreach ($blogSlides as $post): ?>
            <li class="album-item">
              <a href="<?= htmlspecialchars($post['link']) ?>">
                <?php if (!empty($post['image'])): ?>
                  <picture>
                    <?php if ($post['srcsetWebp']): ?>
                      <source type="image/webp" srcset="<?= htmlspecialchars($post['srcsetWebp']) ?>">
                    <?php endif; ?>
                    <?php if ($post['srcsetJpg']): ?>
                      <source type="image/jpeg" srcset="<?= htmlspecialchars($post['srcsetJpg']) ?>">
                    <?php endif; ?>
                    <img src="<?= htmlspecialchars(cache_bust($post['image'])) ?>"
                      alt="<?= htmlspecialchars($post['title']) ?>" class="post-preview-thumb">
                  </picture>
                <?php endif; ?>
                <div class="post-preview-details">
                  <h3><?= htmlspecialchars($post['title']) ?></h3>
                  <div class="post-date"><?= htmlspecialchars(format_blog_date($post['date'])) ?></div>
                  <?php if (!empty($post['categories'])): ?>
                    <div class="post-cats">Categories:
                      <?= htmlspecialchars(implode(', ', blog_category_labels($post['categories']))) ?>
                    </div>
                  <?php endif; ?>
                  <p><?= htmlspecialchars($post['desc']) ?></p>
                </div>
              </a>
            </li>
          <?php endforeach; ?>
          <li class="album-item view-all"><a href="/blog">View All</a></li>
        </ul>
      </section>
    <?php endif; ?>

    <?php if (!empty(trim($bio['text']))): ?>
      <section class="bio">
        <!-- <h2>About</h2> -->
        <div class="bio-body">
          <?php if ($bio['image']): ?>
            <picture>
              <?php if (!empty($bio['srcsetWebp'])): ?>
                <source type="image/webp" srcset="<?= format_srcset($bio['srcsetWebp'], '/') ?>">
              <?php endif; ?>
              <?php if (!empty($bio['srcsetJpg'])): ?>
                <source type="image/jpeg" srcset="<?= format_srcset($bio['srcsetJpg'], '/') ?>">
              <?php endif; ?>
              <img src="<?= htmlspecialchars(cache_bust('/' . $bio['image'])) ?>" alt="bio" class="bio-photo">
            </picture>
          <?php endif; ?>
          <div class="bio-text"><?= $bio['text'] ?></div>
        </div>
      </section>
    <?php endif; ?>

  </main>
  <script src="/js/video-overlay.js"></script>
  <script src="/js/scroll-controls.js?v=<?php echo $version; ?>"></script>
</body>

</html>
