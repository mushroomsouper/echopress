<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}
$stmt = $pdo->query(
    'SELECT p.*, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ",") AS cats
     FROM blog_posts p
     LEFT JOIN blog_post_categories pc ON p.id = pc.post_id
     LEFT JOIN blog_categories c ON pc.category_id = c.id
     GROUP BY p.id
     ORDER BY p.post_date DESC'
);
$posts = $stmt->fetchAll();
foreach ($posts as &$p) {
    $p['categories'] = $p['cats'] ? explode(',', $p['cats']) : [];
}
unset($p);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
    $pageTitle = 'Blog Admin';
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
<h1>Blog Posts</h1>
<p><a href="blog_edit.php">Create New Post</a> | <a href="blog_banner.php">Blog Banner</a> | <a href="index.php">Back to Admin</a></p>
<table class="album-list">
    <thead><tr><th>Title</th><th>Date</th><th>Categories</th><th>Published</th><th>Actions</th></tr></thead>
    <tbody>
    <?php foreach ($posts as $p): ?>
        <tr>
            <td><?= htmlspecialchars($p['title']) ?></td>
            <td><?= htmlspecialchars($p['post_date']) ?></td>
            <td><?= htmlspecialchars(implode(', ', $p['categories'] ?? [])) ?></td>
            <td>
                <form action="toggle_blog.php" method="post" style="display:inline">
                    <input type="hidden" name="slug" value="<?= htmlspecialchars($p['slug']) ?>">
                    <input type="checkbox" name="published" value="1" onchange="this.form.submit()" <?= $p['published'] ? 'checked' : '' ?>>
                </form>
            </td>
            <td>
                <a href="blog_edit.php?article=<?= urlencode($p['slug']) ?>">Edit</a>
                <form action="delete_blog.php" method="post" style="display:inline" onsubmit="return confirm('Delete this post?');">
                    <input type="hidden" name="slug" value="<?= htmlspecialchars($p['slug']) ?>">
                    <button type="submit">Delete</button>
                </form>
            </td>
        </tr>
    <?php endforeach; ?>
    </tbody>
</table>
</body>
</html>
