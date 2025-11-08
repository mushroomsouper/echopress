<?php
$current = strtok($_SERVER['REQUEST_URI'], '?');
?>
<nav class="main-nav">
  <a href="/"<?php if ($current === '/' || $current === '/index.php' || $current === '/index.html') echo ' class="current"'; ?>>Home</a>

  <a href="/blog/"<?php if (strpos($current, '/blog/') === 0) echo ' class="current"'; ?>>Blog</a>
  <a href="/discography/"<?php if (strpos($current, '/discography/') === 0) echo ' class="current"'; ?>>Discography</a>
  <a href="/contact.php"<?php if ($current === '/contact.php') echo ' class="current"'; ?>>Contact</a>
  
</nav>

