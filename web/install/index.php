<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';
require_once dirname(__DIR__, 2) . '/installer/migrate_lib.php';

if (echopress_is_installed()) {
    header('Location: /');
    exit;
}

$storageDir = echopress_storage_path();
$requirements = [
    ['label' => 'PHP 8.1+', 'ok' => version_compare(PHP_VERSION, '8.1.0', '>=')],
    ['label' => 'PDO extension', 'ok' => extension_loaded('PDO')],
    ['label' => 'OpenSSL extension', 'ok' => extension_loaded('openssl')],
    ['label' => 'Writable storage directory', 'ok' => is_dir($storageDir) ? is_writable($storageDir) : is_writable(dirname($storageDir))],
];

// Additional preflight checks (informational; do not block install)
$recommended = [
    ['label' => 'Database driver available (pdo_sqlite or pdo_mysql)', 'ok' => (bool) (extension_loaded('pdo_sqlite') || extension_loaded('pdo_mysql'))],
    ['label' => 'mbstring extension (recommended)', 'ok' => extension_loaded('mbstring')],
    ['label' => 'fileinfo extension (recommended)', 'ok' => extension_loaded('fileinfo')],
    ['label' => 'GD or Imagick for image processing (recommended)', 'ok' => (bool) (extension_loaded('gd') || extension_loaded('imagick'))],
];

// Writable paths created/used by Admin when managing content
$discogDir = realpath(__DIR__ . '/../discography') ?: (__DIR__ . '/../discography');
$blogDir   = realpath(__DIR__ . '/../blog') ?: (__DIR__ . '/../blog');
$pathChecks = [
    ['label' => 'Writable discography directory (web/discography)', 'ok' => is_dir($discogDir) ? is_writable($discogDir) : is_writable(dirname($discogDir))],
    ['label' => 'Writable blog directory (web/blog)', 'ok' => is_dir($blogDir) ? is_writable($blogDir) : is_writable(dirname($blogDir))],
];

// Routing hint
$docroot = $_SERVER['DOCUMENT_ROOT'] ?? '';
$inWeb = false;
if ($docroot !== '') {
    $inWeb = strpos(realpath(__DIR__ . '/..') ?: '', realpath($docroot) ?: '') === 0;
}
$routingHint = $inWeb ? 'Serving directly from /web as document root.' : 'Using root proxy to route into /web.';
$autoPrepend = (string) ini_get('auto_prepend_file');

$canInstall = array_reduce($requirements, fn($carry, $item) => $carry && $item['ok'], true);

