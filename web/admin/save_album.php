<?php
ini_set('display_errors', '1');
ini_set('display_startup_errors', '1');
error_reporting(E_ALL);
// Long running ffmpeg operations can exceed default time limits.
// Allow the script to run to completion so the web server doesn't
// return a 502 error during album saves.
set_time_limit(0);
ignore_user_abort(true);

require_once __DIR__ . '/../includes/app.php';
$defaultArtistName = echopress_artist_name();

define('PROGRESS_DIR', __DIR__ . '/progress');

function progress_sanitize_job(string $job): string
{
    $job = preg_replace('/[^a-zA-Z0-9_-]/', '', $job);
    if (strlen($job) > 80) {
        $job = substr($job, 0, 80);
    }
    return $job;
}

function progress_path(string $job): string
{
    return PROGRESS_DIR . '/' . $job . '.json';
}

function progress_ensure_dir(): void
{
    if (!is_dir(PROGRESS_DIR)) {
        mkdir(PROGRESS_DIR, 0777, true);
    }
}

function progress_cleanup_old(int $ttl = 86400): void
{
    if (!is_dir(PROGRESS_DIR)) {
        return;
    }
    foreach (glob(PROGRESS_DIR . '/*.json') as $file) {
        if (filemtime($file) < time() - $ttl) {
            @unlink($file);
        }
    }
}

function progress_init(string $job, string $headline = 'Processing album…', string $statusText = 'Starting…'): void
{
    if ($job === '') {
        return;
    }
    progress_ensure_dir();
    progress_cleanup_old();
    $data = [
        'job' => $job,
        'headline' => $headline,
        'status' => 'processing',
        'statusText' => $statusText,
        'steps' => [],
        'updated' => time()
    ];
    file_put_contents(progress_path($job), json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

function progress_note(string $job, string $message, array $options = []): void
{
    if ($job === '') {
        return;
    }
    $path = progress_path($job);
    $data = [];
    if (is_file($path)) {
        $data = json_decode(file_get_contents($path), true);
    }
    if (!is_array($data)) {
        $data = [
            'job' => $job,
            'headline' => 'Processing album…',
            'status' => 'processing',
            'statusText' => '',
            'steps' => [],
            'updated' => time()
        ];
    }
    if (isset($options['headline'])) {
        $data['headline'] = $options['headline'];
    }
    if (!empty($options['statusText'])) {
        $data['statusText'] = $options['statusText'];
    }
    if (isset($options['status'])) {
        $data['status'] = $options['status'];
    }
    if (isset($options['percent'])) {
        $data['percent'] = $options['percent'];
    }
    $data['steps'][] = [
        'time' => time(),
        'message' => $message
    ];
    $data['updated'] = time();
    file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT), LOCK_EX);
}

function progress_finish(string $job, string $message, string $status = 'completed'): void
{
    if ($job === '') {
        return;
    }
    $headline = $status === 'completed' ? 'Album saved' : 'Album save failed';
    progress_note($job, $message, [
        'headline' => $headline,
        'statusText' => $message,
        'status' => $status
    ]);
}

function progress_safe_label(string $value): string
{
    $value = str_replace(["\r", "\n"], ' ', $value);
    $value = trim(preg_replace('/\s+/', ' ', $value));
    if ($value === '') {
        return 'Untitled';
    }
    if (strlen($value) > 120) {
        $value = substr($value, 0, 117) . '...';
    }
    return $value;
}

require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/srcset.php';
require_once __DIR__ . '/sitemap.php';
$ffmpegExecutable = echopress_tool_path('ffmpeg');
$ffmpegCmd = escapeshellcmd($ffmpegExecutable);
// Allow large file uploads
ini_set('upload_max_filesize', '200M');
ini_set('post_max_size', '200M');

$rawJobId = $_POST['job_id'] ?? '';
$progressJobId = progress_sanitize_job($rawJobId);
if ($progressJobId === '' && $rawJobId !== '') {
    if (function_exists('random_bytes')) {
        try {
            $progressJobId = 'album_' . bin2hex(random_bytes(4));
        } catch (Exception $e) {
            $progressJobId = 'album_' . uniqid();
        }
    } else {
        $progressJobId = 'album_' . uniqid();
    }
}
$ajaxRequest = (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest') || $progressJobId !== '';

if (empty($_SESSION['logged_in'])) {
    if ($ajaxRequest) {
        header('Content-Type: application/json');
        http_response_code(401);
        echo json_encode(['status' => 'error', 'message' => 'Authentication required', 'job' => $progressJobId]);
    } else {
        header('Location: login.php');
    }
    exit;
}

// Release the session lock before running the long processing steps so that
// album_progress.php can poll for updates while this script is still working.
session_write_close();

if ($progressJobId) {
    progress_init($progressJobId, 'Processing album…', 'Validating album information…');
    progress_note($progressJobId, 'Validating album information…', ['statusText' => 'Validating album information…']);
}

$albumTitle = $_POST['albumTitle'] ?? '';
$volume = preg_replace('/[^0-9]/', '', $_POST['volume'] ?? '');
$original = $_POST['original_name'] ?? '';
$existingCover = $_POST['existing_cover'] ?? '';
$existingBack = $_POST['existing_back'] ?? '';
$existingBackground = $_POST['existing_background'] ?? '';
$existingBackgroundImage = $_POST['existing_background_image'] ?? '';
$existingFont = $_POST['existing_font'] ?? '';
$folderSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '_', $albumTitle));
$newSlug = $folderSlug ?: ($original ?: ('album_' . time()));

