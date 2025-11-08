document.addEventListener("DOMContentLoaded", function () {
  const data = window.albumData;
  if (!data) {
    console.error("albumData is not defined.");
    return;
  }

  // 1) AudioContext + analyser
  const AudioContext = window.AudioContext || window.webkitAudioContext;
  const audioCtx = new AudioContext();
  const analyser = audioCtx.createAnalyser();
  analyser.fftSize = 256;

  const playerContainer = document.querySelector(".audio-player-container");
  const playerContent = playerContainer.querySelector(".player-content");
  const audioEl = playerContainer.querySelector(".audio-element");
  const volumeSlider = playerContainer.querySelector(".volume-slider");
  const playPauseBtn = playerContainer.querySelector(".play-pause-btn");
  const prevBtn = playerContainer.querySelector(".prev-btn");
  const nextBtn = playerContainer.querySelector(".next-btn");
  const repeatBtn = playerContainer.querySelector(".repeat-btn");
  const trackInfoEl = playerContainer.querySelector(".track-info");
  const trackListEl = playerContainer.querySelector(".track-list");
  const toggleBtn = playerContainer.querySelector(".toggle-list-btn");

  // Progress & Time
  const progressBar = playerContainer.querySelector(".progress-bar");
  const timeElapsedEl = playerContainer.querySelector(".time-elapsed");
  const timeDurationEl = playerContainer.querySelector(".time-duration");

  // Extras (lyrics/video)
  const extrasTabs = playerContainer.querySelectorAll(".tab-btn");
  const lyricsView = playerContainer.querySelector("#lyrics-view");
  const videoView = playerContainer.querySelector("#video-view");
  const lyricsContainer = lyricsView
    ? lyricsView.querySelector(".lyrics-text")
    : null;
  const trackVideoEl = videoView
    ? videoView.querySelector(".track-video")
    : null;

  // Connect audio element to analyser
  const sourceNode = audioCtx.createMediaElementSource(audioEl);
  sourceNode.connect(analyser);
  analyser.connect(audioCtx.destination);

  let tracks = data.tracks || [];
  let currentIndex = 0;
  let repeatAlbum = false;

  // Format seconds → "MM:SS"
  function formatTime(sec) {
    const m = Math.floor(sec / 60).toString().padStart(2, "0");
    const s = Math.floor(sec % 60).toString().padStart(2, "0");
    return `${m}:${s}`;
  }

  // Highlight the active <li> in the tracklist
  function highlightActiveTrack() {
    const items = trackListEl.querySelectorAll("li");
    items.forEach((li) => {
      li.classList.toggle("active", Number(li.dataset.index) === currentIndex);
    });
  }

  // Load a track (set audio.src, update track-info, load lyrics/video if any)
  function loadTrack(idx) {
    if (idx < 0 || idx >= tracks.length) return;
    currentIndex = idx;
    const track = tracks[idx];

    // 1) Audio source + track-info
    audioEl.src = data.albumFolder + track.file;
    trackInfoEl.textContent = track.number
      ? `${track.number}. ${track.title}`
      : track.title;
    highlightActiveTrack();

    // 2) Lyrics (if available)
    if (lyricsContainer) {
      if (track.lyrics) {
        lyricsContainer.textContent = track.lyrics;
      } else {
        lyricsContainer.textContent = "No lyrics available for this track.";
      }
    }

    // 3) Video (if available)
    if (trackVideoEl) {
      if (track.videoFile) {
        trackVideoEl.style.display = "";
        trackVideoEl.src = data.albumFolder + track.videoFile;
        if (track.videoPoster) {
          trackVideoEl.poster = data.albumFolder + track.videoPoster;
        } else {
          trackVideoEl.removeAttribute("poster");
        }
        trackVideoEl.load();
      } else {
        trackVideoEl.pause();
        trackVideoEl.src = "";
        trackVideoEl.style.display = "none";
      }
    }
  }

  // Play + collapse helper (call this once per user action that starts playback)
  function playCurrentTrack() {
    if (audioCtx.state === "suspended") {
      audioCtx.resume();
    }
    audioEl.play();
    playPauseBtn.textContent = "⏸️";

    // Automatically collapse the tracklist
    playerContent.classList.add("collapsed");
  }

  // When metadata loads, set slider max & duration
  audioEl.addEventListener("loadedmetadata", () => {
    if (!isNaN(audioEl.duration)) {
      progressBar.max = audioEl.duration;
      timeDurationEl.textContent = formatTime(audioEl.duration);
    }
  });

  // As track plays, update slider & elapsed time
  audioEl.addEventListener("timeupdate", () => {
    if (!isNaN(audioEl.currentTime)) {
      progressBar.value = audioEl.currentTime;
      timeElapsedEl.textContent = formatTime(audioEl.currentTime);
    }
    // If you want video sync, you could do:
    // if (trackVideoEl && !trackVideoEl.paused) {
    //   trackVideoEl.currentTime = audioEl.currentTime;
    // }
  });

  // While dragging the progress bar, only preview time
  progressBar.addEventListener("input", () => {
    const t = Number(progressBar.value);
    timeElapsedEl.textContent = formatTime(t);
  });

  // On “change” (release), seek and resume if it was playing
  progressBar.addEventListener("change", () => {
    const wasPlaying = !audioEl.paused;
    audioEl.currentTime = Number(progressBar.value);
    timeElapsedEl.textContent = formatTime(audioEl.currentTime);
    if (wasPlaying) {
      if (audioCtx.state === "suspended") audioCtx.resume();
      audioEl.play();
      playPauseBtn.textContent = "⏸️";
      playerContent.classList.add("collapsed");
    }
  });

  // Play/Pause toggling
  playPauseBtn.addEventListener("click", () => {
    if (audioCtx.state === "suspended") audioCtx.resume();

    if (audioEl.paused) {
      playCurrentTrack();
    } else {
      audioEl.pause();
      playPauseBtn.textContent = "▶️";
      // Optionally re‐expand on pause:
      // playerContent.classList.remove("collapsed");
    }
  });

  // Next / Prev buttons
  nextBtn.addEventListener("click", () => {
    let nextIdx = currentIndex + 1;
    if (nextIdx < tracks.length) {
      loadTrack(nextIdx);
      playCurrentTrack();
    } else if (repeatAlbum) {
      loadTrack(0);
      playCurrentTrack();
    }
  });
  prevBtn.addEventListener("click", () => {
    let prevIdx = currentIndex - 1;
    if (prevIdx >= 0) {
      loadTrack(prevIdx);
      playCurrentTrack();
    } else if (repeatAlbum) {
      loadTrack(tracks.length - 1);
      playCurrentTrack();
    }
  });

  // Repeat toggle
  repeatBtn.addEventListener("click", () => {
    repeatAlbum = !repeatAlbum;
    repeatBtn.classList.toggle("active", repeatAlbum);
  });

  // Auto‐advance on track end
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
      // Optionally re‐expand tracklist:
      // playerContent.classList.remove("collapsed");
    }
  });

  // Clicking on a track from the list
  trackListEl.addEventListener("click", (e) => {
    const li = e.target.closest("li");
    if (!li) return;
    const idx = Number(li.dataset.index);
    if (idx !== currentIndex) {
      loadTrack(idx);
    }
    if (audioCtx.state === "suspended") audioCtx.resume();
    playCurrentTrack();
  });

  // Toggle‐tracklist button: collapse ↔ expand
  if (toggleBtn) {
    toggleBtn.addEventListener("click", () => {
      playerContent.classList.toggle("collapsed");
      // The CSS ::before of .toggle-list-btn will switch between “✕” and “☰”
    });
  }

  // Extras Tabs (Lyrics / Video)
  if (extrasTabs.length && lyricsView && videoView) {
    extrasTabs.forEach((btn) => {
      btn.addEventListener("click", () => {
        extrasTabs.forEach((b) => b.classList.remove("active"));
        btn.classList.add("active");
        const target = btn.getAttribute("data-target");
        playerContainer
          .querySelectorAll(".tab-content")
          .forEach((panel) => {
            panel.classList.toggle("active", panel.id === target);
          });
      });
    });
  }

  // Initialize first track
  if (tracks.length > 0) {
    loadTrack(0);
  } else {
    trackInfoEl.textContent = "No tracks available.";
  }

  // Visualization (unchanged)
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

  // Volume Slider (unchanged)
  audioEl.volume = Number(volumeSlider.value);
  volumeSlider.addEventListener("input", (e) => {
    audioEl.volume = Number(e.target.value);
  });
});