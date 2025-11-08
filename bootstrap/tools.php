<?php
declare(strict_types=1);

function echopress_tools_manifest(): array
{
    static $manifest;
    if ($manifest === null) {
        $file = echopress_path('tools/manifest.php');
        $manifest = is_file($file) ? (require $file) : [];
    }
    return $manifest;
}

function echopress_tools_bin_dir(): string
{
    $dir = echopress_storage_path('tools/bin');
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

function echopress_detect_platform(): string
{
    $family = PHP_OS_FAMILY;
    $arch = php_uname('m');
    if ($family === 'Linux') {
        if (in_array($arch, ['x86_64', 'amd64'], true)) {
            return 'linux-x86_64';
        }
        if (in_array($arch, ['aarch64', 'arm64'], true)) {
            return 'linux-arm64';
        }
    }
    return strtolower($family);
}

function echopress_tool_path(string $tool): string
{
    $binDir = echopress_tools_bin_dir();
    $candidate = $binDir . '/' . $tool;
    if (PHP_OS_FAMILY === 'Windows') {
        $candidate .= '.exe';
    }
    if (is_file($candidate)) {
        @chmod($candidate, 0775);
        return $candidate;
    }

    if (function_exists('shell_exec')) {
        $system = trim((string) @shell_exec('command -v ' . escapeshellarg($tool)));
        if ($system !== '') {
            return $system;
        }
    }

    return $tool;
}

function echopress_tools_install_required(array $tools, array &$messages = []): bool
{
    $allOk = true;
    foreach ($tools as $tool) {
        if (is_file(echopress_tools_bin_dir() . '/' . $tool)) {
            $messages[] = "{$tool} already installed.";
            continue;
        }
        try {
            echopress_install_tool($tool);
            $messages[] = "Installed {$tool}.";
        } catch (Throwable $e) {
            $messages[] = "Failed to install {$tool}: " . $e->getMessage();
            $allOk = false;
        }
    }
    return $allOk;
}

function echopress_install_tool(string $tool): void
{
    $manifest = echopress_tools_manifest();
    if (!isset($manifest[$tool])) {
        throw new RuntimeException("Unknown tool '{$tool}'.");
    }
    $platform = echopress_detect_platform();
    if (!isset($manifest[$tool]['platforms'][$platform])) {
        throw new RuntimeException("No build available for platform {$platform}.");
    }
    $info = $manifest[$tool]['platforms'][$platform];
    $url = $info['url'];
    $archiveType = $info['archive'] ?? 'tar.xz';
    $packagesDir = echopress_storage_path('tools/packages');
    if (!is_dir($packagesDir)) {
        @mkdir($packagesDir, 0775, true);
    }
    $tmpArchive = $packagesDir . '/' . basename(parse_url($url, PHP_URL_PATH));
    $context = stream_context_create(['http' => ['timeout' => 30]]);
    $data = @file_get_contents($url, false, $context);
    if ($data === false) {
        throw new RuntimeException('Unable to download ' . $url);
    }
    file_put_contents($tmpArchive, $data);
    $extractDir = $packagesDir . '/' . $tool . '-' . $platform;
    if (is_dir($extractDir)) {
        echopress_rrmdir($extractDir);
    }
    mkdir($extractDir, 0775, true);

    if ($archiveType === 'tar.xz') {
        try {
            $phar = new PharData($tmpArchive);
            $phar->decompress();
            $tarFile = preg_replace('/\.xz$/', '', $tmpArchive);
            $tar = new PharData($tarFile);
            $tar->extractTo($extractDir, null, true);
            @unlink($tarFile);
        } catch (Exception $e) {
            throw new RuntimeException('Unable to extract archive: ' . $e->getMessage());
        }
    } elseif ($archiveType === 'zip') {
        $zip = new ZipArchive();
        if ($zip->open($tmpArchive) !== true) {
            throw new RuntimeException('Unable to open downloaded archive');
        }
        $zip->extractTo($extractDir);
        $zip->close();
    } else {
        throw new RuntimeException('Unsupported archive type: ' . $archiveType);
    }

    $binaryName = $info['binary_name'];
    $binaryPath = echopress_find_binary($extractDir, $binaryName);
    if ($binaryPath === null) {
        throw new RuntimeException('Could not find ' . $binaryName . ' inside archive.');
    }
    $binDir = echopress_tools_bin_dir();
    $target = $binDir . '/' . $binaryName;
    copy($binaryPath, $target);
    chmod($target, 0775);
}

function echopress_find_binary(string $dir, string $name): ?string
{
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && $file->getFilename() === $name) {
            return $file->getPathname();
        }
    }
    return null;
}

function echopress_rrmdir(string $dir): void
{
    if (!is_dir($dir)) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isDir()) {
            rmdir($item->getPathname());
        } else {
            unlink($item->getPathname());
        }
    }
    rmdir($dir);
}
