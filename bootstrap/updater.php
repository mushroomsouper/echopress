<?php
declare(strict_types=1);

require_once __DIR__ . '/app.php';

class EchoPressUpdater
{
    private string $channel;
    private string $packagesPath;

    public function __construct()
    {
        $config = echopress_config('updates', []);
        $this->channel = $config['channel'] ?? 'stable';
        $this->packagesPath = $config['packages_path'] ?? echopress_path('updates/packages');
    }

    public function listLocalPackages(): array
    {
        if (!is_dir($this->packagesPath)) {
            return [];
        }
        $files = glob($this->packagesPath . '/*.zip');
        return array_map('basename', $files ?: []);
    }

    public function latestInstalledVersion(): string
    {
        $versionFile = echopress_path('web/version.txt');
        return is_file($versionFile) ? trim((string) file_get_contents($versionFile)) : '0.0.0';
    }

    public function planUpgrade(string $package): array
    {
        // Placeholder for future diffing logic.
        return [
            'package' => $package,
            'channel' => $this->channel,
            'notes' => 'Package analysis not yet implemented.'
        ];
    }
}
