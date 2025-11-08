<?php
require_once __DIR__ . "/utils.php";
/**
 * includes/album-player.php
 *
 * Expects $albumFolderPath to be set, e.g. '/discography/My_Album/'.
 * Then it will:
 *   1. Read manifest.json from that folder.
 *   2. Decode the JSON.
 *   3. Render the player HTML (cover, metadata, controls, track list).
 *   4. Embed albumData JS object for client-side playback logic.
 *
 * Usage (in any PHP page):
 *   <?php
 *     $albumFolderPath = '/discography/My_Album/';
 *     include __DIR__ . '/../includes/album-player.php';
 *   ?>
 */

// 1. Ensure $albumFolderPath is defined
if (!isset($albumFolderPath)) {
  echo "<p><strong>Error:</strong> \$albumFolderPath is not defined.</p>";
  return;
}

// 2. Build the path to the manifest.json on disk
//    __DIR__ is includes/ so we need to back up to the project root or wherever assets are stored.
//    Adjust this if your folder structure differs.
$manifestFullPath = __DIR__ . "/..{$albumFolderPath}manifest.json";

// 3. Read and decode the manifest
if (!file_exists($manifestFullPath)) {
  echo "<p><strong>Error:</strong> manifest.json not found in {$albumFolderPath}</p>";
  return;
}

$manifestJson = file_get_contents($manifestFullPath);
$albumData = json_decode($manifestJson, true);

if (json_last_error() !== JSON_ERROR_NONE) {
  echo "<p><strong>Error:</strong> Failed to decode manifest.json (JSON error).</p>";
  return;
}

// 4. Extract data
$albumTitle = htmlspecialchars($albumData['albumTitle'] ?? 'Untitled Album');
$artist = htmlspecialchars($albumData['artist'] ?? '');
$year = htmlspecialchars($albumData['year'] ?? '');
$coverFileRaw = $albumData['cover'] ?? '';
$coverFile = htmlspecialchars($coverFileRaw);
$coverSrcsetWebp = $albumData['coverSrcsetWebp'] ?? '';
$coverSrcsetJpg = $albumData['coverSrcsetJpg'] ?? ($albumData['coverSrcset'] ?? '');
$coverBlur = $albumData['coverBlur'] ?? '';
$backFileRaw = $albumData['back'] ?? '';
$hasBackCover = $backFileRaw && file_exists(__DIR__ . '/..' . $albumFolderPath . $backFileRaw);
$backFile = htmlspecialchars($backFileRaw);
$backSrcsetWebp = $albumData['backSrcsetWebp'] ?? '';
$backSrcsetJpg = $albumData['backSrcsetJpg'] ?? ($albumData['backSrcset'] ?? '');
$backgroundVideo = htmlspecialchars($albumData['background'] ?? '');
$backgroundImage = htmlspecialchars($albumData['backgroundImage'] ?? '');
$backgroundColor = htmlspecialchars($albumData['backgroundColor'] ?? '#000');
if (!$backgroundImage && $backgroundVideo) {
  $backgroundImage = str_replace('.mp4', '.jpg', $backgroundVideo);
}

// Build URLs for the background assets with cache-busting query strings
$backgroundVideoUrl = '';
$backgroundImageUrl = '';
$backgroundImageShouldBlur = false;
if ($backgroundVideo) {
  $backgroundVideoUrl = $albumFolderPath . $backgroundVideo;
  $videoPath = $_SERVER['DOCUMENT_ROOT'] . $backgroundVideoUrl;
  if (file_exists($videoPath)) {
    $backgroundVideoUrl .= '?v=' . filemtime($videoPath);
  }
}

