<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

$embed = trim(echopress_analytics_embed());
if ($embed === '') {
    return;
}

echo "\n<!-- Analytics Embed -->\n" . $embed . "\n";
