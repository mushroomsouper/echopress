<?php
require_once __DIR__ . '/app.php';

$current = strtok($_SERVER['REQUEST_URI'], '?');
$versionFile = $_SERVER['DOCUMENT_ROOT'] . '/version.txt';
$assetVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1';
$siteName = echopress_site_name();
$socialLinks = array_filter((array) echopress_config('artist.social', []), function ($link) {
  if (is_array($link)) {
    return !empty($link['url']);
  }
  return trim((string) $link) !== '';
});
?>
<header class="header-bar">
  <h1 class="site-title">
    <?= htmlspecialchars($siteName) ?>
  </h1>
  <button class="menu-toggle" aria-label="Menu"><i class="fas fa-bars"></i></button>
  <nav class="main-nav">
    <a href="/" <?php if ($current === '/' || $current === '/index.php' || $current === '/index.html')
      echo ' class="current"'; ?>>Home</a>

    <a href="/blog/" <?php if (strpos($current, '/blog/') === 0)
      echo ' class="current"'; ?>>Blog</a>
    <a href="/discography/" <?php if (strpos($current, '/discography/') === 0)
      echo ' class="current"'; ?>>Discography</a>
    <a href="/contact/" <?php if (strpos($current, '/contact/') === 0)
      echo ' class="current"'; ?>>Contact</a>
    <?php if ($socialLinks): ?>
      <div class="social-links">
        <?php foreach ($socialLinks as $link):
          $url = is_array($link) ? trim((string) ($link['url'] ?? '')) : trim((string) $link);
          $icon = is_array($link) ? trim((string) ($link['icon'] ?? 'fas fa-link')) : 'fas fa-link';
          $label = is_array($link) ? trim((string) ($link['label'] ?? 'Social link')) : 'Social link';
          if ($url === '') {
            continue;
          }
          ?>
          <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener noreferrer"
            class="external-link" aria-label="<?= htmlspecialchars($label) ?>">
            <i class="<?= htmlspecialchars($icon) ?>" aria-hidden="true"></i>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </nav>
</header>
<script src="/js/nav.js?v=<?= htmlspecialchars($assetVersion) ?>"></script>
