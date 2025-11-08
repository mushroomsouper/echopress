<?php
declare(strict_types=1);

require_once dirname(__DIR__) . '/bootstrap/app.php';

$tools = $argv;
array_shift($tools);
if (empty($tools)) {
    $tools = array_keys(echopress_tools_manifest());
}

$messages = [];
$ok = echopress_tools_install_required($tools, $messages);
foreach ($messages as $msg) {
    echo $msg, PHP_EOL;
}
if (!$ok) {
    exit(1);
}
