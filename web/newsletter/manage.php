<?php
date_default_timezone_set('America/Regina');
require_once __DIR__ . '/../admin/db_connect.php';

$versionFile = __DIR__ . '/../version.txt';
$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '0';

$token = $_GET['token'] ?? '';
$action = $_GET['action'] ?? '';

if (!$token) {
    http_response_code(400);
    echo 'Missing token';
    exit;
}

$stmt = $pdo->prepare('SELECT * FROM newsletter_subscribers WHERE manage_token=?');
$stmt->execute([$token]);
$sub = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$sub) {
    http_response_code(404);
    echo 'Invalid token';
    exit;
}

if ($action === 'unsubscribe' && in_array($_SERVER['REQUEST_METHOD'], ['POST', 'GET'], true)) {
    $pdo->prepare('INSERT INTO newsletter_unsubscribed (subscriber_id,email,name,via) VALUES (?,?,?,?)')
        ->execute([$sub['id'], $sub['email'], $sub['name'], $sub['via']]);
    $pdo->prepare('DELETE FROM newsletter_subscribers WHERE id=?')->execute([$sub['id']]);
    ?>
    <!DOCTYPE html>
    <html lang="en">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0" />
        <title>Unsubscribed</title>
        <link rel="stylesheet" href="/css/style.css?v=<?php echo $version; ?>">
    </head>
    <body>
        <?php include __DIR__ . '/../includes/header.php'; ?>
        <main class="container">
            <h1>Unsubscribed</h1>
            <p>You have been unsubscribed.</p>
            <p><a href="/">Return to homepage</a></p>
        </main>
    </body>
    </html>
    <?php
    exit;
}

if ($action === 'regen') {
    $newToken = bin2hex(random_bytes(32));
    $pdo->prepare('UPDATE newsletter_subscribers SET manage_token=? WHERE id=?')->execute([$newToken,$sub['id']]);
    header('Location: manage.php?token=' . urlencode($newToken));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $prefs = [
        'album'       => isset($_POST['album']) ? 1 : 0,
        'single'      => isset($_POST['single']) ? 1 : 0,
        'video'       => isset($_POST['video']) ? 1 : 0,
        'appears'     => isset($_POST['appears']) ? 1 : 0,
        'coming_soon' => isset($_POST['coming_soon']) ? 1 : 0,
        'all_posts'   => isset($_POST['all_posts']) ? 1 : 0,
    ];
    if ($prefs['all_posts']) {
        foreach (['album','single','video','appears','coming_soon'] as $k) { $prefs[$k] = 0; }
    }
    $pdo->prepare('UPDATE newsletter_subscribers SET wants_album=?,wants_single=?,wants_video=?,wants_appears=?,wants_coming_soon=?,wants_all_posts=? WHERE id=?')
        ->execute([$prefs['album'],$prefs['single'],$prefs['video'],$prefs['appears'],$prefs['coming_soon'],$prefs['all_posts'],$sub['id']]);
    $sub = array_merge($sub,$prefs);
    $message = 'Preferences updated.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title>Manage Subscription</title>
    <link rel="stylesheet" href="/css/style.css?v=<?php echo $version; ?>">
</head>
<body>
<?php include __DIR__ . '/../includes/header.php'; ?>
<main class="container newsletter-widget ">
    <h1>Manage Newsletter Subscription</h1>
    <?php if (!empty($message)): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>
    <form method="post">
        <fieldset>
            <legend>Notify me about:</legend>
            <label><input type="checkbox" name="album" <?= $sub['wants_album'] ? 'checked' : '' ?>> New Album</label>
            <label><input type="checkbox" name="single" <?= $sub['wants_single'] ? 'checked' : '' ?>> New EP/Single</label>
            <label><input type="checkbox" name="video" <?= $sub['wants_video'] ? 'checked' : '' ?>> New Video</label>
            <label><input type="checkbox" name="appears" <?= $sub['wants_appears'] ? 'checked' : '' ?>> New Guest Appearance</label>
            <label><input type="checkbox" name="coming_soon" <?= $sub['wants_coming_soon'] ? 'checked' : '' ?>> Coming Soon</label>
            <label><input type="checkbox" name="all_posts" <?= $sub['wants_all_posts'] ? 'checked' : '' ?>> All Posts</label>
        </fieldset>
        <button type="submit">Update Preferences</button>
    </form>
    <form method="post" action="?token=<?= urlencode($token) ?>&action=unsubscribe" onsubmit="return confirm('Unsubscribe?');">
        <button type="submit">Unsubscribe</button>
    </form>
    <p><a href="manage.php?token=<?= urlencode($token) ?>&action=regen">Regenerate management link</a></p>
</main>
<script src="/js/newsletter-manage.js?v=<?php echo $version; ?>"></script>
</body>
</html>
