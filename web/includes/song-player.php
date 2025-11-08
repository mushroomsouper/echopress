<?php
if (!isset($shareData) || !is_array($shareData)) {
    echo '<p><strong>Error:</strong> Missing share data.</p>';
    return;
}

$versionFile = $_SERVER['DOCUMENT_ROOT'] . '/version.txt';
$assetVersion = file_exists($versionFile) ? trim(file_get_contents($versionFile)) : '1';

$title = htmlspecialchars($shareData['title'] ?? 'Untitled');
$albumTitle = htmlspecialchars($shareData['albumTitle'] ?? '');
$artist = htmlspecialchars($shareData['artist'] ?? echopress_artist_name());
$albumUrl = htmlspecialchars($shareData['albumUrl'] ?? '/discography/');
$homeUrl = htmlspecialchars($shareData['homeUrl'] ?? '/');
$audioUrl = htmlspecialchars($shareData['audioUrl'] ?? '');
$coverUrl = htmlspecialchars($shareData['coverUrl'] ?? '');
$backgroundUrl = htmlspecialchars($shareData['backgroundUrl'] ?? '');
$fullBackground = htmlspecialchars($shareData['fullBackground'] ?? $backgroundUrl);
$lyrics = trim((string) ($shareData['lyrics'] ?? ''));
$duration = htmlspecialchars($shareData['duration'] ?? '');
$releaseDate = htmlspecialchars($shareData['releaseDate'] ?? '');
$explicit = !empty($shareData['explicit']);
$slug = htmlspecialchars($shareData['slug'] ?? '');

$hasAudio = $audioUrl !== '';
$hasLyrics = $lyrics !== '';
?>
<div class="song-share__background" style="--background-image: url('<?= $fullBackground ?>')"></div>
<div class="song-share__scrim"></div>
<main class="song-share__content">
  <article class="song-card" aria-live="polite" data-song-slug="<?= $slug ?>">
    <header class="song-card__header">
      <div class="song-card__cover">
        <?php if ($coverUrl): ?>
          <img src="<?= $coverUrl ?>" alt="<?= $albumTitle !== '' ? $albumTitle . ' cover art' : 'Album cover art' ?>" loading="lazy" />
        <?php else: ?>
          <div class="song-card__cover--placeholder" aria-hidden="true">
            <i class="fas fa-music"></i>
          </div>
        <?php endif; ?>
      </div>
      <div class="song-card__meta">
        <p class="song-card__eyebrow"><?= htmlspecialchars(echopress_artist_name()) ?></p>
        <h1 class="song-card__title"><?= $title ?></h1>
        <p class="song-card__subtitle">
          From <a href="<?= $albumUrl ?>"><?= $albumTitle !== '' ? $albumTitle : 'the album' ?></a>
          <?php if ($explicit): ?><span class="song-card__badge" title="Explicit lyrics">Explicit</span><?php endif; ?>
        </p>
      </div>
    </header>

    <section class="song-card__player" aria-label="Audio player">
      <?php if ($hasAudio): ?>
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
                <span class="song-card__time" data-role="duration"><?= $duration !== '' ? $duration : '0:00' ?></span>
              </div>
            </div>
            <div class="song-card__volume">
              <button type="button" class="song-card__mute" data-role="mute" title="Mute">
                <i class="fa-solid fa-volume-high" aria-hidden="true"></i>
                <span class="sr-only">Toggle mute</span>
              </button>
              <label class="sr-only" for="song-share-volume">Volume</label>
              <div class="song-card__volume-popover">
                <input id="song-share-volume" type="range" class="song-card__volume-slider" data-role="volume" min="0" max="1" step="0.01" value="1">
              </div>
            </div>
          </div>
        </div>
        <audio class="song-card__audio" data-role="audio" preload="auto" src="<?= $audioUrl ?>" type="audio/mpeg"></audio>
      <?php else: ?>
        <p class="song-card__warning">The audio file for this track is missing.</p>
      <?php endif; ?>
    </section>

    <section class="song-card__tabs" aria-label="Song information">
      <div class="song-card__tablist" role="tablist">
        <?php if ($hasLyrics): ?>
          <button type="button" role="tab" aria-selected="true" aria-controls="lyrics-panel" id="lyrics-tab" class="song-card__tab song-card__tab--active" data-target="lyrics-panel">Lyrics</button>
        <?php endif; ?>
        <button type="button" role="tab" aria-selected="<?= $hasLyrics ? 'false' : 'true' ?>" aria-controls="details-panel" id="details-tab" class="song-card__tab<?= $hasLyrics ? '' : ' song-card__tab--active' ?>" data-target="details-panel">Details</button>
      </div>
      <div class="song-card__panels">
        <?php if ($hasLyrics): ?>
          <div id="lyrics-panel" role="tabpanel" aria-labelledby="lyrics-tab" class="song-card__panel song-card__panel--active">
            <pre class="song-card__lyrics"><?= htmlspecialchars($lyrics) ?></pre>
          </div>
        <?php endif; ?>
        <div id="details-panel" role="tabpanel" aria-labelledby="details-tab" class="song-card__panel<?= $hasLyrics ? '' : ' song-card__panel--active' ?>">
          <ul class="song-card__details">
            <li><span>Artist</span><span><?= $artist ?></span></li>
            <?php if ($albumTitle !== ''): ?><li><span>Album</span><span><?= $albumTitle ?></span></li><?php endif; ?>
            <?php if ($duration !== ''): ?><li><span>Duration</span><span><?= $duration ?></span></li><?php endif; ?>
            <?php if ($releaseDate !== ''): ?><li><span>Released</span><span><?= $releaseDate ?></span></li><?php endif; ?>
          </ul>
        </div>
      </div>
    </section>

    <footer class="song-card__footer">
      <a class="song-card__button" href="<?= $albumUrl ?>">
        <i class="fa-solid fa-compact-disc" aria-hidden="true"></i>
        Listen to the album
      </a>
      <a class="song-card__button song-card__button--ghost" href="<?= $homeUrl ?>">
        <i class="fa-solid fa-house" aria-hidden="true"></i>
        Back to homepage
      </a>
    </footer>
  </article>
</main>
