<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

if (isset($_GET['export'])) {
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="newsletter_subscribers.csv"');
    $out = fopen('php://output', 'w');
    fputcsv($out, [
        'Email', 'Via', 'Wants Album', 'Wants Single', 'Wants Video',
        'Wants Appears', 'Wants Coming Soon', 'Wants All Posts',
        'Subscribed', 'Manage Link'
    ]);
    $stmt = $pdo->query('SELECT * FROM newsletter_subscribers ORDER BY subscribed_at DESC');
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $manage = '/newsletter/manage.php?token=' . $row['manage_token'];
        fputcsv($out, [
            $row['email'],
            $row['via'],
            $row['wants_album'],
            $row['wants_single'],
            $row['wants_video'],
            $row['wants_appears'],
            $row['wants_coming_soon'],
            $row['wants_all_posts'],
            $row['subscribed_at'],
            $manage
        ]);
    }
    exit;
}

$stmt = $pdo->query('SELECT * FROM newsletter_subscribers ORDER BY subscribed_at DESC');
$subs = $stmt->fetchAll();
$subscriberCount = count($subs);
$cleanupDefaultDate = date('Y-m-d');

$unsStmt = $pdo->query('SELECT * FROM newsletter_unsubscribed ORDER BY unsubscribed_at DESC');
$unsubs = $unsStmt->fetchAll(PDO::FETCH_ASSOC);
$unsubCount = count($unsubs);

// Recent posts from the last day with their categories
$postStmt = $pdo->prepare(<<<'SQL'
SELECT p.id, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ',') AS cats
  FROM blog_posts p
  LEFT JOIN blog_post_categories pc ON p.id = pc.post_id
  LEFT JOIN blog_categories c     ON pc.category_id = c.id
 WHERE p.published = 1
   AND p.post_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)
 GROUP BY p.id
SQL);
$postStmt->execute();
$recentPosts = $postStmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($recentPosts as &$p) {
    $p['categories'] = $p['cats'] ? explode(',', $p['cats']) : [];
}
unset($p);

$logChk = $pdo->prepare('SELECT 1 FROM newsletter_log WHERE subscriber_id=? AND post_id=?');
$types  = ['album','single','video','appears','coming-soon'];
foreach ($subs as &$s) {
    $s['has_pending'] = false;
    foreach ($recentPosts as $p) {
        $logChk->execute([$s['id'], $p['id']]);
        if ($logChk->fetchColumn()) {
            continue;
        }
        if ($s['wants_all_posts']) {
            $s['has_pending'] = true;
            break;
        }
        foreach ($types as $slug) {
            $field = 'wants_' . str_replace('-', '_', $slug);
            if ($s[$field] && in_array($slug, $p['categories'], true)) {
                $s['has_pending'] = true;
                break 2;
            }
        }
    }
}
unset($s);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
    $pageTitle = 'Newsletter Subscribers';
    $versionFile = __DIR__ . '/../version.txt';
    $version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1';
    $adminCssHref = '/css/admin.css?v=' . rawurlencode($version);
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
    <link rel="stylesheet" href="<?= htmlspecialchars($adminCssHref) ?>">
    <script src="/js/admin-session.js?v=<?= htmlspecialchars($version) ?>" defer></script>
    <link rel="apple-touch-icon" sizes="180x180" href="/profile/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/profile/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/profile/favicon/favicon-16x16.png">
    <link rel="manifest" href="/profile/favicon/site.webmanifest">
    <link rel="icon" href="/profile/favicon/favicon.ico">
</head>
<body>
<h1>Newsletter Subscribers (<?= $subscriberCount ?>)</h1>
<p><a href="newsletter.php?export=1">Export CSV</a> | <a href="index.php">Back to Admin</a>
    | <form action="run_newsletters.php" method="post" target="_blank" style="display:inline">
        <button type="submit">Send Pending</button>
      </form>
</p>
<section class="newsletter-tools">
    <h2>Security Tools</h2>
    <form action="newsletter_purge.php" method="post" target="_blank" class="cleanup-form">
        <label>
            Remove public signups on/after
            <input type="date" name="cutoff_date" value="<?= htmlspecialchars($cleanupDefaultDate) ?>">
        </label>
        <label class="checkbox-inline">
            <input type="checkbox" name="check_dns" value="1" checked>
            Remove addresses without valid DNS (MX or A record)
        </label>
        <p class="help">Cleanup deletions are recorded in the unsubscribe log so you can audit changes later.</p>
        <button type="submit">Run Cleanup</button>
    </form>
</section>
<table class="album-list">
<thead>
<tr><th>Email</th><th>Via</th><th>Preferences</th><th>Subscribed</th><th>Pending</th><th>Actions</th></tr>
</thead>
<tbody>
<?php foreach ($subs as $s): ?>
    <tr>
        <td><?= htmlspecialchars($s['email']) ?></td>
        <td><?= htmlspecialchars($s['via']) ?></td>
        <td>
            <?php
                $prefs = [];
                if ($s['wants_all_posts']) {
                    $prefs[] = 'All Posts';
                } else {
                    if ($s['wants_album'])       $prefs[] = 'Album';
                    if ($s['wants_single'])      $prefs[] = 'Single';
                    if ($s['wants_video'])       $prefs[] = 'Video';
                    if ($s['wants_appears'])     $prefs[] = 'Appears';
                    if ($s['wants_coming_soon']) $prefs[] = 'Coming Soon';
                }
                echo htmlspecialchars(implode(', ', $prefs));
            ?>
        </td>
        <td><?= htmlspecialchars($s['subscribed_at']) ?></td>
        <td><?= $s['has_pending'] ? 'Yes' : 'No' ?></td>
        <td>
            <a href="/newsletter/manage.php?token=<?= urlencode($s['manage_token']) ?>" target="_blank">Manage</a>
            <form action="delete_subscriber.php" method="post" style="display:inline" onsubmit="return confirm('Delete subscriber?');">
                <input type="hidden" name="id" value="<?= (int)$s['id'] ?>">
                <button type="submit">Delete</button>
            </form>
        </td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
<h2>Unsubscribed (<?= $unsubCount ?>)</h2>
<table class="album-list">
<thead>
<tr><th>Email</th><th>Via</th><th>Unsubscribed</th></tr>
</thead>
<tbody>
<?php foreach ($unsubs as $u): ?>
    <tr>
        <td><?= htmlspecialchars($u['email']) ?></td>
        <td><?= htmlspecialchars($u['via']) ?></td>
        <td><?= htmlspecialchars($u['unsubscribed_at']) ?></td>
    </tr>
<?php endforeach; ?>
</tbody>
</table>
</body>
</html>
