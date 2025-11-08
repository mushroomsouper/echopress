<?php
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
$albumTitle  = htmlspecialchars($albumData['albumTitle'] ?? 'Untitled Album');
$artist      = htmlspecialchars($albumData['artist'] ?? '');
$year        = htmlspecialchars($albumData['year'] ?? '');
$coverFile   = htmlspecialchars($albumData['cover'] ?? '');
$coverSrcsetWebp = $albumData['coverSrcsetWebp'] ?? '';
$coverSrcsetJpg  = $albumData['coverSrcsetJpg'] ?? ($albumData['coverSrcset'] ?? '');
$backFile    = htmlspecialchars($albumData['back'] ?? '');
$backSrcsetWebp  = $albumData['backSrcsetWebp'] ?? '';
$backSrcsetJpg   = $albumData['backSrcsetJpg'] ?? ($albumData['backSrcset'] ?? '');
$credits     = $albumData['credits'] ?? [];
$tracks      = $albumData['tracks'] ?? [];

// Helper to flatten credits into a string
function formatCredits(array $creditsArr): string {
    $parts = [];
    foreach ($creditsArr as $role => $name) {
        $parts[] = htmlspecialchars($role) . ": " . htmlspecialchars($name);
    }
    return implode(" | ", $parts);
}

// 5. Output the HTML structure
?>
<section class="audio-player-container">
  <!-- Album Info -->
  <div class="album-info">
    <picture class="album-cover">
      <?php if ($coverSrcsetWebp): ?>
        <source type="image/webp" srcset="<?php echo implode(', ', array_map(function($p) use ($albumFolderPath) { return $albumFolderPath . trim($p); }, explode(',', $coverSrcsetWebp))); ?>" sizes="100px">
      <?php endif; ?>
      <img src="<?= htmlspecialchars(cache_bust($albumFolderPath . $coverFile)) ?>"
           alt="<?php echo "$albumTitle cover"; ?>"
           <?php if ($coverSrcsetJpg): ?>
           srcset="<?php echo implode(', ', array_map(function($p) use ($albumFolderPath) { return $albumFolderPath . trim($p); }, explode(',', $coverSrcsetJpg))); ?>"
           <?php endif; ?> />
    </picture>
    <div class="album-details">
      <h2 class="album-title"><?php echo $albumTitle; ?></h2>
      <?php if ($artist): ?>
        <p class="album-artist"><?php echo $artist; ?></p>
      <?php endif; ?>
      <?php if ($year): ?>
        <p class="album-year"><?php echo $year; ?></p>
      <?php endif; ?>
      <?php if (!empty($credits)): ?>
        <p class="album-credits"><?php echo formatCredits($credits); ?></p>
      <?php endif; ?>
    </div>
  </div>

  <!-- Player Controls -->
  <div class="player-controls">
    <audio class="audio-element" preload="auto"></audio>

    <div class="now-playing">
      <button class="prev-btn" title="Previous track">⏮️</button>
      <button class="play-pause-btn" title="Play/Pause">▶️</button>
      <button class="next-btn" title="Next track">⏭️</button>
      <button class="repeat-btn" title="Repeat album">🗘</button>
      <span class="track-info">Loading…</span>
    </div>

    <!-- Progress & Time Display -->
    <div class="progress-container">
      <span class="time-elapsed">00:00</span>
      <input
        type="range"
        class="progress-bar"
        min="0"
        max="100"
        value="0"
        step="0.1"
      />
      <span class="time-duration">00:00</span>
    </div>

  <!-- NEW: Volume Control -->
  <div class="volume-container">
    <label for="volume-slider" class="visually-hidden">Volume</label>
    <input
      id="volume-slider"
      type="range"
      class="volume-slider"
      min="0"
      max="1"
      step="0.01"
      value="1"
      aria-label="Volume control"
    />
  </div>
    <!-- Track List -->
    <ul class="track-list">
      <?php foreach ($tracks as $idx => $track): 
          $number   = htmlspecialchars($track['number'] ?? ($idx+1));
          $title    = htmlspecialchars($track['title'] ?? "Track " . ($idx+1));
          $file     = htmlspecialchars($track['file'] ?? '');
          $length   = htmlspecialchars($track['length'] ?? '');
      ?>
        <li data-index="<?php echo $idx; ?>">
          <span><?php echo "$number. $title"; ?></span>
          <span><?php echo $length; ?></span>
        </li>
      <?php endforeach; ?>
    </ul>

    <!-- Visualization Canvas -->
    <canvas class="audio-visualizer" width="600" height="100"></canvas>
  </div>
</section>

<!-- 6. Embed albumData into JS so the client-side script can pick it up -->
<script>
  window.albumData = {
    albumFolder: "<?php echo $albumFolderPath; ?>",
    albumTitle: "<?php echo $albumTitle; ?>",
    artist: "<?php echo $artist; ?>",
    year: "<?php echo $year; ?>",
    credits: <?php echo json_encode($credits, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>,
    tracks: <?php echo json_encode($tracks, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT); ?>
  };
</script>
<!-- End of album-player.php -->
