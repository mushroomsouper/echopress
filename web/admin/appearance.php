<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/env_writer.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$themesDir = $_SERVER['DOCUMENT_ROOT'] . '/themes';
$themes = ['' => ['slug' => '', 'name' => 'Core (no theme)']];
if (is_dir($themesDir)) {
    foreach (glob($themesDir . '/*', GLOB_ONLYDIR) as $dir) {
        $slug = basename($dir);
        $name = $slug;
        $json = $dir . '/theme.json';
        if (is_file($json)) {
            $data = json_decode(file_get_contents($json), true);
            if (is_array($data) && !empty($data['name'])) $name = $data['name'];
        }
        $themes[$slug] = ['slug' => $slug, 'name' => $name];
    }
}

$message = $_SESSION['appearance_message'] ?? '';
unset($_SESSION['appearance_message']);

// Handle theme switching
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'switch_theme') {
    $theme = trim($_POST['theme'] ?? '');
    $ok = echopress_env_write(['ECHOPRESS_THEME' => $theme]);
    echopress_config_reload();
    $_SESSION['appearance_message'] = $ok ? 'Theme updated.' : 'Failed to write .env';
    header('Location: appearance.php');
    exit;
}

// Handle variable overrides save/reset
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_vars') {
    $active = echopress_theme_active();
    if ($active !== '') {
        $overridePath = echopress_storage_path('themes/' . $active);
        if (!is_dir($overridePath)) {
            @mkdir($overridePath, 0775, true);
        }
        $file = $overridePath . '/variables.json';
        $names = (array) ($_POST['var_name'] ?? []);
        $values = (array) ($_POST['var_value'] ?? []);
        $out = [];
        foreach ($names as $idx => $name) {
            $name = trim((string) $name);
            if ($name === '') continue;
            if (strpos($name, '--') !== 0) $name = '--' . ltrim($name, '-');
            $name = preg_replace('/[^a-z0-9\-]/i', '', $name);
            if ($name === '' || strpos($name, '--') !== 0) continue;
            $val = isset($values[$idx]) ? (string) $values[$idx] : '';
            $out[$name] = $val;
        }
        @file_put_contents($file, json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        $_SESSION['appearance_message'] = 'Theme variables saved.';
    } else {
        $_SESSION['appearance_message'] = 'Select a theme to edit variables.';
    }
    header('Location: appearance.php');
    exit;
}
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && isset($_POST['action']) && $_POST['action'] === 'reset_vars') {
    $active = echopress_theme_active();
    if ($active !== '') {
        $file = echopress_storage_path('themes/' . $active . '/variables.json');
        if (is_file($file)) @unlink($file);
        $_SESSION['appearance_message'] = 'Theme variable overrides cleared.';
    }
    header('Location: appearance.php');
    exit;
}

