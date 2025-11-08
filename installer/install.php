<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/../bootstrap/database.php';

$lockFile = echopress_config('installer.lock_file', __DIR__ . '/../storage/installer.lock');
if (is_file($lockFile)) {
    fwrite(STDOUT, "EchoPress already appears to be installed. Remove {$lockFile} to re-run the installer.\n");
    exit(0);
}

function prompt(string $question, string $default = ''): string
{
    $suffix = $default !== '' ? " [{$default}]" : '';
    fwrite(STDOUT, $question . $suffix . ': ');
    $line = fgets(STDIN);
    if ($line === false) {
        return $default;
    }
    $value = trim($line);
    return $value === '' ? $default : $value;
}

$siteName = prompt('Site name', echopress_site_name());
$siteUrl = prompt('Primary URL (https://example.com)', echopress_primary_url() ?: 'https://example.com');
$contactEmail = prompt('Contact email', echopress_support_email());
$dbDriver = strtolower(prompt('Database driver (sqlite/mysql)', echopress_config('database.default', 'sqlite')));
$mailDriver = strtolower(prompt('Newsletter mail driver (mail/smtp/webhook)', echopress_config('newsletter.mailer.driver', 'mail')));

$env = [
    'ECHOPRESS_SITE_NAME' => $siteName,
    'ECHOPRESS_SITE_URL' => rtrim($siteUrl, '/'),
    'ECHOPRESS_CONTACT_EMAIL' => $contactEmail,
    'ECHOPRESS_DB_CONNECTION' => $dbDriver,
    'ECHOPRESS_MAIL_DRIVER' => $mailDriver,
];

if ($dbDriver === 'mysql') {
    $env['ECHOPRESS_MYSQL_HOST'] = prompt('MySQL host', echopress_config('database.connections.mysql.host', '127.0.0.1'));
    $env['ECHOPRESS_MYSQL_DATABASE'] = prompt('MySQL database', echopress_config('database.connections.mysql.database', 'echopress'));
    $env['ECHOPRESS_MYSQL_USERNAME'] = prompt('MySQL username', echopress_config('database.connections.mysql.username', 'echopress'));
    $env['ECHOPRESS_MYSQL_PASSWORD'] = prompt('MySQL password');
} else {
    $sqlitePath = echopress_config('database.connections.sqlite.database', __DIR__ . '/../storage/database/echopress.sqlite');
    $env['ECHOPRESS_SQLITE_PATH'] = $sqlitePath;
    $dir = dirname($sqlitePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0775, true);
    }
}

$envFile = echopress_path('.env');
$envContent = '';
foreach ($env as $key => $value) {
    $envContent .= $key . '=' . $value . "\n";
}
file_put_contents($envFile, $envContent);
fwrite(STDOUT, "Environment saved to .env\n");

try {
    $pdo = echopress_database();
} catch (Throwable $e) {
    fwrite(STDERR, "Database connection failed: " . $e->getMessage() . "\n");
    exit(1);
}

passthru('php ' . escapeshellarg(__DIR__ . '/migrate.php') . ' --driver=' . escapeshellarg($dbDriver));

$toolMessages = [];
echopress_tools_install_required(['ffmpeg'], $toolMessages);
foreach ($toolMessages as $msg) {
    fwrite(STDOUT, $msg . "\n");
}

touch($lockFile);
fwrite(STDOUT, "Installer complete.\n");
if ($mailDriver === 'smtp') {
    $env['ECHOPRESS_SMTP_HOST'] = prompt('SMTP host', echopress_config('newsletter.mailer.smtp.host', 'smtp.example.com'));
    $env['ECHOPRESS_SMTP_PORT'] = prompt('SMTP port', (string) echopress_config('newsletter.mailer.smtp.port', 587));
    $env['ECHOPRESS_SMTP_USERNAME'] = prompt('SMTP username', echopress_config('newsletter.mailer.smtp.username', ''));
    $env['ECHOPRESS_SMTP_PASSWORD'] = prompt('SMTP password', '');
    $env['ECHOPRESS_SMTP_ENCRYPTION'] = prompt('SMTP encryption (ssl/tls/none)', echopress_config('newsletter.mailer.smtp.encryption', 'tls'));
} elseif ($mailDriver === 'webhook') {
    $env['ECHOPRESS_MAIL_WEBHOOK_URL'] = prompt('Webhook URL', echopress_config('newsletter.mailer.webhook.url', 'https://example.com/newsletter-hook'));
    $env['ECHOPRESS_MAIL_WEBHOOK_SECRET'] = prompt('Webhook secret header (optional)', echopress_config('newsletter.mailer.webhook.secret', ''));
    $env['ECHOPRESS_MAIL_WEBHOOK_METHOD'] = prompt('Webhook method (POST/PUT)', echopress_config('newsletter.mailer.webhook.method', 'POST'));
    $env['ECHOPRESS_MAIL_WEBHOOK_HEADERS'] = prompt('Extra webhook headers (Header: value; comma separated)', echopress_config('newsletter.mailer.webhook.headers', ''));
}
