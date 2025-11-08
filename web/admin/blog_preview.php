<?php
require_once __DIR__ . '/session_secure.php';
session_start();
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}
require_once __DIR__ . '/../includes/utils.php';

$slug = 'preview';
$preview = true;
$dateInput = $_POST['date'] ?? date('Y-m-d H:i');
if (strpos($dateInput, 'T') !== false) {
    $dateInput = str_replace('T', ' ', $dateInput);
}
$post = [
    'title' => $_POST['title'] ?? '',
    'post_date' => $dateInput,
    'categories' => isset($_POST['categories']) ? array_filter((array)$_POST['categories']) : [],
    'body' => $_POST['body'] ?? '',
    'image' => $_POST['orig_image'] ?? '',
    'image_srcset_webp' => $_POST['existing_srcset_webp'] ?? '',
    'image_srcset_jpg' => $_POST['existing_srcset_jpg'] ?? '',
];

include __DIR__ . '/../blog/post.php';

