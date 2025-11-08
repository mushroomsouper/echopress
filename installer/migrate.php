<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/app.php';
require_once __DIR__ . '/migrate_lib.php';

$options = getopt('', ['driver::']);
$driver = $options['driver'] ?? null;

try {
    echopress_run_migrations($driver);
    fwrite(STDOUT, "Migrations complete for " . strtolower($driver ?? echopress_database_driver()) . "\n");
} catch (Throwable $e) {
    fwrite(STDERR, $e->getMessage() . "\n");
    exit(1);
}