// When editing an existing album and the slug changed, rename directories
$folder = $original ?: $newSlug;
$albumPath = "$ALBUMS_DIR/$folder";
if ($original) {
    if ($newSlug && $newSlug !== $original) {
        $oldAlbumPath = "$ALBUMS_DIR/$original";
        $newAlbumPath = "$ALBUMS_DIR/$newSlug";
        if (is_dir($oldAlbumPath)) {
            rename($oldAlbumPath, $newAlbumPath);
        }
        $oldDiscog = __DIR__ . '/../discography/albums/' . $original;
        $newDiscog = __DIR__ . '/../discography/albums/' . $newSlug;
        if (is_dir($oldDiscog)) {
            rename($oldDiscog, $newDiscog);
        }
        $oldCss = __DIR__ . '/../discography/albums/' . $original . '/css/style.css';
        $newCss = __DIR__ . '/../discography/albums/' . $newSlug . '/css/style.css';
        if (file_exists($oldCss)) {
            rename($oldCss, $newCss);
        }
        $folder = $newSlug;
        $albumPath = $newAlbumPath;
    }
} else {
    $albumPath = "$ALBUMS_DIR/$newSlug";
    $folder = $newSlug;
    if (!is_dir($albumPath)) {
        mkdir($albumPath, 0777, true);
    }
}

// ensure we have dedicated download dirs
$downloadMp3 = "$albumPath/download/mp3";
$downloadWav = "$albumPath/download/wav";
$downloadFlac = "$albumPath/download/flac";
if (!is_dir($downloadMp3))
    mkdir($downloadMp3, 0777, true);
if (!is_dir($downloadWav))
    mkdir($downloadWav, 0777, true);
if (!is_dir($downloadFlac))
    mkdir($downloadFlac, 0777, true);


$streamDir = "$albumPath/tracks";
if (!is_dir($streamDir))
    mkdir($streamDir, 0777, true);


$assetDir = "$albumPath/assets";
if (!is_dir($assetDir)) {
    mkdir($assetDir, 0777, true);
}
$additionalDir = "$assetDir/additional";
if (!is_dir($additionalDir)) {
    mkdir($additionalDir, 0777, true);
}

if ($progressJobId) {
    progress_note($progressJobId, 'Prepared album directories and download folders', ['statusText' => 'Preparing album directories…']);
}

// Load existing manifest when editing
$manifestPath = "$albumPath/manifest.json";
$manifest = [];
if ($original && file_exists($manifestPath)) {
    $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
}
$oldManifest = $manifest;

function check_mime(string $tmp, array $allowed): bool
{
    $finfo = new finfo(FILEINFO_MIME_TYPE);
    $mime = $finfo->file($tmp);
    foreach ($allowed as $prefix) {
        if (strpos($mime, $prefix) === 0) {
            return true;
        }
    }
    return false;
}

$errors = [];

if ($volume === '') {
    $errors[] = 'Volume number is required';
}


$manifestDefaults = [
    'albumTitle' => '',
    'artist' => $defaultArtistName,
    'type' => 'album',
    'volume' => null,
    'cover' => '',
    'coverSrcset' => '', // legacy
    'coverSrcsetWebp' => '',
    'coverSrcsetJpg' => '',
    'coverBlur' => '',
    'back' => '',
    'backSrcset' => '',  // legacy
    'backSrcsetWebp' => '',
    'backSrcsetJpg' => '',
    'background' => '',
    'backgroundImage' => '',
    'backgroundColor' => '#000000',
    'font' => '',
    'themeColor' => '#333333',
    'textColor' => '#ffffff',
    'tracks' => [],
    'genre' => '',
    'languages' => [],
    'live' => true,
    'comingSoon' => false,
    'releaseDate' => ''
];
$manifest = array_merge($manifestDefaults, $manifest);
unset($manifest['year']); // remove legacy field
$manifest['albumTitle'] = $albumTitle;
$manifest['type'] = $_POST['type'] ?? 'album';
$manifest['volume'] = $volume !== '' ? (int) $volume : null;
$manifest['releaseDate'] = $_POST['releaseDate'] ?? '';
$manifest['live'] = isset($_POST['live']);
$manifest['comingSoon'] = isset($_POST['comingSoon']);
$manifest['themeColor'] = $_POST['themeColor'] ?? '#333333';
$manifest['textColor'] = $_POST['textColor'] ?? '#ffffff';
$manifest['backgroundColor'] = $_POST['backgroundColor'] ?? '#000000';
$manifest['genre'] = $_POST['genre'] ?? '';
$manifest['languages'] = isset($_POST['languages']) ? (array) $_POST['languages'] : [];
// Custom font scale handling
$fontScaleInput = isset($_POST['font_scale']) ? (float) $_POST['font_scale'] : 1.0;
if (!is_finite($fontScaleInput)) {
    $fontScaleInput = 1.0;
}
$fontScaleInput = max(0.25, min(4.0, $fontScaleInput));
$manifest['fontScale'] = $fontScaleInput;

