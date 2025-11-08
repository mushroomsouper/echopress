(function () {
  const card = document.querySelector('.song-card');
  const audio = card ? card.querySelector('[data-role="audio"]') : null;
  if (!card || !audio) {
    return;
  }

  const playBtn = card.querySelector('[data-role="toggle"]');
  const muteBtn = card.querySelector('[data-role="mute"]');
  const volumeSlider = card.querySelector('[data-role="volume"]');
  const volumeContainer = card.querySelector('.song-card__volume');
  const progress = card.querySelector('[data-role="progress"]');
  const elapsedLabel = card.querySelector('[data-role="elapsed"]');
  const durationLabel = card.querySelector('[data-role="duration"]');
  const tabs = Array.from(card.querySelectorAll('.song-card__tab'));
  const panels = Array.from(card.querySelectorAll('.song-card__panel'));

  const coverUrl = card.querySelector('.song-card__cover img')?.src || '';
  const trackTitle = card.querySelector('.song-card__title')?.textContent?.trim() || '';
  const artist = card.querySelector('.song-card__eyebrow')?.textContent?.trim() || 'EchoPress Artist';
  const album = card.querySelector('.song-card__subtitle a')?.textContent?.trim() || '';

  function formatTime(seconds) {
    if (!isFinite(seconds)) {
      return '0:00';
    }
    const whole = Math.max(0, Math.floor(seconds));
    const mins = Math.floor(whole / 60);
    const secs = whole % 60;
    return `${mins}:${secs.toString().padStart(2, '0')}`;
  }

  function updatePlayState(isPlaying) {
    const icon = playBtn.querySelector('i');
    if (!icon) return;
    if (isPlaying) {
      icon.classList.remove('fa-play');
      icon.classList.add('fa-pause');
      playBtn.setAttribute('aria-pressed', 'true');
    } else {
      icon.classList.remove('fa-pause');
      icon.classList.add('fa-play');
      playBtn.setAttribute('aria-pressed', 'false');
    }
    if ('mediaSession' in navigator) {
      navigator.mediaSession.playbackState = isPlaying ? 'playing' : 'paused';
    }
  }

  function updateMuteState() {
    const icon = muteBtn?.querySelector('i');
    if (!icon) return;
    if (audio.muted || audio.volume === 0) {
      icon.classList.remove('fa-volume-high');
      icon.classList.add('fa-volume-xmark');
    } else {
      icon.classList.remove('fa-volume-xmark');
      icon.classList.add('fa-volume-high');
    }
    updateVolumeSlider();
  }

  function updateVolumeSlider() {
    if (!volumeSlider) return;
    const value = audio.muted ? 0 : audio.volume;
    volumeSlider.value = Number.isFinite(value) ? value.toFixed(2) : '0';
  }

  function updateProgress() {
    if (!isScrubbing) {
      const ratio = audio.duration ? (audio.currentTime / audio.duration) : 0;
      progress.value = (ratio * 100).toFixed(3);
    }
    elapsedLabel.textContent = formatTime(audio.currentTime);
  }

  let isScrubbing = false;
  let lastVolume = Number.isFinite(audio.volume) ? audio.volume : 1;
  let sliderOpen = false;

  function openVolumeSlider() {
    if (!volumeContainer) return;
    volumeContainer.classList.add('song-card__volume--open');
    sliderOpen = true;
  }

  function closeVolumeSlider() {
    if (!volumeContainer) return;
    volumeContainer.classList.remove('song-card__volume--open');
    sliderOpen = false;
  }

  playBtn?.addEventListener('click', () => {
    if (audio.paused) {
      audio.play().catch(() => {/* ignore play rejection */});
    } else {
      audio.pause();
    }
  });

  muteBtn?.addEventListener('click', (event) => {
    if (!sliderOpen) {
      event.preventDefault();
      event.stopPropagation();
      openVolumeSlider();
      return;
    }
    if (audio.muted || audio.volume === 0) {
      audio.muted = false;
      if (audio.volume === 0 && lastVolume > 0) {
        audio.volume = lastVolume;
      }
    } else {
      lastVolume = audio.volume > 0 ? audio.volume : lastVolume;
      audio.muted = true;
    }
    updateMuteState();
  });

  volumeSlider?.addEventListener('input', () => {
    const value = Number(volumeSlider.value);
    if (!Number.isFinite(value)) {
      return;
    }
    audio.volume = Math.min(Math.max(value, 0), 1);
    if (audio.volume > 0) {
      lastVolume = audio.volume;
      audio.muted = false;
    } else {
      audio.muted = true;
    }
    updateMuteState();
  });

  volumeSlider?.addEventListener('focus', () => {
    openVolumeSlider();
  });

  document.addEventListener('click', (event) => {
    if (
      sliderOpen &&
      volumeContainer &&
      !volumeContainer.contains(event.target)
    ) {
      closeVolumeSlider();
    }
  });

  progress?.addEventListener('input', () => {
    isScrubbing = true;
    const ratio = Number(progress.value) / 100;
    const seekTime = audio.duration * ratio;
    if (isFinite(seekTime)) {
      elapsedLabel.textContent = formatTime(seekTime);
    }
  });

  const finishScrub = () => {
    if (!isScrubbing) return;
    const ratio = Number(progress.value) / 100;
    const seekTime = audio.duration * ratio;
    if (isFinite(seekTime)) {
      audio.currentTime = seekTime;
      updateMediaSessionPosition();
    }
    isScrubbing = false;
  };

  progress?.addEventListener('change', finishScrub);
  progress?.addEventListener('mouseup', finishScrub);
  progress?.addEventListener('touchend', finishScrub, { passive: true });

  audio.addEventListener('loadedmetadata', () => {
    durationLabel.textContent = formatTime(audio.duration);
    updateProgress();
    updateMuteState();
    updateVolumeSlider();
    updateMediaSessionMetadata();
    updateMediaSessionPosition();
  });

  audio.addEventListener('timeupdate', () => {
    updateProgress();
    updateMediaSessionPosition();
  });
  audio.addEventListener('play', () => {
    updatePlayState(true);
    updateMediaSessionPosition();
  });
  audio.addEventListener('pause', () => {
    updatePlayState(false);
    updateMediaSessionPosition();
  });
  audio.addEventListener('volumechange', () => {
    updateMuteState();
    updateMediaSessionPosition();
  });

  document.addEventListener('keydown', (event) => {
    if (event.code === 'Space' || event.key === ' ') {
      const activeElement = document.activeElement;
      if (!activeElement || !activeElement.matches('input, textarea, button')) {
        event.preventDefault();
        playBtn?.click();
      }
    }
  });

  tabs.forEach((tab) => {
    tab.addEventListener('click', () => {
      if (tab.classList.contains('song-card__tab--active')) {
        return;
      }
      tabs.forEach((t) => {
        const isActive = t === tab;
        t.classList.toggle('song-card__tab--active', isActive);
        t.setAttribute('aria-selected', isActive ? 'true' : 'false');
      });
      const targetId = tab.getAttribute('data-target');
      panels.forEach((panel) => {
        const isActive = panel.id === targetId;
        panel.classList.toggle('song-card__panel--active', isActive);
        panel.setAttribute('aria-hidden', isActive ? 'false' : 'true');
      });
    });
  });

  function updateMediaSessionMetadata() {
    if (!('mediaSession' in navigator)) return;
    try {
      navigator.mediaSession.metadata = new MediaMetadata({
        title: trackTitle,
        artist: artist,
        album: album,
        artwork: coverUrl ? [{ src: coverUrl, sizes: '512x512', type: 'image/jpeg' }] : []
      });
      navigator.mediaSession.playbackState = audio.paused ? 'paused' : 'playing';
      navigator.mediaSession.setActionHandler('play', () => audio.play());
      navigator.mediaSession.setActionHandler('pause', () => audio.pause());
      navigator.mediaSession.setActionHandler('stop', () => {
        audio.pause();
        audio.currentTime = 0;
        updateMediaSessionPosition();
      });
      navigator.mediaSession.setActionHandler('seekto', (details) => {
        if (typeof details.seekTime !== 'number') return;
        if (details.fastSeek && 'fastSeek' in audio) {
          audio.fastSeek(details.seekTime);
          return;
        }
        audio.currentTime = details.seekTime;
        updateMediaSessionPosition();
      });
    } catch (_) {
      /* ignore */
    }
  }

  function updateMediaSessionPosition() {
    if (
      'mediaSession' in navigator &&
      typeof navigator.mediaSession.setPositionState === 'function'
    ) {
      try {
        navigator.mediaSession.setPositionState({
          duration: isNaN(audio.duration) ? 0 : audio.duration,
          playbackRate: audio.playbackRate || 1,
          position: isNaN(audio.currentTime) ? 0 : audio.currentTime
        });
      } catch (_) {
        /* ignore */
      }
    }
  }

  if (audio.readyState >= 1) {
    durationLabel.textContent = formatTime(audio.duration);
    updateProgress();
    updateMuteState();
    updateVolumeSlider();
    updateMediaSessionMetadata();
    updateMediaSessionPosition();
  } else {
    updateVolumeSlider();
  }
})();
