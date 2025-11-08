<?php
require_once __DIR__ . '/session_secure.php';
session_start();
require_once __DIR__ . '/db_connect.php';
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/../includes/utils.php';
require_once __DIR__ . '/../includes/app.php';
$defaultArtistName = echopress_artist_name();

if (empty($_SESSION['logged_in'])) {
    header('Location: login.php');
    exit;
}

$playlistSlug = trim($_GET['playlist'] ?? '');
$errors = $_SESSION['playlist_errors'] ?? [];
$oldInput = $_SESSION['playlist_old_input'] ?? null;
unset($_SESSION['playlist_errors'], $_SESSION['playlist_old_input'], $_SESSION['playlist_success']);

$formDefaults = [
    'playlistTitle' => '',
    'artist' => $defaultArtistName,
    'description' => '',
    'display_order' => 0,
    'genre' => '',
    'languages' => [],
    'live' => true,
    'comingSoon' => false,
    'themeColor' => '#333333',
    'textColor' => '#ffffff',
    'backgroundColor' => '#000000',
    'existing_cover' => '',
    'existing_background' => '',
    'existing_background_image' => '',
    'existing_font' => '',
    'custom_css' => '',
    'custom_html' => ''
];
$formData = $formDefaults;
$selectedTrackIds = [];
$additionalAssets = [];
$playlistId = null;

if ($playlistSlug !== '') {
    $stmt = $pdo->prepare('SELECT * FROM playlists WHERE slug = ?');
    $stmt->execute([$playlistSlug]);
    $row = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($row) {
        $playlistId = (int) $row['id'];
        $formData['playlistTitle'] = $row['title'];
        $formData['artist'] = $row['artist'];
        $formData['description'] = $row['description'] ?? '';
        $formData['display_order'] = (int) $row['display_order'];
        $formData['genre'] = $row['genre'] ?? '';
        $formData['languages'] = $row['languages'] ? array_filter(explode(',', $row['languages'])) : [];
        $formData['live'] = (bool) $row['live'];
        $formData['comingSoon'] = (bool) $row['comingSoon'];
        $formData['themeColor'] = $row['themeColor'] ?? '#333333';
        $formData['textColor'] = $row['textColor'] ?? '#ffffff';
        $formData['backgroundColor'] = $row['backgroundColor'] ?? '#000000';
        $formData['existing_cover'] = $row['cover'] ?? '';
        $formData['existing_background'] = $row['background'] ?? '';
        $formData['existing_background_image'] = $row['backgroundImage'] ?? '';
        $formData['existing_font'] = $row['font'] ?? '';

        $manifestPath = $PLAYLISTS_DIR . '/' . $playlistSlug . '/manifest.json';
        if (is_file($manifestPath)) {
            $manifestData = json_decode(file_get_contents($manifestPath), true);
            if (is_array($manifestData)) {
                if (isset($manifestData['custom_css']) && !$formData['custom_css']) {
                    $formData['custom_css'] = $manifestData['custom_css'];
                }
            }
        }

        $cssPath = $PLAYLISTS_DIR . '/' . $playlistSlug . '/css/style.css';
        if (is_file($cssPath)) {
            $cssContent = file_get_contents($cssPath);
            if ($cssContent !== false) {
                $cssContent = preg_replace('/@font-face\s*\{[^}]*\}\s*body\s*\{[^}]*\}\s*/s', '', $cssContent, 1);
                $formData['custom_css'] = trim($cssContent);
            }
        }
        $htmlPath = $PLAYLISTS_DIR . '/' . $playlistSlug . '/custom.html';
        if (is_file($htmlPath)) {
            $htmlContent = file_get_contents($htmlPath);
            if ($htmlContent !== false) {
                $formData['custom_html'] = trim($htmlContent);
            }
        }

        $trackStmt = $pdo->prepare('SELECT track_id FROM playlist_tracks WHERE playlist_id = ? ORDER BY position');
        $trackStmt->execute([$playlistId]);
        $selectedTrackIds = array_map('intval', $trackStmt->fetchAll(PDO::FETCH_COLUMN));

        $assetStmt = $pdo->prepare('SELECT filename FROM playlist_assets WHERE playlist_id = ? ORDER BY uploaded_at');
        $assetStmt->execute([$playlistId]);
        $additionalAssets = $assetStmt->fetchAll(PDO::FETCH_COLUMN);
    }
}

