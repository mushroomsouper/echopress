document.addEventListener('DOMContentLoaded', function () {
  const homeLink = document.querySelector('.back-to-home');
  const player = document.querySelector('.audio-player-container');
  const playerbody = document.querySelector('body');
  const audio = player ? player.querySelector('.audio-element') : null;
  let activeDragRemover = null;
  let miniPlayerClosed = false;
  let overlayCleanup = null;

  if (!homeLink || !player || !audio) return;

  function adjustLinks(doc) {
    if (!doc) return;
    doc.querySelectorAll('a[href]').forEach(a => {
      const href = a.getAttribute('href');
      if (!href) return;
      const isExternal = /^https?:\/\//i.test(href) && !href.startsWith(window.location.origin);
      const isPlayer = href.includes('/discography/albums/') || href.includes('/song.php');
      if (isExternal || isPlayer) {
        a.setAttribute('target', '_top');
      }
    });
  }

  function canonicalPath(url) {
    try {
      const u = new URL(url, window.location.origin);
      let path = u.pathname;
      if (path.endsWith('/index.php')) {
        path = path.slice(0, -10);
      }
      if (!path.endsWith('/')) path += '/';
      return path;
    } catch (err) {
      return url;
    }
  }

  function attachPromptHandlers(doc) {
    if (!doc) return;
    doc.querySelectorAll('a[target="_top"]').forEach(a => {
      const href = a.getAttribute('href');
      if (!href) return;
      const isPlayer = href.includes('/discography/albums/') || href.includes('/song.php');
      if (!isPlayer) return;
      a.addEventListener('click', e => {
        if (miniPlayerClosed) return;
        e.preventDefault();
        const currentAlbum = window.albumData ? canonicalPath(window.albumData.albumFolder || '') : '';
        const clicked = canonicalPath(href);
        if (currentAlbum && clicked === currentAlbum) {
          if (typeof overlayCleanup === 'function') {
            overlayCleanup();
          }
          return;
        }
        showClosePrompt(href);
      });
    });
  }

  function waitForNextLink(doc) {
    if (!doc) return;
    function handler(e) {
      const link = e.target.closest('a[href]');
      if (!link) return;
      e.preventDefault();
      window.location.href = link.href;
    }
    doc.addEventListener('click', handler, { once: true });
  }

  function showClosePrompt(url) {
    if (player.querySelector('.close-prompt')) return;
    const prev = {
      left: player.style.left,
      top: player.style.top,
      right: player.style.right,
      bottom: player.style.bottom,
      inset: player.style.inset,
      rect: rectWithoutScale(player)
    };

    if (activeDragRemover && typeof activeDragRemover === 'function') {
      activeDragRemover();
      activeDragRemover = null; // disable dragging while prompt is visible
    }

    player.classList.add('prompt-center');
    player.style.left = '';
    player.style.top = '';
    player.style.right = '';
    player.style.bottom = '';

    const trackEl = player.querySelector('.track-info .info-text');
    const trackTitle = trackEl ? trackEl.textContent.trim() : '';
    const currentAlbum = window.albumData ? window.albumData.albumTitle : '';

    const prompt = document.createElement('div');
    prompt.className = 'close-prompt';
    prompt.innerHTML =
      `<p class="default-font">This will close</p><p>"${currentAlbum}".</p>` +
      (trackTitle ? `<p class="default-font">Currently listening to:</p><p>"${trackTitle}"</p>` : '') +
      `<div class="prompt-buttons">` +
      `<button class="cancel">Keep Listening</button>` +
      `<button class="proceed">Proceed<i class="fas fa-arrow-right"></i></button>` +
      `</div>`;

    const proceed = prompt.querySelector('.proceed');
    const cancel = prompt.querySelector('.cancel');
    proceed.addEventListener('click', () => {
      window.location.href = url;
    });
    cancel.addEventListener('click', () => {
      prompt.remove();
      player.classList.remove('prompt-center');
      player.style.left = prev.left;
      player.style.top = prev.top;
      player.style.right = prev.right;
      player.style.bottom = prev.bottom;
      if (prev.inset) {
        player.style.inset = prev.inset;
      } else {
        player.style.removeProperty('inset');
      }
      if (!prev.left && !prev.top && !prev.right && !prev.bottom) {
        // restore using actual pixels if no inline values existed
        player.style.left = prev.rect.left + 'px';
        player.style.top = prev.rect.top + 'px';
        player.style.right = 'auto';
        player.style.bottom = 'auto';
      }
      enforceBounds(player);
      if (activeDragRemover) {
        activeDragRemover();
      }
      activeDragRemover = makeDraggable(player);
    });

    player.appendChild(prompt);
  }

  const SAFE_MARGIN = 30;

  function getScale(el) {
    const tr = window.getComputedStyle(el).transform;
    if (!tr || tr === 'none') return { x: 1, y: 1 };
    const m = tr.match(/^matrix\(([^,]+),[^,]+,[^,]+,([^,]+),/);
    if (m) {
      const sx = parseFloat(m[1]);
      const sy = parseFloat(m[2]);
      if (!Number.isNaN(sx) && !Number.isNaN(sy) && sx !== 0 && sy !== 0) {
        return { x: sx, y: sy };
      }
    }
    return { x: 1, y: 1 };
  }

  function rectWithoutScale(el) {
    const rect = el.getBoundingClientRect();
    const scale = getScale(el);
    if (scale.x === 1 && scale.y === 1) return rect;
    const left = rect.left + (rect.width - rect.width / scale.x) / 2;
    const top = rect.top + (rect.height - rect.height / scale.y) / 2;
    const width = rect.width / scale.x;
    const height = rect.height / scale.y;
    return { left, top, width, height };
  }

  function enforceBounds(el) {
    const rect = rectWithoutScale(el);
    const vpW = window.innerWidth;
    const vpH = window.innerHeight;
    let left = parseFloat(el.style.left);
    let top = parseFloat(el.style.top);
    if (Number.isNaN(left)) left = rect.left;
    if (Number.isNaN(top)) top = rect.top;
    if (left < SAFE_MARGIN) left = SAFE_MARGIN;
    if (top < SAFE_MARGIN) top = SAFE_MARGIN;
    if (left + rect.width > vpW - SAFE_MARGIN) left = vpW - SAFE_MARGIN - rect.width;
    if (top + rect.height > vpH - SAFE_MARGIN) top = vpH - SAFE_MARGIN - rect.height;
    el.style.left = left + 'px';
    el.style.top = top + 'px';
    el.style.bottom = 'auto';
    el.style.right = 'auto';
  }

  function makeDraggable(el, handleEl = el) {
    let startX, startY, dragging = false, moved = false;

    function onMove(e) {
      if (!dragging) return;
      e.preventDefault();
      const x = e.touches ? e.touches[0].clientX : e.clientX;
      const y = e.touches ? e.touches[0].clientY : e.clientY;
      let newLeft = x - startX;
      let newTop = y - startY;
      el.style.left = newLeft + 'px';
      el.style.top = newTop + 'px';
      el.style.bottom = 'auto';
      el.style.right = 'auto';
      moved = true;
    }
    function end() {
      const wasDragging = dragging;
      dragging = false;
      document.removeEventListener('mousemove', onMove);
      document.removeEventListener('touchmove', onMove);
      document.removeEventListener('mouseup', end);
      document.removeEventListener('touchend', end);
      document.removeEventListener('mouseleave', end);
      document.removeEventListener('touchcancel', end);
      if (wasDragging && moved) {
        enforceBounds(el);
      }
      moved = false;
    }

    function start(e) {
      if (e.target.closest('.player-controls') || e.target.closest('.expand-player') || e.target.closest('.close-player')) return;
      e.preventDefault();
      dragging = true;
      moved = false;
      const rect = rectWithoutScale(el);
      startX = (e.clientX || 0) - rect.left;
      el.style.left = rect.left + "px";
      el.style.top = rect.top + "px";
      el.style.bottom = "auto";
      el.style.right = "auto";
      startY = (e.clientY || 0) - rect.top;
      document.addEventListener('mousemove', onMove);
      document.addEventListener('mouseup', end);
      document.addEventListener('mouseleave', end);

    }
    function startTouch(e) {
      if (e.target.closest(".player-controls") || e.target.closest(".expand-player") || e.target.closest(".close-player")) return;
      e.preventDefault();
      dragging = true;
      moved = false;
      const rect = rectWithoutScale(el);
      el.style.left = rect.left + "px";
      el.style.top = rect.top + "px";
      el.style.bottom = "auto";
      el.style.right = "auto";
      startX = e.touches[0].clientX - rect.left;
      startY = e.touches[0].clientY - rect.top;
      document.addEventListener('touchmove', onMove, { passive: false });
      document.addEventListener('touchend', end);
      document.addEventListener('touchcancel', end);

    }
    handleEl.addEventListener('mousedown', start);
    handleEl.addEventListener('touchstart', startTouch);
    return function removeDraggable() {
      handleEl.removeEventListener('mousedown', start);
      handleEl.removeEventListener('touchstart', startTouch);
      end();
    };

  }

  function openOverlay() {
    if (document.querySelector('.home-iframe-overlay')) return;
    miniPlayerClosed = false;
    // reset any inline positioning so the player starts bottom-right
    player.style.left = '';
    player.style.top = '';
    player.style.right = '';
    player.style.bottom = '';
    player.style.removeProperty('inset');
    const overlay = document.createElement('div');
    overlay.className = 'home-iframe-overlay';
    const iframe = document.createElement('iframe');
    iframe.src = homeLink.href;
    overlay.appendChild(iframe);
    const closeBtn = document.createElement('button');
    closeBtn.className = 'close-iframe';
    closeBtn.innerHTML = '\u00d7';
    overlay.appendChild(closeBtn);
    document.body.appendChild(overlay);

    const expandBtn = document.createElement('button');
    expandBtn.className = 'expand-player';
    expandBtn.innerHTML = '\u26F6';
    player.appendChild(expandBtn);

    const closePlayerBtn = document.createElement('button');
    closePlayerBtn.className = 'close-player';
    closePlayerBtn.innerHTML = '\u00d7';
    player.appendChild(closePlayerBtn);

    let removeDrag;
    let iframeDoc;
    function onResize() {
      enforceBounds(player);
    }
    function cleanup() {
      overlay.remove();
      player.classList.remove('floating-player');
      playerbody.classList.remove('floating-state');
      if (removeDrag) removeDrag();
      activeDragRemover = null;
      window.removeEventListener('resize', onResize);
      player.removeEventListener('mouseenter', onEnter);
      player.removeEventListener('mouseleave', hideControls);
      player.removeEventListener('touchstart', onTouchStart);
      document.removeEventListener('touchstart', outsideTouch);
      document.removeEventListener('mousedown', outsideTouch);
      if (iframeDoc) {
        iframeDoc.removeEventListener('touchstart', outsideTouch);
        iframeDoc.removeEventListener('mousedown', outsideTouch);
      }
      expandBtn.remove();
      closePlayerBtn.remove();
      player.style.removeProperty('inset');
      player.style.left = '';
      player.style.top = '';
      player.style.right = '';
      player.style.bottom = '';
      overlayCleanup = null;
    }
    overlayCleanup = cleanup;
    closeBtn.addEventListener('click', () => {
      cleanup();
      audio.pause();
    });
    expandBtn.addEventListener('click', () => {
      cleanup();
      const albumUrl = window.albumData ? canonicalPath(window.albumData.albumFolder || '') : '/';
      history.replaceState({}, '', albumUrl);
    });
    closePlayerBtn.addEventListener('click', () => {
      miniPlayerClosed = true;
      audio.pause();
      if (removeDrag) removeDrag();
      activeDragRemover = null;
      window.removeEventListener('resize', onResize);
      expandBtn.remove();
      closePlayerBtn.remove();
      player.classList.remove('floating-player');
      player.style.removeProperty('inset');
      player.style.left = '';
      player.style.top = '';
      player.style.right = '';
      player.style.bottom = '';
      player.style.display = 'none';
      waitForNextLink(iframe.contentDocument);
    });

    function showControls() {
      player.classList.add('show-controls');
      player.classList.add('hovered');
    }
    function hideControls() {
      player.classList.remove('show-controls');
      player.classList.remove('hovered');
    }

    function onEnter(e) {
      if (!e.target.closest('.player-controls')) showControls();
    }
    function onTouchStart(e) {
      if (!e.target.closest('.player-controls')) showControls();
    }
    function outsideTouch(e) {
      if (!player.contains(e.target)) hideControls();
    }

    player.addEventListener('mouseenter', onEnter);
    player.addEventListener('mouseleave', hideControls);
    player.addEventListener('touchstart', onTouchStart);
    document.addEventListener('touchstart', outsideTouch);
    document.addEventListener('mousedown', outsideTouch);

    iframe.addEventListener('load', () => {
      iframeDoc = iframe.contentDocument;
      adjustLinks(iframeDoc);
      attachPromptHandlers(iframeDoc);
      iframeDoc.addEventListener('touchstart', outsideTouch);
      iframeDoc.addEventListener('mousedown', outsideTouch);
      const loc = iframe.contentWindow.location;
      const iframeUrl = loc.pathname + loc.search + loc.hash;
      history.replaceState({ mini: true, iframeUrl }, '', iframeUrl);

      // ----- Bridge pull-to-refresh gesture from iframe to parent -----
      let startY = 0;
      let bridging = false;

      function startBridge(evt) {
        if (iframe.contentWindow.scrollY <= 0) {
          startY = evt.touches[0].clientY;
          bridging = true;
        } else {
          bridging = false;
        }
      }

      function moveBridge(evt) {
        if (!bridging) return;
        const delta = evt.touches[0].clientY - startY;
        if (delta > 0) {
          window.scrollTo(0, -delta);
          evt.preventDefault();
        } else {
          bridging = false;
        }
      }

      function endBridge() {
        bridging = false;
      }

      iframeDoc.addEventListener('touchstart', startBridge, { passive: true });
      iframeDoc.addEventListener('touchmove', moveBridge, { passive: false });
      iframeDoc.addEventListener('touchend', endBridge, { passive: true });
      iframeDoc.addEventListener('touchcancel', endBridge, { passive: true });

    });

    function refreshMarquees() {
      window.dispatchEvent(new Event('refresh-marquees'));
    }

    player.classList.add('floating-player');
    playerbody.classList.add('floating-state');
    refreshMarquees();
    player.addEventListener('transitionend', refreshMarquees, { once: true });
    setTimeout(refreshMarquees, 400);

    removeDrag = makeDraggable(player);
    activeDragRemover = removeDrag;
    enforceBounds(player);
    window.addEventListener('resize', onResize);

  }

  homeLink.addEventListener('click', function (e) {
    if (!audio.paused) {
      e.preventDefault();
      openOverlay();
    }
  });
});
