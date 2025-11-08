<?php
// Handle newsletter signup

require_once __DIR__ . '/../includes/app.php';
require_once __DIR__ . '/../admin/db_connect.php';
require_once __DIR__ . '/../includes/newsletter_guard.php';
require_once __DIR__ . '/../includes/mailer.php';

$versionFile = __DIR__ . '/../version.txt';
$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '0';
$recaptchaSiteKey = newsletter_recaptcha_site_key();
$recaptchaSecret = newsletter_recaptcha_secret();
$siteName = echopress_site_name();
$newsletterSettings = (array) echopress_config('newsletter', []);
$fromName = $newsletterSettings['from_name'] ?? $siteName;
$fromEmail = $newsletterSettings['from_email'] ?? 'newsletter@example.com';
$replyToEmail = $newsletterSettings['reply_to'] ?? '';

$email     = trim($_POST['email'] ?? '');
$name      = trim($_POST['name']  ?? '');
$honeypot  = trim($_POST['nickname'] ?? '');
$captcha   = $_POST['g-recaptcha-response'] ?? '';

if (mb_strlen($name) > 120) {
    $name = mb_substr($name, 0, 120);
}

// build preferences array
$prefs = [
    'album'       => !empty($_POST['album'])       ? 1 : 0,
    'single'      => !empty($_POST['single'])      ? 1 : 0,
    'video'       => !empty($_POST['video'])       ? 1 : 0,
    'appears'     => !empty($_POST['appears'])     ? 1 : 0,
    'coming_soon' => !empty($_POST['coming_soon']) ? 1 : 0,
    'all_posts'   => !empty($_POST['all_posts'])   ? 1 : 0,
];
// if they want all posts, clear the rest
if ($prefs['all_posts']) {
    foreach (['album','single','video','appears','coming_soon'] as $k) {
        $prefs[$k] = 0;
    }
}

$success = false;
$error   = '';
$existing = false;
$manageLink = '';

