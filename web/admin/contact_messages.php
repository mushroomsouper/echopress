<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';

if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

function mask_email(string $email): string {
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    $local = $parts[0];
    $domain = $parts[1];
    if (strlen($local) <= 2) {
        $local = substr($local, 0, 1) . str_repeat('*', max(strlen($local)-1,0));
    } else {
        $local = $local[0] . str_repeat('*', strlen($local)-2) . $local[-1];
    }
    return $local . '@' . $domain;
}

$stmt = $pdo->query('SELECT * FROM contact_messages ORDER BY created_at DESC');
$messages = $stmt->fetchAll();

$versionFile = __DIR__ . '/../version.txt';
$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Contact Activity</title>
  <link rel="stylesheet" href="/css/style.css?v=<?= htmlspecialchars($version) ?>">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <link rel="stylesheet" href="/css/admin.css?v=<?= htmlspecialchars($version) ?>">
  <script src="/js/admin-session.js?v=<?= htmlspecialchars($version) ?>" defer></script>
</head>
<body>
<h1>Contact Form Activity</h1>
<p><a href="index.php">Back to Admin</a></p>
<table class="album-list">
  <thead>
  <tr>
    <th>Date</th>
    <th>Name</th>
    <th>Email</th>
    <th>Message</th>
    <th>IP</th>
    <th>Agent</th>
    <th>Sent</th>
  </tr>
  </thead>
  <tbody>
  <?php foreach ($messages as $m): ?>
    <tr>
      <td><?= htmlspecialchars($m['created_at']) ?></td>
      <td><?= htmlspecialchars($m['name']) ?></td>
      <td><?= htmlspecialchars(mask_email($m['email'])) ?></td>
      <td><?= nl2br(htmlspecialchars($m['message'])) ?></td>
      <td><?= htmlspecialchars($m['ip_address']) ?></td>
      <td><?= htmlspecialchars($m['user_agent']) ?></td>
      <td><?= $m['sent_success'] ? 'Yes' : 'No' ?></td>
    </tr>
  <?php endforeach; ?>
  </tbody>
</table>
</body>
</html>
