<?php
require_once __DIR__ . '/app.php';

/**
 * Utility helpers for securing newsletter signups.
 */

if (!defined('NEWSLETTER_THROTTLE_MAX_ATTEMPTS')) {
    define('NEWSLETTER_THROTTLE_MAX_ATTEMPTS', 5);
}
if (!defined('NEWSLETTER_THROTTLE_WINDOW_SECONDS')) {
    define('NEWSLETTER_THROTTLE_WINDOW_SECONDS', 900); // 15 minutes
}
if (!defined('NEWSLETTER_THROTTLE_RETENTION_SECONDS')) {
    define('NEWSLETTER_THROTTLE_RETENTION_SECONDS', 172800); // 48 hours
}

/**
 * Validate that an email belongs to a domain with DNS records.
 */
function newsletter_has_valid_email_domain(string $email): bool
{
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }
    $domain = substr(strrchr($email, '@'), 1);
    if (!$domain) {
        return false;
    }
    if (!preg_match('/^[A-Z0-9.-]+\.[A-Z]{2,}$/i', $domain)) {
        return false;
    }
    if (checkdnsrr($domain, 'MX') || checkdnsrr($domain, 'A')) {
        return true;
    }
    return false;
}

function newsletter_verify_recaptcha(string $captchaResponse): bool
{
    $secret = newsletter_recaptcha_secret();
    if ($secret === '') {
        return false;
    }
    if ($captchaResponse === '') {
        return false;
    }
    $payload = http_build_query([
        'secret'   => $secret,
        'response' => $captchaResponse,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]);
    $context = stream_context_create([
        'http' => [
            'header'  => "Content-type: application/x-www-form-urlencoded\r\n",
            'method'  => 'POST',
            'content' => $payload,
            'timeout' => 5,
        ],
    ]);
    $result = @file_get_contents('https://www.google.com/recaptcha/api/siteverify', false, $context);
    if ($result === false && function_exists('curl_init')) {
        $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
        curl_setopt_array($ch, [
            CURLOPT_POST           => true,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POSTFIELDS     => $payload,
            CURLOPT_TIMEOUT        => 5,
        ]);
        $result = curl_exec($ch);
        curl_close($ch);
    }
    if ($result === false) {
        return false;
    }
    $data = json_decode($result, true);
    return is_array($data) && !empty($data['success']);
}

function newsletter_recaptcha_site_key(): string
{
    $siteKey = (string) echopress_config('newsletter.recaptcha.site_key', '');
    if ($siteKey !== '') {
        return $siteKey;
    }
    return (string) echopress_config('contact.recaptcha.site_key', '');
}

function newsletter_recaptcha_secret(): string
{
    $secret = (string) echopress_config('newsletter.recaptcha.secret_key', '');
    if ($secret !== '') {
        return $secret;
    }
    return (string) echopress_config('contact.recaptcha.secret_key', '');
}

/**
 * Simple IP-based throttle to limit repeated signup attempts.
 *
 * @return array{0:bool,1:int} Tuple indicating whether the request is allowed and
 *                            how many seconds remain until the next attempt is permitted.
 */
function newsletter_throttle_check(PDO $pdo, string $ip, string $email, int $maxAttempts = NEWSLETTER_THROTTLE_MAX_ATTEMPTS, int $windowSeconds = NEWSLETTER_THROTTLE_WINDOW_SECONDS): array
{
    $ip = trim($ip) !== '' ? trim($ip) : 'unknown';
    $ip = substr($ip, 0, 45);
    $email = substr($email, 0, 255);

    $now = time();
    $windowStart = date('Y-m-d H:i:s', $now - $windowSeconds);
    $pruneCutoff = date('Y-m-d H:i:s', $now - NEWSLETTER_THROTTLE_RETENTION_SECONDS);

    try {
        // prune old rows to keep the table lean
        $pruneStmt = $pdo->prepare('DELETE FROM newsletter_attempts WHERE attempted_at < ?');
        $pruneStmt->execute([$pruneCutoff]);

        $countStmt = $pdo->prepare('SELECT COUNT(*) FROM newsletter_attempts WHERE ip_address = ? AND attempted_at >= ?');
        $countStmt->execute([$ip, $windowStart]);
        $attempts = (int)$countStmt->fetchColumn();

        if ($attempts >= $maxAttempts) {
            $oldestStmt = $pdo->prepare('SELECT MIN(attempted_at) FROM newsletter_attempts WHERE ip_address = ? AND attempted_at >= ?');
            $oldestStmt->execute([$ip, $windowStart]);
            $oldest = $oldestStmt->fetchColumn();
            $retryAfter = $windowSeconds;
            if ($oldest) {
                $retryAfter = max(0, $windowSeconds - max(0, $now - strtotime($oldest)));
            }
            return [false, $retryAfter];
        }

        $insertStmt = $pdo->prepare('INSERT INTO newsletter_attempts (ip_address, email, attempted_at) VALUES (?, ?, NOW())');
        $insertStmt->execute([$ip, $email]);
    } catch (\PDOException $e) {
        // If the throttle table has not been provisioned yet, allow the attempt.
        if ($e->getCode() === '42S02') {
            return [true, 0];
        }
        throw $e;
    }

    return [true, 0];
}
