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

// Build nav links with feature toggles
$links = [
  ['label' => 'Home', 'href' => '/', 'active' => ($current === '/' || $current === '/index.php' || $current === '/index.html')],
];
if (echopress_feature_enabled('blog')) {
  $links[] = ['label' => 'Blog', 'href' => '/blog/', 'active' => strpos($current, '/blog/') === 0];
}
$links[] = ['label' => 'Discography', 'href' => '/discography/', 'active' => strpos($current, '/discography/') === 0];
if (echopress_feature_enabled('contact')) {
  $links[] = ['label' => 'Contact', 'href' => '/contact/', 'active' => strpos($current, '/contact/') === 0];
}

// Allow plugins/themes to modify nav
$links = apply_filters('nav_links', $links, $current);
?>
<header class="header-bar">
  <h1 class="site-title">
    <?= htmlspecialchars($siteName) ?>
  </h1>
  <button class="menu-toggle" aria-label="Menu"><i class="fas fa-bars"></i></button>
  <?php do_action('header_before_nav'); ?>
  <nav class="main-nav">
    <?php foreach ($links as $l):
      $href = (string) ($l['href'] ?? '#');
      $label = (string) ($l['label'] ?? '');
      $active = !empty($l['active']);
      ?>
      <a href="<?= htmlspecialchars($href) ?>"<?= $active ? ' class="current"' : '' ?>><?= htmlspecialchars($label) ?></a>
    <?php endforeach; ?>
    <?php if ($socialLinks): ?>
      <div class="social-links">
        <?php foreach ($socialLinks as $link):
          $url = is_array($link) ? trim((string) ($link['url'] ?? '')) : trim((string) $link);
          $icon = is_array($link) ? trim((string) ($link['icon'] ?? 'fas fa-link')) : 'fas fa-link';
          $label = is_array($link) ? trim((string) ($link['label'] ?? 'Social link')) : 'Social link';
          if ($url === '') { continue; }
          ?>
          <a href="<?= htmlspecialchars($url) ?>" target="_blank" rel="noopener noreferrer"
            class="external-link" aria-label="<?= htmlspecialchars($label) ?>">
            <i class="<?= htmlspecialchars($icon) ?>" aria-hidden="true"></i>
          </a>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </nav>
  <?php do_action('header_after_nav'); ?>
  <?php do_action('header_end'); ?>
</header>
<script src="/js/nav.js?v=<?= htmlspecialchars($assetVersion) ?>"></script>
