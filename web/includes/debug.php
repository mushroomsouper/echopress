<?php
$debugEnv   = getenv('DEBUG');
$debugLevel = getenv('DEBUG_LEVEL');
// allow ?debug=1 in the query string to toggle debugging
$debugQuery = isset($_GET['debug']) ? $_GET['debug'] : null;

$debugOn = false;
if ($debugEnv !== false && strtolower($debugEnv) !== '0' && strtolower($debugEnv) !== 'false') {
    $debugOn = true;
}
if ($debugQuery !== null && strtolower($debugQuery) !== '0' && strtolower($debugQuery) !== 'false') {
    $debugOn = true;
}

if ($debugOn) {
    ini_set('display_errors', '1');
    ini_set('display_startup_errors', '1');

    if ($debugLevel && strtolower($debugLevel) === 'notice') {
        error_reporting(E_ALL & ~E_STRICT);
    } else {
        error_reporting(E_ALL);
    }

    $logFile = getenv('DEBUG_LOG');
    if ($logFile) {
        ini_set('log_errors', '1');
        ini_set('error_log', $logFile);
    }
}
