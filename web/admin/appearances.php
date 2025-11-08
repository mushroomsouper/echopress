<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}
$stmt = $pdo->query('SELECT * FROM appearances ORDER BY appearance_order DESC');
$rows = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
    $pageTitle = 'Also Appears On';
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
<h1>Also Appears On</h1>
<p><a href="appearance_edit.php">Add New</a> | <a href="index.php">Back to Admin</a></p>
<table class="album-list">
<thead>
<tr><th>Cover</th><th>Title</th><th>Artist</th><th>Order</th><th>Released</th><th>Actions</th></tr>
</thead>
<tbody>
<?php foreach ($rows as $r): ?>
<tr>
    <td><?php if ($r['cover']): ?><img src="<?= htmlspecialchars(cache_bust('/discography/appearances/' . $r['slug'] . '/' . $r['cover'])) ?>" alt="cover" height="50"><?php endif; ?></td>
    <td><?= htmlspecialchars($r['title']) ?></td>
    <td><?= htmlspecialchars($r['artist']) ?></td>
    <td><?= (int)$r['appearance_order'] ?></td>
    <td><?= $r['released'] ? 'Yes' : 'No' ?></td>
    <td>
        <a href="appearance_edit.php?slug=<?= urlencode($r['slug']) ?>">Edit</a>
        <form action="delete_appearance.php" method="post" style="display:inline" onsubmit="return confirm('Delete this entry?');">
            <input type="hidden" name="slug" value="<?= htmlspecialchars($r['slug']) ?>">
            <button type="submit">Delete</button>
        </form>
    </td>
</tr>
<?php endforeach; ?>
</tbody>
</table>
</body>
</html>
