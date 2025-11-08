<?php
require_once __DIR__ . '/../bootstrap/app.php';

$checks = [];

function add_check(array &$checks, string $label, bool $ok, string $help = ''): void {
    $checks[] = compact('label', 'ok', 'help');
}

// PHP version
$minPhp = '8.1.0';
add_check($checks, 'PHP version ≥ ' . $minPhp, version_compare(PHP_VERSION, $minPhp, '>='), 'Your host reports PHP ' . PHP_VERSION . '. Ask support to enable PHP ' . $minPhp . ' or newer.');

// Required extensions
$required = [
    'json' => 'JSON support is required to read manifests and config.',
    'pdo' => 'PDO is required for database access.',
];
foreach ($required as $ext => $msg) {
    add_check($checks, 'Extension: ' . $ext, extension_loaded($ext), $msg);
}

// Recommended extensions
$recommended = [
    'pdo_sqlite' => 'Simplest DB for single-user sites. Optional if using MySQL.',
    'pdo_mysql' => 'Required if you choose MySQL in the installer.',
    'mbstring' => 'Improves string handling for multibyte text.',
    'fileinfo' => 'Improves MIME type detection for uploads.',
    'gd' => 'Used for image processing if Imagick is unavailable.',
    'imagick' => 'Better image processing and blurs for covers.',
];
foreach ($recommended as $ext => $msg) {
    add_check($checks, 'Recommended: ' . $ext, extension_loaded($ext), $msg);
}

// Writable paths
$writable = [
    '../storage' => 'Holds SQLite DB, locks, temp files',
    './discography' => 'Albums, playlists, assets (created via Admin)',
    './blog' => 'Blog posts and images (created via Admin)'
];
foreach ($writable as $path => $desc) {
    $abs = realpath(__DIR__ . '/' . $path) ?: __DIR__ . '/' . $path;
    $ok = is_dir($abs) ? is_writable($abs) : is_writable(dirname($abs));
    add_check($checks, 'Writable: ' . $path, $ok, $desc . '. Set permissions to allow the web server to write here.');
}

// URL rewrite / docroot behavior hints
$docroot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$inWeb = strpos(realpath(__DIR__), realpath($docroot) ?: '') === 0;
$rewriteHint = $inWeb ? 'Serving from /web as document root.' : 'Using root proxy (/.htaccess + /index.php) to route into /web.';
add_check($checks, 'Routing OK', true, $rewriteHint);

// .user.ini effectiveness check (auto_prepend_file)
$prepend = ini_get('auto_prepend_file');
if ($prepend) {
    add_check($checks, 'PHP auto_prepend_file active', true, 'auto_prepend_file=' . $prepend);
} else {
    add_check($checks, 'PHP auto_prepend_file', false, 'Not required, but helps when docroot cannot be changed.');
}

// Render HTML
?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>EchoPress Preflight</title>
  <link rel="stylesheet" href="/css/style.css?v=<?php echo htmlspecialchars(@file_get_contents(__DIR__ . '/version.txt') ?: '0'); ?>">
  <style>
    body { max-width: 820px; margin: 2rem auto; padding: 0 1rem; }
    h1 { margin-bottom: .5rem; }
    .check { display: flex; align-items: flex-start; gap: .75rem; padding: .5rem 0; border-bottom: 1px solid #eee; }
    .ok { color: #2e7d32; }
    .bad { color: #c62828; }
    .label { font-weight: 600; }
    .help { color: #555; font-size: .95rem; }
    .actions { margin-top: 1rem; }
    .btn { display: inline-block; background: #111; color: #fff; padding: .6rem 1rem; text-decoration: none; border-radius: 4px; }
  </style>
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/analyticstracking.php'; ?>
  </head>
<body>
  <h1>EchoPress Preflight</h1>
  <p>This page checks your hosting environment for the most common requirements. You can run the installer at any time.</p>
  <div class="checks">
    <?php foreach ($checks as $c): ?>
      <div class="check">
        <div class="icon <?php echo $c['ok'] ? 'ok' : 'bad'; ?>">
          <i class="fa-solid fa-<?php echo $c['ok'] ? 'circle-check' : 'circle-xmark'; ?>"></i>
        </div>
        <div>
          <div class="label"><?php echo htmlspecialchars($c['label']); ?></div>
          <?php if (!empty($c['help'])): ?>
            <div class="help"><?php echo htmlspecialchars($c['help']); ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>
  </div>
  <div class="actions">
    <a class="btn" href="/install/">Run Installer</a>
  </div>
</body>
</html>

