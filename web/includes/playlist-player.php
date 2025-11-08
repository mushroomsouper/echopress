<?php
require_once __DIR__ . '/utils.php';

if (!isset($playlistFolderPath)) {
  echo '<p><strong>Error:</strong> $playlistFolderPath is not defined.</p>';
  return;
}

$manifestPath = __DIR__ . "/..{$playlistFolderPath}manifest.json";
if (!file_exists($manifestPath)) {
  echo '<p><strong>Error:</strong> Playlist manifest not found.</p>';
  return;
}

$manifestJson = file_get_contents($manifestPath);
$playlistData = json_decode($manifestJson, true);
if (json_last_error() !== JSON_ERROR_NONE || !is_array($playlistData)) {
  echo '<p><strong>Error:</strong> Failed to decode playlist manifest.</p>';
  return;
}

$title = htmlspecialchars($playlistData['title'] ?? 'Untitled Playlist');
$artist = htmlspecialchars($playlistData['artist'] ?? echopress_artist_name());
$description = trim((string) ($playlistData['description'] ?? ''));
$displayOrder = (int) ($playlistData['displayOrder'] ?? 0);
$themeColor = htmlspecialchars($playlistData['themeColor'] ?? '#333333');
$textColor = htmlspecialchars($playlistData['textColor'] ?? '#ffffff');
$backgroundColor = htmlspecialchars($playlistData['backgroundColor'] ?? '#000000');
$cover = trim((string) ($playlistData['cover'] ?? ''));
$coverBlur = trim((string) ($playlistData['coverBlur'] ?? ''));
$coverSrcsetWebp = trim((string) ($playlistData['coverSrcsetWebp'] ?? ''));
$coverSrcsetJpg = trim((string) ($playlistData['coverSrcsetJpg'] ?? ''));
$backgroundVideo = trim((string) ($playlistData['background'] ?? ''));
$backgroundImage = trim((string) ($playlistData['backgroundImage'] ?? ''));
$tracks = is_array($playlistData['tracks'] ?? null) ? $playlistData['tracks'] : [];
$genres = trim((string) ($playlistData['genre'] ?? ''));
$languages = $playlistData['languages'] ?? [];

$playlistRuntimeSeconds = 0;
$hasExplicit = false;
foreach ($tracks as $track) {
  if (!$hasExplicit && !empty($track['explicit'])) {
    $hasExplicit = true;
  }
  $len = $track['length'] ?? '';
  if (preg_match('/(\d+):(\d+)/', (string) $len, $m)) {
    $playlistRuntimeSeconds += ((int) $m[1]) * 60 + (int) $m[2];
  }
}
$runtimeMinutes = $playlistRuntimeSeconds > 0 ? floor($playlistRuntimeSeconds / 60) : 0;
$runtimeSeconds = $playlistRuntimeSeconds > 0 ? $playlistRuntimeSeconds % 60 : 0;
$runtimeFormatted = sprintf('%d:%02d', $runtimeMinutes, $runtimeSeconds);
$trackCount = count($tracks);

$playlistFolder = rtrim($playlistFolderPath, '/') . '/';
$coverUrl = $cover ? cache_bust($playlistFolder . ltrim($cover, '/')) : '';
$coverBlurUrl = $coverBlur ? cache_bust($playlistFolder . ltrim($coverBlur, '/')) : '';
$backgroundVideoUrl = '';
$backgroundImageUrl = '';
if ($backgroundVideo) {
  $backgroundVideoUrl = $playlistFolder . ltrim($backgroundVideo, '/');
  $videoPath = $_SERVER['DOCUMENT_ROOT'] . $backgroundVideoUrl;
  if (file_exists($videoPath)) {
    $backgroundVideoUrl = cache_bust($backgroundVideoUrl);
  }
}
if ($backgroundImage) {
  $backgroundImageUrl = $playlistFolder . ltrim($backgroundImage, '/');
  $imagePath = $_SERVER['DOCUMENT_ROOT'] . $backgroundImageUrl;
  if (file_exists($imagePath)) {
    $backgroundImageUrl = cache_bust($backgroundImageUrl);
  }
} elseif ($coverUrl) {
  $backgroundImageUrl = $coverBlurUrl ?: $coverUrl;
}

