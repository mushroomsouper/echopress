<?php
require_once __DIR__ . '/app.php';

$theme = echopress_theme_active();
if ($theme !== '') {
    $baseJson = $_SERVER['DOCUMENT_ROOT'] . '/themes/' . $theme . '/theme.json';
    $overrideJson = echopress_storage_path('themes/' . $theme . '/variables.json');
    $vars = [];
    if (is_file($baseJson)) {
        $data = json_decode(file_get_contents($baseJson), true) ?: [];
        if (!empty($data['variables']) && is_array($data['variables'])) {
            $vars = $data['variables'];
        }
    }
    if (is_file($overrideJson)) {
        $o = json_decode(file_get_contents($overrideJson), true) ?: [];
        if (is_array($o)) {
            foreach ($o as $k => $v) {
                $vars[$k] = $v; // site override wins
            }
        }
    }
    if ($vars) {
        echo "<style>:root{";
        foreach ($vars as $k => $v) {
            $k = preg_replace('/[^a-z0-9\-]/i','',$k);
            if ($k === '' || strpos($k, '--') !== 0) continue; // must be CSS variable
            $v = htmlspecialchars((string)$v, ENT_QUOTES);
            echo "$k:$v;";
        }
        echo "}</style>";
    }
}