function post_value(string $key, $default = '') {
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

$defaults = [
    'site_name' => echopress_site_name(),
    'artist_name' => echopress_artist_name(),
    'site_tagline' => echopress_site_tagline(),
    'site_url' => echopress_primary_url() ?: ('https://' . ($_SERVER['HTTP_HOST'] ?? 'example.com')),
    'contact_email' => echopress_support_email(),
    'timezone' => echopress_timezone(),
    'db_driver' => 'sqlite',
    'mysql_host' => '127.0.0.1',
    'mysql_port' => '3306',
    'mysql_database' => 'echopress',
    'mysql_username' => 'echopress',
    'mysql_password' => '',
    'mail_driver' => 'mail',
    'smtp_host' => 'smtp.example.com',
    'smtp_port' => '587',
    'smtp_username' => '',
    'smtp_password' => '',
    'smtp_encryption' => 'tls',
    'webhook_url' => '',
    'webhook_secret' => '',
    'webhook_method' => 'POST',
    'webhook_headers' => '',
    'newsletter_enabled' => '1',
];

$values = [];
foreach ($defaults as $key => $default) {
    $values[$key] = post_value($key, $default);
}

$errors = [];
$success = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $canInstall) {
    if ($values['site_name'] === '') {
        $errors[] = 'Site name is required.';
    }
    if (!filter_var($values['site_url'], FILTER_VALIDATE_URL)) {
        $errors[] = 'Primary URL must be a valid URL.';
    }
    if (!filter_var($values['contact_email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Contact email must be valid.';
    }
    if ($values['timezone'] === '') {
        $errors[] = 'Timezone is required.';
    }

    $dbDriver = strtolower($values['db_driver'] ?: 'sqlite');
    if ($dbDriver === 'mysql') {
        foreach (['mysql_host', 'mysql_database', 'mysql_username'] as $field) {
            if ($values[$field] === '') {
                $errors[] = 'MySQL ' . strtoupper(str_replace('mysql_', '', $field)) . ' is required.';
            }
        }
    }

    $mailDriver = strtolower($values['mail_driver'] ?: 'mail');
    if ($mailDriver === 'smtp') {
        if ($values['smtp_host'] === '') {
            $errors[] = 'SMTP host is required.';
        }
        if (!ctype_digit($values['smtp_port'])) {
            $errors[] = 'SMTP port must be numeric.';
        }
    } elseif ($mailDriver === 'webhook') {
        if (!filter_var($values['webhook_url'], FILTER_VALIDATE_URL)) {
            $errors[] = 'Webhook URL must be valid.';
        }
    }

    if (!$errors) {
        if (!is_dir($storageDir)) {
            mkdir($storageDir, 0775, true);
        }
        if (!is_dir($storageDir . '/database')) {
            mkdir($storageDir . '/database', 0775, true);
        }
        if (!is_dir(echopress_path('updates/packages'))) {
            mkdir(echopress_path('updates/packages'), 0775, true);
        }

        $env = [
            'ECHOPRESS_SITE_NAME' => $values['site_name'],
            'ECHOPRESS_SITE_TAGLINE' => $values['site_tagline'],
            'ECHOPRESS_SITE_URL' => rtrim($values['site_url'], '/'),
            'ECHOPRESS_CONTACT_EMAIL' => $values['contact_email'],
            'ECHOPRESS_TIMEZONE' => $values['timezone'],
            'ECHOPRESS_DB_CONNECTION' => $dbDriver,
            'ECHOPRESS_MAIL_DRIVER' => $mailDriver,
            'ECHOPRESS_ARTIST_NAME' => $values['artist_name'],
            'ECHOPRESS_FEATURE_NEWSLETTER' => ((isset($_POST['newsletter_enabled']) && $_POST['newsletter_enabled'] === '1') ? '1' : '0'),
        ];

        if ($dbDriver === 'mysql') {
            $env['ECHOPRESS_MYSQL_HOST'] = $values['mysql_host'];
            $env['ECHOPRESS_MYSQL_PORT'] = $values['mysql_port'] ?: '3306';
            $env['ECHOPRESS_MYSQL_DATABASE'] = $values['mysql_database'];
            $env['ECHOPRESS_MYSQL_USERNAME'] = $values['mysql_username'];
            $env['ECHOPRESS_MYSQL_PASSWORD'] = $values['mysql_password'];
        } else {
            $sqlitePath = echopress_storage_path('database/echopress.sqlite');
            $env['ECHOPRESS_SQLITE_PATH'] = $sqlitePath;
        }

        if ($mailDriver === 'smtp') {
            $env['ECHOPRESS_SMTP_HOST'] = $values['smtp_host'];
            $env['ECHOPRESS_SMTP_PORT'] = $values['smtp_port'] ?: '587';
            $env['ECHOPRESS_SMTP_USERNAME'] = $values['smtp_username'];
            $env['ECHOPRESS_SMTP_PASSWORD'] = $values['smtp_password'];
            $env['ECHOPRESS_SMTP_ENCRYPTION'] = $values['smtp_encryption'] ?: 'tls';
        } elseif ($mailDriver === 'webhook') {
            $env['ECHOPRESS_MAIL_WEBHOOK_URL'] = $values['webhook_url'];
            $env['ECHOPRESS_MAIL_WEBHOOK_SECRET'] = $values['webhook_secret'];
            $env['ECHOPRESS_MAIL_WEBHOOK_METHOD'] = strtoupper($values['webhook_method'] ?: 'POST');
            $env['ECHOPRESS_MAIL_WEBHOOK_HEADERS'] = $values['webhook_headers'];
        }

        $envFile = echopress_path('.env');
        $envContent = '';
        foreach ($env as $key => $value) {
            $escaped = addcslashes((string) $value, "\\\"");
            $envContent .= sprintf("%s=\"%s\"\n", $key, $escaped);
            putenv($key . '=' . $value);
            $_ENV[$key] = $value;
        }
        file_put_contents($envFile, $envContent);

        echopress_config_reload();

        try {
            echopress_run_migrations($dbDriver);
            $toolMessages = [];
            $toolsOk = echopress_tools_install_required(['ffmpeg'], $toolMessages);
            if (!$toolsOk) {
                $errors = array_merge($errors, $toolMessages);
            }
            if (!$errors) {
                touch(echopress_install_lock_path());
                $success = true;
            }
        } catch (Throwable $e) {
            $errors[] = 'Migration failed: ' . $e->getMessage();
        }

        if ($success) {
            header('Location: /first-run/');
            exit;
        }
    }
}

function checked($value, $expected)
{
    return $value === $expected ? 'checked' : '';
}

function selected($value, $expected)
{
    return $value === $expected ? 'selected' : '';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EchoPress Installer</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <link rel="stylesheet" href="/css/style.css?v=<?= htmlspecialchars(echopress_asset_version()) ?>">
    <style>
        body { font-family: sans-serif; padding: 2rem; background: #0c0c0f; color: #f4f4f4; }
        .card { max-width: 800px; margin: 0 auto; background: #1b1b21; padding: 2rem; border-radius: 12px; }
        label { display: block; margin-top: 1rem; }
        input, select, textarea { width: 100%; padding: 0.75rem; border-radius: 6px; border: 1px solid #333; background: #0f0f14; color: #f4f4f4; }
        .fieldset { background: #13131a; padding: 1rem; border-radius: 8px; margin-top: 1rem; }
        .actions { margin-top: 2rem; }
        .requirements { list-style: none; padding: 0; }
        .requirements li { margin-bottom: 0.5rem; }
        .error { color: #ff6b6b; }
        .success { color: #5ad678; }
    </style>
</head>
<body>
<div class="card">
    <h1>EchoPress Installer</h1>
    <p>Step 1: Preflight. Step 2: Settings.</p>
    <h2>Preflight</h2>
    <h3>Requirements</h3>
    <ul class="requirements">
        <?php foreach ($requirements as $req): ?>
            <li><?= $req['ok'] ? '✅' : '⚠️' ?> <?= htmlspecialchars($req['label']) ?></li>
        <?php endforeach; ?>
    </ul>
    <h3>Recommended</h3>
    <ul class="requirements">
        <?php foreach ($recommended as $rec): ?>
            <li><?= $rec['ok'] ? '✅' : 'ℹ️' ?> <?= htmlspecialchars($rec['label']) ?></li>
        <?php endforeach; ?>
    </ul>
    <h3>Paths</h3>
    <ul class="requirements">
        <?php foreach ($pathChecks as $pc): ?>
            <li><?= $pc['ok'] ? '✅' : 'ℹ️' ?> <?= htmlspecialchars($pc['label']) ?></li>
        <?php endforeach; ?>
    </ul>
    <h3>Routing</h3>
    <ul class="requirements">
        <li>ℹ️ <?= htmlspecialchars($routingHint) ?></li>
        <li>ℹ️ auto_prepend_file: <?= htmlspecialchars($autoPrepend !== '' ? $autoPrepend : 'none') ?></li>
    </ul>
    <?php if (!$canInstall): ?>
        <p class="error">Please satisfy the requirements above before continuing.</p>
    <?php endif; ?>

    <?php if ($errors): ?>
        <div class="error">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <!-- EchoPress Wizard -->
    <form method="post" id="wizard-form" style="margin-bottom:2rem;">
      <input type="hidden" name="current_step" id="current_step" value="<?= htmlspecialchars((string)($_POST['current_step'] ?? '1')) ?>">
      <!-- Step 1: Preflight (animated) -->
      <section class="wizard-step" data-step="1">
        <p>Step 1 of 7 — Preflight checks</p>
        <ul class="requirements checks" id="checks-required">
          <?php foreach ($requirements as $req): ?>
            <li class="check-item" data-ok="<?= $req['ok'] ? '1' : '0' ?>"><span class="icon">⏳</span> <?= htmlspecialchars($req['label']) ?></li>
          <?php endforeach; ?>
        </ul>
        <h3>Recommended</h3>
        <ul class="requirements checks" id="checks-recommended">
          <?php foreach ($recommended as $rec): ?>
            <li class="check-item" data-ok="<?= $rec['ok'] ? '1' : '0' ?>"><span class="icon">⏳</span> <?= htmlspecialchars($rec['label']) ?></li>
          <?php endforeach; ?>
        </ul>
        <h3>Paths</h3>
        <ul class="requirements checks" id="checks-paths">
          <?php foreach ($pathChecks as $pc): ?>
            <li class="check-item" data-ok="<?= $pc['ok'] ? '1' : '0' ?>"><span class="icon">⏳</span> <?= htmlspecialchars($pc['label']) ?></li>
          <?php endforeach; ?>
        </ul>
        <h3>Routing</h3>
        <ul class="requirements">
          <li>ℹ️ <?= htmlspecialchars($routingHint) ?></li>
          <li>ℹ️ auto_prepend_file: <?= htmlspecialchars($autoPrepend !== '' ? $autoPrepend : 'none') ?></li>
        </ul>
        <p id="preflight-summary" class="success" style="display:none;">Preflight complete. Continue to settings.</p>
      </section>

      <!-- Step 2: Site -->
      <section class="wizard-step" data-step="2" style="display:none;">
        <p>Step 2 of 7 — Site</p>
        <label>Site Name<input type="text" name="site_name" value="<?= htmlspecialchars($values['site_name']) ?>" required></label>
        <label>Artist Name<input type="text" name="artist_name" value="<?= htmlspecialchars($values['artist_name'] ?? echopress_artist_name()) ?>" required></label>
        <label>Tagline (optional)<input type="text" name="site_tagline" value="<?= htmlspecialchars($values['site_tagline']) ?>"></label>
        <label>Primary URL<input type="url" name="site_url" value="<?= htmlspecialchars($values['site_url']) ?>" required></label>
      </section>

      <!-- Step 3: Email -->
      <section class="wizard-step" data-step="3" style="display:none;">
        <p>Step 3 of 7 — Email</p>
        <label>Contact Email<input type="email" name="contact_email" value="<?= htmlspecialchars($values['contact_email']) ?>" required></label>
      </section>

      <!-- Step 4: Timezone (searchable) -->
      <section class="wizard-step" data-step="4" style="display:none;">
        <p>Step 4 of 7 — Timezone</p>
        <input type="text" id="tz-filter" placeholder="Search timezone…" autocomplete="off">
        <select name="timezone" id="tz-select" size="8" required>
          <?php foreach (timezone_identifiers_list() as $tz): ?>
            <option value="<?= htmlspecialchars($tz) ?>"<?= $tz === $values['timezone'] ? ' selected' : '' ?>><?= htmlspecialchars($tz) ?></option>
          <?php endforeach; ?>
        </select>
      </section>

      <!-- Step 5: Database -->
      <section class="wizard-step" data-step="5" style="display:none;">
        <p>Step 5 of 7 — Database</p>
        <p><strong>SQLite</strong> is easiest on shared hosting. <strong>MySQL/MariaDB</strong> is better for larger sites or multiple admins.</p>
        <label><input type="radio" name="db_driver" value="sqlite" <?= checked($values['db_driver'], 'sqlite') ?>> SQLite (default)</label>
        <label><input type="radio" name="db_driver" value="mysql" <?= checked($values['db_driver'], 'mysql') ?>> MySQL / MariaDB</label>
        <div id="mysql-fields" style="display: <?= $values['db_driver'] === 'mysql' ? 'block' : 'none' ?>; margin-top:.5rem;">
          <label>Host<input type="text" name="mysql_host" value="<?= htmlspecialchars($values['mysql_host']) ?>"></label>
          <label>Port<input type="text" name="mysql_port" value="<?= htmlspecialchars($values['mysql_port']) ?>"></label>
          <label>Database Name<input type="text" name="mysql_database" value="<?= htmlspecialchars($values['mysql_database']) ?>"></label>
          <label>Username<input type="text" name="mysql_username" value="<?= htmlspecialchars($values['mysql_username']) ?>"></label>
          <label>Password<input type="password" name="mysql_password" value="<?= htmlspecialchars($values['mysql_password']) ?>"></label>
        </div>
      </section>

      <!-- Step 6: Newsletter -->
      <section class="wizard-step" data-step="6" style="display:none;">
        <p>Step 6 of 7 — Newsletter (optional)</p>
        <!-- Hidden default ensures a value is submitted even if checkbox is unchecked -->
        <input type="hidden" name="newsletter_enabled" value="0">
        <label><input type="checkbox" id="newsletter-enabled" name="newsletter_enabled" value="1" <?= ($values['newsletter_enabled'] === '1' ? 'checked' : '') ?>> Enable Newsletter</label>
        <div id="newsletter-settings" style="margin-top:.5rem;">
          <label><input type="radio" name="mail_driver" value="mail" <?= checked($values['mail_driver'], 'mail') ?>> PHP mail()</label>
          <label><input type="radio" name="mail_driver" value="smtp" <?= checked($values['mail_driver'], 'smtp') ?>> SMTP</label>
          <label><input type="radio" name="mail_driver" value="webhook" <?= checked($values['mail_driver'], 'webhook') ?>> Webhook</label>
          <div id="smtp-fields" style="display: <?= $values['mail_driver'] === 'smtp' ? 'block' : 'none' ?>;">
            <label>SMTP Host<input type="text" name="smtp_host" value="<?= htmlspecialchars($values['smtp_host']) ?>"></label>
            <label>SMTP Port<input type="text" name="smtp_port" value="<?= htmlspecialchars($values['smtp_port']) ?>"></label>
            <label>SMTP Username<input type="text" name="smtp_username" value="<?= htmlspecialchars($values['smtp_username']) ?>"></label>
            <label>SMTP Password<input type="password" name="smtp_password" value="<?= htmlspecialchars($values['smtp_password']) ?>"></label>
            <label>Encryption
              <select name="smtp_encryption">
                <option value="tls" <?= selected($values['smtp_encryption'], 'tls') ?>>TLS</option>
                <option value="ssl" <?= selected($values['smtp_encryption'], 'ssl') ?>>SSL</option>
                <option value="none" <?= selected($values['smtp_encryption'], 'none') ?>>None</option>
              </select>
            </label>
          </div>
          <div id="webhook-fields" style="display: <?= $values['mail_driver'] === 'webhook' ? 'block' : 'none' ?>;">
            <label>Webhook URL<input type="url" name="webhook_url" value="<?= htmlspecialchars($values['webhook_url']) ?>"></label>
            <label>Webhook Secret Header<input type="text" name="webhook_secret" value="<?= htmlspecialchars($values['webhook_secret']) ?>"></label>
            <label>HTTP Method
              <select name="webhook_method">
                <option value="POST" <?= selected(strtoupper($values['webhook_method']), 'POST') ?>>POST</option>
                <option value="PUT" <?= selected(strtoupper($values['webhook_method']), 'PUT') ?>>PUT</option>
                <option value="PATCH" <?= selected(strtoupper($values['webhook_method']), 'PATCH') ?>>PATCH</option>
              </select>
            </label>
            <label>Additional Headers (comma separated `Header: value`)
              <textarea name="webhook_headers" rows="3"><?= htmlspecialchars($values['webhook_headers']) ?></textarea>
            </label>
          </div>
        </div>
      </section>

      <!-- Step 7: Review & Install -->
      <section class="wizard-step" data-step="7" style="display:none;">
        <p>Step 7 of 7 — Review</p>
        <div id="review"></div>
        <p>Click Install to write configuration and set up the database.</p>
      </section>

      <div class="actions">
        <button type="button" id="prev-btn" disabled>Back</button>
        <button type="button" id="next-btn">Next</button>
        <button type="submit" id="install-btn" style="display:none;" <?= $canInstall ? '' : 'disabled' ?>>Install EchoPress</button>
      </div>
    </form>

    <!-- Legacy static form below is hidden by JS; kept as fallback -->
    <form method="post" id="legacy-install-form">
        <label>Site Name
            <input type="text" name="site_name" value="<?= htmlspecialchars($values['site_name']) ?>" required>
        </label>
        <label>Tagline
            <input type="text" name="site_tagline" value="<?= htmlspecialchars($values['site_tagline']) ?>">
        </label>
        <label>Primary URL
            <input type="url" name="site_url" value="<?= htmlspecialchars($values['site_url']) ?>" required>
        </label>
        <label>Contact Email
            <input type="email" name="contact_email" value="<?= htmlspecialchars($values['contact_email']) ?>" required>
        </label>
        <label>Timezone (e.g. UTC, America/Los_Angeles)
            <input type="text" name="timezone" value="<?= htmlspecialchars($values['timezone']) ?>" required>
        </label>

        <div class="fieldset">
            <strong>Database</strong><br>
            <label><input type="radio" name="db_driver" value="sqlite" <?= checked($values['db_driver'], 'sqlite') ?>> SQLite (default)</label>
            <label><input type="radio" name="db_driver" value="mysql" <?= checked($values['db_driver'], 'mysql') ?>> MySQL / MariaDB</label>
            <div id="mysql-fields" style="display: <?= $values['db_driver'] === 'mysql' ? 'block' : 'none' ?>;">
                <label>Host
                    <input type="text" name="mysql_host" value="<?= htmlspecialchars($values['mysql_host']) ?>">
                </label>
                <label>Port
                    <input type="text" name="mysql_port" value="<?= htmlspecialchars($values['mysql_port']) ?>">
                </label>
                <label>Database Name
                    <input type="text" name="mysql_database" value="<?= htmlspecialchars($values['mysql_database']) ?>">
                </label>
                <label>Username
                    <input type="text" name="mysql_username" value="<?= htmlspecialchars($values['mysql_username']) ?>">
                </label>
                <label>Password
                    <input type="password" name="mysql_password" value="<?= htmlspecialchars($values['mysql_password']) ?>">
                </label>
            </div>
        </div>

        <div class="fieldset">
            <strong>Newsletter Delivery</strong><br>
            <label><input type="radio" name="mail_driver" value="mail" <?= checked($values['mail_driver'], 'mail') ?>> PHP mail()</label>
            <label><input type="radio" name="mail_driver" value="smtp" <?= checked($values['mail_driver'], 'smtp') ?>> SMTP</label>
            <label><input type="radio" name="mail_driver" value="webhook" <?= checked($values['mail_driver'], 'webhook') ?>> Webhook (SendGrid, custom API, etc.)</label>

            <div id="smtp-fields" style="display: <?= $values['mail_driver'] === 'smtp' ? 'block' : 'none' ?>;">
                <label>SMTP Host
                    <input type="text" name="smtp_host" value="<?= htmlspecialchars($values['smtp_host']) ?>">
                </label>
                <label>SMTP Port
                    <input type="text" name="smtp_port" value="<?= htmlspecialchars($values['smtp_port']) ?>">
                </label>
                <label>SMTP Username
                    <input type="text" name="smtp_username" value="<?= htmlspecialchars($values['smtp_username']) ?>">
                </label>
                <label>SMTP Password
                    <input type="password" name="smtp_password" value="<?= htmlspecialchars($values['smtp_password']) ?>">
                </label>
                <label>Encryption
                    <select name="smtp_encryption">
                        <option value="tls" <?= selected($values['smtp_encryption'], 'tls') ?>>TLS</option>
                        <option value="ssl" <?= selected($values['smtp_encryption'], 'ssl') ?>>SSL</option>
                        <option value="none" <?= selected($values['smtp_encryption'], 'none') ?>>None</option>
                    </select>
                </label>
            </div>

            <div id="webhook-fields" style="display: <?= $values['mail_driver'] === 'webhook' ? 'block' : 'none' ?>;">
                <label>Webhook URL
                    <input type="url" name="webhook_url" value="<?= htmlspecialchars($values['webhook_url']) ?>">
                </label>
                <label>Webhook Secret Header
                    <input type="text" name="webhook_secret" value="<?= htmlspecialchars($values['webhook_secret']) ?>">
                </label>
                <label>HTTP Method
                    <select name="webhook_method">
                        <option value="POST" <?= selected(strtoupper($values['webhook_method']), 'POST') ?>>POST</option>
                        <option value="PUT" <?= selected(strtoupper($values['webhook_method']), 'PUT') ?>>PUT</option>
                        <option value="PATCH" <?= selected(strtoupper($values['webhook_method']), 'PATCH') ?>>PATCH</option>
                    </select>
                </label>
                <label>Additional Headers (comma separated `Header: value`)
                    <textarea name="webhook_headers" rows="3"><?= htmlspecialchars($values['webhook_headers']) ?></textarea>
                </label>
            </div>
        </div>

        <div class="actions">
            <button type="submit" <?= $canInstall ? '' : 'disabled' ?>>Install EchoPress</button>
        </div>
    </form>
</div>
<script>
(function(){
  // Hide legacy preflight blocks (keep error messages visible)
  try {
    const card = document.querySelector('.card');
    if (card) {
      card.querySelectorAll('h2, h3, .requirements').forEach(el => { el.style.display = 'none'; });
    }
  } catch(e) {}

  const steps = Array.from(document.querySelectorAll('.wizard-step'));
  const prevBtn = document.getElementById('prev-btn');
  const nextBtn = document.getElementById('next-btn');
  const installBtn = document.getElementById('install-btn');
  let idx = 0;
  const initialStep = parseInt('<?= isset($_POST['current_step']) ? (int) $_POST['current_step'] : 1 ?>', 10) || 1;

  function showStep(i){
    steps.forEach((s, j) => s.style.display = (i===j) ? 'block' : 'none');
    prevBtn.disabled = (i === 0);
    nextBtn.style.display = (i < steps.length - 1) ? 'inline-block' : 'none';
    installBtn.style.display = (i === steps.length - 1) ? 'inline-block' : 'none';
    // Persist step for postback
    const stepInput = document.getElementById('current_step');
    if (stepInput) stepInput.value = String(i + 1);
    if (i === 0) animateChecks();
    if (i === steps.length - 1) buildReview();
  }

  prevBtn.addEventListener('click', () => { if (idx>0) { idx--; showStep(idx); } });
  nextBtn.addEventListener('click', () => { if (idx<steps.length-1) { idx++; showStep(idx); } });

  // Validate on Install; if invalid, jump to the step containing the first invalid field
  installBtn.addEventListener('click', (ev) => {
    const form = document.getElementById('wizard-form');
    if (!form) return;
    if (!form.reportValidity()) {
      ev.preventDefault();
      const firstInvalid = form.querySelector(':invalid');
      if (firstInvalid) {
        // Find which step contains it
        let s = firstInvalid.closest('.wizard-step');
        if (s) {
          const stepIndex = steps.indexOf(s);
          if (stepIndex >= 0) {
            idx = stepIndex;
            showStep(idx);
            // Focus the field so the browser shows the message
            try { firstInvalid.focus(); } catch(e) {}
          }
        }
      }
    } else {
      // Give visual feedback while submitting
      nextBtn.disabled = true; prevBtn.disabled = true; installBtn.disabled = true;
      installBtn.textContent = 'Installing…';
    }
  });

  function animateChecks(){
    const items = document.querySelectorAll('.checks .check-item');
    let i = 0;
    function tick(){
      if (i >= items.length) {
        const required = document.querySelectorAll('#checks-required .check-item');
        let allOk = true;
        required.forEach(li => { if (li.dataset.ok !== '1') allOk = false; });
        nextBtn.disabled = !allOk;
        const sum = document.getElementById('preflight-summary');
        if (sum) sum.style.display = allOk ? 'block' : 'none';
        return;
      }
      const li = items[i];
      const ok = li.dataset.ok === '1';
      const icon = li.querySelector('.icon'); if (icon) icon.textContent = ok ? '✅' : '⚠️';
      li.style.opacity = '1';
      i++;
      setTimeout(tick, 250);
    }
    items.forEach(li => { li.style.opacity = '0.6'; });
    nextBtn.disabled = true;
    tick();
  }

  const tzFilter = document.getElementById('tz-filter');
  const tzSelect = document.getElementById('tz-select');
  if (tzFilter && tzSelect) {
    tzFilter.addEventListener('input', () => {
      const q = tzFilter.value.toLowerCase();
      Array.from(tzSelect.options).forEach(opt => {
        const match = opt.value.toLowerCase().includes(q);
        opt.style.display = match ? '' : 'none';
      });
    });
  }

  const dbRadios = document.querySelectorAll('input[name="db_driver"]');
  const mysqlFields = document.getElementById('mysql-fields');
  function updateDbFields(){
    const sel = document.querySelector('input[name="db_driver"]:checked');
    if (mysqlFields) mysqlFields.style.display = (sel && sel.value === 'mysql') ? 'block' : 'none';
  }
  dbRadios.forEach(r => r.addEventListener('change', updateDbFields));
  updateDbFields();

  const newsletterEnabled = document.getElementById('newsletter-enabled');
  const newsletterSettings = document.getElementById('newsletter-settings');
  const mailRadios = document.querySelectorAll('input[name="mail_driver"]');
  const smtpFields = document.getElementById('smtp-fields');
  const webhookFields = document.getElementById('webhook-fields');
  function updateNewsletter(){
    const on = newsletterEnabled && newsletterEnabled.checked;
    if (newsletterEnabled) newsletterEnabled.value = on ? '1' : '0';
    if (newsletterSettings) newsletterSettings.style.display = on ? 'block' : 'none';
  }
  function updateMailFields(){
    const sel = document.querySelector('input[name="mail_driver"]:checked');
    const driver = sel ? sel.value : 'mail';
    if (smtpFields) smtpFields.style.display = driver === 'smtp' ? 'block' : 'none';
    if (webhookFields) webhookFields.style.display = driver === 'webhook' ? 'block' : 'none';
  }
  if (newsletterEnabled) newsletterEnabled.addEventListener('change', updateNewsletter);
  mailRadios.forEach(r => r.addEventListener('change', updateMailFields));
  updateNewsletter();
  updateMailFields();

  function buildReview(){
    const review = document.getElementById('review');
    if (!review) return;
    const data = new FormData(document.getElementById('wizard-form'));
    const rows = [];
    function add(label, key, transform){
      let val = (data.get(key) || '');
      if (typeof transform === 'function') val = transform(val);
      rows.push(`<tr><td style=\"padding:.25rem .5rem;opacity:.8;\">${label}</td><td style=\"padding:.25rem .5rem;\">${val}</td></tr>`);
    }
    add('Site Name','site_name');
    add('Artist Name','artist_name');
    add('Tagline','site_tagline');
    add('URL','site_url');
    add('Email','contact_email');
    add('Timezone','timezone');
    const driver = (data.get('db_driver')||'');
    add('DB Driver','db_driver');
    if (driver === 'mysql') {
      add('MySQL Host','mysql_host');
      add('MySQL DB','mysql_database');
    }
    add('Newsletter Enabled','newsletter_enabled', v => (v==='1' ? 'Yes' : 'No'));
    add('Mail Driver','mail_driver');
    review.innerHTML = `<table style=\"width:100%;border-collapse:collapse;\">${rows.join('')}</table>`;
  }

  showStep(Math.max(0, initialStep - 1));
})();
</script>
</body>
</html>