$current = echopress_theme_active();
$baseVars = [];
$overrideVars = [];
if ($current !== '') {
    $baseJson = $_SERVER['DOCUMENT_ROOT'] . '/themes/' . $current . '/theme.json';
    if (is_file($baseJson)) {
        $data = json_decode(file_get_contents($baseJson), true) ?: [];
        if (!empty($data['variables']) && is_array($data['variables'])) {
            $baseVars = $data['variables'];
        }
    }
    $overrideJson = echopress_storage_path('themes/' . $current . '/variables.json');
    if (is_file($overrideJson)) {
        $o = json_decode(file_get_contents($overrideJson), true) ?: [];
        if (is_array($o)) $overrideVars = $o;
    }
}
$mergedVars = $baseVars;
foreach ($overrideVars as $k => $v) { $mergedVars[$k] = $v; }
$version = @file_get_contents($_SERVER['DOCUMENT_ROOT'] . '/version.txt') ?: '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Appearance</title>
  <link rel="stylesheet" href="/css/style.css?v=<?= htmlspecialchars($version) ?>">
  <link rel="stylesheet" href="/css/admin.css?v=<?= htmlspecialchars($version) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <?php include $_SERVER['DOCUMENT_ROOT'] . '/includes/theme_vars.php'; ?>
  <style>
    .vars-table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
    .vars-table th, .vars-table td { border: 1px solid #333; padding: .5rem; }
    .preview-card { margin-top: 1rem; padding: 1rem; border-radius: 8px; background: var(--theme-color, #111); color: var(--text-color, #fff); }
    .row-actions { text-align: right; }
    .add-row { margin-top: .5rem; }
  </style>
</head>
<body>
  <h1>Appearance</h1>
  <p><a href="index.php">← Back to Admin</a></p>
  <?php if ($message): ?><p><?= htmlspecialchars($message) ?></p><?php endif; ?>
  <form method="post">
    <input type="hidden" name="action" value="switch_theme">
    <label>Active Theme
      <select name="theme">
        <?php foreach ($themes as $slug => $meta): ?>
          <option value="<?= htmlspecialchars($slug) ?>"<?= ($slug === $current) ? ' selected' : '' ?>><?= htmlspecialchars($meta['name']) ?></option>
        <?php endforeach; ?>
      </select>
    </label>
    <button type="submit">Save</button>
  </form>
  <hr>
  <h2>Theme Variables</h2>
  <?php if ($current === ''): ?>
    <p>Select a theme to edit variables.</p>
  <?php else: ?>
  <form method="post" id="vars-form">
    <input type="hidden" name="action" value="save_vars">
    <table class="vars-table" id="vars-table">
      <thead>
        <tr><th>Variable (must start with --)</th><th>Value</th></tr>
      </thead>
      <tbody>
        <?php foreach ($mergedVars as $k => $v): ?>
          <tr>
            <td><input type="text" name="var_name[]" value="<?= htmlspecialchars($k) ?>" required></td>
            <td><input type="text" name="var_value[]" value="<?= htmlspecialchars((string)$v) ?>" class="var-input"></td>
          </tr>
        <?php endforeach; ?>
        <tr>
          <td><input type="text" name="var_name[]" placeholder="--new-var" ></td>
          <td><input type="text" name="var_value[]" placeholder="#abcdef or any CSS value" class="var-input"></td>
        </tr>
      </tbody>
    </table>
    <button type="button" class="add-row" id="add-row">Add Row</button>
    <div class="row-actions">
      <button type="submit">Save Variables</button>
    </div>
  </form>
  <form method="post" style="margin-top:.5rem">
    <input type="hidden" name="action" value="reset_vars">
    <button type="submit" onclick="return confirm('Clear all overrides?')">Reset Overrides</button>
  </form>
  <div class="preview-card">
    <strong>Live Preview</strong>
    <p>This box uses your theme variables (background: var(--theme-color); color: var(--text-color)). Adjust values above to preview instantly.</p>
    <p><a href="/" target="_blank" style="color:inherit;text-decoration:underline">Open Site</a></p>
  </div>
  <script>
    (function(){
      function applyVars() {
        const names = document.querySelectorAll('input[name="var_name[]"]');
        const values = document.querySelectorAll('input[name="var_value[]"]');
        names.forEach((n, i) => {
          const name = (n.value || '').trim();
          const val = (values[i] ? values[i].value : '').trim();
          if (name.startsWith('--') && name.match(/^[A-Za-z0-9\-]+$/)) {
            document.documentElement.style.setProperty(name, val);
          }
        });
      }
      document.getElementById('vars-form').addEventListener('input', applyVars);
      document.getElementById('add-row').addEventListener('click', function(){
        const tr = document.createElement('tr');
        tr.innerHTML = '<td><input type="text" name="var_name[]" placeholder="--new-var"></td>' +
                       '<td><input type="text" name="var_value[]" placeholder="#abcdef or any CSS value" class="var-input"></td>';
        document.querySelector('#vars-table tbody').appendChild(tr);
      });
    })();
  </script>
  <?php endif; ?>
  <h2>Preview</h2>
  <p>The active theme’s variables apply site‑wide. Open the homepage in a new tab to check styles.</p>
  <p><a href="/" target="_blank">Open Site</a></p>
</body>
</html>
