<?php
require_once __DIR__ . '/includes/app.php';

$siteName = echopress_site_name();
$metaDesc = 'The page you were looking for could not be found on ' . $siteName . '.';

$host = echopress_base_url();
if ($host === '') {
    $host = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https')
          . '://' . ($_SERVER['HTTP_HOST'] ?? '');
}
http_response_code(404);

$versionFile = __DIR__ . '/version.txt';
$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '0';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 | <?= htmlspecialchars($siteName) ?></title>

    <meta property="og:type"        content="website">
    <meta property="og:site_name"   content="<?= htmlspecialchars($siteName) ?>">
    <meta property="og:title"       content="404 — Page Not Found">
    <!-- <meta property="og:description" content="</?= htmlspecialchars($metaDesc) ?>">
    <meta name="description"        content="</?= htmlspecialchars($metaDesc) ?>"> -->

      <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css?v=<?php echo $version; ?>">
    <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/analyticstracking.php'; ?>
</head>
<body class="not-found">
<?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/header.php'; ?>

<main class="container not-found-wrapper">
    <h1>404 — Page Not Found</h1>
    <p>Sorry, we couldn’t find that page.</p>
    <p><a class="btn" href="/">Navigate to Homepage</a></p>
</main>
</body>
</html>
