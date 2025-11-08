<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../includes/newsletter_guard.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: newsletter.php');
    exit;
}

$cutoffRaw   = trim($_POST['cutoff_date'] ?? '');
$checkDns    = !empty($_POST['check_dns']);
$cutoffLabel = 'none';
$cutoffDate  = null;

if ($cutoffRaw !== '') {
    $dt = DateTime::createFromFormat('Y-m-d', $cutoffRaw, new DateTimeZone('UTC'));
    if ($dt) {
        $dt->setTime(0, 0, 0);
        $cutoffDate = $dt;
        $cutoffLabel = $dt->format('Y-m-d');
    }
}

$toDelete = [];
$suspiciousDomains = [];
$all = $pdo->query('SELECT id,email,name,via,subscribed_at FROM newsletter_subscribers ORDER BY subscribed_at DESC');

while ($row = $all->fetch(PDO::FETCH_ASSOC)) {
    if ($row['via'] === 'admin') {
        continue;
    }
    $reasons = [];
    if ($cutoffDate) {
        $subscribed = DateTime::createFromFormat('Y-m-d H:i:s', $row['subscribed_at']);
        if ($subscribed && $subscribed >= $cutoffDate) {
            $reasons[] = 'cutoff-date';
        }
    }
    if ($checkDns && !newsletter_has_valid_email_domain($row['email'])) {
        $reasons[] = 'invalid-domain';
        $domain = substr(strrchr($row['email'], '@'), 1) ?: '';
        if ($domain !== '') {
            $suspiciousDomains[$domain] = ($suspiciousDomains[$domain] ?? 0) + 1;
        }
    }
    if (!empty($reasons)) {
        $toDelete[] = [
            'id'       => (int)$row['id'],
            'email'    => $row['email'],
            'name'     => $row['name'],
            'via'      => $row['via'],
            'reasons'  => $reasons,
            'signedUp' => $row['subscribed_at'],
        ];
    }
}

$deleteStmt = $pdo->prepare('DELETE FROM newsletter_subscribers WHERE id = ?');
$unsubStmt  = $pdo->prepare('INSERT INTO newsletter_unsubscribed (subscriber_id,email,name,via) VALUES (?,?,?,?)');

$deletedCount = 0;
foreach ($toDelete as $item) {
    $deleteStmt->execute([$item['id']]);
    $unsubStmt->execute([$item['id'], $item['email'], $item['name'], 'admin']);
    $deletedCount++;
}

header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Newsletter Cleanup Results</title>
    <link rel="stylesheet" href="/css/style.css?v=<?php echo htmlspecialchars(file_exists(__DIR__ . '/../version.txt') ? trim(file_get_contents(__DIR__ . '/../version.txt')) : '1'); ?>">
    <style>
        body { padding: 20px; }
        table { border-collapse: collapse; width: 100%; margin-top: 20px; }
        th, td { border: 1px solid #444; padding: 8px; text-align: left; }
        .meta { margin-bottom: 15px; }
    </style>
</head>
<body>
    <h1>Newsletter Cleanup Results</h1>
    <p class="meta">
        Removed <strong><?= $deletedCount ?></strong> subscribers.<br>
        Cutoff date: <strong><?= htmlspecialchars($cutoffLabel) ?></strong><br>
        DNS validation: <strong><?= $checkDns ? 'enabled' : 'disabled' ?></strong>
    </p>
    <?php if ($deletedCount): ?>
    <table>
        <thead>
            <tr>
                <th>Email</th>
                <th>Subscribed At</th>
                <th>Reasons</th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($toDelete as $row): ?>
            <tr>
                <td><?= htmlspecialchars($row['email']) ?></td>
                <td><?= htmlspecialchars($row['signedUp']) ?></td>
                <td><?= htmlspecialchars(implode(', ', $row['reasons'])) ?></td>
            </tr>
        <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <p>No subscribers matched the cleanup rules.</p>
    <?php endif; ?>

    <?php if ($suspiciousDomains): ?>
    <h2>Suspicious Domains</h2>
    <ul>
        <?php foreach ($suspiciousDomains as $domain => $count): ?>
        <li><?= htmlspecialchars($domain) ?> (<?= (int)$count ?>)</li>
        <?php endforeach; ?>
    </ul>
    <?php endif; ?>

    <p><a href="newsletter.php">Return to newsletter admin</a></p>
</body>
</html>
