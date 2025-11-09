<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../includes/app.php';
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->query('SELECT * FROM albums ORDER BY volume DESC');
$albums = $stmt->fetchAll();
foreach ($albums as &$a) {
    $tracks = $pdo->prepare('SELECT length FROM album_tracks WHERE album_id=? ORDER BY track_number');
    $tracks->execute([$a['id']]);
    $lenArr = $tracks->fetchAll(PDO::FETCH_COLUMN);
    $runTimeSec = 0;
    foreach ($lenArr as $l) {
        if (preg_match('/^(\d+):(\d+)/', $l, $m)) {
            $runTimeSec += $m[1] * 60 + $m[2];
        }
    }
    $a['numTracks'] = count($lenArr);
    $a['runTime'] = $a['numTracks'] ? sprintf('%d:%02d', floor($runTimeSec/60), $runTimeSec%60) : '0:00';
    $a['url'] = '/discography/albums/' . rawurlencode($a['slug']) . '/';
}
unset($a);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
    $pageTitle = 'Album Admin';
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
<?php
  // Show a setup prompt if the first-run lock doesn't exist
  $firstRunLock = dirname(__DIR__, 1) . '/../storage/first_run.lock';
  if (!is_file($firstRunLock)) {
      echo '<div class="notice" style="background:#fff3cd;color:#856404;padding:10px;border-radius:6px;margin:10px 0;">';
      echo '✔ Installation complete. Continue setup in the <a href="/first-run/">First‑Run wizard</a>.';
      echo '</div>';
  }
?>
<h1>Albums</h1>
<p>
  <a href="album_edit.php">Create New Album</a>
  <?php if (echopress_feature_enabled('playlists')): ?> | <a href="playlists.php">Playlists</a><?php endif; ?>
   | <a href="bio_edit.php">Edit Bio</a>
  <?php if (echopress_feature_enabled('blog')): ?> | <a href="blog.php">Blog</a> | <a href="blog_edit.php">Blog Editor</a><?php endif; ?>
   | <a href="appearances.php">Also Appears On</a>
  <?php if (echopress_feature_enabled('videos')): ?> | <a href="videos.php">Videos</a><?php endif; ?>
   | <a href="blog_banner.php">Blog Banner</a> | <a href="meta_edit.php">Page Meta</a> | <a href="analytics_embed.php">Analytics Embed</a>
  <?php if (echopress_feature_enabled('newsletter')): ?> | <a href="newsletter.php">Newsletter</a><?php endif; ?>
   | <a href="contact_messages.php">Contact Activity</a> | <a href="logout.php">Logout</a> | <a href="favicon.php">Favicon</a>
   | <a href="appearance.php">Appearance</a>
</p>
<table class="album-list">
    <thead>
    <tr>
        <th>Cover</th>
        <th>Title</th>
        <th>URL</th>
        <th>Volume</th>
        <th>Live</th>
        <th>Tracks</th>
        <th>Runtime</th>
        <th>Actions</th>
    </tr>
    </thead>
    <tbody>
    <?php foreach ($albums as $a): ?>
        <tr>
            <td>
                <?php if ($a['cover']): ?>
                    <img src="<?= htmlspecialchars(cache_bust($a['url'] . $a['cover'])) ?>" alt="cover" height="50">
                <?php endif; ?>
            </td>
            <td><?= htmlspecialchars($a['albumTitle']) ?></td>
            <td><a href="<?= htmlspecialchars($a['url']) ?>" target="_blank"><?= htmlspecialchars($a['url']) ?></a></td>
            <td><?= $a['volume'] ?></td>
            <td>
                <form action="update_live.php" method="post" style="display:inline">
                    <input type="hidden" name="album" value="<?= htmlspecialchars($a['slug']) ?>">
                    <input type="checkbox" name="live" value="1" onchange="this.form.submit()" <?= $a['live'] ? 'checked' : '' ?>>
                </form>
            </td>
            <td><?= $a['numTracks'] ?></td>
            <td><?= $a['runTime'] ?></td>
            <td>
                <a href="album_edit.php?album=<?= urlencode($a['slug']) ?>">Edit</a>
                <form action="delete_album.php" method="post" style="display:inline" onsubmit="return confirm('Delete this album?');">
                    <input type="hidden" name="album" value="<?= htmlspecialchars($a['slug']) ?>">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>

<section style="margin-top:2rem">
  <h2>Getting Started</h2>
  <ul>
    <li><a href="album_edit.php">Create your first album</a></li>
    <li><a href="bio_edit.php">Write your bio</a></li>
    <li><a href="appearance.php">Pick a theme and colors</a></li>
    <?php if (echopress_feature_enabled('blog')): ?><li><a href="blog_edit.php">Publish a blog post</a></li><?php endif; ?>
    <?php if (echopress_feature_enabled('newsletter')): ?><li><a href="newsletter.php">Configure newsletter</a></li><?php endif; ?>
    <li><a href="analytics_embed.php">Add analytics</a></li>
    <li><a href="/preflight.php" target="_blank">Run preflight checks</a></li>
  </ul>
  <p><small>Tip: You can toggle features (Blog, Playlists, Newsletter, Videos, Contact) during First‑Run or later via env variables.</small></p>
</section>
</body>
</html>
