(function () {
  document.addEventListener('DOMContentLoaded', () => {
    const data = window.playlistData;
    const container = document.querySelector('[data-playlist]');
    if (!data || !Array.isArray(data.tracks) || !container) {
      return;
    }

    const tracks = data.tracks;
    const audio = container.querySelector('[data-role="audio"]');
    if (!audio) return;

    const playBtn = container.querySelector('[data-role="toggle"]');
    const muteBtn = container.querySelector('[data-role="mute"]');
    const shuffleBtn = container.querySelector('[data-role="shuffle"]');
    const volumeSlider = container.querySelector('[data-role="volume"]');
    const volumeContainer = container.querySelector('.song-card__volume');
    const progress = container.querySelector('[data-role="progress"]');
    const elapsedLabel = container.querySelector('[data-role="elapsed"]');
    const durationLabel = container.querySelector('[data-role="duration"]');
    const nowTitle = container.querySelector('[data-role="now-title"]');
    const nowAlbum = container.querySelector('[data-role="now-album"]');
    const trackButtons = Array.from(container.querySelectorAll('.playlist-track__button'));
    const headerShell = container.querySelector('.playlist-card__header-shell');

    if (!playBtn || !progress || !elapsedLabel || !durationLabel) {
      return;
    }

    window._paq = window._paq || [];
    const matomo = window._paq;

    let currentIndex = 0;
    let isScrubbing = false;
    let sliderOpen = false;
    let lastVolume = Number.isFinite(audio.volume) ? audio.volume : 1;
    let shuffleMode = false;
    let shufflePool = [];
    let condensedState = false;

    function formatTime(seconds) {
      if (!isFinite(seconds) || seconds < 0) {
        return '0:00';
      }
      const whole = Math.floor(seconds);
      const mins = Math.floor(whole / 60);
      const secs = whole % 60;
      return `${mins}:${String(secs).padStart(2, '0')}`;
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
      updateTrackIcons(isPlaying);
      if ('mediaSession' in navigator) {
        navigator.mediaSession.playbackState = isPlaying ? 'playing' : 'paused';
      }
    }

    function updateTrackIcons(isPlaying) {
      trackButtons.forEach((btn, idx) => {
        const li = btn.closest('.playlist-track');
        if (li) {
          li.classList.toggle('is-active', idx === currentIndex);
        }
        const icon = btn.querySelector('.playlist-track__icon i');
        if (!icon) return;
        if (idx === currentIndex && isPlaying) {
          icon.classList.remove('fa-play');
          icon.classList.add('fa-pause');
        } else {
          icon.classList.remove('fa-pause');
          icon.classList.add('fa-play');
        }
      });
    }

    function updateMuteState() {
      if (!muteBtn) return;
      const icon = muteBtn.querySelector('i');
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

    function getArtworkEntries(track) {
      const baseUrl = window.location.origin;
      let path = '';
      if (track && track.album && track.album.cover) {
        path = track.album.cover;
      } else if (data.cover) {
        path = data.cover;
      }
      if (!path) return [];
      let src = path;
      if (!src.startsWith('http')) {
        src = baseUrl + src;
      }
      const clean = src.split('?')[0].toLowerCase();
      let type = 'image/jpeg';
      if (clean.endsWith('.png')) type = 'image/png';
      else if (clean.endsWith('.webp')) type = 'image/webp';
      else if (clean.endsWith('.gif')) type = 'image/gif';
      return [{ src, sizes: '512x512', type }];
    }

    function updateMediaSessionMetadata(track) {
      if (!('mediaSession' in navigator)) return;
      const activeTrack = track || tracks[currentIndex];
      if (!activeTrack) return;
      const artistName =
        activeTrack.artist ||
        (activeTrack.album && activeTrack.album.artist) ||
        data.artist ||
        'EchoPress Artist';
      const albumName =
        activeTrack.albumTitle ||
        (activeTrack.album && activeTrack.album.title) ||
        data.title ||
        '';
      try {
        navigator.mediaSession.metadata = new MediaMetadata({
          title: activeTrack.title || data.title || 'Playlist',
          artist: artistName,
          album: albumName,
          artwork: getArtworkEntries(activeTrack)
        });
      } catch (err) {
        // ignore metadata errors
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
        } catch (err) {
          // ignore unsupported position updates
        }
      }
    }

    if ('mediaSession' in navigator) {
      try {
        navigator.mediaSession.setActionHandler('previoustrack', () => {
          if (!tracks.length) return;
          const prev = (currentIndex - 1 + tracks.length) % tracks.length;
          loadTrack(prev, true);
        });
        navigator.mediaSession.setActionHandler('nexttrack', () => {
          const next = getNextIndex();
          if (next !== null) {
            loadTrack(next, true);
            return;
          }
          if (!shuffleMode && tracks.length > 0) {
            loadTrack(0, true);
          }
        });
        navigator.mediaSession.setActionHandler('play', () => audio.play().catch(() => {}));
        navigator.mediaSession.setActionHandler('pause', () => audio.pause());
        navigator.mediaSession.setActionHandler('stop', () => {
          audio.pause();
          audio.currentTime = 0;
          updateMediaSessionPosition();
          if ('mediaSession' in navigator) {
            navigator.mediaSession.playbackState = 'none';
          }
        });
        navigator.mediaSession.setActionHandler('seekto', (details) => {
          if (typeof details.seekTime !== 'number') return;
          if (details.fastSeek && typeof audio.fastSeek === 'function') {
            audio.fastSeek(details.seekTime);
          } else {
            audio.currentTime = details.seekTime;
          }
          updateMediaSessionPosition();
        });
      } catch (err) {
        // ignore unsupported media session handlers
      }
    }

    function updateProgress() {
      if (!progress) return;
      if (!isScrubbing && audio.duration) {
        const ratio = audio.currentTime / audio.duration;
        progress.value = (ratio * 100).toFixed(3);
      }
      elapsedLabel.textContent = formatTime(audio.currentTime);
    }

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

    function setNowPlaying(track) {
      if (!track) return;
      if (nowTitle) {
        nowTitle.textContent = track.title || '';
      }
      if (nowAlbum) {
        const parts = [];
        if (track.albumTitle) parts.push(track.albumTitle);
        if (Number.isFinite(track.trackNumber)) parts.push(`Track ${track.trackNumber}`);
        if (track.length) parts.push(track.length);
        nowAlbum.textContent = parts.join(' · ');
      }
    }

    function loadTrack(index, autoplay = false) {
      if (index < 0 || index >= tracks.length) {
        return;
      }
      currentIndex = index;
      const track = tracks[index];
      const src = track && track.audio ? track.audio : '';
      if (src) {
        audio.src = src;
        playBtn.disabled = false;
      } else {
        audio.removeAttribute('src');
        playBtn.disabled = true;
      }
      durationLabel.textContent = track && track.length ? track.length : '0:00';
      progress.value = '0';
      elapsedLabel.textContent = '0:00';
      setNowPlaying(track);
      updateMediaSessionMetadata(track);
      updateMediaSessionPosition();
      refreshShufflePool();
      updateTrackIcons(!audio.paused && src !== '');
      if (autoplay && src) {
        audio.play().catch(() => {/* ignore */});
      }
      if (window._paq && typeof window._paq.push === 'function') {
        window._paq.push([
          'trackEvent',
          'Playlist',
          'load track',
          `${data.title || 'Playlist'} :: ${track.title || `Track ${index + 1}`}`,
          index + 1
        ]);
      }
    }

    function refreshShufflePool() {
      if (!shuffleMode) return;
      shufflePool = tracks.map((_, idx) => idx).filter((idx) => idx !== currentIndex);
    }

    function getNextIndex() {
      if (shuffleMode) {
        if (!shufflePool.length) {
          refreshShufflePool();
        }
        if (!shufflePool.length) {
          return null;
        }
        const randomIndex = Math.floor(Math.random() * shufflePool.length);
        const next = shufflePool.splice(randomIndex, 1)[0];
        return next;
      }
      const next = currentIndex + 1;
      return next < tracks.length ? next : null;
    }

    playBtn.addEventListener('click', () => {
      if (audio.paused) {
        audio.play().catch(() => {/* ignore */});
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
      if (!Number.isFinite(value)) return;
      audio.volume = Math.min(Math.max(value, 0), 1);
      if (audio.volume > 0) {
        audio.muted = false;
        lastVolume = audio.volume;
      } else {
        audio.muted = true;
      }
      updateMuteState();
    });

    volumeSlider?.addEventListener('focus', () => openVolumeSlider());

    document.addEventListener('click', (event) => {
      if (
        sliderOpen &&
        volumeContainer &&
        !volumeContainer.contains(event.target)
      ) {
        closeVolumeSlider();
      }
    });

    if (progress) {
      progress.addEventListener('input', () => {
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
        }
        isScrubbing = false;
      };

      progress.addEventListener('change', finishScrub);
      progress.addEventListener('mouseup', finishScrub);
      progress.addEventListener('touchend', finishScrub, { passive: true });
    }

    trackButtons.forEach((btn, idx) => {
      btn.addEventListener('click', () => {
        if (idx === currentIndex) {
          if (audio.paused) {
            audio.play().catch(() => {/* ignore */});
          } else {
            audio.pause();
          }
          return;
        }
        loadTrack(idx, true);
      });
    });

    audio.addEventListener('loadedmetadata', () => {
      durationLabel.textContent = formatTime(audio.duration);
      updateProgress();
      updateMuteState();
      updateVolumeSlider();
      updateMediaSessionMetadata(tracks[currentIndex]);
      updateMediaSessionPosition();
    });

    audio.addEventListener('timeupdate', () => {
      updateProgress();
      updateMediaSessionPosition();
    });

    audio.addEventListener('play', () => {
      updatePlayState(true);
      updateMediaSessionPosition();
      const track = tracks[currentIndex];
      if (window._paq && typeof window._paq.push === 'function' && track) {
        window._paq.push([
          'trackEvent',
          'Playlist',
          'play',
          `${data.title || 'Playlist'} :: ${track.title || `Track ${currentIndex + 1}`}`,
          currentIndex + 1
        ]);
      }
    });

    audio.addEventListener('pause', () => {
      updatePlayState(false);
      updateMediaSessionPosition();
      const track = tracks[currentIndex];
      if (window._paq && typeof window._paq.push === 'function' && track) {
        window._paq.push([
          'trackEvent',
          'Playlist',
          'pause',
          `${data.title || 'Playlist'} :: ${track.title || `Track ${currentIndex + 1}`}`,
          audio.currentTime
        ]);
      }
    });

    audio.addEventListener('volumechange', () => {
      updateMuteState();
      updateMediaSessionPosition();
    });

    audio.addEventListener('ended', () => {
      const nextIndex = getNextIndex();
      if (nextIndex !== null) {
        loadTrack(nextIndex, true);
        return;
      }
      audio.currentTime = 0;
      audio.pause();
      updatePlayState(false);
      updateMediaSessionPosition();
      if (window._paq && typeof window._paq.push === 'function') {
        window._paq.push([
          'trackEvent',
          'Playlist',
          'ended',
          `${data.title || 'Playlist'} :: track ${currentIndex + 1}`
        ]);
      }
    });

    document.addEventListener('keydown', (event) => {
      if (event.code === 'Space' || event.key === ' ') {
        const activeElement = document.activeElement;
        if (!activeElement || !activeElement.matches('input, textarea, button')) {
          event.preventDefault();
          playBtn.click();
        }
      }
    });

    shuffleBtn?.addEventListener('click', () => {
      shuffleMode = !shuffleMode;
      shuffleBtn.classList.toggle('is-active', shuffleMode);
      shuffleBtn.setAttribute('aria-pressed', shuffleMode ? 'true' : 'false');
      if (shuffleMode) {
        refreshShufflePool();
      } else {
        shufflePool = [];
      }
      if (window._paq && typeof window._paq.push === 'function') {
        window._paq.push([
          'trackEvent',
          'Playlist',
          shuffleMode ? 'shuffle on' : 'shuffle off',
          data.title || 'Playlist'
        ]);
      }
    });

    let expandedShellHeight = headerShell ? headerShell.offsetHeight : 0;
    if (headerShell) {
      headerShell.style.height = `${expandedShellHeight}px`;
    }

    function handleScroll() {
      const shouldCondense = window.scrollY > 120;
      if (shouldCondense === condensedState) {
        return;
      }

      condensedState = shouldCondense;
      container.classList.toggle('playlist-card--condensed', condensedState);
      if (headerShell) {
        headerShell.classList.toggle('playlist-card__header-shell--condensed', condensedState);
      }
    }

    window.addEventListener('scroll', handleScroll, { passive: true });
    window.addEventListener('resize', () => {
      if (!headerShell || condensedState) {
        return;
      }
      headerShell.style.height = 'auto';
      expandedShellHeight = headerShell.offsetHeight;
      headerShell.style.height = `${expandedShellHeight}px`;
    });

    window.addEventListener('load', () => {
      if (!headerShell || condensedState) {
        return;
      }
      headerShell.style.height = 'auto';
      expandedShellHeight = headerShell.offsetHeight;
      headerShell.style.height = `${expandedShellHeight}px`;
    }, { once: true });

    if (headerShell) {
      const coverImage = headerShell.querySelector('.playlist-card__cover img');
      if (coverImage && !coverImage.complete) {
        coverImage.addEventListener('load', () => {
          if (condensedState) {
            return;
          }
          headerShell.style.height = 'auto';
          expandedShellHeight = headerShell.offsetHeight;
          headerShell.style.height = `${expandedShellHeight}px`;
        });
      }
    }

    handleScroll();

    function initPlaylistShareMenuFallback() {
      const shareMenu = container.querySelector('.playlist-card__share.share-menu');
      if (!shareMenu) {
        return;
      }

      const trigger = shareMenu.querySelector('.share-btn');
      const overlay = shareMenu.querySelector('.share-overlay');
      const panel = overlay ? overlay.querySelector('.share-panel') : null;
      const closeBtn = shareMenu.querySelector('.share-close');
      const options = Array.from(shareMenu.querySelectorAll('.share-option'));

      if (shareMenu.dataset.shareMenuInit === '1') {
        return;
      }

      if (!trigger || !overlay || trigger.dataset.playlistShareBound === '1') {
        return;
      }

      trigger.dataset.playlistShareBound = '1';
      if (shareMenu.dataset.shareMenuInit !== '1') {
        shareMenu.dataset.shareMenuInit = '1';
      }

      options.forEach((option) => {
        const srFeedback = option.querySelector('.share-feedback-sr');
        if (srFeedback && !srFeedback.dataset.defaultText) {
          srFeedback.dataset.defaultText = srFeedback.textContent || 'Share link ready';
        }
      });

      let lastActive = null;
      let closeOnOutside = null;

      const copyToClipboard = (text) => {
        if (navigator.clipboard && typeof navigator.clipboard.writeText === 'function') {
          return navigator.clipboard.writeText(text);
        }
        return new Promise((resolve, reject) => {
          const textarea = document.createElement('textarea');
          textarea.value = text;
          textarea.setAttribute('readonly', '');
          textarea.style.position = 'fixed';
          textarea.style.opacity = '0';
          textarea.style.pointerEvents = 'none';
          textarea.style.left = '-9999px';
          document.body.appendChild(textarea);
          textarea.select();
          textarea.setSelectionRange(0, textarea.value.length);
          try {
            const successful = document.execCommand('copy');
            document.body.removeChild(textarea);
            if (successful) {
              resolve();
            } else {
              reject(new Error('execCommand copy failed'));
            }
          } catch (err) {
            document.body.removeChild(textarea);
            reject(err);
          }
        });
      };

      const resetOptionFeedback = (option) => {
        if (!option) return;
        if (option.dataset.feedbackTimer) {
          clearTimeout(Number(option.dataset.feedbackTimer));
          delete option.dataset.feedbackTimer;
        }
        option.classList.remove('copied', 'copy-error');
        const srFeedback = option.querySelector('.share-feedback-sr');
        if (srFeedback) {
          const defaultText = srFeedback.dataset.defaultText ?? '';
          srFeedback.textContent = defaultText;
        }
      };

      const showOptionFeedback = (option, status) => {
        if (!option) return;
        const srFeedback = option.querySelector('.share-feedback-sr');
        if (srFeedback && !srFeedback.dataset.defaultText) {
          srFeedback.dataset.defaultText = srFeedback.textContent || '';
        }
        const timeout = status === 'success' ? 1500 : 2000;
        if (srFeedback) {
          srFeedback.textContent =
            status === 'success' ? 'Link copied to clipboard.' : 'Copy failed. Please try again.';
        }
        option.classList.toggle('copied', status === 'success');
        option.classList.toggle('copy-error', status === 'error');
        if (option.dataset.feedbackTimer) {
          clearTimeout(Number(option.dataset.feedbackTimer));
        }
        const timerId = window.setTimeout(() => {
          option.classList.remove('copied', 'copy-error');
          if (srFeedback) {
            const defaultText = srFeedback.dataset.defaultText ?? '';
            srFeedback.textContent = defaultText;
          }
          delete option.dataset.feedbackTimer;
        }, timeout);
        option.dataset.feedbackTimer = String(timerId);
      };

      const updateBodyState = () => {
        if (document.querySelector('.share-menu.open')) {
          document.body.classList.add('share-menu-open');
        } else {
          document.body.classList.remove('share-menu-open');
        }
      };

      const closeMenu = ({ returnFocus } = { returnFocus: false }) => {
        if (!shareMenu.classList.contains('open')) {
          return;
        }
        shareMenu.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
        trigger.setAttribute('aria-expanded', 'false');
        document.removeEventListener('keydown', handleEscape, true);
        options.forEach(resetOptionFeedback);
        if (closeOnOutside) {
          document.removeEventListener('click', closeOnOutside, true);
          closeOnOutside = null;
        }
        updateBodyState();
        if (returnFocus) {
          const target = trigger || lastActive;
          if (target && typeof target.focus === 'function') {
            target.focus({ preventScroll: true });
          }
        }
        lastActive = null;
      };

      const openMenu = () => {
        if (shareMenu.classList.contains('open')) {
          return;
        }
        lastActive = document.activeElement;
        shareMenu.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');
        trigger.setAttribute('aria-expanded', 'true');
        document.addEventListener('keydown', handleEscape, true);
        updateBodyState();
        closeOnOutside = (event) => {
          if (overlay.contains(event.target) && (!panel || panel.contains(event.target))) {
            return;
          }
          closeMenu({ returnFocus: false });
        };
        window.setTimeout(() => {
          document.addEventListener('click', closeOnOutside, true);
        }, 0);

        window.setTimeout(() => {
          if (panel) {
            panel.focus({ preventScroll: true });
          } else if (options[0]) {
            options[0].focus({ preventScroll: true });
          }
        }, 60);
      };

      function handleEscape(event) {
        if (event.key === 'Escape') {
          event.stopPropagation();
          closeMenu({ returnFocus: true });
        }
      }

      trigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        if (shareMenu.classList.contains('open')) {
          closeMenu({ returnFocus: true });
        } else {
          openMenu();
        }
      });

      if (closeBtn) {
        closeBtn.addEventListener('click', (event) => {
          event.stopPropagation();
          closeMenu({ returnFocus: true });
        });
      }

      if (overlay) {
        overlay.addEventListener('click', (event) => {
          if (!panel || panel.contains(event.target)) {
            return;
          }
          closeMenu({ returnFocus: true });
        });
      }

      options.forEach((option) => {
        option.addEventListener('click', async (event) => {
          event.stopPropagation();
          const url = option.getAttribute('data-share-url');
          if (!url) return;

          options.forEach((opt) => {
            if (opt !== option) {
              resetOptionFeedback(opt);
            }
          });

          try {
            await copyToClipboard(url);
            showOptionFeedback(option, 'success');
          } catch (err) {
            console.error('Copy to clipboard failed', err);
            showOptionFeedback(option, 'error');
          }
        });
      });
    }

    handleScroll();
    initPlaylistShareMenuFallback();

    loadTrack(0, false);
    updateMuteState();
    updateTrackIcons(false);
  });
})();
