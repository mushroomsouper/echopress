<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/utils.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$stmt = $pdo->query('SELECT p.*, COUNT(pt.id) AS track_count FROM playlists p LEFT JOIN playlist_tracks pt ON pt.playlist_id = p.id GROUP BY p.id ORDER BY p.display_order DESC, p.title');
$playlists = $stmt->fetchAll(PDO::FETCH_ASSOC);

$messages = [
    'success' => $_SESSION['playlist_success'] ?? null,
    'errors' => $_SESSION['playlist_errors'] ?? null
];
unset($_SESSION['playlist_success'], $_SESSION['playlist_errors']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
    $pageTitle = 'Playlists Admin';
    $versionFile = __DIR__ . '/../version.txt';
    $version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1';
    $versionParam = htmlspecialchars($version, ENT_QUOTES);
    $headExtras = '<link rel="stylesheet" href="/css/admin.css?v=' . $versionParam . '">' . "\n" .
                  '<script src="/js/admin-session.js?v=' . $versionParam . '" defer></script>';
?>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/css/style.css?v=<?= htmlspecialchars($version) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <?= $headExtras ?>
</head>
<body>
    <h1>Playlists</h1>
    <p>
        <a href="playlist_edit.php">Create New Playlist</a> |
        <a href="index.php">Album Admin</a> |
        <a href="videos.php">Videos</a> |
        <a href="logout.php">Logout</a>
    </p>

    <?php if ($messages['success']): ?>
        <div class="admin-alert admin-alert--success"><?= htmlspecialchars($messages['success']) ?></div>
    <?php endif; ?>
    <?php if (!empty($messages['errors']) && is_array($messages['errors'])): ?>
        <div class="admin-alert admin-alert--error">
            <ul>
                <?php foreach ($messages['errors'] as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <table class="album-list">
        <thead>
            <tr>
                <th>Cover</th>
                <th>Title</th>
                <th>Tracks</th>
                <th>Order</th>
                <th>Live</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($playlists as $playlist):
            $coverUrl = '';
            if (!empty($playlist['cover'])) {
                $coverUrl = '/discography/playlists/' . rawurlencode($playlist['slug']) . '/' . ltrim($playlist['cover'], '/');
                $coverUrl = cache_bust($coverUrl);
            }
            $playlistUrl = '/discography/playlists/' . rawurlencode($playlist['slug']) . '/';
        ?>
            <tr>
                <td>
                    <?php if ($coverUrl): ?>
                        <img src="<?= htmlspecialchars($coverUrl) ?>" alt="cover" height="50">
                    <?php endif; ?>
                </td>
                <td>
                    <strong><?= htmlspecialchars($playlist['title']) ?></strong><br>
                    <small><?= htmlspecialchars($playlistUrl) ?></small>
                </td>
                <td><?= (int) $playlist['track_count'] ?></td>
                <td><?= (int) $playlist['display_order'] ?></td>
                <td>
                    <form action="update_playlist_live.php" method="post" style="display:inline">
                        <input type="hidden" name="playlist" value="<?= htmlspecialchars($playlist['slug']) ?>">
                        <input type="checkbox" name="live" value="1" onchange="this.form.submit()" <?= $playlist['live'] ? 'checked' : '' ?>>
                    </form>
                </td>
                <td>
                    <a href="playlist_edit.php?playlist=<?= urlencode($playlist['slug']) ?>">Edit</a>
                    <form action="delete_playlist.php" method="post" style="display:inline" onsubmit="return confirm('Delete this playlist?');">
                        <input type="hidden" name="playlist" value="<?= htmlspecialchars($playlist['slug']) ?>">
                        <button type="submit">Delete</button>
                    </form>
                </td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
</body>
</html>
