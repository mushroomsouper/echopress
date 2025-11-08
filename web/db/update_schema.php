<?php
require_once __DIR__ . '/../../bootstrap/database.php';

try {
    $pdo = echopress_database();
} catch (Throwable $e) {
    die('Database connection failed: ' . $e->getMessage());
}

$schemaFile = __DIR__ . '/schema.sql';
$schemaSql = file_get_contents($schemaFile);
$schemaStatements = array_filter(array_map('trim', explode(';', $schemaSql)));
foreach ($schemaStatements as $stmt) {
    if ($stmt === '') {
        continue;
    }
    $pdo->exec($stmt . ';');
}

$stagingFile = __DIR__ . '/staging.sql';
if (!file_exists($stagingFile) || trim(file_get_contents($stagingFile)) === '') {
    echo "No staging changes found.\n";
    exit(0);
}

$stagingSql = file_get_contents($stagingFile);
$statements = array_filter(array_map('trim', explode(';', $stagingSql)));

foreach ($statements as $stmt) {
    if ($stmt === '') {
        continue;
    }
    $pdo->exec($stmt . ';');
    if (preg_match('/ALTER TABLE\s+(\w+)\s+ADD COLUMN\s+(.+)/i', $stmt, $m)) {
        $table = $m[1];
        $columnDef = $m[2];
        updateSchema($schemaFile, $table, $columnDef);
    }
}

file_put_contents($stagingFile, '');
echo "Staging changes applied.\n";

function updateSchema($file, $table, $columnDef)
{
    $lines = file($file);
    $inTable = false;
    for ($i = 0; $i < count($lines); $i++) {
        if (!$inTable && preg_match('/^CREATE TABLE IF NOT EXISTS\s+' . preg_quote($table, '/') . '\s*\(/i', trim($lines[$i]))) {
            $inTable = true;
            continue;
        }
        if ($inTable && preg_match('/\);/', trim($lines[$i]))) {
            $prev = $i - 1;
            if ($prev >= 0 && substr(rtrim($lines[$prev]), -1) !== ',') {
                $lines[$prev] = rtrim($lines[$prev]) . ",\n";
            }
            array_splice($lines, $i, 0, ['    ' . trim($columnDef) . "\n"]);
            break;
        }
    }
    file_put_contents($file, implode('', $lines));
}
