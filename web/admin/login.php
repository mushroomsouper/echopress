<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user = $_POST['username'] ?? '';
    $pass = $_POST['password'] ?? '';

    $stmt = $pdo->prepare('SELECT password_hash FROM users WHERE username = ?');
    $stmt->execute([$user]);
    $row = $stmt->fetch();
    if ($row && password_verify($pass, $row['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['logged_in'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error = 'Invalid credentials';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<?php
    $pageTitle = 'Admin Login';
    $headExtras = '<link rel="stylesheet" href="/css/admin.css?v=<?php echo $version; ?>">';
    $versionFile = __DIR__ . '/../version.txt';
    $version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1';
    $pageDescription = $pageDescription ?? '';
    $pageKeywords = $pageKeywords ?? '';
?>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
<?php if ($pageDescription !== ''): ?>
    <meta name="description" content="<?= htmlspecialchars($pageDescription) ?>" />
<?php endif; ?>
<?php if ($pageKeywords !== ''): ?>
    <meta name="keywords" content="<?= htmlspecialchars($pageKeywords) ?>" />
<?php endif; ?>
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/css/style.css?v=<?= htmlspecialchars($version) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <?= $headExtras ?>
    <link rel="apple-touch-icon" sizes="180x180" href="/profile/favicon/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/profile/favicon/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/profile/favicon/favicon-16x16.png">
    <link rel="manifest" href="/profile/favicon/site.webmanifest">
    <link rel="icon" href="/profile/favicon/favicon.ico">
</head>
<body>
<h1>Login</h1>
<?php if ($error): ?><p style="color:red;"><?= htmlspecialchars($error) ?></p><?php endif; ?>
<form method="post">
    <label>Username: <input type="text" name="username"></label><br>
    <label>Password: <input type="password" name="password"></label><br>
    <button type="submit">Login</button>
</form>
</body>
</html>
