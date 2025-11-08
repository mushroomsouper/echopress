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

$canInstall = array_reduce($requirements, fn($carry, $item) => $carry && $item['ok'], true);

function post_value(string $key, $default = '') {
    return isset($_POST[$key]) ? trim((string) $_POST[$key]) : $default;
}

$defaults = [
    'site_name' => echopress_site_name(),
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
            header('Location: /admin/login.php?installed=1');
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
    <p>Complete the form below to configure your site. Once finished, you’ll be redirected to the admin login.</p>
    <h2>Requirements</h2>
    <ul class="requirements">
        <?php foreach ($requirements as $req): ?>
            <li><?= $req['ok'] ? '✅' : '⚠️' ?> <?= htmlspecialchars($req['label']) ?></li>
        <?php endforeach; ?>
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

    <form method="post">
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
const dbRadios = document.querySelectorAll('input[name="db_driver"]');
const mysqlFields = document.getElementById('mysql-fields');
function updateDbFields() {
    const selected = document.querySelector('input[name="db_driver"]:checked');
    if (selected && selected.value === 'mysql') {
        mysqlFields.style.display = 'block';
    } else {
        mysqlFields.style.display = 'none';
    }
}
dbRadios.forEach(r => r.addEventListener('change', updateDbFields));
updateDbFields();

const mailRadios = document.querySelectorAll('input[name="mail_driver"]');
const smtpFields = document.getElementById('smtp-fields');
const webhookFields = document.getElementById('webhook-fields');
function updateMailFields() {
    const selected = document.querySelector('input[name="mail_driver"]:checked');
    const driver = selected ? selected.value : 'mail';
    smtpFields.style.display = driver === 'smtp' ? 'block' : 'none';
    webhookFields.style.display = driver === 'webhook' ? 'block' : 'none';
}
mailRadios.forEach(r => r.addEventListener('change', updateMailFields));
updateMailFields();
</script>
</body>
</html>