if ($backgroundImage) {
  $backgroundImageUrl = $albumFolderPath . $backgroundImage;
  $imgPath = $_SERVER['DOCUMENT_ROOT'] . $backgroundImageUrl;
  if (file_exists($imgPath)) {
    $backgroundImageUrl .= '?v=' . filemtime($imgPath);
  }
} elseif ($coverBlur) {
  $backgroundImageUrl = $albumFolderPath . $coverBlur;
  $imgPath = $_SERVER['DOCUMENT_ROOT'] . $backgroundImageUrl;
  if (file_exists($imgPath)) {
    $backgroundImageUrl .= '?v=' . filemtime($imgPath);
  }
  $backgroundImageShouldBlur = true;
}
// Optional custom HTML/JS overlay inserted into the player background
$customBgHtml = '';
$htmlPath = __DIR__ . '/..' . $albumFolderPath . 'custom.html';
if (file_exists($htmlPath)) {
  $customBgHtml = file_get_contents($htmlPath);
}
if (!$backgroundImageUrl && !$backgroundVideoUrl && $coverFileRaw) {
  $backgroundImageUrl = $albumFolderPath . $coverFileRaw;
  $imgPath = $_SERVER['DOCUMENT_ROOT'] . $backgroundImageUrl;
  if (file_exists($imgPath)) {
    $backgroundImageUrl .= '?v=' . filemtime($imgPath);
  }
  $backgroundImageShouldBlur = true;
}
$credits = $albumData['credits'] ?? [];
$tracks = $albumData['tracks'] ?? [];
$trackCount = count($tracks);
$runtimeSeconds = 0;
$hasExplicit = false;
foreach ($tracks as $t) {
  if (!empty($t['length']) && preg_match('/(\d+):(\d+)/', $t['length'], $m)) {
    $runtimeSeconds += $m[1] * 60 + $m[2];
  }
  if (!$hasExplicit && !empty($t['explicit'])) {
    $hasExplicit = true;
  }
}
$runtime = sprintf('%d:%02d', floor($runtimeSeconds/60), $runtimeSeconds%60);

