<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
if (empty($_SESSION['logged_in'])) { header('Location: login.php'); exit; }
$id = isset($_POST['id']) ? (int)$_POST['id'] : 0;
if ($id) {
    $fetch = $pdo->prepare('SELECT email,name,via FROM newsletter_subscribers WHERE id=?');
    $fetch->execute([$id]);
    if ($row = $fetch->fetch(PDO::FETCH_ASSOC)) {
        $pdo->prepare('INSERT INTO newsletter_unsubscribed (subscriber_id,email,name,via) VALUES (?,?,?,?)')
            ->execute([$id, $row['email'], $row['name'], $row['via']]);
    }
    $stmt = $pdo->prepare('DELETE FROM newsletter_subscribers WHERE id=?');
    $stmt->execute([$id]);
}
header('Location: newsletter.php');

