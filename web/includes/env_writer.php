<?php
// Lightweight .env writer for EchoPress
function echopress_env_path(): string {
    return dirname(__DIR__, 2) . '/.env';
}

function echopress_env_read(): array {
    $path = echopress_env_path();
    if (!is_file($path)) return [];
    $lines = @file($path, FILE_IGNORE_NEW_LINES);
    if ($lines === false) return [];
    $data = [];
    foreach ($lines as $line) {
        if ($line === '' || $line[0] === '#') continue;
        $eq = strpos($line, '=');
        if ($eq === false) continue;
        $k = trim(substr($line, 0, $eq));
        $v = substr($line, $eq + 1);
        $data[$k] = $v;
    }
    return $data;
}

function echopress_env_write(array $pairs): bool {
    $path = echopress_env_path();
    $cur = echopress_env_read();
    foreach ($pairs as $k => $v) {
        $cur[$k] = (string) $v;
    }
    ksort($cur);
    $out = "";
    foreach ($cur as $k => $v) {
        $out .= $k . '=' . $v . "\n";
    }
    return file_put_contents($path, $out) !== false;
}

