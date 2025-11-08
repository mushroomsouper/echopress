<?php
declare(strict_types=1);

use PHPMailer\PHPMailer\Exception as PHPMailerException;
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/app.php';
require_once __DIR__ . '/../lib/PHPMailer/src/PHPMailer.php';
require_once __DIR__ . '/../lib/PHPMailer/src/SMTP.php';
require_once __DIR__ . '/../lib/PHPMailer/src/Exception.php';

function echopress_mail(array $message, ?array $transport = null): bool
{
    $transport = $transport ?? (array) echopress_config('newsletter.mailer', []);
    $driver = strtolower((string) ($transport['driver'] ?? 'mail'));

    $from = echopress_normalize_mailbox($message['from'] ?? [
        'email' => echopress_config('newsletter.from_email', 'newsletter@example.com'),
        'name' => echopress_config('newsletter.from_name', echopress_artist_name()),
    ]);
    $replyTo = echopress_normalize_mailbox($message['reply_to'] ?? null);
    $to = echopress_normalize_recipients($message['to'] ?? []);

    if (!$to) {
        return false;
    }

    $subject = trim((string) ($message['subject'] ?? ''));
    $htmlBody = (string) ($message['html'] ?? '');
    $textBody = (string) ($message['text'] ?? strip_tags($htmlBody));

    if ($driver === 'smtp') {
        return echopress_send_via_smtp($transport['smtp'] ?? [], $from, $to, $subject, $htmlBody, $textBody, $replyTo, $message['headers'] ?? []);
    }

    if ($driver === 'webhook') {
        return echopress_send_via_webhook($transport['webhook'] ?? [], $from, $to, $subject, $htmlBody, $textBody, $replyTo, $message['headers'] ?? []);
    }

    return echopress_send_via_mail($from, $to, $subject, $htmlBody ?: $textBody, $replyTo, $message['headers'] ?? []);
}

function echopress_send_via_smtp(array $config, array $from, array $to, string $subject, string $html, string $text, ?array $replyTo, array $extraHeaders): bool
{
    $mailer = new PHPMailer(true);
    try {
        $mailer->isSMTP();
        $mailer->Host = $config['host'] ?? '';
        $mailer->Port = (int) ($config['port'] ?? 587);
        if (!empty($config['username'])) {
            $mailer->SMTPAuth = true;
            $mailer->Username = $config['username'];
            $mailer->Password = $config['password'] ?? '';
        }
        $encryption = strtolower((string) ($config['encryption'] ?? 'tls'));
        if (in_array($encryption, ['ssl', 'tls'], true)) {
            $mailer->SMTPSecure = $encryption;
        }
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom($from['email'], $from['name'] ?? '');
        if ($replyTo) {
            $mailer->addReplyTo($replyTo['email'], $replyTo['name'] ?? '');
        }
        foreach ($to as $recipient) {
            $mailer->addAddress($recipient['email'], $recipient['name'] ?? '');
        }
        foreach ($extraHeaders as $headerName => $headerValue) {
            $mailer->addCustomHeader($headerName, $headerValue);
        }
        $mailer->Subject = $subject;
        if ($html !== '') {
            $mailer->isHTML(true);
            $mailer->Body = $html;
            if ($text !== '') {
                $mailer->AltBody = $text;
            }
        } else {
            $mailer->Body = $text;
        }
        return $mailer->send();
    } catch (PHPMailerException $e) {
        error_log('EchoPress SMTP error: ' . $e->getMessage());
        return false;
    }
}

function echopress_send_via_mail(array $from, array $to, string $subject, string $body, ?array $replyTo, array $extraHeaders): bool
{
    $headers = [
        'MIME-Version' => '1.0',
        'Content-Type' => 'text/html; charset=UTF-8',
        'From' => echopress_format_mailbox($from),
    ];
    if ($replyTo) {
        $headers['Reply-To'] = echopress_format_mailbox($replyTo);
    }
    foreach ($extraHeaders as $name => $value) {
        $headers[$name] = $value;
    }
    $headerString = '';
    foreach ($headers as $name => $value) {
        $headerString .= $name . ': ' . $value . "\r\n";
    }
    $toHeader = implode(', ', array_map('echopress_format_mailbox', $to));
    return mail($toHeader, $subject, $body, rtrim($headerString));
}

function echopress_send_via_webhook(array $config, array $from, array $to, string $subject, string $html, string $text, ?array $replyTo, array $extraHeaders): bool
{
    $url = trim((string) ($config['url'] ?? ''));
    if ($url === '') {
        error_log('EchoPress webhook mailer requires a URL.');
        return false;
    }
    $method = strtoupper($config['method'] ?? 'POST');
    if (!in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
        $method = 'POST';
    }
    $headers = ['Content-Type: application/json'];
    $secret = trim((string) ($config['secret'] ?? ''));
    if ($secret !== '') {
        $headers[] = 'X-EchoPress-Signature: ' . $secret;
    }
    $extraHeaderString = trim((string) ($config['headers'] ?? ''));
    if ($extraHeaderString !== '') {
        foreach (preg_split('/[\r\n;,]+/', $extraHeaderString) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }
            $headers[] = $line;
        }
    }

    $payload = [
        'subject' => $subject,
        'html' => $html,
        'text' => $text,
        'from' => $from,
        'reply_to' => $replyTo,
        'recipients' => $to,
        'headers' => $extraHeaders,
    ];
    $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    if ($body === false) {
        return false;
    }

    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_TIMEOUT => 10,
        ]);
        $response = curl_exec($ch);
        $error = curl_error($ch);
        $status = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);
        if ($response === false || ($status >= 400 && $status !== 0)) {
            error_log('EchoPress webhook mailer error: ' . ($error ?: 'HTTP ' . $status));
            return false;
        }
        return true;
    }

    $context = stream_context_create([
        'http' => [
            'method' => $method,
            'header' => implode("\r\n", $headers),
            'content' => $body,
            'timeout' => 10,
        ],
    ]);
    $result = @file_get_contents($url, false, $context);
    if ($result === false) {
        error_log('EchoPress webhook mailer error: failed to reach endpoint.');
        return false;
    }
    return true;
}

function echopress_normalize_recipients($value): array
{
    if (is_string($value)) {
        return [echopress_normalize_mailbox($value)];
    }
    if (isset($value['email'])) {
        return [echopress_normalize_mailbox($value)];
    }
    $recipients = [];
    foreach ((array) $value as $entry) {
        $mailbox = echopress_normalize_mailbox($entry);
        if ($mailbox) {
            $recipients[] = $mailbox;
        }
    }
    return $recipients;
}

function echopress_normalize_mailbox($value): ?array
{
    if (is_string($value)) {
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        return ['email' => $value];
    }
    if (!is_array($value)) {
        return null;
    }
    $email = trim((string) ($value['email'] ?? ''));
    if ($email === '') {
        return null;
    }
    $name = trim((string) ($value['name'] ?? ''));
    return ['email' => $email, 'name' => $name];
}

function echopress_format_mailbox(array $mailbox): string
{
    $email = $mailbox['email'];
    $name = trim($mailbox['name'] ?? '');
    if ($name === '') {
        return $email;
    }
    return sprintf('"%s" <%s>', addslashes($name), $email);
}