if ($oldInput) {
    foreach ($formDefaults as $key => $default) {
        if (array_key_exists($key, $oldInput)) {
            $value = $oldInput[$key];
            if (is_array($default)) {
                $formData[$key] = array_filter((array) $value);
            } elseif (is_bool($default)) {
                $formData[$key] = (bool) $value;
            } else {
                $formData[$key] = is_string($value) ? $value : $default;
            }
        }
    }
    $formData['live'] = isset($oldInput['live']);
    $formData['comingSoon'] = isset($oldInput['comingSoon']);
    if (!empty($oldInput['tracks'])) {
        $selectedTrackIds = array_map('intval', (array) $oldInput['tracks']);
    }
}

$selectedTrackIds = array_values(array_unique($selectedTrackIds));

$tracksStmt = $pdo->query('SELECT at.id, at.track_number, at.title, at.length, at.explicit, a.slug AS album_slug, a.albumTitle, a.artist AS album_artist FROM album_tracks at JOIN albums a ON at.album_id = a.id ORDER BY a.albumTitle, at.track_number');
$allTracks = $tracksStmt->fetchAll(PDO::FETCH_ASSOC);
$tracksById = [];
foreach ($allTracks as $track) {
    $tracksById[(int) $track['id']] = $track;
}

$selectedTracks = [];
foreach ($selectedTrackIds as $trackId) {
    if (isset($tracksById[$trackId])) {
        $selectedTracks[] = $tracksById[$trackId];
    }
}