$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$host = $_SERVER['HTTP_HOST'] ?? '';
$baseUrl = $host ? $scheme . '://' . $host : '';
$requestUri = $_SERVER['REQUEST_URI'] ?? '';
$playlistSharePath = $requestUri ?: $playlistFolderPath;
$playlistShareUrl = $baseUrl . $playlistSharePath;
$playlistSlugForId = trim($playlistFolderPath, '/');
$shareDropdownId = 'playlist-share-' . preg_replace('/[^a-z0-9_\-]/i', '', str_replace('/', '-', $playlistSlugForId) ?: uniqid('playlist'));
$shareHeadingId = $shareDropdownId . '-title';

$playlistForJs = [
  'title' => $playlistData['title'] ?? 'Untitled Playlist',
  'artist' => $playlistData['artist'] ?? echopress_artist_name(),
  'description' => $playlistData['description'] ?? '',
  'themeColor' => $playlistData['themeColor'] ?? '#333333',
  'textColor' => $playlistData['textColor'] ?? '#ffffff',
  'backgroundColor' => $playlistData['backgroundColor'] ?? '#000000',
  'cover' => $cover ? $playlistFolder . ltrim($cover, '/') : null,
  'coverBlur' => $coverBlur ? $playlistFolder . ltrim($coverBlur, '/') : null,
  'backgroundVideo' => $backgroundVideo ? $playlistFolder . ltrim($backgroundVideo, '/') : null,
  'backgroundImage' => $backgroundImage ? $playlistFolder . ltrim($backgroundImage, '/') : null,
  'tracks' => [],
];

$trackItems = [];
$shareTracks = [];
foreach ($tracks as $index => $track) {
  if (!is_array($track)) {
    continue;
  }
  $trackTitle = $track['title'] ?? ('Track ' . ($index + 1));
  $album = $track['album'] ?? [];
  $albumTitle = $album['title'] ?? '';
  $albumSlug = $album['slug'] ?? '';
  $albumUrl = $albumSlug ? '/discography/albums/' . $albumSlug . '/' : '';
  $audioPath = $track['audio'] ?? '';
  if ($audioPath && isset($audioPath[0]) && $audioPath[0] === '/') {
    $audioPublicPath = $audioPath;
  } else {
    $audioPublicPath = $audioPath ? $playlistFolder . ltrim($audioPath, '/') : '';
  }
  $length = $track['length'] ?? '';
  $trackItems[] = [
    'title' => $trackTitle,
    'albumTitle' => $albumTitle,
    'albumUrl' => $albumUrl,
    'length' => $length,
    'explicit' => !empty($track['explicit']),
    'audio' => $audioPublicPath,
    'trackNumber' => $track['trackNumber'] ?? ($index + 1),
  ];
  $playlistForJs['tracks'][] = [
    'id' => $track['id'] ?? null,
    'title' => $trackTitle,
    'albumTitle' => $albumTitle,
    'albumUrl' => $albumUrl,
    'length' => $length,
    'explicit' => !empty($track['explicit']),
    'audio' => $audioPublicPath,
    'trackNumber' => $track['trackNumber'] ?? ($index + 1),
    'lyrics' => $track['lyrics'] ?? '',
  ];

  $shareSlug = slugify($trackTitle);
  if ($shareSlug !== '') {
    $shareTracks[] = [
      'number' => $index + 1,
      'title' => $trackTitle,
      'url' => $baseUrl . '/song.php?' . rawurlencode($shareSlug),
    ];
  }
}

$playlistJson = json_encode($playlistForJs, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);
?>
<div class="playlist-backdrop" style="--playlist-background-color: <?= $backgroundColor ?>;">
  <?php if ($backgroundVideoUrl): ?>
    <video class="playlist-backdrop__video" src="<?= htmlspecialchars($backgroundVideoUrl) ?>" autoplay muted loop playsinline></video>
  <?php endif; ?>
  <?php if ($backgroundImageUrl): ?>
    <div class="playlist-backdrop__image" style="background-image: url('<?= htmlspecialchars($backgroundImageUrl) ?>');"></div>
  <?php endif; ?>
  <div class="playlist-backdrop__scrim"></div>
