<?php
session_start();
require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../includes/env_writer.php';

$lockFile = echopress_path('storage/first_run.lock');
if (is_file($lockFile)) {
    header('Location: /');
    exit;
}

$step = isset($_GET['step']) ? max(1, (int) $_GET['step']) : 1;
$data = $_SESSION['first_run'] ?? [];

function posted(): bool { return ($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST'; }
function next_step_url(int $n): string { return '/first-run/?step=' . $n; }

if (posted()) {
    if ($step === 1) {
        $data['site_name'] = trim($_POST['site_name'] ?? '');
        $data['artist_name'] = trim($_POST['artist_name'] ?? '');
        $data['site_url'] = trim($_POST['site_url'] ?? '');
        $data['timezone'] = trim($_POST['timezone'] ?? 'UTC');
        $_SESSION['first_run'] = $data;
        header('Location: ' . next_step_url(2));
        exit;
    }
    if ($step === 2) {
        if (isset($_POST['skip'])) {
            header('Location: ' . next_step_url(3));
            exit;
        }
        $data['smtp'] = [
            'host' => trim($_POST['smtp_host'] ?? ''),
            'port' => (int) ($_POST['smtp_port'] ?? 587),
            'username' => trim($_POST['smtp_username'] ?? ''),
            'password' => trim($_POST['smtp_password'] ?? ''),
            'encryption' => trim($_POST['smtp_encryption'] ?? 'tls'),
        ];
        $_SESSION['first_run'] = $data;
        header('Location: ' . next_step_url(3));
        exit;
    }
    if ($step === 3) {
        $data['features'] = [
            'blog' => isset($_POST['feat_blog']) ? 1 : 0,
            'playlists' => isset($_POST['feat_playlists']) ? 1 : 0,
            'newsletter' => isset($_POST['feat_newsletter']) ? 1 : 0,
            'videos' => isset($_POST['feat_videos']) ? 1 : 0,
            'contact' => isset($_POST['feat_contact']) ? 1 : 0,
        ];
        $_SESSION['first_run'] = $data;
        header('Location: ' . next_step_url(4));
        exit;
    }
    if ($step === 4) {
        $data['theme'] = trim($_POST['theme'] ?? '');
        $_SESSION['first_run'] = $data;

        // Write .env values
        $pairs = [];
        if (!empty($data['site_name'])) $pairs['ECHOPRESS_SITE_NAME'] = $data['site_name'];
        if (!empty($data['artist_name'])) $pairs['ECHOPRESS_ARTIST_NAME'] = $data['artist_name'];
        if (!empty($data['site_url'])) $pairs['ECHOPRESS_SITE_URL'] = rtrim($data['site_url'], '/');
        if (!empty($data['timezone'])) $pairs['ECHOPRESS_TIMEZONE'] = $data['timezone'];
        if (!empty($data['theme'])) $pairs['ECHOPRESS_THEME'] = $data['theme'];
        if (!empty($data['features'])) {
            foreach ($data['features'] as $k => $v) {
                $pairs['ECHOPRESS_FEATURE_' . strtoupper($k)] = (string) ((int) $v);
            }
        }
        if (!empty($data['smtp'])) {
            $s = $data['smtp'];
            if (!empty($s['host'])) $pairs['ECHOPRESS_SMTP_HOST'] = $s['host'];
            if (!empty($s['port'])) $pairs['ECHOPRESS_SMTP_PORT'] = (string) $s['port'];
            if (!empty($s['username'])) $pairs['ECHOPRESS_SMTP_USERNAME'] = $s['username'];
            if (!empty($s['password'])) $pairs['ECHOPRESS_SMTP_PASSWORD'] = $s['password'];
            if (!empty($s['encryption'])) $pairs['ECHOPRESS_SMTP_ENCRYPTION'] = $s['encryption'];
        }
        echopress_env_write($pairs);

        // Lock first-run
        @file_put_contents($lockFile, (string) time());
        unset($_SESSION['first_run']);
        header('Location: /');
        exit;
    }
}

$version = @file_get_contents(__DIR__ . '/../version.txt') ?: '0';

?><!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>First Run – EchoPress</title>
  <link rel="stylesheet" href="/css/style.css?v=<?= htmlspecialchars($version) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
</head>
<body class="container">
  <h1>Welcome to EchoPress</h1>
  <p>Let’s set up the basics. You can change everything later in Admin.</p>

  <?php if ($step === 1): ?>
    <h2>Site Basics</h2>
    <form method="post">
      <label>Site Name<br><input type="text" name="site_name" value="<?= htmlspecialchars($data['site_name'] ?? echopress_site_name()) ?>" required></label><br>
      <label>Artist Name<br><input type="text" name="artist_name" value="<?= htmlspecialchars($data['artist_name'] ?? echopress_artist_name()) ?>" required></label><br>
      <label>Site URL<br><input type="url" name="site_url" value="<?= htmlspecialchars($data['site_url'] ?? echopress_primary_url()) ?>" placeholder="https://example.com"></label><br>
      <label>Timezone<br><input type="text" name="timezone" value="<?= htmlspecialchars($data['timezone'] ?? echopress_timezone()) ?>" placeholder="UTC"></label><br>
      <button type="submit">Continue</button>
    </form>
  <?php elseif ($step === 2): ?>
    <h2>Email (optional)</h2>
    <form method="post">
      <label>SMTP Host<br><input type="text" name="smtp_host" value="<?= htmlspecialchars($data['smtp']['host'] ?? '') ?>"></label><br>
      <label>SMTP Port<br><input type="number" name="smtp_port" value="<?= htmlspecialchars((string) ($data['smtp']['port'] ?? 587)) ?>"></label><br>
      <label>SMTP Username<br><input type="text" name="smtp_username" value="<?= htmlspecialchars($data['smtp']['username'] ?? '') ?>"></label><br>
      <label>SMTP Password<br><input type="password" name="smtp_password" value="<?= htmlspecialchars($data['smtp']['password'] ?? '') ?>"></label><br>
      <label>Encryption<br>
        <select name="smtp_encryption">
          <option value="tls"<?= (($data['smtp']['encryption'] ?? 'tls') === 'tls') ? ' selected' : '' ?>>TLS</option>
          <option value="ssl"<?= (($data['smtp']['encryption'] ?? '') === 'ssl') ? ' selected' : '' ?>>SSL</option>
          <option value=""<?= (($data['smtp']['encryption'] ?? '') === '') ? ' selected' : '' ?>>None</option>
        </select>
      </label><br>
      <button type="submit">Continue</button>
      <button type="submit" name="skip" value="1">Skip</button>
    </form>
  <?php elseif ($step === 3): ?>
    <h2>Features</h2>
    <?php
      $feat = $data['features'] ?? [
        'blog' => (int) echopress_feature_enabled('blog', true),
        'playlists' => (int) echopress_feature_enabled('playlists', true),
        'newsletter' => (int) echopress_feature_enabled('newsletter', true),
        'videos' => (int) echopress_feature_enabled('videos', true),
        'contact' => (int) echopress_feature_enabled('contact', true),
      ];
    ?>
    <form method="post">
      <label><input type="checkbox" name="feat_blog" <?= $feat['blog'] ? 'checked' : '' ?>> Blog</label><br>
      <label><input type="checkbox" name="feat_playlists" <?= $feat['playlists'] ? 'checked' : '' ?>> Playlists</label><br>
      <label><input type="checkbox" name="feat_newsletter" <?= $feat['newsletter'] ? 'checked' : '' ?>> Newsletter</label><br>
      <label><input type="checkbox" name="feat_videos" <?= $feat['videos'] ? 'checked' : '' ?>> Videos</label><br>
      <label><input type="checkbox" name="feat_contact" <?= $feat['contact'] ? 'checked' : '' ?>> Contact</label><br>
      <button type="submit">Continue</button>
    </form>
  <?php elseif ($step === 4): ?>
    <h2>Theme</h2>
    <form method="post">
      <?php
        $themesDir = __DIR__ . '/../themes';
        $options = ['' => 'Core (no theme)'];
        if (is_dir($themesDir)) {
            foreach (glob($themesDir . '/*', GLOB_ONLYDIR) as $dir) {
                $slug = basename($dir);
                $meta = ['name' => $slug];
                $json = $dir . '/theme.json';
                if (is_file($json)) {
                    $decoded = json_decode(file_get_contents($json), true);
                    if (is_array($decoded) && !empty($decoded['name'])) $meta['name'] = $decoded['name'];
                }
                $options[$slug] = $meta['name'];
            }
        }
        $current = $data['theme'] ?? echopress_theme_active();
      ?>
      <label>Active Theme<br>
        <select name="theme">
          <?php foreach ($options as $slug => $name): ?>
            <option value="<?= htmlspecialchars($slug) ?>"<?= ($slug === $current) ? ' selected' : '' ?>><?= htmlspecialchars($name) ?></option>
          <?php endforeach; ?>
        </select>
      </label><br>
      <button type="submit">Finish</button>
    </form>
  <?php endif; ?>

  <p style="margin-top:2rem"><a href="/">Skip setup</a></p>
</body>
</html>