if ($honeypot !== '') {
    $error = 'Signup blocked. Please contact us if this was a mistake.';
} elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = 'Please enter a valid email address.';
} elseif (!newsletter_has_valid_email_domain($email)) {
    $error = 'We could not verify that email domain. Please try a different address.';
} elseif ($recaptchaSiteKey === '' || $recaptchaSecret === '') {
    $error = 'Signup is temporarily unavailable. Please try again later.';
} elseif (!newsletter_verify_recaptcha($captcha)) {
    $error = 'reCAPTCHA validation failed. Please try again.';
} else {
    [$allowed, $retryAfter] = newsletter_throttle_check($pdo, $_SERVER['REMOTE_ADDR'] ?? '', $email);
    if (!$allowed) {
        $minutes = max(1, (int)ceil($retryAfter / 60));
        $error = $minutes <= 1
            ? 'Too many signup attempts from this network. Please try again in about a minute.'
            : 'Too many signup attempts from this network. Please try again in about ' . $minutes . ' minutes.';
    } else {
        // check if subscriber already exists
        $check = $pdo->prepare('SELECT id, manage_token FROM newsletter_subscribers WHERE email=?');
        $check->execute([$email]);
        if ($row = $check->fetch(PDO::FETCH_ASSOC)) {
            // existing subscriber, keep their manage token
            $token = $row['manage_token'];
            $stmt = $pdo->prepare('
                UPDATE newsletter_subscribers
                   SET name=?,
                       wants_album=?, wants_single=?, wants_video=?,
                       wants_appears=?, wants_coming_soon=?, wants_all_posts=?,
                       via=?
                 WHERE id=?
            ');
            $stmt->execute([
                $name,
                $prefs['album'],
                $prefs['single'],
                $prefs['video'],
                $prefs['appears'],
                $prefs['coming_soon'],
                $prefs['all_posts'],
                'public',
                $row['id']
            ]);
            $existing = true;
        } else {
            // new subscriber
            $token = bin2hex(random_bytes(32));
            $stmt = $pdo->prepare('
                INSERT INTO newsletter_subscribers
                    (email,name,
                     wants_album,wants_single,wants_video,wants_appears,wants_coming_soon,wants_all_posts,
                     manage_token,via)
                VALUES (?,?,?,?,?,?,?,?,?,?)
            ');
            $stmt->execute([
                $email,
                $name,
                $prefs['album'],
                $prefs['single'],
                $prefs['video'],
                $prefs['appears'],
                $prefs['coming_soon'],
                $prefs['all_posts'],
                $token,
                'public'
            ]);
            $existing = false;
        }

        $success = true;

        // build URLs
        $baseUrl        = echopress_base_url()
                        ?: ((isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https')
                           . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost'));
        $manageLink     = '/newsletter/manage.php?token=' . urlencode($token);
        $manageUrl      = rtrim($baseUrl, '/') . $manageLink;
        $unsubscribeUrl = $manageUrl . '&action=unsubscribe';

        // sanitize display name
        $displayName = htmlspecialchars($name ?: 'there', ENT_QUOTES, 'UTF-8');
        $currentYear = date('Y');

        // email subject & body
        $subject = '🎉 Welcome to ' . $siteName . ' newsletter!';
        $body = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>{$subject}</title>
</head>
<body style="margin:0;padding:0;font-family:Arial,sans-serif;color:#333;background-color:#f5f5f5;">
  <table width="100%" cellpadding="0" cellspacing="0" border="0">
    <tr>
      <td align="center" style="padding:20px 0;background-color:#222;">
        <h1 style="margin:0;color:#fff;">{$siteName}</h1>
        <a href="{$baseUrl}"
           style="display:block;color:#fff;text-decoration:none;">
          {$baseUrl}
        </a>
      </td>
    </tr>
    <tr>
      <td style="padding:20px;background-color:#fff;">
        <p>Hi <strong>{$displayName}</strong>,</p>
        <p>Thank you for subscribing to the <strong>{$siteName}</strong> newsletter. You’ll now be first to know about:</p>
        <ul>
          <li>New Albums</li>
          <li>New EPs &amp; Singles</li>
          <li>New Videos &amp; Guest Appearances</li>
          <li>Exclusive Behind-the-Scenes Content</li>
        </ul>
        <p style="text-align:center;margin:30px 0;">
          <a href="{$manageUrl}"
             style="background-color:#0073e6;color:#fff;text-decoration:none;
                    padding:12px 20px;border-radius:4px;display:inline-block;">
            Manage Your Preferences
          </a>
        </p>
        <hr>
        <p style="font-size:12px;color:#777;">
          You can
          <a href="{$manageUrl}" style="color:#0073e6;text-decoration:none;">
            update your preferences
          </a>
          any time, or
          <a href="{$unsubscribeUrl}" style="color:#0073e6;text-decoration:none;">
            unsubscribe
          </a>
          if you ever change your mind.
        </p>
      </td>
    </tr>
    <tr>
      <td align="center" style="padding:10px 0;font-size:12px;color:#aaa;">
        &copy; {$currentYear} {$siteName}
      </td>
    </tr>
  </table>
</body>
</html>
HTML;

        if (!$existing) {
            $message = [
                'to' => ['email' => $email, 'name' => $name],
                'subject' => $subject,
                'html' => $body,
                'text' => strip_tags($body),
                'from' => ['email' => $fromEmail, 'name' => $fromName],
                'headers' => [
                    'List-Unsubscribe' => "<{$unsubscribeUrl}>",
                    'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
                ],
            ];
            if ($replyToEmail !== '') {
                $message['reply_to'] = ['email' => $replyToEmail, 'name' => $fromName];
            }
            echopress_mail($message);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <title>Newsletter Signup</title>
  <link rel="stylesheet" href="/css/style.css?v=<?php echo $version; ?>">
</head>
<body>
  <?php include __DIR__ . '/../includes/header.php'; ?>
  <main class="container">
    <?php if ($success): ?>
      <?php if ($existing): ?>
        <h1>Preferences Updated</h1>
        <p>Your subscription preferences have been updated.</p>
      <?php else: ?>
        <h1>Subscription Confirmed</h1>
        <p>Thank you for subscribing! Check your inbox for a welcome message.</p>
      <?php endif; ?>
      <p>
        You can manage your preferences here:
        <a href="<?= htmlspecialchars($manageLink) ?>">Manage Subscription</a>
      </p>
    <?php else: ?>
      <h1>Signup Error</h1>
      <p class="error"><?= htmlspecialchars($error) ?></p>
      <p><a href="/newsletter/">Return to signup</a></p>
    <?php endif; ?>
  </main>
</body>
</html>