$albumArtworkChanged = false;

// Determine if album-level metadata that affects ID3 tags changed
$albumMetaChanged = false;
foreach (['albumTitle', 'artist', 'releaseDate'] as $fld) {
    $oldVal = $oldManifest[$fld] ?? '';
    $newVal = $manifest[$fld] ?? '';
    if ($oldVal !== $newVal) {
        $albumMetaChanged = true;
        break;
    }
}

foreach (['cover', 'back'] as $img) {
    if (!empty($_FILES[$img]['tmp_name'])) {
        // Remove any existing generated images for this slot
        foreach (glob("$assetDir/{$img}*.{jpg,jpeg,png,gif,webp}", GLOB_BRACE) as $old) {

            if (is_file($old))
                unlink($old);
        }
        if (!check_mime($_FILES[$img]['tmp_name'], ['image/'])) {
            $errors[] = ucfirst($img) . ' must be an image file';
            continue;
        }
        list($fallback, $webpSet, $jpgSet) = create_image_set(
            $_FILES[$img]['tmp_name'],
            $assetDir,
            $img,
            $_FILES[$img]['name'] ?? '',
            'assets/'
        );
        if ($img === 'cover') {
            $manifest['cover'] = $fallback;
            $manifest['coverSrcsetWebp'] = $webpSet;
            $manifest['coverSrcsetJpg'] = $jpgSet;
            // maintain legacy key
            $manifest['coverSrcset'] = $jpgSet;
            $ext = pathinfo($fallback, PATHINFO_EXTENSION);
            $blurName = 'cover_blur.' . $ext;
            create_blur_image("$assetDir/" . basename($fallback), "$assetDir/$blurName");
            $manifest['coverBlur'] = 'assets/' . $blurName;
            $albumArtworkChanged = true;
        } else {
            $manifest['back'] = $fallback;
            $manifest['backSrcsetWebp'] = $webpSet;
            $manifest['backSrcsetJpg'] = $jpgSet;
            $manifest['backSrcset'] = $jpgSet;
        }
        progress_note($progressJobId, 'Updated ' . $img . ' artwork', ['statusText' => ucfirst($img) . ' artwork updated']);
    }
}

if ($albumArtworkChanged) {
    $albumMetaChanged = true;
}

if (!empty($_FILES['background']['tmp_name'])) {
    if (!check_mime($_FILES['background']['tmp_name'], ['video/'])) {
        $errors[] = 'Background must be a video file';
    } else {
        foreach (glob("$assetDir/background.*") as $old) {
            if (is_file($old))
                unlink($old);
        }
        $bgName = 'background.' . pathinfo($_FILES['background']['name'], PATHINFO_EXTENSION);
        move_uploaded_file($_FILES['background']['tmp_name'], "$assetDir/$bgName");
        $manifest['background'] = 'assets/' . $bgName;
        progress_note($progressJobId, 'Updated background video', ['statusText' => 'Background video updated']);
    }
}

if (!empty($_FILES['background_image']['tmp_name'])) {
    if (!check_mime($_FILES['background_image']['tmp_name'], ['image/'])) {
        $errors[] = 'Background image must be an image file';
    } else {
        foreach (glob("$assetDir/background_image.*") as $old) {
            if (is_file($old))
                unlink($old);
        }
        $bgImgName = 'background_image.' . pathinfo($_FILES['background_image']['name'], PATHINFO_EXTENSION);
        move_uploaded_file($_FILES['background_image']['tmp_name'], "$assetDir/$bgImgName");
        $manifest['backgroundImage'] = 'assets/' . $bgImgName;
        progress_note($progressJobId, 'Updated background image', ['statusText' => 'Background image updated']);
    }
} elseif ($existingBackgroundImage) {
    $manifest['backgroundImage'] = $existingBackgroundImage;
}