$parts = explode('/', trim($albumFolderPath, '/'));
$slug = end($parts);
// Download ZIPs live under the download sub-dirs now
$mp3Zip  = $albumFolderPath . 'download/mp3/' . $slug . '_MP3.zip';
$wavZip  = $albumFolderPath . 'download/wav/' . $slug . '_WAV.zip';
$flacZip = $albumFolderPath . 'download/flac/' . $slug . '_FLAC.zip';
$mp3Size = '';
$wavSize = '';
$flacSize = '';
if (file_exists(__DIR__ . '/..' . $mp3Zip)) {
  $mp3Size = round(filesize(__DIR__ . '/..' . $mp3Zip)/1048576); // MB
}
if (file_exists(__DIR__ . '/..' . $wavZip)) {
  $wavSize = round(filesize(__DIR__ . '/..' . $wavZip)/1048576);
}
if (file_exists(__DIR__ . '/..' . $flacZip)) {
  $flacSize = round(filesize(__DIR__ . '/..' . $flacZip)/1048576);
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = $host ? $scheme . '://' . $host : '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$albumShareUrl = $baseUrl . ($requestUri ?: $albumFolderPath);
$shareDropdownId = 'share-options-' . preg_replace('/[^a-z0-9_\-]/i', '', $slug ?: uniqid('album'));
$shareHeadingId = $shareDropdownId . '-title';

// Helper to flatten credits into a string
function formatCredits(array $creditsArr): string
{
  $parts = [];
  foreach ($creditsArr as $role => $name) {
    $parts[] = htmlspecialchars($role) . ": " . htmlspecialchars($name);
  }
  return implode(" | ", $parts);
}

// 5. Output the HTML structure
  $backgroundOverlayClasses = 'bg-overlay';
  if ($backgroundImageShouldBlur) {
    $backgroundOverlayClasses .= ' bg-overlay--blur';
  }
?>
<div class="album-header">
  <a href="/" class="back-to-home"><i class="fas fa-home"></i></a>
  <div class="header-actions">
    <div class="share-menu">
      <button type="button" class="download-btn share-btn" aria-haspopup="dialog" aria-expanded="false" aria-controls="<?= htmlspecialchars($shareDropdownId) ?>"><i class="fa-solid fa-share-nodes"></i> Share</button>
      <div class="share-overlay" id="<?= htmlspecialchars($shareDropdownId) ?>" aria-hidden="true">
        <div class="share-panel" role="dialog" aria-modal="true" aria-labelledby="<?= htmlspecialchars($shareHeadingId) ?>" tabindex="-1">
          <header class="share-header">
            <div class="share-heading">
              <h4 id="<?= htmlspecialchars($shareHeadingId) ?>">Share this album</h4>
              <p>Select what you want to share. We'll copy the link to your clipboard.</p>
            </div>
            <button type="button" class="share-close" aria-label="Close share menu"><i class="fa-solid fa-xmark"></i></button>
          </header>
          <div class="share-panel-body">
            <section class="share-section">
              <h5>Album</h5>
          <button type="button" class="share-option" data-share-url="<?= htmlspecialchars($albumShareUrl) ?>">
            <span class="share-label">Copy album link</span>
            <span class="share-feedback" aria-hidden="true">
              <i class="fa-solid fa-share-nodes feedback-icon feedback-default"></i>
              <i class="fa-solid fa-check feedback-icon feedback-success"></i>
              <i class="fa-solid fa-xmark feedback-icon feedback-error"></i>
            </span>
            <span class="share-feedback-sr sr-only" aria-live="polite">Share link ready</span>
          </button>
            </section>
            <?php if (!empty($tracks)): ?>
            <section class="share-section">
              <h5>Songs</h5>
              <div class="share-track-grid">
                <?php foreach ($tracks as $idx => $track):
                  $title = htmlspecialchars($track['title'] ?? 'Track ' . ($idx + 1));
                  $numberLabel = htmlspecialchars($track['number'] ?? (string)($idx + 1));
                  $songSlug = slugify($track['title'] ?? (string)($idx + 1));
                  $songShareUrl = $baseUrl . '/song.php?' . rawurlencode($songSlug);
                ?>
                <button type="button" class="share-option" data-share-url="<?= htmlspecialchars($songShareUrl) ?>">
                  <span class="share-label">
                    <?= $numberLabel ?>. <?= $title ?>
                  </span>
                  <span class="share-feedback" aria-hidden="true">
                    <i class="fa-solid fa-share-nodes feedback-icon feedback-default"></i>
                    <i class="fa-solid fa-check feedback-icon feedback-success"></i>
                    <i class="fa-solid fa-xmark feedback-icon feedback-error"></i>
                  </span>
                  <span class="share-feedback-sr sr-only" aria-live="polite">Share link ready</span>
                </button>
                <?php endforeach; ?>
              </div>
            </section>
            <?php endif; ?>
          </div>
        </div>
      </div>
    </div>
    <div class="download-menu">
      <button type="button" class="download-btn"><i class="fa-solid fa-download"></i> Download</button>
      <div class="download-dropdown">
  <?php if ($mp3Size): ?>
    <a href="<?= $mp3Zip ?>">
      MP3 ZIP Archive (<?= $mp3Size ?> MB)
    </a>
  <?php endif; ?>
  <?php if ($flacSize): ?>
    <a href="<?= $flacZip ?>">
      FLAC ZIP Archive (<?= $flacSize ?> MB)
    </a>
  <?php endif; ?>
  <?php if ($wavSize): ?>
    <a href="<?= $wavZip ?>">
      WAV ZIP Archive (<?= $wavSize ?> MB)
    </a>
  <?php endif; ?>
    </div>
    </div>
  </div>
</div>
<div class="audio-player-container album-player slick-player">
  <!-- Background video/image layer -->
  <div class="player-background" style="background-color: <?= $backgroundColor ?>;">
    <!-- Option A: looping video -->
    <video class="bg-video" src="<?= $backgroundVideoUrl ?>" muted loop playsinline <?php if (!$backgroundVideoUrl)
          echo 'style="display:none"'; ?>></video>
    <!-- Option B: static image as fallback/overlay -->
    <?php if ($backgroundImageUrl): ?>
      <div class="<?= $backgroundOverlayClasses ?>" style="background-image: url('<?= $backgroundImageUrl ?>');"></div>
    <?php endif; ?>
    <?php if ($customBgHtml): ?>
      <?= $customBgHtml ?>
    <?php endif; ?>
  </div>
  <div class="main-content">
    <div class="player-content">

      <!-- ---------------- Left Column: Cover + Details ---------------- -->
      <!-- ---------------- Left Column: Cover + Details ---------------- -->
      <!-- ---------------- Left Column: Cover + Details ---------------- -->
      <!-- ---------------- Left Column: Cover + Details ---------------- -->
      <div class="cover-section">
        <div class="cover-card">
          <div class="cover-inner">
            <!-- Front face -->
            <picture class="cover-front">
              <?php if ($coverSrcsetWebp): ?>
                <source type="image/webp" srcset="<?= format_srcset($coverSrcsetWebp, $albumFolderPath) ?>"
                  sizes="(max-width: 600px) 90vw, 600px">
              <?php endif; ?>
              <?php if ($coverSrcsetJpg): ?>
                <source type="image/jpeg" srcset="<?= format_srcset($coverSrcsetJpg, $albumFolderPath) ?>"
                  sizes="(max-width: 600px) 90vw, 600px">
              <?php endif; ?>
              <img src="<?= htmlspecialchars(cache_bust($albumFolderPath . $coverFile)) ?>" alt="<?= $albumTitle ?> front cover">
            </picture>
            <?php if ($hasBackCover): ?>
            <!-- Back face -->
            <picture class="cover-back">
              <?php if ($backSrcsetWebp): ?>
                <source type="image/webp" srcset="<?= format_srcset($backSrcsetWebp, $albumFolderPath) ?>"
                  sizes="(max-width: 600px) 90vw, 600px">
              <?php endif; ?>
              <?php if ($backSrcsetJpg): ?>
                <source type="image/jpeg" srcset="<?= format_srcset($backSrcsetJpg, $albumFolderPath) ?>"
                  sizes="(max-width: 600px) 90vw, 600px">
              <?php endif; ?>
              <img src="<?= htmlspecialchars(cache_bust($albumFolderPath . $backFile)) ?>" alt="<?= $albumTitle ?> back cover">
            </picture>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($hasBackCover): ?>
        <!-- Flip button below the cover -->
        <button class="flip-button" title="Flip cover" style="display:block;"><img src="<?= htmlspecialchars(cache_bust('/images/ico_rotate.png')) ?>"></button>
        <?php endif; ?>

        <div class="album-details">
          <h2 class="album-title"><?= $albumTitle ?></h2>
          <?php if ($artist): ?>
            <p class="album-artist"><?= $artist ?></p><?php endif; ?>
          <?php if ($year): ?>
            <p class="album-year"><?= $year ?></p><?php endif; ?>
          <?php if (!empty($credits)): ?>
            <p class="album-credits"><?= formatCredits($credits) ?></p>
          <?php endif; ?>
        </div>
      </div>
      <!-- ------------------------- Right Column: Controls + Tracklist + Extras ------------------------- -->
      <div class="playback-section">
        <!-- 1) Player Controls (always visible) -->

        <!-- 3) Extras Panel (Album Info / Lyrics) -->
        <div class="extras-panel">
          <div class="extras-tabs">
            <button class="tab-btn active" data-target="info-view">Album Info</button>
            <button class="tab-btn" data-target="lyrics-view">Lyrics</button>
            <button class="tab-btn close-tab-btn" title="Close">&times;</button>
          </div>
          <div id="info-view" class="tab-content album-info-view active">
            <div class="album-info">
              <h3><?= $albumTitle ?></h3>
              <p><strong>Artist:</strong> <?= $artist ?></p>
              <?php if ($year): ?><p><strong>Year:</strong> <?= $year ?></p><?php endif; ?>
              <?php if (!empty($albumData['releaseDate'])): ?>
                <p><strong>Release Date:</strong> <?= htmlspecialchars($albumData['releaseDate']) ?></p>
              <?php endif; ?>
              <?php if (!empty($albumData['volume'])): ?>
                <p><strong>Volume:</strong> <?= htmlspecialchars($albumData['volume']) ?></p>
              <?php endif; ?>
              <p><strong>Type:</strong> <?= ($albumData['type'] ?? 'album') === 'album' ? 'LP' : 'EP/Single' ?></p>
              <?php if (!empty($albumData['genre'])): ?>
                <p><strong>Genre:</strong> <?= htmlspecialchars($albumData['genre']) ?></p>
              <?php endif; ?>
              <?php if (!empty($albumData['languages'])): ?>
                <p><strong>Language:</strong> <?= htmlspecialchars(implode(', ', (array)$albumData['languages'])) ?></p>
              <?php endif; ?>
              <?php if ($hasExplicit): ?>
                <p><strong>Explicit:</strong> Yes</p>
              <?php else: ?>
                <p><strong>Explicit:</strong> No</p>
              <?php endif; ?>
              <p><strong>Tracks:</strong> <?= $trackCount ?> | Runtime: <?= $runtime ?></p>
              <?php if (!empty($credits)): ?>
                <h4>Credits</h4>
                <ul>
                  <?php foreach ($credits as $role => $name): ?>
                    <li><strong><?= htmlspecialchars($role) ?>:</strong> <?= htmlspecialchars($name) ?></li>
                  <?php endforeach; ?>
                </ul>
              <?php endif; ?>
            </div>
          </div>
          <div id="lyrics-view" class="tab-content lyrics-view">
            <div class="lyrics-container">
              <?php foreach ($tracks as $i => $t): ?>
                <div class="lyrics-track">
                  <?php $num = htmlspecialchars($t['number'] ?? ($i + 1)); ?>
                  <h4><?= $num ?>. <?= htmlspecialchars($t['title'] ?? 'Track') ?><?php if (!empty($t['explicit'])): ?><span class="explicit_icon" title="Explicit"><i class="fa-solid fa-e"></i></span><?php endif; ?></h4>
                  <p class="track-meta">
                    <?php if (!empty($t['length'])): ?><span class="length"><?= htmlspecialchars($t['length']) ?></span><?php endif; ?>
                  </p>
                  <?php if (!empty($t['lyrics'])): ?>
                    <pre class="lyrics-text"><?= htmlspecialchars($t['lyrics']) ?></pre>
                  <?php else: ?>
                    <p class="lyrics-text">No lyrics available.</p>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            </div>
          </div>
        </div>

        <!-- 2) Tracklist Wrapper (collapsible) -->
        <div class="tracklist-wrapper">
          <ul class="track-list">
            <?php foreach ($tracks as $idx => $track):
              $number = htmlspecialchars($track['number'] ?? ($idx + 1));
              $title = htmlspecialchars($track['title'] ?? "Track " . ($idx + 1));
              $length = htmlspecialchars($track['length'] ?? '');
              ?>
              <li data-index="<?= $idx ?>" class="track-item">
                <canvas class="track-waveform"></canvas>
                <span class="title-number"><?= "$number." ?></span>
                <span class="track-title"><span class="title-text"><?= "$title" ?><?php if (!empty($track['explicit'])): ?><span class="explicit_icon" title="Explicit"><i class="fa-solid fa-e"></i></span><?php endif; ?></span></span>
                <span class="track-length"><?= $length ?></span>
                <?php $titleSlug = slugify($track['title'] ?? (string)$number); ?>
                <button type="button" class="share-track" data-url="/song.php?<?= rawurlencode($titleSlug) ?>" title="Share this song"><i class="fas fa-share"></i></button>
              </li>
            <?php endforeach; ?>
          </ul>
        </div>





      </div>
      <!-- Include these two scripts in <head> or right before </body> -->
    </div>
    <div class="player-controls">
      <!-- Audio element (hidden) -->
      <audio class="audio-element" preload="auto" type="audio/mpeg"></audio>
      <div class="player-controls-top">
        <!-- Now‐Playing Bar: Prev / Play‐Pause / Next / Repeat / Track Info -->
        <div class="now-playing">
          <button class="prev-btn" title="Previous track"><i class="fas fa-backward"></i></button>
          <button class="play-pause-btn" title="Play/Pause"><i class="fas fa-play"></i></button>
          <button class="next-btn" title="Next track"><i class="fas fa-forward"></i></button>
          <button class="repeat-btn" title="Repeat album"><i class="fas fa-retweet"></i></button>
        </div>
 <span class="track-info"><span class="info-text">Loading…</span></span>        <div class="right-controls">
          <!-- Volume Slider -->
          <div class="volume-container">
            <i class="fas fa-volume-up volume-icon"></i>
            <input id="volume-slider" type="range" class="volume-slider" min="0" max="1" step="0.01" value="1"
              aria-label="Volume" />
          </div>

          <!-- Toggle Button: switch between tracklist and album info -->
          <button class="toggle-list-btn" title="Show Album Info"><i class="fas fa-info-circle"></i></button>
        </div>
      </div>

      <!-- Progress & Time -->
      <div class="progress-container">
        <span class="time-elapsed">00:00</span>
        <input type="range" class="progress-bar" min="0" max="100" value="0" step="0.1" />
        <span class="time-duration">00:00</span>
      </div>

    </div>

    <!-- <script src="https://cdn.jsdelivr.net/npm/vanilla-tilt@1.7.0/dist/vanilla-tilt.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/hammerjs@2.0.8/hammer.min.js"></script> -->
    <!-- Embed albumData JS as before -->
    <script>
        window.albumData = {
          albumFolder: "<?= $albumFolderPath ?>",
          albumTitle: "<?= $albumTitle ?>",
          artist: "<?= $artist ?>",
          cover: "<?= $coverFile ?>",
          year: "<?= $year ?>",
          releaseDate: "<?= htmlspecialchars($albumData['releaseDate'] ?? '') ?>",
          volume: "<?= htmlspecialchars($albumData['volume'] ?? '') ?>",
          trackCount: <?= $trackCount ?>,
          runtime: "<?= $runtime ?>",
          credits: <?= json_encode($credits, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
          tracks: <?= json_encode($tracks, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT) ?>,
          hasBackCover: <?= $hasBackCover ? 'true' : 'false' ?>
        };
    </script>
    <!-- Include your external JS (e.g., player-logic.js) here -->

    <!-- End of album-player.php -->
