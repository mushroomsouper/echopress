<?php
declare(strict_types=1);

require_once __DIR__ . '/../bootstrap/database.php';

function echopress_run_migrations(?string $driver = null): void
{
    $driver = strtolower($driver ?? echopress_database_driver());
    $migrationDir = __DIR__ . '/migrations/' . $driver;
    if (!is_dir($migrationDir)) {
        throw new RuntimeException("No migrations found for driver '{$driver}'.");
    }

    $files = glob($migrationDir . '/*.sql');
    sort($files);
    foreach ($files as $file) {
        $sql = file_get_contents($file);
        if ($sql === false) {
            throw new RuntimeException("Unable to read migration file {$file}");
        }
        $statements = preg_split('/;\s*(?:\r?\n|$)/', $sql);
        foreach ($statements as $statement) {
            $statement = trim($statement);
            if ($statement === '' || str_starts_with(ltrim($statement), '--')) {
                continue;
            }
            try {
                echopress_database()->exec($statement);
            } catch (PDOException $e) {
                throw new RuntimeException('Migration failed in ' . basename($file) . ': ' . $e->getMessage(), 0, $e);
            }
        }
    }
}
