<?php
declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/bootstrap/app.php';

$recaptchaSite = echopress_config('contact.recaptcha.site_key', '');
$recaptchaSecret = echopress_config('contact.recaptcha.secret_key', '');

define('RECAPTCHA_SITE_KEY', $recaptchaSite);
define('RECAPTCHA_SECRET_KEY', $recaptchaSecret);

$ALBUMS_DIR = __DIR__ . '/../discography/albums';
$PLAYLISTS_DIR = __DIR__ . '/../discography/playlists';
