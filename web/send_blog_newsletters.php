<?php
// send_blog_newsletters.php
// Daily newsletter automation script
// Cron example: 0 7 * * * /usr/bin/php /path/to/send_blog_newsletters.php

require_once __DIR__ . '/includes/app.php';
date_default_timezone_set(echopress_timezone());
require_once __DIR__ . '/admin/db_connect.php';
require_once __DIR__ . '/includes/utils.php';
require_once __DIR__ . '/includes/mailer.php';

echo "Script started...\n";

//–– SITE CONFIG ––
$siteName = echopress_site_name();
$baseUrl = echopress_base_url();
if ($baseUrl === '') {
    $baseUrl = 'https://example.com';
}
$specialTags = [
    'album' => 'New Album',
    'single' => 'New EP/Single',
    'video' => 'New Video',
    'appears' => 'New Guest Appearance',
    'coming-soon' => 'Coming Soon',
];

//–– FETCH TODAYS POSTS ––
$stmt = $pdo->prepare(<<<'SQL'
SELECT p.*, GROUP_CONCAT(c.name ORDER BY c.name SEPARATOR ",") AS cats
  FROM blog_posts p
  LEFT JOIN blog_post_categories pc ON p.id = pc.post_id
  LEFT JOIN blog_categories c     ON pc.category_id = c.id
 WHERE p.published = 1
   AND p.post_date >= DATE_SUB(NOW(), INTERVAL 1 DAY)
 GROUP BY p.id
SQL
);
$stmt->execute();
$posts = $stmt->fetchAll(PDO::FETCH_ASSOC);
foreach ($posts as &$p) {
    $p['categories'] = $p['cats']
        ? explode(',', $p['cats'])
        : [];
}
unset($p);

echo "Found " . count($posts) . " new posts\n";
if (!count($posts)) {
    echo "No posts to send.\n";
    exit;
}

//–– FETCH YOUR SUBSCRIBERS ––
$subs = $pdo
    ->query('SELECT * FROM newsletter_subscribers')
    ->fetchAll(PDO::FETCH_ASSOC);
$logCheck = $pdo->prepare('SELECT 1 FROM newsletter_log WHERE subscriber_id=? AND post_id=?');
$logInsert = $pdo->prepare('INSERT INTO newsletter_log (subscriber_id, post_id) VALUES (?, ?)');

echo "Found " . count($subs) . " subscribers\n";

foreach ($subs as $sub) {
    // 1) collect all new posts for this subscriber
    $send = [];
    foreach ($posts as $post) {
        $logCheck->execute([$sub['id'], $post['id']]);
        if ($logCheck->fetchColumn()) {
            $logCheck->closeCursor();
            continue;
        }
        $logCheck->closeCursor();
        if ($sub['wants_all_posts']) {
            $send[] = $post;
            continue;
        }
        foreach ($specialTags as $slug => $label) {
            $field = 'wants_' . str_replace('-', '_', $slug);
            if ($sub[$field] && in_array($slug, $post['categories'], true)) {
                $send[] = $post;
                break;
            }
        }
    }

    if (empty($send)) {
        echo "No posts for {$sub['email']}\n";
        continue;
    }

    // 2) build the raw HTML body
    $body = '';
    foreach ($send as $p) {
        $url = rtrim($baseUrl, '/') . '/blog/post.php?article=' . urlencode($p['slug']);
        $body .= '<hr>';
        $body .= '<h2><a href="' . $url . '">' . htmlspecialchars($p['title']) . '</a></h2>';
        $body .= '<p><em>Published on '
            . htmlspecialchars(date('F j, Y', strtotime($p['post_date'])))
            . '</em></p>';
        if (!empty($p['image'])) {
            $body .= '<p><img src="'
                . htmlspecialchars($baseUrl . cache_bust($p['image']))
                . '" alt="' . htmlspecialchars($p['title'])
                . '" style="display:block; width:100%; max-width:600px; height:auto; margin:0 auto;"></p>';
        }
        $body .= '<div>' . $p['body'] . '</div>';
        $body .= '<p><a href="' . $url . '">Read on the website</a></p>';
    }
    // ————————————————————————————————
// Build unsubscribe & manage-prefs URLs
    $tokenParam = 'token=' . urlencode($sub['manage_token']);
    // POINT THIS AT WHERE YOUR manage.php LIVES:
    $manageScriptUrl = rtrim($baseUrl, '/') . '/newsletter/manage.php';

    $unsubscribeUrl = "{$manageScriptUrl}?{$tokenParam}&action=unsubscribe";
    $managePrefsUrl = "{$manageScriptUrl}?{$tokenParam}";

    // append to the email body
    $body .= <<<HTML
<hr>
<p style="font-family:Arial,sans-serif;font-size:14px;">
  <a href="{$unsubscribeUrl}" target="_blank" style="color:#555;text-decoration:none;">
    Unsubscribe
  </a> |
  <a href="{$managePrefsUrl}" target="_blank" style="color:#555;text-decoration:none;">
    Manage Preferences
  </a>
</p>
HTML;
    // ————————————————————————————————

    // 3) strip any inline color/background styles
    $cleanBody = preg_replace(
        '/(?:background(?:-color)?|color)\s*:\s*[^;"]+;?/i',
        '',
        $body
    );

    // 4) wrap with a minimal reset stylesheet
    $mailHtml = '<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <style type="text/css">
    body {
      background-color: transparent !important;
      color:            inherit     !important;
      margin:           0;
      padding:          20px;
    }
    * {
      background: transparent !important;
      color:      inherit     !important;
    }
    a {
      color: #1a0dab !important; /* default link blue */
    }
  </style>
</head>
<body>
' . $cleanBody . '
</body>
</html>';

    // 5) pick the subject label
    $subjectLabel = 'New Post from ' . $siteName;
    foreach ($send as $p) {
        foreach ($specialTags as $slug => $label) {
            if (in_array($slug, $p['categories'], true)) {
                $subjectLabel = $label . ' from ' . $siteName;
                break 2;
            }
        }
    }

    // 6) send via configured mail transport
    $sent = echopress_mail([
        'to' => ['email' => $sub['email'], 'name' => $sub['name'] ?: ''],
        'subject' => $subjectLabel,
        'html' => $mailHtml,
        'text' => strip_tags($cleanBody),
        'headers' => [
            'List-Unsubscribe' => "<{$unsubscribeUrl}>",
            'List-Unsubscribe-Post' => 'List-Unsubscribe=One-Click',
        ],
    ]);

    if ($sent) {
        echo "Mail sent to {$sub['email']}\n";
    } else {
        echo "❌ Failed to send newsletter to {$sub['email']}\n";
    }

    // 7) log what we sent
    foreach ($send as $p) {
        try {
            $logInsert->execute([$sub['id'], $p['id']]);
        } catch (\PDOException $logError) {
            if ($logError->getCode() !== '23000') {
                throw $logError;
            }
        }
    }
}
