<?php
declare(strict_types=1);

require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/utils.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$analyticsDir = echopress_storage_path('analytics');
if (!is_dir($analyticsDir)) {
    mkdir($analyticsDir, 0775, true);
}
$analyticsFile = $analyticsDir . '/embed.html';

$status = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $embed = (string) ($_POST['embed_code'] ?? '');
    file_put_contents($analyticsFile, $embed);
    $status = 'Analytics embed updated.';
}

$current = is_file($analyticsFile) ? (string) file_get_contents($analyticsFile) : echopress_config('analytics.embed_code', '');
$siteName = echopress_site_name();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?php
        $pageTitle = 'Analytics Embed';
        $version = echopress_asset_version();
    ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/css/style.css?v=<?= htmlspecialchars($version) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body>
    <h1>Analytics Embed</h1>
    <p>
        <a href="index.php">Admin Home</a> |
        <a href="newsletter.php">Newsletter</a> |
        <a href="logout.php">Logout</a>
    </p>
    <p>Paste the tracking snippet from Google Analytics, Matomo, Plausible, or any other provider. EchoPress will output this code on every public page so you can manage reporting from your provider’s dashboard.</p>
    <?php if ($status): ?>
        <div class="admin-alert admin-alert--success"><?= htmlspecialchars($status) ?></div>
    <?php endif; ?>
    <form method="post">
        <label for="embed_code">Analytics code</label>
        <textarea id="embed_code" name="embed_code" rows="12" style="width:100%;"><?= htmlspecialchars($current) ?></textarea>
        <p class="help">Most analytics suites give you a block of script tags to drop before the closing &lt;/body&gt;. Copy it directly into this box.</p>
        <button type="submit">Save Embed</button>
    </form>
</body>
</html>