// Font upload goes into the album assets directory
if (!empty($_FILES['font']['tmp_name'])) {
    if (!check_mime($_FILES['font']['tmp_name'], ['font/', 'application/font', 'application/x-font', 'application/octet-stream'])) {
        $errors[] = 'Invalid font file type';
    } else {
        if ($existingFont) {
            $oldFont = "$assetDir/" . basename($existingFont);
            if (is_file($oldFont))
                unlink($oldFont);
        }
        $fontName = preg_replace('/[^a-zA-Z0-9_.-]/', '_', basename($_FILES['font']['name']));
        $fontDest = "$assetDir/$fontName";
        move_uploaded_file($_FILES['font']['tmp_name'], $fontDest);
        $manifest['font'] = $fontName;
        progress_note($progressJobId, 'Updated album font asset', ['statusText' => 'Font asset updated']);
    }
}

// Rebuild track list preserving existing files when no new upload is provided
$manifest['tracks'] = [];
if (!empty($_POST['tracks'])) {
    $oldTracks = $oldManifest['tracks'] ?? [];
    $totalTrackCount = count($_POST['tracks']);
    $trackCountChanged = $totalTrackCount !== count($oldTracks);
    foreach ($_POST['tracks'] as $i => $track) {
        $trackNum = $i + 1;
        $title = $track['title'] ?? 'Track ' . $trackNum;
        $length = $track['length'] ?? '';
        $artist = $track['artist'] ?? '';
        $year = $track['year'] ?? '';
        $genre = $track['genre'] ?? '';
        $composer = $track['composer'] ?? '';
        $lyricist = $track['lyricist'] ?? '';
        $explicit = isset($track['explicit']) ? (bool) $track['explicit'] : false;
        $comment = $track['comment'] ?? '';
        $lyrics = $track['lyrics'] ?? '';
        $trackLabel = progress_safe_label($title);
        progress_note(
            $progressJobId,
            sprintf('Processing track %02d of %02d: %s', $trackNum, max(1, $totalTrackCount), $trackLabel),
            ['statusText' => sprintf('Processing track %02d of %02d…', $trackNum, max(1, $totalTrackCount))]
        );

        // build base name
        $base = 'track' . str_pad($trackNum, 2, '0', STR_PAD_LEFT);

        $uploadedName = $_FILES['tracks']['name'][$i]['file'] ?? '';
        $uploadedTmp = $_FILES['tracks']['tmp_name'][$i]['file'] ?? '';
        $existingFile = $track['existing_file'] ?? ($oldTracks[$i]['file'] ?? '');
        $wavPath = "$downloadWav/$base.wav";
        $mp3Path = "$downloadMp3/$base.mp3";
        $flacPath = "$downloadFlac/$base.flac";
        $sourceFile = '';
        $fname = $existingFile;
        $fileUploaded = false;

        if ($uploadedName) {
            $fileUploaded = true;
            $ext = strtolower(pathinfo($uploadedName, PATHINFO_EXTENSION));
            if ($ext === 'wav') {
                $wavFile = "$base.wav";
                move_uploaded_file($uploadedTmp, "$downloadWav/$wavFile");
                $sourceFile = "$downloadWav/$wavFile";
            } else {
                $mp3File = "$base.mp3";
                move_uploaded_file($uploadedTmp, "$downloadMp3/$mp3File");
                $sourceFile = "$downloadMp3/$mp3File";
            }
        } elseif ($existingFile) {
            $sourceFile = "$albumPath/" . ltrim($existingFile, '/');
        } else {
            if (is_file($wavPath)) {
                $sourceFile = $wavPath;
                $fname = "download/mp3/$base.mp3";
            } elseif (is_file($mp3Path)) {
                $sourceFile = $mp3Path;
                $fname = "download/mp3/$base.mp3";
            }
        }

        $old = $oldTracks[$i] ?? [];
        $metaChanged = false;
        foreach (['title', 'artist', 'genre', 'composer', 'lyricist'] as $f) {
            $newV = (string) ($$f ?? '');
            $oldV = isset($old[$f]) ? (string) $old[$f] : '';
            if ($newV !== $oldV) {
                $metaChanged = true;
                break;
            }
        }

        $existingNumber = null;
        if ($existingFile && preg_match('/track(\d+)\.(mp3|wav)$/i', basename($existingFile), $m)) {
            $existingNumber = (int) $m[1];
        }
        $renumbered = $existingNumber && $existingNumber !== $trackNum;

        $needRegen = $fileUploaded || $metaChanged || $albumMetaChanged || !$sourceFile || $renumbered || $trackCountChanged;

        $coverPath = "$albumPath/assets/cover.jpg";
        $coverInput = $sourceFile && is_file($coverPath) ? " -i " . escapeshellarg($coverPath) : "";
        $trackArtist = $artist ?: $manifest['artist'];
        $metaList = [
            "album_artist={$manifest['artist']}",
            "title={$title}",
            "artist={$trackArtist}",
            "album={$manifest['albumTitle']}",
            "date={$manifest['releaseDate']}",
            "track={$trackNum}/{$totalTrackCount}",
            "genre={$genre}",
            "composer={$composer}",
            "TXXX:Lyricist={$lyricist}"  // custom lyricist frame
        ];
        $metaFlags = '';
        foreach ($metaList as $m) {
            $metaFlags .= ' -metadata ' . escapeshellarg($m);
        }

        if ($needRegen && $sourceFile) {
            $originalSource = $sourceFile;
            progress_note(
                $progressJobId,
                sprintf('Encoding download MP3 for track %02d (%s)', $trackNum, $trackLabel),
                ['statusText' => sprintf('Encoding download MP3 for track %02d…', $trackNum)]
            );
            if (preg_match('/\.wav$/i', $sourceFile)) {
                $outMp3 = "$base.mp3";
                $fullDest = "$downloadMp3/$outMp3";

                // Build ffmpeg command for full-quality MP3 with tags
                $cmd = $ffmpegCmd . " -y -i " . escapeshellarg($sourceFile)
                    . $coverInput
                    . " -map 0:a"
                    . ($coverInput
                        ? " -map 1:v -c:v copy -disposition:v:0 attached_pic"
                        : "")
                    . " -codec:a libmp3lame -b:a 320k"
                    . " -id3v2_version 4"
                    . " -write_id3v1 0"
                    . $metaFlags
                    . " " . escapeshellarg($fullDest);
                exec($cmd);

                $fname = "download/mp3/$outMp3";

            } else {
                $fullDest = "$downloadMp3/{$base}.mp3";

                // For non-WAV sources, copy streams but still overwrite tags

                $cmd = $ffmpegCmd . " -y -i " . escapeshellarg($sourceFile)
                    . $coverInput
                    . " -map 0:a"
                    . ($coverInput
                        ? " -map 1:v -c:v copy -disposition:v:0 attached_pic"
                        : "")
                    . " -codec copy"
                    . " -id3v2_version 4"
                    . " -write_id3v1 0"

                    . $metaFlags
                    . " " . escapeshellarg($fullDest);
                exec($cmd);
                $fname = "download/mp3/{$base}.mp3";
            }

            progress_note(
                $progressJobId,
                sprintf('Encoding streaming MP3 for track %02d (%s)', $trackNum, $trackLabel),
                ['statusText' => sprintf('Encoding streaming MP3 for track %02d…', $trackNum)]
            );
            // —— now generate the streaming-quality MP3 ——

            // Preserve all metadata (remove the “-map_metadata -1” that was stripping everything)
            $streamDest = "$streamDir/{$base}.mp3";
            $streamCmd = $ffmpegCmd . " -y -i " . escapeshellarg($fullDest)
                . " -codec:a libmp3lame -b:a 128k"
                . " -map_metadata 0"
                . " -write_id3v1 0"
                . " " . escapeshellarg($streamDest);
            exec($streamCmd);

            if ($renumbered && !$fileUploaded && is_file($originalSource)) {
                unlink($originalSource);

            }
        } elseif ($sourceFile && !$needRegen) {
            progress_note(
                $progressJobId,
                sprintf('Track %02d unchanged (%s)', $trackNum, $trackLabel),
                ['statusText' => sprintf('Track %02d unchanged', $trackNum)]
            );
        } elseif (!$sourceFile) {
            progress_note(
                $progressJobId,
                sprintf('Skipping track %02d (%s): no audio source', $trackNum, $trackLabel),
                ['statusText' => sprintf('Skipping track %02d – no audio', $trackNum)]
            );
        }

        if ($sourceFile && ($needRegen || !is_file($flacPath))) {
            // ---- generate FLAC copy if missing or regenerating ----
            // treat FLAC like WAV: no embedded artwork or ID3 metadata
            progress_note(
                $progressJobId,
                sprintf('Generating FLAC copy for track %02d (%s)', $trackNum, $trackLabel),
                ['statusText' => sprintf('Generating FLAC for track %02d…', $trackNum)]
            );
            $flacCmd = $ffmpegCmd . " -y -i " . escapeshellarg(is_file($wavPath) ? $wavPath : $sourceFile)
                . " -map 0:a -map_metadata -1"
                . " -codec:a flac -compression_level 8"
                . " " . escapeshellarg($flacPath);
            exec($flacCmd);
        }

        $manifest['tracks'][] = [
            'number' => $trackNum,
            'title' => $title,
            'file' => $fname,
            'length' => $length,
            'artist' => $artist,
            'year' => $year,
            'genre' => $genre,
            'composer' => $composer,
            'comment' => $comment,
            'lyricist' => $lyricist,
            'explicit' => $explicit ? 1 : 0,
            'lyrics' => $lyrics
        ];
    }
    progress_note($progressJobId, 'Finished processing track audio', ['statusText' => 'Track processing complete']);
}

