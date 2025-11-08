<?php
require_once __DIR__ . '/session_secure.php';
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/../includes/app.php';
$defaultArtistName = echopress_artist_name();

if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$album = $_GET['album'] ?? '';
$manifest = [];
$albumUrl = '';
$albumId = null;
$additionalAssets = [];

// Default values
$manifestDefaults = [
    'albumTitle' => '',
    'artist' => $defaultArtistName,
    'type' => 'album',
    'volume' => null,
    'releaseDate' => '',
    'live' => true,
    'comingSoon' => false,
    'themeColor' => '#333333',
    'textColor' => '#ffffff',
    'backgroundColor' => '#000000',
    'background' => '',
    'backgroundImage' => '',
    'cover' => '',
    'back' => '',
    'font' => '',
    'fontScale' => 1.0,
    'genre' => '',
    'languages' => [],
    'tracks' => []
];

if ($album) {
    // 1) Fetch album record (including artist & id)
    $stmt = $pdo->prepare(
        'SELECT
            id,
            slug,
            albumTitle,
            artist,
            volume,
            releaseDate,
            live,
            comingSoon,
            themeColor,
            textColor,
            backgroundColor,
            background,
            backgroundImage,
            cover,
            back,
            font,
            genre,
            languages,
            type
         FROM albums
         WHERE slug = ?'
    );
    $stmt->execute([$album]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($row) {
        $albumId = (int) $row['id'];
        // Map DB row into manifest
        $manifest = [
            'albumTitle' => $row['albumTitle'],
            'artist' => $row['artist'],
            'volume' => $row['volume'],
            'releaseDate' => $row['releaseDate'],
            'live' => (bool) $row['live'],
            'comingSoon' => (bool) $row['comingSoon'],
            'themeColor' => $row['themeColor'],
            'textColor' => $row['textColor'],
            'backgroundColor' => $row['backgroundColor'],
            'background' => $row['background'],
            'backgroundImage' => $row['backgroundImage'],
            'cover' => $row['cover'],
            'back' => $row['back'],
            'font' => $row['font'],
            'genre' => $row['genre'] ?? '',
            'languages' => $row['languages'] ? explode(',', $row['languages']) : [],
            'type' => $row['type'] ?? 'album',
            'tracks' => []
        ];

        // 2) Fetch tracks (preserving track-level artist, etc)
        $t = $pdo->prepare(
            'SELECT title,
          file,
          length,
          artist,
          year,
          genre,
          composer,
          comment,
          lyricist,
          explicit,
          lyrics
     FROM album_tracks
    WHERE album_id = ?
 ORDER BY track_number'
        );
        $t->execute([$albumId]);
        foreach ($t->fetchAll(PDO::FETCH_ASSOC) as $track) {
            $manifest['tracks'][] = [
                'title' => $track['title'],
                'file' => $track['file'],
                'length' => $track['length'],
                'artist' => $track['artist'] ?: $row['artist'],
                'year' => $track['year'] ?: date('Y', strtotime($row['releaseDate'])),
                'genre' => $track['genre'] ?? '',
                'composer' => $track['composer'] ?? '',
                'comment' => $track['comment'] ?? '',
                'lyricist' => $track['lyricist'] ?? '',
                'explicit' => (bool)($track['explicit'] ?? 0),
                'lyrics' => $track['lyrics'] ?? ''
            ];
        }

        // If the DB didn't return any tracks, fall back to the manifest file
if (empty($manifest['tracks'])) {
    $manifestPath = __DIR__ . "/../discography/albums/{$album}/manifest.json";
    if (file_exists($manifestPath)) {
        $fileData = json_decode(file_get_contents($manifestPath), true);
        if (isset($fileData['tracks']) && is_array($fileData['tracks'])) {
            $manifest['tracks'] = $fileData['tracks'];
        }
        if (isset($fileData['fontScale'])) {
            $manifest['fontScale'] = (float)$fileData['fontScale'];
        }
    }
}
    } else {
        // Fallback to manifest.json
        $manifestPath = __DIR__ . "/../discography/albums/{$album}/manifest.json";
        if (file_exists($manifestPath)) {
    $manifest = json_decode(file_get_contents($manifestPath), true) ?: [];
    if (isset($manifest['fontScale'])) {
        $manifest['fontScale'] = (float)$manifest['fontScale'];
    }
}
    }

    $albumUrl = '/discography/albums/' . rawurlencode($album) . '/';
    $assetsStmt = $pdo->prepare('SELECT id, filename FROM album_assets WHERE album_id=? ORDER BY uploaded_at');
    $assetsStmt->execute([$albumId]);
    $additionalAssets = $assetsStmt->fetchAll(PDO::FETCH_ASSOC);
}

// Merge defaults so every key exists
$manifest = array_merge($manifestDefaults, $manifest);
if (!isset($manifest['fontScale']) || !is_numeric($manifest['fontScale'])) {
    $manifest['fontScale'] = 1.0;
}

// Load existing custom CSS and custom HTML for the album if present
$customCss = '';
$customHtml = '';
if ($album) {
    $cssFile = __DIR__ . '/../discography/albums/' . $album . '/css/style.css';
    if (is_file($cssFile)) {
        $cssContent = file_get_contents($cssFile);
        // Strip the automatically generated font CSS
        $cssContent = preg_replace('/@font-face\s*\{[^}]*size-adjust[^}]*\}\s*/s', '', $cssContent, 1);
        $cssContent = preg_replace('/body\.album-font-scope\s*\{[^}]*\}\s*/s', '', $cssContent, 1);
        $customCss = trim($cssContent);
    }
    $htmlFile = __DIR__ . '/../discography/albums/' . $album . '/custom.html';
    if (is_file($htmlFile)) {
        $customHtml = file_get_contents($htmlFile);
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <?php
    $pageTitle = ($album ? 'Edit' : 'New') . ' Album';
    $versionFile = __DIR__ . '/../version.txt';
    $version = file_exists($versionFile)
        ? trim(file_get_contents($versionFile))
        : '1';
    $versionParam = htmlspecialchars($version, ENT_QUOTES);
    $headExtras = '<link rel="stylesheet" href="/css/admin.css?v=' . $versionParam . '">' . "\n" .
                  '<script src="/js/admin-session.js?v=' . $versionParam . '" defer></script>';
    ?>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="/css/style.css?v=<?= htmlspecialchars($version) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <?= $headExtras ?>
</head>

<body>
    <h1><?= htmlspecialchars($pageTitle) ?></h1>
    <form action="save_album.php" method="post" enctype="multipart/form-data">
        <input type="hidden" name="original_name" value="<?= htmlspecialchars($album) ?>">
        <input type="hidden" name="album_id" value="<?= htmlspecialchars($albumId) ?>">
        <input type="hidden" name="existing_cover" value="<?= htmlspecialchars($manifest['cover']) ?>">
        <input type="hidden" name="existing_back" value="<?= htmlspecialchars($manifest['back']) ?>">
        <input type="hidden" name="existing_background" value="<?= htmlspecialchars($manifest['background']) ?>">
        <input type="hidden" name="existing_background_image"
            value="<?= htmlspecialchars($manifest['backgroundImage']) ?>">
        <input type="hidden" name="existing_font" value="<?= htmlspecialchars($manifest['font']) ?>">

        <label>Album Title:
            <input type="text" name="albumTitle" required value="<?= htmlspecialchars($manifest['albumTitle']) ?>">
        </label><br>

        <label>Album Artist:
            <input type="text" name="artist" required value="<?= htmlspecialchars($manifest['artist']) ?>">
        </label><br>

        <label>Volume Number:
            <input type="number" name="volume" value="<?= htmlspecialchars($manifest['volume']) ?>">
        </label><br>

        <label>Release Type:
            <select name="type">
                <?php $t = $manifest['type']; ?>
                <option value="album" <?= $t === 'album' ? 'selected' : '' ?>>LP</option>
                <option value="ep" <?= $t === 'ep' ? 'selected' : '' ?>>EP</option>
                <option value="single" <?= $t === 'single' ? 'selected' : '' ?>>Single</option>
            </select>
        </label><br>

        <label>Album Genre:
            <input type="text" name="genre" value="<?= htmlspecialchars($manifest['genre']) ?>">
        </label><br>

        <label>Languages:
            <select name="languages[]" multiple>
<?php
    $langs = [
        'English','Spanish','French','German','Italian','Portuguese','Russian','Japanese','Korean','Chinese'
    ];
    foreach ($langs as $lang): ?>
                <option value="<?= $lang ?>" <?= in_array($lang, $manifest['languages']) ? 'selected' : '' ?>><?= $lang ?></option>
<?php endforeach; ?>
            </select>
        </label><br>

        <label>Release Date:
            <input type="date" name="releaseDate" value="<?= htmlspecialchars($manifest['releaseDate']) ?>">
        </label><br>

        <label>Live:
            <input type="checkbox" name="live" value="1" <?= $manifest['live'] ? 'checked' : '' ?>>
        </label><br>

        <label>Coming Soon:
            <input type="checkbox" name="comingSoon" value="1" <?= $manifest['comingSoon'] ? 'checked' : '' ?>>
        </label><br>

        <label>Theme Color:
            <input type="color" name="themeColor" value="<?= htmlspecialchars($manifest['themeColor']) ?>">
        </label><br>

        <label>Text Color:
            <input type="color" name="textColor" value="<?= htmlspecialchars($manifest['textColor']) ?>">
        </label><br>

        <label>Background Color:
            <input type="color" name="backgroundColor" value="<?= htmlspecialchars($manifest['backgroundColor']) ?>">
        </label><br>

        <label>Font File:
            <input type="file" name="font">
            <?php if ($manifest['font']): ?>
                (current: <?= htmlspecialchars($manifest['font']) ?>)
            <?php endif; ?>
        </label><br>

        <label>Font Scale:
            <input type="number" name="font_scale" step="0.05" min="0.25" max="4"
                value="<?= htmlspecialchars(number_format((float)($manifest['fontScale'] ?? 1.0), 2, '.', '')) ?>">
            <small class="hint">Default is 1.00. Adjust if your custom font renders unusually large or small.</small>
        </label><br>

        <label>Cover Image:
            <input type="file" name="cover">
            <?php if ($manifest['cover']): ?>
                <img src="<?= htmlspecialchars(cache_bust($albumUrl . $manifest['cover'])) ?>" height="50">
            <?php endif; ?>
        </label><br>

        <!-- existing fields up to Cover Image… -->

        <!-- Back Image -->
        <label>Back Image:
            <input type="file" name="back">
            <?php if ($manifest['back']): ?>
                <img src="<?= htmlspecialchars(cache_bust($albumUrl . $manifest['back'])) ?>" height="50">
            <?php endif; ?>
        </label><br>

        <!-- Background Video -->
        <label>Background Video (MP4):
            <input type="file" name="background">
        </label><br>

        <!-- Fallback Background Image -->
        <label>Fallback Background Image:
            <input type="file" name="background_image">
        </label><br>

        <label>Custom CSS:<br>
            <textarea name="custom_css" rows="6" cols="60"><?= htmlspecialchars($customCss) ?></textarea>
        </label><br>
        <label>Custom HTML/JS:<br>
            <textarea name="custom_html" rows="6" cols="60"><?= htmlspecialchars($customHtml) ?></textarea>
        </label><br>

        <h3>Additional Assets</h3>
        <?php if (!empty($additionalAssets)): ?>
            <div id="additional-assets-list">
                <?php foreach ($additionalAssets as $asset):
                    $path = $albumUrl . 'assets/additional/' . $asset['filename']; ?>
                    <div class="additional-asset" style="margin-bottom:8px;">
                        <?php if (preg_match('/\.(mp4|webm|ogg)$/i', $asset['filename'])): ?>
                            <video src="<?= htmlspecialchars($path) ?>" width="100" controls></video>
                        <?php else: ?>
                            <img src="<?= htmlspecialchars(cache_bust($path)) ?>" width="100" alt="">
                        <?php endif; ?>
                        <input type="text" readonly value="<?= htmlspecialchars($path) ?>" style="width:300px;">
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
        <label>Upload Additional Assets:
            <input type="file" name="additional_assets[]" multiple>
        </label><br>
        <div class="track-list-header">
            <h3>Tracks</h3>
            <div class="btn-group">

                <button type="button" id="toggle-sort">Sort Tracks</button>
                <button type="button" id="save-sort" style="display:none">Save Sort Order</button>
            </div>
        </div>
        <div id="tracks">
            <?php
            // ensure at least one track placeholder
            $trackList = $manifest['tracks'];
            if (empty($trackList)) {
                $trackList[] = [
                    'title' => '',
                    'length' => '',
                    'file' => '',
                    'artist' => $manifest['artist'],
                    'year' => ($manifest['releaseDate'] ? date('Y', strtotime($manifest['releaseDate'])) : ''),
                    'genre' => '',
                    'composer' => '',
                    'comment' => '',
                    'lyricist' => '',
                    'explicit' => false,
                    'lyrics' => ''
                ];
            }
            foreach ($trackList as $i => $t): ?>
                <div class="track">
                    <span class="track-number"><?= $i + 1 ?></span>
                    <input type="text" name="tracks[<?= $i ?>][title]" value="<?= htmlspecialchars($t['title']) ?>"
                        placeholder="Title">
                    <input type="text" name="tracks[<?= $i ?>][length]" value="<?= htmlspecialchars($t['length']) ?>"
                        placeholder="Length">
                    <input type="hidden" name="tracks[<?= $i ?>][existing_file]"
                        value="<?= htmlspecialchars($t['file']) ?>">
                    <input type="file" name="tracks[<?= $i ?>][file]">
                    <input type="text" name="tracks[<?= $i ?>][artist]" value="<?= htmlspecialchars($t['artist']) ?>"
                        placeholder="Artist">
                    <input type="text" name="tracks[<?= $i ?>][year]" value="<?= htmlspecialchars($t['year']) ?>"
                        placeholder="Year">
                    <input type="text" name="tracks[<?= $i ?>][genre]" value="<?= htmlspecialchars($t['genre']) ?>"
                        placeholder="Genre">
                    <input type="text" name="tracks[<?= $i ?>][composer]" value="<?= htmlspecialchars($t['composer']) ?>"
                        placeholder="Composer">
                    <input type="text" name="tracks[<?= $i ?>][comment]" value="<?= htmlspecialchars($t['comment']) ?>"
                        placeholder="Comment">
                    <input type="text" name="tracks[<?= $i ?>][lyricist]"
                        value="<?= htmlspecialchars($t['lyricist'] ?? '') ?>" placeholder="Lyricist">
                    <label class="explicit-field">Explicit?
                        <input type="checkbox" name="tracks[<?= $i ?>][explicit]" value="1" <?= !empty($t['explicit']) ? 'checked' : '' ?>>
                    </label>
                    <textarea name="tracks[<?= $i ?>][lyrics]"
                        placeholder="Lyrics"><?= htmlspecialchars($t['lyrics']) ?></textarea>
                    <button type="button" class="remove-track">Remove</button>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" id="add-track">Add Track</button>

        <div id="size-warning" style="display:none;color:red;margin-top:6px"></div>
        <button type="button" id="zip-tracks">Generate Zips</button><br><br>
        <button type="button" id="bump-version">Reset Asset Cache</button>

        <button type="submit">Save</button>
    </form>
    <p><a href="index.php">Back to Albums</a></p>

    <script src="album_edit.js?v=<?php echo $version; ?>"></script>
</body>

</html>
