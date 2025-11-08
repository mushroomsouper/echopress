<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/srcset.php';
if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$bioText = $_POST['bio_text'] ?? '';
$existing = $_POST['existing_image'] ?? '';
$existingWebp = $_POST['existing_srcset_webp'] ?? '';
$existingJpg  = $_POST['existing_srcset_jpg'] ?? '';
$bioFile = __DIR__ . '/../profile/bio/bio.json';
$bioDir  = __DIR__ . '/../profile/bio';
if (!is_dir($bioDir)) {
    mkdir($bioDir, 0777, true);
}
$imagePath = $existing;
$webpSet = $existingWebp;
$jpgSet  = $existingJpg;
if (!empty($_FILES['bio_image']['tmp_name'])) {
    foreach (glob("$bioDir/bio*.{jpg,jpeg,png,gif,webp}", GLOB_BRACE) as $old) {
        if (is_file($old)) unlink($old);
    }
    list($imagePath, $webpSet, $jpgSet) = create_image_set(
        $_FILES['bio_image']['tmp_name'],
        $bioDir,
        'bio',
        $_FILES['bio_image']['name'] ?? '',
        'profile/bio/'
    );
}
file_put_contents($bioFile, json_encode([
    'text' => $bioText,
    'image' => $imagePath,
    'srcsetWebp' => $webpSet,
    'srcsetJpg'  => $jpgSet
], JSON_PRETTY_PRINT));
header('Location: index.php');
