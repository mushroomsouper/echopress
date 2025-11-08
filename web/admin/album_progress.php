<?php
require_once __DIR__ . '/session_secure.php';
session_start();

header('Content-Type: application/json');
header('Cache-Control: no-store, no-cache, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

if (empty($_SESSION['logged_in'])) {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Authentication required']);
    exit;
}

// We only needed the session to confirm authentication; release the lock so
// the long-running save_album.php script can continue writing progress updates.
session_write_close();

$job = $_GET['job'] ?? '';
$job = preg_replace('/[^a-zA-Z0-9_-]/', '', $job);
if ($job === '') {
    http_response_code(400);
    echo json_encode(['status' => 'error', 'message' => 'Missing job id']);
    exit;
}

$progressDir = __DIR__ . '/progress';
$path = $progressDir . '/' . $job . '.json';
if (!is_file($path)) {
    echo json_encode(['status' => 'pending', 'job' => $job]);
    exit;
}

$data = json_decode(file_get_contents($path), true);
if (!is_array($data)) {
    $data = ['status' => 'pending'];
}
$data['job'] = $job;
$data['updated'] = filemtime($path) ?: time();

echo json_encode($data);