if ($errors) {
    $errorMessage = 'Validation failed: ' . implode('; ', $errors);
    progress_finish($progressJobId, $errorMessage, 'failed');
    if ($ajaxRequest) {
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode([
            'status' => 'error',
            'message' => 'Validation failed',
            'errors' => $errors,
            'job' => $progressJobId
        ]);
    } else {
        echo '<h1>Upload Error</h1>';
        foreach ($errors as $e) {
            echo '<p>' . htmlspecialchars($e) . '</p>';
        }
        echo '<p><a href="javascript:history.back()">Go back</a></p>';
    }
    exit;
}

file_put_contents("$albumPath/manifest.json", json_encode($manifest, JSON_PRETTY_PRINT));
progress_note($progressJobId, 'Updated album manifest.json', ['statusText' => 'Writing manifest…']);

// Insert or update album in database
progress_note($progressJobId, 'Updating album metadata in database', ['statusText' => 'Updating database…']);
$stmt = $pdo->prepare('SELECT id FROM albums WHERE slug=?');
$stmt->execute([$folder]);
$albumId = $stmt->fetchColumn();
if ($albumId) {
    $stmt = $pdo->prepare('UPDATE albums SET albumTitle=?, volume=?, releaseDate=?, live=?, comingSoon=?, themeColor=?, textColor=?, backgroundColor=?, background=?, backgroundImage=?, cover=?, back=?, font=?, genre=?, languages=?, type=? WHERE id=?');
    $stmt->execute([
        $manifest['albumTitle'],
        $manifest['volume'],
        $manifest['releaseDate'],
        $manifest['live'] ? 1 : 0,
        $manifest['comingSoon'] ? 1 : 0,
        $manifest['themeColor'],
        $manifest['textColor'],
        $manifest['backgroundColor'],
        $manifest['background'],
        $manifest['backgroundImage'],
        $manifest['cover'],
        $manifest['back'],
        $manifest['font'],
        $manifest['genre'],
        implode(',', $manifest['languages']),
        $manifest['type'],
        $albumId
    ]);
    $pdo->prepare('DELETE FROM album_tracks WHERE album_id=?')->execute([$albumId]);
} else {
    $stmt = $pdo->prepare('INSERT INTO albums (slug, albumTitle, volume, releaseDate, live, comingSoon, themeColor, textColor, backgroundColor, background, backgroundImage, cover, back, font, genre, languages, type) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
    $stmt->execute([
        $folder,
        $manifest['albumTitle'],
        $manifest['volume'],
        $manifest['releaseDate'],
        $manifest['live'] ? 1 : 0,
        $manifest['comingSoon'] ? 1 : 0,
        $manifest['themeColor'],
        $manifest['textColor'],
        $manifest['backgroundColor'],
        $manifest['background'],
        $manifest['backgroundImage'],
        $manifest['cover'],
        $manifest['back'],
        $manifest['font'],
        $manifest['genre'],
        implode(',', $manifest['languages']),
        $manifest['type']
    ]);
    $albumId = $pdo->lastInsertId();
}
foreach ($manifest['tracks'] as $i => $t) {
    $pdo->prepare('INSERT INTO album_tracks (album_id, track_number, title, file, length, artist, year, genre, composer, comment, lyricist, explicit, lyrics) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?)')->execute([
        $albumId,
        $i + 1,
        $t['title'],
        $t['file'],
        $t['length'],
        $t['artist'],
        $t['year'],
        $t['genre'],
        $t['composer'],
        $t['comment'],
        $t['lyricist'],
        $t['explicit'] ? 1 : 0,
        $t['lyrics']
    ]);
}
progress_note($progressJobId, 'Saved track metadata to database', ['statusText' => 'Database updated']);

// Generate simple album CSS using the uploaded font if provided
$cssDir = __DIR__ . '/../discography/albums/' . $folder . '/css';
if (!is_dir($cssDir)) {
    mkdir($cssDir, 0777, true);
}
$cssPath = "$cssDir/style.css";
$fontFile = $manifest['font'];
$fontScale = $manifest['fontScale'] ?? 1.0;
$includeBaseStyles = $fontFile || abs($fontScale - 1.0) > 0.0001;
$cssContent = '';
if ($includeBaseStyles) {
    if ($fontFile) {
        $fontFamily = $folder . 'Font';
        $cssContent .= "@font-face {\n" .
            "    font-family: '{$fontFamily}';\n" .
            "    src: url('../assets/{$fontFile}') format('woff2');\n" .
            "    font-weight: normal;\n" .
            "    font-style: normal;\n" .
            "    font-display: swap;\n" .
            "    size-adjust: " . number_format($fontScale, 4, '.', '') . ";\n" .
            "    font-size-adjust: " . number_format($fontScale, 4, '.', '') . ";\n" .
            "}\n\n";
    }
    // Apply a consistent font-size adjustment across album player text via CSS variable
    $remDelta = $fontScale - 1.0; // 1.00 = no change; 1.10 => +0.10rem; 0.90 => -0.10rem
    $cssContent .= ".album-player {\n" .
                   "    --custom-fontsize-adjust: " . number_format($remDelta, 3, '.', '') . "rem;\n" .
                   "}\n\n";

    $cssContent .= "body.album-font-scope {\n";
    if ($fontFile) {
        $cssContent .= "    font-family: '{$fontFamily}', serif;\n";
    }
    $cssContent .= "}\n\n";
}
$customCss = trim($_POST['custom_css'] ?? '');
if ($customCss !== '') {
    $cssContent .= "\n" . $customCss . "\n";
}

function create_blur_image(string $src, string $dest): void
{
    if (!is_file($src)) {
        return;
    }
    if (extension_loaded('imagick')) {
        $img = new Imagick($src);
        $img->gaussianBlurImage(0, 25);
        $img->setImageFormat('jpeg');
        $img->setImageCompressionQuality(90);
        $img->writeImage($dest);
        $img->destroy();
    } elseif (function_exists('imagecreatetruecolor')) {
        $data = file_get_contents($src);
        if ($data === false) {
            return;
        }
        $img = imagecreatefromstring($data);
        if (!$img) {
            return;
        }
        for ($i = 0; $i < 3; $i++) {
            imagefilter($img, IMG_FILTER_GAUSSIAN_BLUR);
        }
        imagejpeg($img, $dest);
        imagedestroy($img);
    } else {
        copy($src, $dest);
    }
}

if ($manifest['cover'] && empty($manifest['coverBlur'])) {
    $src = "$assetDir/" . basename($manifest['cover']);
    if (is_file($src)) {
        $ext = pathinfo($src, PATHINFO_EXTENSION);
        $blurName = 'cover_blur.' . $ext;
        create_blur_image($src, "$assetDir/$blurName");
        $manifest['coverBlur'] = 'assets/' . $blurName;
    }
}

// Handle additional asset uploads
$additionalUploaded = 0;
if (!empty($_FILES['additional_assets']) && is_array($_FILES['additional_assets']['tmp_name'])) {
    foreach ($_FILES['additional_assets']['tmp_name'] as $idx => $tmp) {
        if (!$tmp) {
            continue;
        }
        $origName = $_FILES['additional_assets']['name'][$idx] ?? '';
        $cleanName = preg_replace('/[^a-zA-Z0-9._-]/', '_', basename($origName));
        $dest = "$additionalDir/$cleanName";
        if (!move_uploaded_file($tmp, $dest)) {
            continue;
        }
        $additionalUploaded++;
        $pdo->prepare('INSERT INTO album_assets (album_id, filename) VALUES (?, ?)')->execute([
            $albumId,
            $cleanName
        ]);
    }
}
if ($additionalUploaded > 0) {
    $label = $additionalUploaded === 1 ? 'Stored 1 additional asset' : 'Stored ' . $additionalUploaded . ' additional assets';
    progress_note($progressJobId, $label, ['statusText' => 'Additional assets updated']);
}
$cssUpdated = false;
if ($cssContent !== '') {
    file_put_contents($cssPath, $cssContent);
    $cssUpdated = true;
}
if ($cssUpdated) {
    progress_note($progressJobId, 'Updated album CSS theme', ['statusText' => 'Album CSS updated']);
}
$customHtml = trim($_POST['custom_html'] ?? '');
$htmlPath = __DIR__ . '/../discography/albums/' . $folder . '/custom.html';
$customHtmlUpdated = false;
$customHtmlCleared = false;
if ($customHtml !== '') {
    file_put_contents($htmlPath, $customHtml);
    $customHtmlUpdated = true;
} elseif (is_file($htmlPath)) {
    unlink($htmlPath);
    $customHtmlCleared = true;
}
if ($customHtmlUpdated) {
    progress_note($progressJobId, 'Updated custom HTML snippet', ['statusText' => 'Custom HTML updated']);
} elseif ($customHtmlCleared) {
    progress_note($progressJobId, 'Removed custom HTML snippet', ['statusText' => 'Custom HTML removed']);
}
$cssVersion = file_exists($cssPath) ? filemtime($cssPath) : time();
$versionFile = __DIR__ . '/../version.txt';
$assetVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : $cssVersion;
$assetVersion = preg_replace('/[^0-9]/', '', (string)$assetVersion);
if ($assetVersion === '') {
    $assetVersion = (string) $cssVersion;
}

// --- Create or update the public player page ---
$discogDir = __DIR__ . '/../discography/albums/' . $folder;
if (!is_dir($discogDir)) {
    mkdir($discogDir, 0777, true);
}

$pagePath = "$discogDir/index.php";
$title = htmlspecialchars($manifest["albumTitle"] ?? $folder);
$year = '';
if (!empty($manifest['releaseDate'])) {
    $ts = strtotime($manifest['releaseDate']);
    if ($ts !== false)
        $year = date('Y', $ts);
}
$themeColor = $manifest["themeColor"] ?? "#333333";
$textColor = $manifest["textColor"] ?? "#ffffff";
$trackCount = count($manifest['tracks']);
$runtimeSeconds = 0;
foreach ($manifest['tracks'] as $t) {
    if (!empty($t['length']) && preg_match('/(\d+):(\d+)/', $t['length'], $m)) {
        $runtimeSeconds += $m[1] * 60 + $m[2];
    }
}
$runtimeMinutes = ceil($runtimeSeconds / 60);
$pageTitle = $title . ", by " . $defaultArtistName . ($year ? " ($year)" : "");
$description = "$trackCount Track Album - {$runtimeMinutes} minutes";
$keywords = $defaultArtistName . ", $title";
$host = (isset($_SERVER['REQUEST_SCHEME']) ? $_SERVER['REQUEST_SCHEME'] : 'https')
    . '://' . $_SERVER['HTTP_HOST'];
$albumPath = "/discography/albums/{$folder}/";
// Always use the live site for open graph images so social previews are correct
$liveSite = echopress_primary_url();
if ($liveSite === '') {
    $liveSite = $host;
}
$coverImage = rtrim($liveSite, '/') . $albumPath . ($manifest['cover'] ?? '');
$albumOgUrl = rtrim($liveSite, '/') . $albumPath;

$pageContent = <<<PHP
<?php
/* automatically generated album player */
require_once \$_SERVER['DOCUMENT_ROOT'] . '/admin/session_secure.php';
session_start();
\$manifest = json_decode(file_get_contents(__DIR__ . '/manifest.json'), true);
\$live = \$manifest['live'] ?? true;
\$comingSoon = \$manifest['comingSoon'] ?? false;
if ((!\$live || \$comingSoon) && empty(\$_SESSION['logged_in'])) {
    include \$_SERVER['DOCUMENT_ROOT'] . '/404.php';
    exit;
}
\$albumFolderPath = "/discography/albums/{$folder}/";
\$versionFile = \$_SERVER['DOCUMENT_ROOT'] . '/version.txt';
\$assetVersion = file_exists(\$versionFile) ? trim(file_get_contents(\$versionFile)) : {$assetVersion};
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{$pageTitle}</title>
  <meta name="description" content="{$description}">
  <meta name="keywords" content="{$keywords}">
  
    <!-- OPEN GRAPH TAGS -->
  <meta property="og:type"        content="music.album"/>
  <meta property="og:site_name"   content="{$defaultArtistName}"/>
  <meta property="og:title"       content="{$pageTitle}"/>
  <meta property="og:description" content="{$description}"/>
  <meta property="og:url"         content="{$albumOgUrl}"/>
  <meta property="og:image"       content="{$coverImage}"/>

  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
  <link rel="stylesheet" href="/css/album-player.css?v=<?php echo \$assetVersion; ?>" />
  <link rel="stylesheet" href="/discography/albums/{$folder}/css/style.css?v=<?php echo \$assetVersion; ?>" />
  <link rel="apple-touch-icon" sizes="180x180" href="/profile/favicon/apple-touch-icon.png">
  <link rel="icon" type="image/png" sizes="32x32" href="/profile/favicon/favicon-32x32.png">
  <link rel="icon" type="image/png" sizes="16x16" href="/profile/favicon/favicon-16x16.png">
  <link rel="manifest" href="/profile/favicon/site.webmanifest">
  <link rel="icon" href="/profile/favicon/favicon.ico">
  <style>:root { --theme-color: {$themeColor}; --text-color: {$textColor}; }</style>
  <?php include \$_SERVER['DOCUMENT_ROOT'] . '/includes/analyticstracking.php'; ?>
</head>
<body class="album-font-scope">
<?php include \$_SERVER['DOCUMENT_ROOT'] . '/includes/album-player.php'; ?>
<script src="/js/audio-player.js?v=<?php echo \$assetVersion; ?>" defer></script>
<script src="/js/home-iframe.js?v=<?php echo \$assetVersion; ?>" defer></script>
<script src="/js/share-links.js?v=<?php echo \$assetVersion; ?>" defer></script>
</body>
</html>
PHP;

file_put_contents($pagePath, $pageContent);
progress_note($progressJobId, 'Updated public album page', ['statusText' => 'Public album page updated']);
regenerate_sitemap($pdo);
progress_note($progressJobId, 'Regenerated sitemap', ['statusText' => 'Sitemap updated']);
if ($progressJobId) {
    progress_finish($progressJobId, 'Album saved successfully');
}

if ($ajaxRequest) {
    header('Content-Type: application/json');
    echo json_encode([
        'status' => 'ok',
        'job' => $progressJobId,
        'redirect' => 'index.php'
    ]);
    exit;
}

header('Location: index.php');
exit;
?>
