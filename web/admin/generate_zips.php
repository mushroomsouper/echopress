<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
header('Content-Type: application/json');

try {
    if (empty($_SESSION['logged_in'])) {
        throw new Exception('Not logged in', 403);
    }
    $album = $_POST['album'] ?? '';
    if (!$album) {
        throw new Exception('Missing album');
    }
    $albumPath = rtrim($ALBUMS_DIR, '/') . "/$album";
    $manifestFile = "$albumPath/manifest.json";
    if (!file_exists($manifestFile)) {
        throw new Exception('Manifest not found');
    }

    $manifest = json_decode(file_get_contents($manifestFile), true);
    if (!is_array($manifest) || empty($manifest['tracks'])) {
        throw new Exception('Manifest malformed or no tracks');
    }

    $slug = preg_replace('/[^A-Za-z0-9]+/', '_', basename($album));
    $mp3Dir = "$albumPath/download/mp3";
    $wavDir = "$albumPath/download/wav";
    $flacDir = "$albumPath/download/flac";

    // make sure the dirs exist
    if (!is_dir($mp3Dir))
        mkdir($mp3Dir, 0777, true);
    if (!is_dir($wavDir))
        mkdir($wavDir, 0777, true);
    if (!is_dir($flacDir))
        mkdir($flacDir, 0777, true);

    $mp3Zip = "$mp3Dir/{$slug}_MP3.zip";
    $wavZip = "$wavDir/{$slug}_WAV.zip";
    $flacZip = "$flacDir/{$slug}_FLAC.zip";

    // helper
    $clean = fn($t) => trim(preg_replace('/[^A-Za-z0-9]+/', '_', $t), '_');

    // 1) MP3
    $mp3Count = 0;
    $zip = new ZipArchive();
    if ($zip->open($mp3Zip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new Exception("Cannot open MP3 zip at $mp3Zip");
    }
    foreach ($manifest['tracks'] as $i => $t) {
        $file = sprintf("$mp3Dir/track%02d.mp3", $i + 1);
        if (is_file($file)) {
            $mp3Count++;
            $zipName = sprintf('%02d-%s.mp3', $i + 1, $clean($t['title'] ?? 'track'));
            $zip->addFile($file, $zipName);
        }
    }
    $zip->close();

    // 2) WAV only if all tracks have them
    $allWav = true;
    foreach ($manifest['tracks'] as $i => $_) {
        if (!is_file(sprintf("$wavDir/track%02d.wav", $i + 1))) {
            $allWav = false;
            break;
        }
    }
    $wavCount = 0;
    if ($allWav) {
        $zip = new ZipArchive();
        if ($zip->open($wavZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Cannot open WAV zip at $wavZip");
        }
        foreach ($manifest['tracks'] as $i => $t) {
            $file = sprintf("$wavDir/track%02d.wav", $i + 1);
            if (is_file($file)) {
                $wavCount++;
                $zipName = sprintf('%02d-%s.wav', $i + 1, $clean($t['title'] ?? 'track'));
                $zip->addFile($file, $zipName);
            }
        }
        // Add cover/back art if present
        $cover = "$albumPath/assets/cover.jpg";
        $back = "$albumPath/assets/back.jpg";
        if (is_file($cover)) {
            $zip->addFile($cover, 'cover.jpg');
        }
        if (is_file($back)) {
            $zip->addFile($back, 'back.jpg');
        }

        // Build info.txt
        $totalTracks = count($manifest['tracks']);
        $totalSeconds = 0;
        foreach ($manifest['tracks'] as $t) {
            if (!empty($t['length']) && preg_match('/(\d+):(\d+)/', $t['length'], $m)) {
                $totalSeconds += $m[1] * 60 + $m[2];
            }
        }
        $totalRuntime = sprintf('%d:%02d', floor($totalSeconds / 60), $totalSeconds % 60);
        $year = '';
        if (!empty($manifest['releaseDate']) && ($ts = strtotime($manifest['releaseDate']))) {
            $year = date('Y', $ts);
        }
        $albumFiglet = shell_exec("figlet -f big " . escapeshellarg($manifest['albumTitle']));
        $artistFiglet = shell_exec("figlet -f small " . escapeshellarg($manifest['artist']));

        $info = rtrim($albumFiglet) . "\n" . rtrim($artistFiglet) . "\n\n";

        $info .= "Album: {$manifest['albumTitle']}\n" .
            "Artist: {$manifest['artist']}\n";
        ($year ? "Year: {$year}\n" : '') .
            "Total Tracks: {$totalTracks}\n" .
            "Total Runtime: {$totalRuntime}\n\n" .
            "Tracklist:\n";
        foreach ($manifest['tracks'] as $i => $t) {
            $len = $t['length'] ?? '';
            $title = $t['title'] ?? ('Track ' . ($i + 1));
            $info .= sprintf("%02d. %s — %s\n", $i + 1, $title, $len);
        }
        $info .= "\nThese are uncompressed WAV files (24-bit / 44.1kHz).\nNo DRM. You are free to burn, share, or archive.";
        $zip->addFromString('info.txt', $info);
        $metaFile = "$albumPath/metadata.txt";
        if (is_file($metaFile)) {
            $zip->addFile($metaFile, 'metadata.txt');
        }

        $cue = "$albumPath/{$slug}.cue";
        if (is_file($cue)) {
            $zip->addFile($cue, basename($cue));
        }
        $log = "$albumPath/{$slug}.log";
        if (is_file($log)) {
            $zip->addFile($log, basename($log));
        }
        $checksum = "$albumPath/checksum.md5";
        if (is_file($checksum)) {
            $zip->addFile($checksum, 'checksum.md5');
        }
        $ffp = "$albumPath/ffp.txt";
        if (is_file($ffp)) {
            $zip->addFile($ffp, 'ffp.txt');
        }
        $zip->close();
    }

    // 3) FLAC only if all tracks have them
    $allFlac = true;
    foreach ($manifest['tracks'] as $i => $_) {
        if (!is_file(sprintf("$flacDir/track%02d.flac", $i + 1))) {
            $allFlac = false;
            break;
        }
    }
    $flacCount = 0;
    if ($allFlac) {
        $zip = new ZipArchive();
        if ($zip->open($flacZip, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new Exception("Cannot open FLAC zip at $flacZip");
        }
        foreach ($manifest['tracks'] as $i => $t) {
            $file = sprintf("$flacDir/track%02d.flac", $i + 1);
            if (is_file($file)) {
                $flacCount++;
                $zipName = sprintf('%02d-%s.flac', $i + 1, $clean($t['title'] ?? 'track'));
                $zip->addFile($file, $zipName);
            }
        }

        // Add cover/back art if present
        $cover = "$albumPath/assets/cover.jpg";
        $back  = "$albumPath/assets/back.jpg";
        if (is_file($cover)) {
            $zip->addFile($cover, 'cover.jpg');
        }
        if (is_file($back)) {
            $zip->addFile($back, 'back.jpg');
        }

        // Build info.txt
        $totalTracks = count($manifest['tracks']);
        $totalSeconds = 0;
        foreach ($manifest['tracks'] as $t) {
            if (!empty($t['length']) && preg_match('/(\d+):(\d+)/', $t['length'], $m)) {
                $totalSeconds += $m[1] * 60 + $m[2];
            }
        }
        $totalRuntime = sprintf('%d:%02d', floor($totalSeconds/60), $totalSeconds%60);
        $year = '';
        if (!empty($manifest['releaseDate']) && ($ts=strtotime($manifest['releaseDate']))) {
            $year = date('Y', $ts);
        }
        $info = "Album: {$manifest['albumTitle']}\n" .
                "Artist: {$manifest['artist']}\n" .
                ($year ? "Year: {$year}\n" : '') .
                "Total Tracks: {$totalTracks}\n" .
                "Total Runtime: {$totalRuntime}\n\n" .
                "Tracklist:\n";
        foreach ($manifest['tracks'] as $i => $t) {
            $len = $t['length'] ?? '';
            $title = $t['title'] ?? ('Track '.($i+1));
            $info .= sprintf("%02d. %s — %s\n", $i+1, $title, $len);
        }
        $info .= "\nThese are lossless FLAC files (level 8).\nNo DRM. You are free to burn, share, or archive.";
        $zip->addFromString('info.txt', $info);
        $metaFile = "$albumPath/metadata.txt";
        if (is_file($metaFile)) {
            $zip->addFile($metaFile, 'metadata.txt');
        }

        $cue = "$albumPath/{$slug}.cue";
        if (is_file($cue)) {
            $zip->addFile($cue, basename($cue));
        }
        $log = "$albumPath/{$slug}.log";
        if (is_file($log)) {
            $zip->addFile($log, basename($log));
        }
        $checksum = "$albumPath/checksum.md5";
        if (is_file($checksum)) {
            $zip->addFile($checksum, 'checksum.md5');
        }
        $ffp = "$albumPath/ffp.txt";
        if (is_file($ffp)) {
            $zip->addFile($ffp, 'ffp.txt');
        }

        $zip->close();
    }

    echo json_encode([
        'success' => true,
        'mp3Zip' => realpath($mp3Zip),
        'mp3Count' => $mp3Count,
        'wavZip' => $allWav ? realpath($wavZip) : null,
        'wavCount' => $wavCount,
        'allWav' => $allWav,
        'flacZip' => $allFlac ? realpath($flacZip) : null,
        'flacCount' => $flacCount,
        'allFlac' => $allFlac
    ]);
} catch (Exception $e) {
    http_response_code($e->getCode() ?: 500);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