</div>

<main class="playlist-card" data-playlist style="--playlist-theme: <?= $themeColor ?>; --playlist-text: <?= $textColor ?>; --playlist-base: <?= $backgroundColor ?>;">
  <nav class="playlist-card__nav">
    <div class="playlist-card__nav-actions">
      <a href="/" class="playlist-card__home playlist-card__nav-button">
        <i class="fa-solid fa-house" aria-hidden="true"></i>
        <span>Back to homepage</span>
      </a>
      <div class="playlist-card__share share-menu">
        <button
          type="button"
          class="playlist-card__share-btn share-btn playlist-card__nav-button"
          aria-haspopup="dialog"
          aria-expanded="false"
          aria-controls="<?= htmlspecialchars($shareDropdownId) ?>"
        >
          <i class="fa-solid fa-share-nodes" aria-hidden="true"></i>
          <span>Share</span>
        </button>
        <div class="share-overlay" id="<?= htmlspecialchars($shareDropdownId) ?>" aria-hidden="true">
          <div class="share-panel" role="dialog" aria-modal="true" aria-labelledby="<?= htmlspecialchars($shareHeadingId) ?>" tabindex="-1">
            <header class="share-header">
              <div class="share-heading">
                <h4 id="<?= htmlspecialchars($shareHeadingId) ?>">Share this playlist</h4>
                <p>Select what you want to share. We'll copy the link to your clipboard.</p>
              </div>
              <button type="button" class="share-close" aria-label="Close share menu"><i class="fa-solid fa-xmark"></i></button>
            </header>
            <div class="share-panel-body">
              <section class="share-section">
                <h5>Playlist</h5>
                <button type="button" class="share-option" data-share-url="<?= htmlspecialchars($playlistShareUrl) ?>">
                  <span class="share-label">Copy playlist link</span>
                  <span class="share-feedback" aria-hidden="true">
                    <i class="fa-solid fa-share-nodes feedback-icon feedback-default"></i>
                    <i class="fa-solid fa-check feedback-icon feedback-success"></i>
                    <i class="fa-solid fa-xmark feedback-icon feedback-error"></i>
                  </span>
                  <span class="share-feedback-sr sr-only" aria-live="polite">Share link ready</span>
                </button>
              </section>
              <?php if (!empty($shareTracks)): ?>
                <section class="share-section">
                  <h5>Songs</h5>
                  <div class="share-track-grid">
                    <?php foreach ($shareTracks as $shareTrack): ?>
                      <button type="button" class="share-option" data-share-url="<?= htmlspecialchars($shareTrack['url']) ?>">
                        <span class="share-label">
                          <?= htmlspecialchars($shareTrack['number']) ?>. <?= htmlspecialchars($shareTrack['title']) ?>
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
    </div>
  </nav>
  <div class="playlist-card__header-shell">
    <header class="playlist-card__header">
      <div class="playlist-card__cover">
        <?php if ($coverUrl): ?>
          <picture>
            <?php if ($coverSrcsetWebp): ?>
              <source type="image/webp" srcset="<?= format_srcset($coverSrcsetWebp, $playlistFolder) ?>" sizes="(max-width: 600px) 60vw, 320px">
            <?php endif; ?>
          <?php if ($coverSrcsetJpg): ?>
            <source type="image/jpeg" srcset="<?= format_srcset($coverSrcsetJpg, $playlistFolder) ?>" sizes="(max-width: 600px) 60vw, 320px">
          <?php endif; ?>
          <img src="<?= htmlspecialchars($coverUrl) ?>" alt="<?= $title ?> cover art">
        </picture>
      <?php else: ?>
        <div class="playlist-card__cover--placeholder">
          <i class="fa-solid fa-compact-disc" aria-hidden="true"></i>
        </div>
      <?php endif; ?>
      </div>
      <div class="playlist-card__meta">
        <p class="playlist-card__eyebrow">Curated by <?= $artist ?></p>
        <h1 class="playlist-card__title"><?= $title ?></h1>
        <p class="playlist-card__stats">
          <?= $trackCount ?> <?= $trackCount === 1 ? 'track' : 'tracks' ?>
          <?php if ($playlistRuntimeSeconds > 0): ?> · <?= $runtimeFormatted ?><?php endif; ?>
          <?php if ($hasExplicit): ?> · <span class="playlist-card__badge" title="Explicit content">Explicit</span><?php endif; ?>
        </p>
        <?php if ($description !== ''): ?>
          <p class="playlist-card__description"><?= nl2br(htmlspecialchars($description)) ?></p>
        <?php endif; ?>
      </div>
    </header>
  </div>

  <section class="playlist-card__player song-card__player" aria-label="Playlist player">
    <div class="song-card__controls">
      <button type="button" class="song-card__play" data-role="toggle">
        <i class="fa-solid fa-play" aria-hidden="true"></i>
        <span class="sr-only">Play</span>
      </button>
      <div class="song-card__control-group">
        <div class="song-card__timeline">
          <input type="range" class="song-card__progress" data-role="progress" min="0" max="100" step="0.1" value="0" aria-label="Playback position">
          <div class="song-card__timecodes">
            <span class="song-card__time" data-role="elapsed">0:00</span>
            <span class="song-card__time" data-role="duration">0:00</span>
          </div>
        </div>
        <div class="playlist-control-buttons">
          <button type="button" class="song-card__shuffle" data-role="shuffle" aria-pressed="false" title="Shuffle playlist">
            <i class="fa-solid fa-shuffle" aria-hidden="true"></i>
            <span class="sr-only">Toggle shuffle</span>
          </button>
          <div class="song-card__volume">
            <button type="button" class="song-card__mute" data-role="mute" title="Mute">
              <i class="fa-solid fa-volume-high" aria-hidden="true"></i>
              <span class="sr-only">Toggle mute</span>
            </button>
            <label class="sr-only" for="playlist-volume">Volume</label>
            <div class="song-card__volume-popover">
              <input id="playlist-volume" type="range" class="song-card__volume-slider" data-role="volume" min="0" max="1" step="0.01" value="1">
            </div>
          </div>
        </div>
      </div>
    </div>
    <div class="playlist-now-playing">
      <div class="playlist-now-playing__labels">
        <span class="playlist-now-playing__eyebrow">Now Playing</span>
        <span class="playlist-now-playing__title" data-role="now-title">&nbsp;</span>
        <span class="playlist-now-playing__meta" data-role="now-album"></span>
      </div>
    </div>
    <audio class="playlist-card__audio" data-role="audio" preload="auto"></audio>
  </section>

  <section class="playlist-card__tracks" aria-label="Playlist tracks">
    <ol class="playlist-tracklist">
      <?php foreach ($trackItems as $index => $track): ?>
        <li class="playlist-track" data-track-index="<?= $index ?>">
          <button type="button" class="playlist-track__button" data-track-index="<?= $index ?>">
            <span class="playlist-track__number"><?= $index + 1 ?></span>
            <span class="playlist-track__titles">
              <span class="playlist-track__title"><?= htmlspecialchars($track['title']) ?></span>
              <?php if (!empty($track['albumTitle'])): ?>
                <span class="playlist-track__album">
                  <?php if (!empty($track['albumUrl'])): ?>
                    <a href="<?= htmlspecialchars($track['albumUrl']) ?>"><?= htmlspecialchars($track['albumTitle']) ?></a>
                  <?php else: ?>
                    <?= htmlspecialchars($track['albumTitle']) ?>
                  <?php endif; ?>
                </span>
              <?php endif; ?>
            </span>
            <span class="playlist-track__length">
              <?php if (!empty($track['explicit'])): ?>
                <span class="playlist-card__badge" title="Explicit">E</span>
              <?php endif; ?>
              <?= htmlspecialchars($track['length']) ?>
            </span>
            <span class="playlist-track__icon" aria-hidden="true"><i class="fa-solid fa-play"></i></span>
          </button>
        </li>
      <?php endforeach; ?>
    </ol>
  </section>
</main>

<script>window.playlistData = <?= $playlistJson ?>;</script>
