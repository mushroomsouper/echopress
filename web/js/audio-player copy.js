document.addEventListener("DOMContentLoaded", function () {
  const data = window.albumData;
  if (!data) {
    console.error("albumData is not defined.");
    return;
  }

  // 1. Create AudioContext and analyser so audioCtx exists before any handler uses it.
  const AudioContext = window.AudioContext || window.webkitAudioContext;
  const audioCtx = new AudioContext();
  const analyser = audioCtx.createAnalyser();
  analyser.fftSize = 256;

  const playerContainer = document.querySelector(".audio-player-container");
  const audioEl = playerContainer.querySelector(".audio-element");
  const volumeSlider = playerContainer.querySelector(".volume-slider");
  const playPauseBtn = playerContainer.querySelector(".play-pause-btn");
  const prevBtn = playerContainer.querySelector(".prev-btn");
  const nextBtn = playerContainer.querySelector(".next-btn");
  const repeatBtn = playerContainer.querySelector(".repeat-btn");
  const trackInfoEl = playerContainer.querySelector(".track-info");
  const trackListEl = playerContainer.querySelector(".track-list");

  // Progress & Time
  const progressBar = playerContainer.querySelector(".progress-bar");
  const timeElapsedEl = playerContainer.querySelector(".time-elapsed");
  const timeDurationEl = playerContainer.querySelector(".time-duration");

  // Extras (lyrics/video) – only if present in the DOM
  const extrasTabs = playerContainer.querySelectorAll(".tab-btn");
  const lyricsView = playerContainer.querySelector("#lyrics-view");
  const videoView = playerContainer.querySelector("#video-view");
  const lyricsContainer = lyricsView
    ? lyricsView.querySelector(".lyrics-text")
    : null;
  const trackVideoEl = videoView
    ? videoView.querySelector(".track-video")
    : null;

  // Hook up analyser to the <audio> element
  const sourceNode = audioCtx.createMediaElementSource(audioEl);
  sourceNode.connect(analyser);
  analyser.connect(audioCtx.destination);

  let tracks = data.tracks || [];
  let currentIndex = 0;
  let repeatAlbum = false;

  // Utility: format seconds → "MM:SS"
  function formatTime(sec) {
    const m = Math.floor(sec / 60);
    const s = Math.floor(sec % 60);
    return String(m).padStart(2, "0") + ":" + String(s).padStart(2, "0");
  }
const playerContent = document.querySelector(".player-content");
const expandBtn = playerContainer.querySelector(".expand-btn");

if (expandBtn) {
  expandBtn.addEventListener("click", () => {
    // Remove “collapsed” so full tracklist re-appears as overlay
    playerContent.classList.remove("collapsed");
  });
}

// Whenever we call playCurrentTrack(), ensure we collapse:
function playCurrentTrack() {
  // resume AudioContext if needed
  if (audioCtx.state === "suspended") audioCtx.resume();
  
  audioEl.play(); 
  playPauseBtn.textContent = "⏸️";

  // Collapse tracklist into mini-player
  playerContent.classList.add("collapsed");
}
  // Load a track by index, update audio src, metadata, highlight, lyrics/video
  function loadTrack(idx) {
    if (idx < 0 || idx >= tracks.length) return;
    currentIndex = idx;
    const track = tracks[idx];

    // 1) Set audio source and metadata display
    audioEl.src = data.albumFolder + track.file;
    trackInfoEl.textContent = track.number
      ? `${track.number}. ${track.title}`
      : track.title;
    highlightActiveTrack();

    // 2) Handle lyrics (if the markup exists and the track has a "lyrics" property)
    if (lyricsContainer) {
      if (track.lyrics) {
        lyricsContainer.textContent = track.lyrics;
      } else {
        lyricsContainer.textContent = "No lyrics available for this track.";
      }
    }

    // 3) Handle video (if the markup exists)
    if (trackVideoEl) {
      if (track.videoFile) {
        // Show and load the video
        trackVideoEl.style.display = "";
        trackVideoEl.src = data.albumFolder + track.videoFile;
        // Optionally set a poster if provided
        if (track.videoPoster) {
          trackVideoEl.poster = data.albumFolder + track.videoPoster;
        } else {
          trackVideoEl.removeAttribute("poster");
        }
        trackVideoEl.load();
      } else {
        // Hide the video element if no video for this track
        trackVideoEl.pause();
        trackVideoEl.src = "";
        trackVideoEl.style.display = "none";
      }
    }
  }

  // Highlight the currently active <li> in the tracklist
  function highlightActiveTrack() {
    if (!trackListEl) return;
    const items = trackListEl.querySelectorAll("li");
    items.forEach((li) => {
      li.classList.toggle("active", Number(li.dataset.index) === currentIndex);
    });
  }

  // When metadata loads, set slider max & duration display
  audioEl.addEventListener("loadedmetadata", () => {
    if (!isNaN(audioEl.duration)) {
      progressBar.max = audioEl.duration;
      timeDurationEl.textContent = formatTime(audioEl.duration);
    }
  });

  // As the track plays, update slider position & elapsed time
  audioEl.addEventListener("timeupdate", () => {
    if (!isNaN(audioEl.currentTime)) {
      progressBar.value = audioEl.currentTime;
      timeElapsedEl.textContent = formatTime(audioEl.currentTime);
    }
    // If video exists and is visible, you could sync it here (optional)
    // Example: if (trackVideoEl && !trackVideoEl.paused) trackVideoEl.currentTime = audioEl.currentTime;
  });

  // While dragging, only preview time (do NOT start playback)
  progressBar.addEventListener("input", () => {
    const previewTime = Number(progressBar.value);
    timeElapsedEl.textContent = formatTime(previewTime);
  });

  // On release (“change” event), seek—and only resume if it was playing already
  progressBar.addEventListener("change", () => {
    const wasPlaying = !audioEl.paused;
    audioEl.currentTime = Number(progressBar.value);

    // Update elapsed‐time display right away
    timeElapsedEl.textContent = formatTime(audioEl.currentTime);

    if (wasPlaying) {
      // Resume AudioContext if needed, then continue playing
      if (audioCtx.state === "suspended") {
        audioCtx.resume();
      }
      playCurrentTrack();
      playPauseBtn.textContent = "⏸️";
      playerContent.classList.add("collapsed");
    }
    // Otherwise, leave it paused and don’t toggle the icon
  });

  // Click-to-seek: same logic as “change”
  progressBar.addEventListener("click", (e) => {
    // Calculate clicked time
    const rect = progressBar.getBoundingClientRect();
    const clickX = e.clientX - rect.left;
    const pct = clickX / rect.width;
    const newTime = pct * audioEl.duration;

    const wasPlaying = !audioEl.paused;
    audioEl.currentTime = newTime;
    progressBar.value = newTime;
    timeElapsedEl.textContent = formatTime(newTime);

    if (wasPlaying) {
      if (audioCtx.state === "suspended") {
        audioCtx.resume();
      }
      playCurrentTrack();
      playPauseBtn.textContent = "⏸️";
    }
    // If it was paused before, leave it paused and leave the ▶️ icon
  });

  // Play / Pause button
  playPauseBtn.addEventListener("click", () => {
    // Resume AudioContext on first real play
    if (audioCtx.state === "suspended") {
      audioCtx.resume();
    }

    if (audioEl.paused) {
      playCurrentTrack();
    } else {
      audioEl.pause();
      playPauseBtn.textContent = "▶️";
    }
  });

  // Next / Prev
  nextBtn.addEventListener("click", () => {
    let nextIdx = currentIndex + 1;
    if (nextIdx < tracks.length) {
      loadTrack(nextIdx);
      playCurrentTrack();
      playPauseBtn.textContent = "⏸️";
    } else if (repeatAlbum) {
      loadTrack(0);
      playCurrentTrack();
      playPauseBtn.textContent = "⏸️";
    }
  });
  prevBtn.addEventListener("click", () => {
    let prevIdx = currentIndex - 1;
    if (prevIdx >= 0) {
      loadTrack(prevIdx);
      playCurrentTrack();
      playPauseBtn.textContent = "⏸️";
    } else if (repeatAlbum) {
      loadTrack(tracks.length - 1);
      playCurrentTrack();
      playPauseBtn.textContent = "⏸️";
    }
  });

  // Repeat toggle
  repeatBtn.addEventListener("click", () => {
    repeatAlbum = !repeatAlbum;
    repeatBtn.classList.toggle("active", repeatAlbum);
  });

  // Auto-advance
  audioEl.addEventListener("ended", () => {
    let nextIdx = currentIndex + 1;
    if (nextIdx < tracks.length) {
      loadTrack(nextIdx);
      playCurrentTrack();
    } else if (repeatAlbum) {
      loadTrack(0);
      playCurrentTrack();
    } else {
      playPauseBtn.textContent = "▶️";
    }
  });

  // Track list click
  trackListEl.addEventListener("click", (e) => {
    const li = e.target.closest("li");
    if (!li) return;
    const idx = Number(li.dataset.index);

    // Load the selected track
    if (idx !== currentIndex) {
      loadTrack(idx);
    }

    // Resume AudioContext if it’s still suspended
    if (audioCtx.state === "suspended") {
      audioCtx.resume();
    }

    // Now play and set the button icon
    playCurrentTrack();
    playPauseBtn.textContent = "⏸️";
  });

  // --- Extras Tabs (Lyrics / Video) ---
  if (extrasTabs.length && lyricsView && videoView) {
    extrasTabs.forEach((btn) => {
      btn.addEventListener("click", () => {
        // Remove "active" from all tabs, then add to clicked one
        extrasTabs.forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");

        // Show/hide content panels based on data-target
        const target = btn.getAttribute("data-target");
        const allPanels = playerContainer.querySelectorAll(".tab-content");
        allPanels.forEach((panel) => {
          panel.classList.toggle("active", panel.id === target);
        });
      });
    });
  }

  // Initialize first track (and extras views if present)
  if (tracks.length > 0) {
    loadTrack(0);
  } else {
    trackInfoEl.textContent = "No tracks available.";
  }

  // --- Visualization Setup (unchanged) ---
  const canvas = playerContainer.querySelector(".audio-visualizer");
  if (canvas) {
    const ctxCanvas = canvas.getContext("2d");
    const bufferLength = analyser.frequencyBinCount;
    const dataArray = new Uint8Array(bufferLength);

    function drawVisualizer() {
      requestAnimationFrame(drawVisualizer);
      analyser.getByteFrequencyData(dataArray);

      ctxCanvas.fillStyle = "#111";
      ctxCanvas.fillRect(0, 0, canvas.width, canvas.height);

      const barWidth = (canvas.width / bufferLength) * 2.5;
      let x = 0;
      for (let i = 0; i < bufferLength; i++) {
        const barHeight = (dataArray[i] / 255) * canvas.height;
        ctxCanvas.fillStyle = "#4caf50";
        ctxCanvas.fillRect(x, canvas.height - barHeight, barWidth, barHeight);
        x += barWidth + 1;
      }
    }

    drawVisualizer();
  }

  // 3.1 Initialize audioEl.volume from slider’s default
  audioEl.volume = Number(volumeSlider.value);

  // 3.2 Listen for volume changes
  volumeSlider.addEventListener("input", (e) => {
    audioEl.volume = Number(e.target.value);
  });
});
