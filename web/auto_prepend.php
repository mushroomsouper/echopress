<?php
// Adjust PHP runtime state so EchoPress behaves as if /web is the docroot
// when the actual vhost docroot points above this folder.

$webRoot = realpath(__DIR__);
if ($webRoot && is_dir($webRoot)) {
    // Make absolute-path includes like $_SERVER['DOCUMENT_ROOT'].'/includes/..' resolve under /web
    $_SERVER['DOCUMENT_ROOT'] = $webRoot;

    // Ensure relative includes work the same as if running inside /web
    @chdir($webRoot);
}

// Optional: normalize SCRIPT_NAME when front controller is rewritten
if (!empty($_SERVER['REQUEST_URI']) && (empty($_SERVER['SCRIPT_NAME']) || strpos($_SERVER['SCRIPT_NAME'], '/web/') !== 0)) {
    $_SERVER['SCRIPT_NAME'] = '/index.php';
}

