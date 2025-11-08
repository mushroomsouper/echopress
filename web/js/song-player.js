document.addEventListener("DOMContentLoaded", function () {
  const data = window.songData;
  if (!data) {
    console.error("songData is not defined.");
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
  const volumeIcon = playerContainer.querySelector(".volume-icon");
  const volumeContainer = playerContainer.querySelector(".volume-container");
  const playPauseBtn = playerContainer.querySelector(".play-pause-btn");
  const playIcon = playPauseBtn.querySelector("i");
  const prevBtn = playerContainer.querySelector(".prev-btn");
  const nextBtn = playerContainer.querySelector(".next-btn");
  const repeatBtn = playerContainer.querySelector(".repeat-btn");
  const trackInfoEl = playerContainer.querySelector(".track-info");
  const trackInfoTextEl = trackInfoEl
    ? trackInfoEl.querySelector(".info-text")
    : null;
  const trackListEl = playerContainer.querySelector(".track-list");
  const toggleBtn = playerContainer.querySelector(".toggle-list-btn");
  const toggleIcon = toggleBtn ? toggleBtn.querySelector("i") : null;
  const bgVideoEl = playerContainer.querySelector(".bg-video");
  const mobileQuery = window.matchMedia("(max-width: 600px)");

  function updateMediaSessionMetadata(track) {
    if (!('mediaSession' in navigator)) return;
    const baseUrl = window.location.origin;
    const artworkPath = (track.albumFolder || '') + (track.albumCover || '');
    const artworkSrc = artworkPath.startsWith('http') ? artworkPath : baseUrl + artworkPath;
    const meta = {
      title: track.title || '',
      artist: track.artist || data.artist || 'EchoPress Artist',
      album: track.albumTitle || '',
      artwork: artworkSrc ? [{ src: artworkSrc, sizes: '512x512', type: 'image/jpeg' }] : []
    };
    navigator.mediaSession.metadata = new MediaMetadata(meta);
  }

  function updateMediaSessionPosition() {
    if (
      'mediaSession' in navigator &&
      typeof navigator.mediaSession.setPositionState === 'function'
    ) {
      try {
        navigator.mediaSession.setPositionState({
          duration: isNaN(audioEl.duration) ? 0 : audioEl.duration,
          playbackRate: audioEl.playbackRate || 1,
          position: isNaN(audioEl.currentTime) ? 0 : audioEl.currentTime
        });
      } catch (err) {
        // ignore errors for invalid state
      }
    }
  }

  if ('mediaSession' in navigator) {
    navigator.mediaSession.setActionHandler('previoustrack', () => prevBtn.click());
    navigator.mediaSession.setActionHandler('nexttrack', () => nextBtn.click());
    navigator.mediaSession.setActionHandler('play', () => playCurrentTrack());
    navigator.mediaSession.setActionHandler('pause', () => audioEl.pause());
  }

  function enableMarquee(el) {
    if (!el) return;
    const diff = el.scrollWidth - el.clientWidth;
    if (diff <= 0) return;
    el.style.setProperty("--scroll-distance", `${diff}px`);
    const baseSpeed = 25; // px per second
    const duration = Math.max(2, diff / baseSpeed);
    el.style.setProperty("--duration", `${duration}s`);
    el.classList.add("marquee");
  }

  function disableMarquee(el) {
    if (!el) return;
    el.classList.remove("marquee");
    el.style.removeProperty("--scroll-distance");
    el.style.removeProperty("--duration");
  }

  function refreshMarquee(el) {
    if (!el) return;
    disableMarquee(el);
    enableMarquee(el);
  }

  function refreshAllMarquees() {
    if (trackInfoEl) {
      if (trackInfoEl.scrollWidth > trackInfoEl.clientWidth) {
        enableMarquee(trackInfoEl);
      } else {
        disableMarquee(trackInfoEl);
      }
    }
    trackListEl.querySelectorAll("li").forEach((li) => {
      const titleEl = li.querySelector(".track-title");
      if (!titleEl) return;
      const isActive = Number(li.dataset.index) === currentIndex;
      const isHovered = li.matches(":hover");
      if ((isActive || isHovered) && titleEl.scrollWidth > titleEl.clientWidth) {
        enableMarquee(titleEl);
      } else if (!isActive && !isHovered) {
        disableMarquee(titleEl);
      } else {
        disableMarquee(titleEl);
      }
    });
  }

  // Body element to signal playback state
  const bodyEl = document.body;

  function updateBodyState() {
    if (audioEl.paused) {
      bodyEl.classList.add("paused");
      bodyEl.classList.remove("playing");
    } else {
      bodyEl.classList.add("playing");
      bodyEl.classList.remove("paused");
    }
  }

  // Initial state
  updateBodyState();

  // Ensure background video starts paused
  if (bgVideoEl) {
    bgVideoEl.pause();
  }

  // Progress & Time
  const progressBar = playerContainer.querySelector(".progress-bar");
  const volumeBar = playerContainer.querySelector(".volume-slider");
  const timeElapsedEl = playerContainer.querySelector(".time-elapsed");
  const timeDurationEl = playerContainer.querySelector(".time-duration");

  let isScrubbing = false;

  function startScrubbing(event) {
    isScrubbing = true;
    if (
      event &&
      typeof event.pointerId === "number" &&
      typeof progressBar.setPointerCapture === "function"
    ) {
      try {
        progressBar.setPointerCapture(event.pointerId);
      } catch (err) {
        // Ignore pointer capture errors
      }
    }
  }

  function stopScrubbing(event) {
    if (
      event &&
      typeof event.pointerId === "number" &&
      typeof progressBar.releasePointerCapture === "function"
    ) {
      try {
        progressBar.releasePointerCapture(event.pointerId);
      } catch (err) {
        // Ignore pointer capture errors
      }
    }
    isScrubbing = false;
  }

  if (window.PointerEvent) {
    const handlePointerEnd = (event) => {
      stopScrubbing(event);
    };
    progressBar.addEventListener("pointerdown", startScrubbing);
    progressBar.addEventListener("pointerup", handlePointerEnd);
    progressBar.addEventListener("pointercancel", handlePointerEnd);
    window.addEventListener("pointerup", handlePointerEnd);
    window.addEventListener("pointercancel", handlePointerEnd);
  } else {
    const legacyScrubStart = () => {
      isScrubbing = true;
    };
    const legacyScrubEnd = () => {
      isScrubbing = false;
    };
    progressBar.addEventListener("mousedown", legacyScrubStart);
    document.addEventListener("mouseup", legacyScrubEnd);
    progressBar.addEventListener(
      "touchstart",
      legacyScrubStart,
      { passive: true }
    );
    document.addEventListener("touchend", legacyScrubEnd);
    document.addEventListener("touchcancel", legacyScrubEnd);
  }

  function updateProgressFill() {
    const max = Number(progressBar.max) || 0;
    const val = Number(progressBar.value);
    const percent = max ? (val / max) * 100 : 0;
    progressBar.style.backgroundSize = `${percent}% 100%`;
    progressBar.style.setProperty('--fill-percentage', `${percent}%`);


  }

  function updateVolumeFill() {
    const max = Number(volumeBar.max) || 0;
    const val = Number(volumeBar.value);
    const percent = max ? (val / max) * 100 : 0;
    if (mobileQuery.matches) {
      volumeBar.style.backgroundSize = `100% ${percent}%`;
    } else {
      volumeBar.style.backgroundSize = `${percent}% 100%`;
    }
    volumeBar.style.setProperty('--fill-percentage', `${percent}%`);


  }
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

  // Element used to preload the next track for seamless playback
  const preloadAudio = new Audio();
  preloadAudio.preload = "auto";

  let tracks = data.tracks || [];
  let currentIndex = 0;
  let repeatAlbum = true;
  // Reflect default repeat state in the UI
  if (repeatBtn) repeatBtn.classList.add("active");

  // ----- Optional analytics tracking (Matomo-compatible) -----
  function trackSongEvent(action, value) {
    if (!window._paq || typeof window._paq.push !== 'function') {
      return;
    }

    const track = tracks[currentIndex] || {};
    const title = track.title || `Track ${currentIndex + 1}`;
    const label = track.albumTitle
      ? `${track.albumTitle} - ${title}`
      : title;
    const val = Number.isFinite(value) ? Math.round(value) : undefined;
    window._paq.push(['trackEvent', 'Audio', action, label, val]);
  }

  function trackDownloadEvent(format) {
    if (!window._paq || typeof window._paq.push !== 'function') {
      return;
    }
    const track = tracks[currentIndex] || {};
    const album = track.albumTitle || 'Unknown Album';
    window._paq.push(['trackEvent', 'Download', format, album]);
  }

  function parseLengthToSeconds(str) {
    if (!str) return NaN;
    const parts = str.split(':').map(Number);
    if (parts.length === 2) return parts[0] * 60 + parts[1];
    if (parts.length === 3) return parts[0] * 3600 + parts[1] * 60 + parts[2];
    return Number(str) || NaN;
  }

  // Format seconds → "MM:SS"
  function formatTime(sec) {
    const m = Math.floor(sec / 60).toString().padStart(2, "0");
    const s = Math.floor(sec % 60).toString().padStart(2, "0");
    return `${m}:${s}`;
  }

  // Highlight the active <li> in the tracklist
  let oscraf = null;
  let activeCanvas = null;

  function drawOscilloscope() {
    if (!activeCanvas) return;
    const ctx = activeCanvas.getContext("2d");
    const bufferLen = analyser.fftSize;
    const dataArr = new Uint8Array(bufferLen);

    function draw() {
      oscraf = requestAnimationFrame(draw);
      analyser.getByteTimeDomainData(dataArr);
      ctx.clearRect(0, 0, activeCanvas.width, activeCanvas.height);
      ctx.lineWidth = 2;
      const color = getComputedStyle(activeCanvas.parentElement).color;
      ctx.strokeStyle = color;
      ctx.beginPath();
      const slice = activeCanvas.width / bufferLen;
      let x = 0;
      for (let i = 0; i < bufferLen; i++) {
        const v = dataArr[i] / 128.0;
        const y = (v * activeCanvas.height) / 2;
        if (i === 0) ctx.moveTo(x, y);
        else ctx.lineTo(x, y);
        x += slice;
      }
      ctx.stroke();
    }
    draw();
  }

  function stopOscilloscope() {
    if (oscraf) cancelAnimationFrame(oscraf);
    oscraf = null;
    if (activeCanvas) {
      const ctx = activeCanvas.getContext("2d");
      ctx.clearRect(0, 0, activeCanvas.width, activeCanvas.height);
    }
    activeCanvas = null;
  }

  function highlightActiveTrack() {
    const items = trackListEl.querySelectorAll("li");
    items.forEach((li) => {
      const isActive = Number(li.dataset.index) === currentIndex;
      li.classList.toggle("active", isActive);
      const canvas = li.querySelector(".track-waveform");
      const titleEl = li.querySelector(".track-title");
      if (isActive) {
        if (canvas) {
          if (canvas) {
            activeCanvas = canvas;
            activeCanvas.width = li.clientWidth;
            activeCanvas.height = li.clientHeight;
            drawOscilloscope();
          } else {
            activeCanvas = null;
          }
        } else {
          activeCanvas = null;
        }
        if (titleEl) refreshMarquee(titleEl);
      } else if (canvas) {
        if (canvas === activeCanvas) stopOscilloscope();
        const ctx = canvas.getContext("2d");
        ctx.clearRect(0, 0, canvas.width, canvas.height);
        if (titleEl && !li.matches(":hover")) disableMarquee(titleEl);
      }
    });
  }

  // Load a track (set audio.src, update track-info, load lyrics/video if any)
  function loadTrack(idx) {
    if (idx < 0 || idx >= tracks.length) return;
    currentIndex = idx;
    const track = tracks[idx];
    const durSec = track.length ? parseLengthToSeconds(track.length) : undefined;
    trackSongEvent('Load', durSec);
    lastProgressMark = 0;

    // 1) Audio source + track-info
    audioEl.src = track.albumFolder + track.file;
    if (trackInfoTextEl) {
      trackInfoTextEl.textContent = track.number
        ? `${track.number}. ${track.title}`
        : track.title;
    }

    refreshMarquee(trackInfoEl);
    highlightActiveTrack();
    progressBar.value = 0;
    updateProgressFill();
    updateVolumeFill();
    updateMediaSessionMetadata(track);
    updateMediaSessionPosition();
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
        trackVideoEl.src = track.albumFolder + track.videoFile;
        if (track.videoPoster) {
          trackVideoEl.poster = track.albumFolder + track.videoPoster;
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

    // Preload the upcoming track for gapless transition
    preloadNextTrack();
  }

  function preloadNextTrack() {
    let nextIdx = currentIndex + 1;
    if (nextIdx >= tracks.length) {
      if (repeatAlbum) nextIdx = 0; else return;
    }
    preloadAudio.src = tracks[nextIdx].albumFolder + tracks[nextIdx].file;
    preloadAudio.load();
  }

  // Play + collapse helper (call this once per user action that starts playback)
  function playCurrentTrack() {
    if (audioCtx.state === "suspended") {
      audioCtx.resume();
    }
    audioEl.play();
    if (bgVideoEl && bgVideoEl.paused) {
      bgVideoEl.play().catch(() => { });
    }
    if (playIcon) { playIcon.classList.remove("fa-play"); playIcon.classList.add("fa-pause"); }
    updateBodyState();
    updateMediaSessionPosition();

    // Automatically collapse the tracklist (disabled for now)
    // playerContent.classList.add("collapsed");
  }

  // When metadata loads, set slider max & duration
  audioEl.addEventListener("loadedmetadata", () => {
    if (!isNaN(audioEl.duration)) {
      progressBar.max = audioEl.duration;
      timeDurationEl.textContent = formatTime(audioEl.duration);
      updateProgressFill();
      trackSongEvent('Loaded', audioEl.duration);
      updateMediaSessionPosition();
    }
  });

  // As track plays, update slider & elapsed time
  let lastProgressMark = 0;
  audioEl.addEventListener("timeupdate", () => {
    if (!isNaN(audioEl.currentTime)) {
      if (!isScrubbing) {
        progressBar.value = audioEl.currentTime;
        timeElapsedEl.textContent = formatTime(audioEl.currentTime);
        updateProgressFill();
      }
      updateMediaSessionPosition();
      if (audioEl.currentTime - lastProgressMark >= 30) {
        lastProgressMark = audioEl.currentTime;
        trackSongEvent('Progress', audioEl.currentTime);
      }
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
    updateProgressFill();
  });

  // On “change” (release), seek and resume if it was playing
  progressBar.addEventListener("change", () => {
    const wasPlaying = !audioEl.paused;
    isScrubbing = false;
    audioEl.currentTime = Number(progressBar.value);
    timeElapsedEl.textContent = formatTime(audioEl.currentTime);
    updateProgressFill();
    updateMediaSessionPosition();
    trackSongEvent('Seek', audioEl.currentTime);
    if (wasPlaying) {
      if (audioCtx.state === "suspended") audioCtx.resume();
      audioEl.play();
      if (playIcon) { playIcon.classList.remove("fa-play"); playIcon.classList.add("fa-pause"); }
      // playerContent.classList.add("collapsed"); // disabled auto collapse
    }
  });

  // Update body classes on playback state changes
  audioEl.addEventListener("play", () => {
    updateBodyState();
    updateMediaSessionPosition();
    trackSongEvent('Play', audioEl.currentTime);
  });
  audioEl.addEventListener("pause", () => {
    updateBodyState();
    updateMediaSessionPosition();
    trackSongEvent('Pause', audioEl.currentTime);
  });
  audioEl.addEventListener("ended", () => {
    updateBodyState();
    updateMediaSessionPosition();
    trackSongEvent('Progress', audioEl.duration);
    trackSongEvent('Ended', audioEl.duration);
  });

  // Play/Pause toggling
  playPauseBtn.addEventListener("click", () => {
    if (audioCtx.state === "suspended") audioCtx.resume();

    if (audioEl.paused) {
      playCurrentTrack();
    } else {
      audioEl.pause();
      if (bgVideoEl && !bgVideoEl.paused) {
        bgVideoEl.pause();
      }
      if (playIcon) { playIcon.classList.remove("fa-pause"); playIcon.classList.add("fa-play"); }
      updateBodyState();
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

  // Repeat toggle (if repeat button exists)
  if (repeatBtn) {
    repeatBtn.addEventListener("click", () => {
      repeatAlbum = !repeatAlbum;
      repeatBtn.classList.toggle("active", repeatAlbum);
      preloadNextTrack();
    });
  }

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
      if (playIcon) { playIcon.classList.remove("fa-pause"); playIcon.classList.add("fa-play"); }
      if (bgVideoEl && !bgVideoEl.paused) {
        bgVideoEl.pause();
      }
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

  trackListEl.querySelectorAll("li").forEach((li) => {
    const titleEl = li.querySelector(".track-title");
    if (!titleEl) return;
    li.addEventListener("mouseenter", () => {
      refreshMarquee(titleEl);
    });
    li.addEventListener("mouseleave", () => {
      if (Number(li.dataset.index) !== currentIndex) {
        disableMarquee(titleEl);
      } else {
        refreshMarquee(titleEl);
      }
    });
  });

  // Toggle‐tracklist button: collapse ↔ expand
  if (toggleBtn) {
    toggleBtn.addEventListener("click", () => {
      const collapsed = playerContent.classList.toggle("collapsed");
      if (toggleIcon) {
        toggleIcon.classList.toggle("fa-list", collapsed);
        toggleIcon.classList.toggle("fa-times", !collapsed);
      }
      toggleBtn.title = collapsed ? "Show Tracklist" : "Hide Tracklist";
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
    if (trackInfoTextEl) {
      trackInfoTextEl.textContent = "No tracks available.";
      refreshMarquee(trackInfoTextEl);
    } else if (trackInfoEl) {
      trackInfoEl.textContent = "No tracks available.";
      refreshMarquee(trackInfoEl);
    }
  }


  refreshAllMarquees();
  window.addEventListener("resize", refreshAllMarquees);


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
  // Volume control + mute toggle
  let lastVolume = Number(volumeSlider.value);
  audioEl.volume = lastVolume;
  let sliderVisible = false;
  function showSlider() {
    volumeContainer.classList.add("show-slider");
    sliderVisible = true;
  }
  function hideSlider() {
    volumeContainer.classList.remove("show-slider");
    sliderVisible = false;
  }
  volumeSlider.addEventListener("input", (e) => {
    const v = Number(e.target.value);
    audioEl.volume = v;
    audioEl.muted = v === 0;
    if (v === 0) {
      volumeIcon.classList.remove("fa-volume-up");
      volumeIcon.classList.add("fa-volume-mute");
    } else {
      volumeIcon.classList.remove("fa-volume-mute");
      volumeIcon.classList.add("fa-volume-up");
      lastVolume = v;
    }
    updateVolumeFill();
  });
  if (volumeIcon) {
    volumeIcon.addEventListener("click", (e) => {
      if (mobileQuery.matches && !sliderVisible) {
        e.stopPropagation();
        showSlider();
        return;
      }
      if (audioEl.muted || audioEl.volume === 0) {
        audioEl.muted = false;
        audioEl.volume = lastVolume || 1;
        volumeSlider.value = audioEl.volume;
        updateProgressFill();
        volumeIcon.classList.remove("fa-volume-mute");
        volumeIcon.classList.add("fa-volume-up");
      } else {
        audioEl.muted = true;
        lastVolume = audioEl.volume;
        audioEl.volume = 0;
        volumeSlider.value = 0;
        updateProgressFill();
        volumeIcon.classList.remove("fa-volume-up");
        volumeIcon.classList.add("fa-volume-mute");
      }
      updateVolumeFill();
    });
  }

  // Always listen for clicks; only act if we're currently in "mobile" (<600px)
  document.addEventListener("click", (ev) => {
    if (
      mobileQuery.matches &&    // only on small screens
      sliderVisible &&          // only when the slider is open
      !volumeContainer.contains(ev.target)  // and click occurred outside it
    ) {
      hideSlider();
    }
  });


  // -----  Cover Grab/Flip Logic  -----
  // ----- Cover Grab + Flip Logic -----

  // ----- Cover Grab + Directional Flip Logic -----
  // ----- Cover Grab + Directional Flip Logic -----
  // ----- Cover Grab + Directional Flip Logic -----
  // ----- Cover Grab + Directional Flip Logic -----

  // ----- Cover Grab + Directional Flip Logic -----
  // ----- Cover Grab + Directional Flip Logic -----

  // ----- Cover Grab + Directional Flip Logic -----
  // ----- Cover Grab + Directional Flip Logic -----

  // ----- Cover Grab + Directional Flip Logic with isFlipping Flag -----
  // ----- Cover Grab + Directional Flip Logic -----

  // ----- Cover Grab + Directional Flip Logic -----
  // ----- Cover Grab + Directional Flip Logic -----
  // ----- Cover Grab + Directional Flip Logic (RelY‐Based) -----
  // ----- Cover Grab + Directional Flip Logic (Current‐Y → Target‐Y) -----
  // ----- Cover Grab + Directional Flip Logic (Corrected) -----
  // ----- Cover Grab + Directional Flip Logic (Final) -----
  // ----- Cover Grab + Directional Flip Logic (Fixed for “left = ↑degrees”) -----
  // ----- COVER GRAB + DIRECTIONAL FLIP LOGIC (FINAL) -----
  // ----- COVER GRAB + DIRECTIONAL FLIP LOGIC (JS‐ANIMATED) -----
  // -----------------------------
  //  Cover Grab + Directional Flip Logic
  // -----------------------------
  // ----- Card Flip Logic (Corrected) -----
  // ----- Card Flip Logic with Continuous Rotation (No 360° Reset) -----
  // ----- Card Flip Logic with Continuous Rotation, Corrected Drag Direction, and Subtle Hover Tilt -----
  // ----- Card Flip Logic with Infinite Rotation (Positive & Negative) and Improved Hover Tilt -----
  // ----- Card Flip Logic with Infinite Rotation (No Hover Tilt) -----
  // ----- Card Flip Logic with Infinite Rotation and Fixed Tilt on Interaction -----
  // ----- Card Flip Logic with Infinite Rotation, Fixed Tilt on Interaction (Corrected) -----
  // ----- Updated Cover Grab + Flip Logic (with Consistent Tilt Through Flip) -----
  // ----- COVER GRAB + FLIP LOGIC (with Hover‐Exit Debounce) -----
  // ----- COVER GRAB + FLIP LOGIC (Mouse + Touch) -----
  const coverInner = document.querySelector(".cover-inner");
  const flipButton = document.querySelector(".flip-button");
  if (!coverInner) return; // nothing to do if there’s no cover

  // 1) State variables for dragging + flipping
  let isDragging = false;
  let startX = 0;
  let startY = 0;
  let pointerInside = false;

  // baseAngle: accumulates in degrees (..., -360, -180, 0, 180, 360, 540, …)
  let baseAngle = 0;

  // When dragging begins, record the face’s rotation without tilt
  let startAngleY_noTilt = 0;
  let startAngleX_noTilt = 0;

  // Fixed “tilt offset” when interacting (hover or dragging)
  const fixedTiltY = 10;  // degrees to tilt toward viewer on front face
  const fixedTiltX = 3;   // degrees to tilt downward

  // Drag sensitivity (degrees of rotation per pixel)
  const sensitivityY = 0.3;
  const sensitivityX = 0.2;

  // Flip threshold: ±60° relative to the current face
  const flipThreshold = 60;

  // Transition presets
  const TRANSITION_HOVER = "transform 0.2s ease";
  const TRANSITION_FLIP = "transform 0.5s ease";

  // Debounce timer for hover‐exit checks
  let hoverDebounceTimer = null;

  // 2) Utility helpers
  function clamp(val, min, max) {
    return Math.max(min, Math.min(max, val));
  }

  // Compute relY ∈ [–180, +180), where 0 means “exactly at baseAngle”
  function getRelativeY(newY, base) {
    let rawDelta = newY - base;
    rawDelta = ((rawDelta % 360) + 540) % 360 - 180;
    return rawDelta;
  }

  // Apply a rotateY/rotateX transform directly
  function applyTransform(yDeg, xDeg) {
    coverInner.style.transform = `rotateY(${yDeg}deg) rotateX(${xDeg}deg)`;
  }

  // Snap back to exactly baseAngle (no tilt)
  function snapToBase() {
    applyTransform(baseAngle, 0);
  }

  // Determine “tilted” Y depending on front vs. back face
  function tiltY_forInteraction(faceAngle) {
    return (Math.abs(faceAngle % 360) === 180)
      ? faceAngle - fixedTiltY
      : faceAngle + fixedTiltY;
  }

  // Determine “tilted” X depending on front vs. back face
  function tiltX_forInteraction() {
    return (Math.abs(baseAngle % 360) === 180) ? -fixedTiltX : fixedTiltX;
  }

  // Schedule a quick check to see if the mouse is still over coverInner.
  // If not (and not dragging), snap back to flat.
  function scheduleHoverCheck() {
    if (hoverDebounceTimer) clearTimeout(hoverDebounceTimer);
    hoverDebounceTimer = setTimeout(() => {
      if (!coverInner.matches(":hover") && !isDragging) {
        snapToBase();
      }
    }, 100);
  }

  // 3) Flip helper (unified jump→animate sequence)
  // Now takes both Y and X at jump (including tilt)
  function doFlip(jumpY, jumpX, finalAngle, isNowBack) {
    // 1) Jump instantly to jumpY/jumpX (no transition)
    coverInner.style.transition = "none";
    applyTransform(jumpY, jumpX);

    // 2) Update baseAngle & CSS class
    baseAngle = finalAngle;
    if (isNowBack) {
      coverInner.classList.add("flipped");
    } else {
      coverInner.classList.remove("flipped");
    }

    // 3) In next frame, animate to tilted finalAngle
    requestAnimationFrame(() => {
      coverInner.style.transition = TRANSITION_FLIP;
      const yTilt = tiltY_forInteraction(baseAngle);
      const xTilt = tiltX_forInteraction();
      applyTransform(yTilt, xTilt);

      // After the flip animation starts, schedule a hover‐exit check
      scheduleHoverCheck();
    });
  }

  // ---------------------------------------------
  // 4) Hover enter: apply fixed tilt relative to baseAngle (mouse only)
  // ---------------------------------------------
  coverInner.addEventListener("mouseenter", () => {
    pointerInside = true;
    if (isDragging) return;

    coverInner.style.transition = TRANSITION_HOVER;
    const yTilt = tiltY_forInteraction(baseAngle);
    const xTilt = tiltX_forInteraction();
    applyTransform(yTilt, xTilt);
  });

  // ---------------------------------------------
  // 5) Hover move: maintain the same fixed tilt (mouse only)
  // ---------------------------------------------
  coverInner.addEventListener("mousemove", () => {
    if (isDragging) return;
    coverInner.style.transition = TRANSITION_HOVER;
    const yTilt = tiltY_forInteraction(baseAngle);
    const xTilt = tiltX_forInteraction();
    applyTransform(yTilt, xTilt);
  });

  // ---------------------------------------------
  // 6) Hover leave: snap back to flat baseAngle (mouse only)
  // ---------------------------------------------
  coverInner.addEventListener("mouseleave", () => {
    pointerInside = false;
    if (isDragging) return;

    coverInner.style.transition = TRANSITION_HOVER;
    snapToBase();
  });

  // ---------------------------------------------
  // 7) Mousedown: begin dragging (mouse only)
  // ---------------------------------------------
  function onPointerDown(clientX, clientY) {
    isDragging = true;
    startX = clientX;
    startY = clientY;

    // Determine the current on‐screen rotation (which includes tilt)
    const inline = coverInner.style.transform || "";
    let currY = baseAngle, currX = 0;

    const matchY = inline.match(/rotateY\(([-\d.]+)deg\)/);
    if (matchY) currY = parseFloat(matchY[1]);

    const matchX = inline.match(/rotateX\(([-\d.]+)deg\)/);
    if (matchX) currX = parseFloat(matchX[1]);

    // Subtract fixed tilt to get “no‐tilt” starting angles
    const tiltY = (Math.abs(baseAngle % 360) === 180) ? -fixedTiltY : fixedTiltY;
    const tiltX = (Math.abs(baseAngle % 360) === 180) ? -fixedTiltX : fixedTiltX;
    startAngleY_noTilt = currY - tiltY;
    startAngleX_noTilt = currX - tiltX;

    coverInner.style.transition = "none";
    coverInner.classList.add("dragging");
  }

  coverInner.addEventListener("mousedown", (e) => {
    e.preventDefault();
    onPointerDown(e.clientX, e.clientY);
  });

  // ---------------------------------------------
  // 8) Mousemove while dragging: live preview + flip check
  // ---------------------------------------------
  function onPointerMove(clientX, clientY) {
    if (!isDragging) return;

    const dx = clientX - startX;
    const dy = clientY - startY;

    // Compute preview WITHOUT tilt
    const previewY_noTilt = startAngleY_noTilt + dx * sensitivityY;
    const previewX_noTilt = clamp(startAngleX_noTilt + dy * sensitivityX, -20, 20);

    // Then add fixed tilt on top
    const tiltY = (Math.abs(baseAngle % 360) === 180) ? -fixedTiltY : fixedTiltY;
    const tiltX = (Math.abs(baseAngle % 360) === 180) ? -fixedTiltX : fixedTiltX;
    const previewY = previewY_noTilt + tiltY;
    const previewX = previewX_noTilt + tiltX;

    // Compute relative angle to baseAngle (strip out tilt)
    const relY = getRelativeY(previewY - tiltY, baseAngle);

    // ─── FRONT → BACK ───
    if ((baseAngle % 360 === 0) && Math.abs(relY) > flipThreshold) {
      const direction = relY > 0 ? +1 : -1; // +1 = spin forward, -1 = spin backward
      const newBase = baseAngle + 180 * direction;
      doFlip(previewY, previewX, newBase, true);
      isDragging = false;
      coverInner.classList.remove("dragging");
      return;
    }

    // ─── BACK → FRONT ───
    if ((Math.abs(baseAngle % 360) === 180) && Math.abs(relY) > flipThreshold) {
      const direction = relY > 0 ? +1 : -1;
      const newBase = baseAngle + 180 * direction;
      doFlip(previewY, previewX, newBase, false);

      if (direction > 0) {
        // If forward spin, snap any residual offset at transition end
        coverInner.addEventListener(
          "transitionend",
          function onEnd(ev) {
            if (ev.propertyName === "transform") {
              applyTransform(newBase, 0);
            }
          },
          { once: true }
        );
      }

      isDragging = false;
      coverInner.classList.remove("dragging");
      return;
    }

    // If no flip, just preview with tilt
    applyTransform(previewY, previewX);
  }

  document.addEventListener("mousemove", (e) => {
    onPointerMove(e.clientX, e.clientY);
  });

  // ---------------------------------------------
  // 7b) Touchstart: begin dragging (touch-only)
  // ---------------------------------------------
  coverInner.addEventListener(
    "touchstart",
    (e) => {
      // Don’t call preventDefault here; let the browser decide until we know it’s a horizontal swipe
      const t = e.touches[0];
      onPointerDown(t.clientX, t.clientY);
    },
    { passive: true }
  );

  // ---------------------------------------------
  // 8b) Touchmove while dragging: live preview + flip check (touch-only)
  // ---------------------------------------------
  coverInner.addEventListener(
    "touchmove",
    (e) => {
      const t = e.touches[0];
      e.preventDefault(); // block page scroll/pull-to-refresh
      onPointerMove(t.clientX, t.clientY);
    },
    { passive: false }
  );

  // ---------------------------------------------
  // 9) Mouseup / Touchend: finalize any partial drag
  // ---------------------------------------------
  function onPointerUp() {
    if (!isDragging) return;
    isDragging = false;
    coverInner.classList.remove("dragging");
    coverInner.style.transition = TRANSITION_FLIP;

    // 1) Parse finalY from inline style if possible
    let finalY = baseAngle;
    const inline = coverInner.style.transform || "";
    const matchY = inline.match(/rotateY\(([-\d.]+)deg\)/);
    if (matchY) {
      finalY = parseFloat(matchY[1]);
    } else {
      // Fallback to computed style matrix
      const computed = window.getComputedStyle(coverInner).transform;
      if (computed && computed !== "none") {
        const vals = computed.replace(/^matrix3d\(|\)$/g, "").split(",");
        const m11 = parseFloat(vals[0]),
          m13 = parseFloat(vals[2]),
          m23 = parseFloat(vals[6]);
        const b = Math.asin(clamp(-m23, -1, 1));
        const cosB = Math.cos(b);
        let a = 0;
        if (Math.abs(cosB) > 1e-6) {
          a = Math.atan2(m13 / cosB, m11);
        }
        finalY = (a * 180) / Math.PI;
      }
    }

    // 2) Compute relative angle to base (strip out tilt)
    const tiltY = (Math.abs(baseAngle % 360) === 180) ? -fixedTiltY : fixedTiltY;
    const unTiltedFinalY = finalY - tiltY;
    const relY = getRelativeY(unTiltedFinalY, baseAngle);

    // ─── FRONT → BACK on pointer up ───
    if ((baseAngle % 360 === 0) && Math.abs(relY) > flipThreshold) {
      const direction = relY > 0 ? +1 : -1;
      const newBase = baseAngle + 180 * direction;
      const finalX = (Math.abs(baseAngle % 360) === 180) ? -fixedTiltX : fixedTiltX;
      doFlip(finalY, finalX, newBase, true);
      return;
    }

    // ─── BACK → FRONT on pointer up ───
    if ((Math.abs(baseAngle % 360) === 180) && Math.abs(relY) > flipThreshold) {
      const direction = relY > 0 ? +1 : -1;
      const newBase = baseAngle + 180 * direction;
      const finalX = (Math.abs(baseAngle % 360) === 180) ? -fixedTiltX : fixedTiltX;
      doFlip(finalY, finalX, newBase, false);

      if (direction > 0) {
        coverInner.addEventListener(
          "transitionend",
          function onEnd(ev) {
            if (ev.propertyName === "transform") {
              applyTransform(newBase, 0);
            }
          },
          { once: true }
        );
      }
      return;
    }

    // Otherwise, snap back to the correct final tilt or flat
    if (pointerInside) {
      const yTilt = tiltY_forInteraction(baseAngle);
      const xTilt = tiltX_forInteraction();
      applyTransform(yTilt, xTilt);
      // Schedule a check in case the pointer has actually left right at this moment
      scheduleHoverCheck();
    } else {
      snapToBase();
    }
  }

  document.addEventListener("mouseup", onPointerUp);
  document.addEventListener(
    "touchend",
    (e) => {
      // Only preventDefault when we’re actually dragging
      if (isDragging) {
        e.preventDefault();
        onPointerUp();
      }
      // If isDragging is false, do nothing—this allows a tap to become a normal click
    },
    { passive: false }
  );

  // ---------------------------------------------
  // 10) Flip button: toggle front/back (always forward spin)
  // ---------------------------------------------
  if (flipButton) {
    flipButton.addEventListener("click", () => {
      const newBase = baseAngle + 180;           // always forward +180
      const nowBack = (Math.abs(baseAngle % 360) === 0);
      // For a button‐triggered flip, jump from current tilted angles:
      const jumpY = tiltY_forInteraction(baseAngle);
      const jumpX = tiltX_forInteraction();
      doFlip(jumpY, jumpX, newBase, nowBack);
    });
  }
  // ------------- End of Cover Grab + Flip Logic -------------
  // ------------- End of COVER GRAB Logic ------------- //

  // ------------- End of COVER GRAB Logic ------------- //

  // ------------- End of Cover Logic ------------- //

  // ------------- End of Cover Logic ------------- //

  // ------------- End of Cover Logic ------------- //

  // ------------- End of Cover Logic ------------- //
  // ------------- End of Cover Logic ------------- //
  // ----- Download Menu Tracking -----
  const menus = document.querySelectorAll('.download-menu');
  menus.forEach((menu) => {
    const btn = menu.querySelector('.download-btn');
    if (btn) {
      btn.addEventListener('click', (e) => {
        e.stopPropagation();
        menu.classList.toggle('open');
      });
    }
    menu.querySelectorAll('.download-dropdown a').forEach((link) => {
      link.addEventListener('click', () => {
        menu.classList.remove('open');
        trackDownloadEvent(link.textContent.trim());
      });
    });
  });

  document.addEventListener('click', () => {
    menus.forEach((menu) => menu.classList.remove('open'));
  });

  updateVolumeFill();
});
