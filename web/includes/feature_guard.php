<?php
require_once __DIR__ . '/app.php';

function echopress_require_feature(string $feature): void
{
    if (!echopress_feature_enabled($feature)) {
        // Render the public 404 page and stop.
        include $_SERVER['DOCUMENT_ROOT'] . '/404.php';
        exit;
    }
}