$availableTracksPayload = [];
foreach ($allTracks as $track) {
    $availableTracksPayload[] = [
        'id' => (int) $track['id'],
        'title' => $track['title'],
        'length' => $track['length'],
        'explicit' => (bool) $track['explicit'],
        'albumTitle' => $track['albumTitle'],
        'albumSlug' => $track['album_slug'],
        'trackNumber' => (int) $track['track_number'],
        'albumArtist' => $track['album_artist']
    ];
}
$availableTracksJson = json_encode($availableTracksPayload, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
$selectedIdsJson = json_encode($selectedTrackIds, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

$langOptions = ['English','Spanish','French','German','Italian','Portuguese','Russian','Japanese','Korean','Chinese'];

$versionFile = __DIR__ . '/../version.txt';
$version = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1';
$versionParam = htmlspecialchars($version, ENT_QUOTES);
$headExtras = '<link rel="stylesheet" href="/css/admin.css?v=' . $versionParam . '">' . "\n" .
              '<script src="/js/admin-session.js?v=' . $versionParam . '" defer></script>' . "\n" .
              '<script src="/js/playlist-edit.js?v=' . $versionParam . '" defer></script>';

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <title><?= htmlspecialchars(($playlistSlug ? 'Edit' : 'New') . ' Playlist') ?></title>
    <link rel="stylesheet" href="/css/style.css?v=<?= htmlspecialchars($version) ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.7.2/css/all.min.css">
    <?= $headExtras ?>
</head>
<body class="admin-playlist-edit">
    <h1><?= htmlspecialchars($playlistSlug ? 'Edit Playlist' : 'Create Playlist') ?></h1>
    <p><a href="playlists.php">&larr; Back to Playlists</a></p>

    <?php if ($errors): ?>
        <div class="admin-alert admin-alert--error">
            <ul>
                <?php foreach ($errors as $err): ?>
                    <li><?= htmlspecialchars($err) ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <form action="save_playlist.php" method="post" enctype="multipart/form-data" id="playlist-form">
        <input type="hidden" name="original_name" value="<?= htmlspecialchars($playlistSlug) ?>">
        <input type="hidden" name="playlist_id" value="<?= htmlspecialchars($playlistId ?? '') ?>">
        <input type="hidden" name="existing_cover" value="<?= htmlspecialchars($formData['existing_cover']) ?>">
        <input type="hidden" name="existing_background" value="<?= htmlspecialchars($formData['existing_background']) ?>">
        <input type="hidden" name="existing_background_image" value="<?= htmlspecialchars($formData['existing_background_image']) ?>">
        <input type="hidden" name="existing_font" value="<?= htmlspecialchars($formData['existing_font']) ?>">

        <fieldset>
            <legend>Playlist Details</legend>
            <label>Title:
                <input type="text" name="playlistTitle" value="<?= htmlspecialchars($formData['playlistTitle']) ?>" required>
            </label><br>
            <label>Artist:
                <input type="text" name="artist" value="<?= htmlspecialchars($formData['artist']) ?>">
            </label><br>
            <label>Description:
                <textarea name="description" rows="3" cols="60"><?= htmlspecialchars($formData['description']) ?></textarea>
            </label><br>
            <label>Display Order:
                <input type="number" name="display_order" value="<?= (int) $formData['display_order'] ?>">
            </label><br>
            <label>Playlist Genre:
                <input type="text" name="genre" value="<?= htmlspecialchars($formData['genre']) ?>">
            </label><br>
            <label>Languages:
                <select name="languages[]" multiple>
                    <?php foreach ($langOptions as $lang): ?>
                        <option value="<?= htmlspecialchars($lang) ?>" <?= in_array($lang, $formData['languages'], true) ? 'selected' : '' ?>><?= htmlspecialchars($lang) ?></option>
                    <?php endforeach; ?>
                </select>
            </label><br>
            <label>Live:
                <input type="checkbox" name="live" value="1" <?= $formData['live'] ? 'checked' : '' ?>>
            </label><br>
            <label>Coming Soon:
                <input type="checkbox" name="comingSoon" value="1" <?= $formData['comingSoon'] ? 'checked' : '' ?>>
            </label><br>
            <label>Theme Color:
                <input type="color" name="themeColor" value="<?= htmlspecialchars($formData['themeColor']) ?>">
            </label><br>
            <label>Text Color:
                <input type="color" name="textColor" value="<?= htmlspecialchars($formData['textColor']) ?>">
            </label><br>
            <label>Background Color:
                <input type="color" name="backgroundColor" value="<?= htmlspecialchars($formData['backgroundColor']) ?>">
            </label><br>
        </fieldset>

        <fieldset>
            <legend>Artwork &amp; Media</legend>
            <label>Cover Art:
                <input type="file" name="cover" accept="image/*">
            </label>
            <?php if ($formData['existing_cover']): ?>
                <div class="current-asset">
                    <img src="<?= htmlspecialchars(cache_bust('/discography/playlists/' . $playlistSlug . '/' . ltrim($formData['existing_cover'], '/'))) ?>" alt="Current cover" height="100">
                </div>
            <?php endif; ?>
            <br>
            <label>Background Video:
                <input type="file" name="background" accept="video/*">
            </label>
            <?php if ($formData['existing_background']): ?>
                <div class="current-asset">
                    <code><?= htmlspecialchars($formData['existing_background']) ?></code>
                </div>
            <?php endif; ?>
            <br>
            <label>Background Image:
                <input type="file" name="background_image" accept="image/*">
            </label>
            <?php if ($formData['existing_background_image']): ?>
                <div class="current-asset">
                    <img src="<?= htmlspecialchars(cache_bust('/discography/playlists/' . $playlistSlug . '/' . ltrim($formData['existing_background_image'], '/'))) ?>" alt="Current background" height="100">
                </div>
            <?php endif; ?>
            <br>
            <label>Custom Font:
                <input type="file" name="font" accept=".woff,.woff2,.ttf,.otf">
            </label>
            <?php if ($formData['existing_font']): ?>
                <div class="current-asset"><code><?= htmlspecialchars($formData['existing_font']) ?></code></div>
            <?php endif; ?>
        </fieldset>

        <fieldset>
            <legend>Additional Assets</legend>
            <?php if ($additionalAssets): ?>
                <div class="additional-assets">
                    <?php foreach ($additionalAssets as $asset):
                        $assetPath = '/discography/playlists/' . $playlistSlug . '/assets/additional/' . $asset;
                    ?>
                        <div class="additional-asset">
                            <?php if (preg_match('/\.(mp4|webm|ogg)$/i', $asset)): ?>
                                <video src="<?= htmlspecialchars($assetPath) ?>" width="120" controls></video>
                            <?php else: ?>
                                <img src="<?= htmlspecialchars(cache_bust($assetPath)) ?>" width="120" alt="">
                            <?php endif; ?>
                            <input type="text" readonly value="<?= htmlspecialchars($assetPath) ?>" size="40">
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>
            <label>Upload Additional Assets:
                <input type="file" name="additional_assets[]" multiple>
            </label>
        </fieldset>

        <fieldset>
            <legend>Custom Styling</legend>
            <label>Custom CSS:
                <textarea name="custom_css" rows="6" cols="60"><?= htmlspecialchars($formData['custom_css']) ?></textarea>
            </label><br>
            <label>Custom HTML/JS:
                <textarea name="custom_html" rows="6" cols="60"><?= htmlspecialchars($formData['custom_html']) ?></textarea>
            </label>
        </fieldset>

        <fieldset class="playlist-tracks">
            <legend>Playlist Tracks</legend>
            <div class="playlist-track-columns">
                <section class="playlist-track-picker">
                    <div class="playlist-track-picker__search">
                        <label for="track-search">Search tracks:
                            <input type="search" id="track-search" placeholder="Search by title or album">
                        </label>
                    </div>
                    <div class="playlist-track-picker__list" id="available-tracks">
                        <?php foreach ($allTracks as $track):
                            $trackId = (int) $track['id'];
                            $isSelected = in_array($trackId, $selectedTrackIds, true);
                        ?>
                            <label class="track-option" data-track-id="<?= $trackId ?>" data-album="<?= htmlspecialchars(strtolower($track['albumTitle'])) ?>" data-title="<?= htmlspecialchars(strtolower($track['title'])) ?>">
                                <input type="checkbox" value="<?= $trackId ?>" <?= $isSelected ? 'checked' : '' ?>>
                                <span class="track-option__title"><?= htmlspecialchars($track['title']) ?></span>
                                <span class="track-option__meta">
                                    <?= htmlspecialchars($track['albumTitle']) ?> · Track <?= (int) $track['track_number'] ?>
                                    <?php if (!empty($track['length'])): ?>
                                        · <?= htmlspecialchars($track['length']) ?>
                                    <?php endif; ?>
                                    <?php if (!empty($track['explicit'])): ?>
                                        · Explicit
                                    <?php endif; ?>
                                </span>
                            </label>
                        <?php endforeach; ?>
                    </div>
                </section>
                <section class="playlist-track-selected">
                    <h3>Selected Tracks</h3>
                    <div class="selected-track__toolbar">
                        <button type="button" class="selected-track__shuffle" id="shuffle-tracks">
                            <i class="fa-solid fa-shuffle" aria-hidden="true"></i>
                            Shuffle
                        </button>
                    </div>
                    <ol id="selected-tracks" class="selected-track-list">
                        <?php foreach ($selectedTracks as $track):
                            $trackId = (int) $track['id'];
                        ?>
                            <li class="selected-track" data-track-id="<?= $trackId ?>">
                                <input type="hidden" name="tracks[]" value="<?= $trackId ?>">
                                <div class="selected-track__info">
                                    <span class="selected-track__title"><?= htmlspecialchars($track['title']) ?></span>
                                    <span class="selected-track__meta"><?= htmlspecialchars($track['albumTitle']) ?> · Track <?= (int) $track['track_number'] ?><?php if (!empty($track['length'])): ?> · <?= htmlspecialchars($track['length']) ?><?php endif; ?></span>
                                </div>
                                <div class="selected-track__actions">
                                    <button type="button" class="selected-track__btn" data-action="move-up" aria-label="Move up"><i class="fa-solid fa-chevron-up"></i></button>
                                    <button type="button" class="selected-track__btn" data-action="move-down" aria-label="Move down"><i class="fa-solid fa-chevron-down"></i></button>
                                    <button type="button" class="selected-track__btn" data-action="remove" aria-label="Remove"><i class="fa-solid fa-xmark"></i></button>
                                </div>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                    <p id="selected-tracks-empty" <?= $selectedTracks ? 'hidden' : '' ?>>No tracks selected yet.</p>
                </section>
            </div>
        </fieldset>

        <button type="submit" class="primary-btn">Save Playlist</button>
    </form>

    <script type="application/json" id="available-tracks-data"><?= $availableTracksJson ?></script>
    <script type="application/json" id="selected-track-ids"><?= $selectedIdsJson ?></script>
</body>
</html>
