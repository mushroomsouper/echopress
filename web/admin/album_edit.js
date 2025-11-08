document.addEventListener('DOMContentLoaded', function () {
  const tracks = document.getElementById('tracks');
  const addBtn = document.getElementById('add-track');
  const toggleSort = document.getElementById('toggle-sort');
  const saveSort = document.getElementById('save-sort');
  const zipBtn = document.getElementById('zip-tracks');
  const form = document.querySelector('form');

  const previewInputs = ['cover', 'back', 'background', 'background_image'];
  const SIZE_LIMIT = 100 * 1024 * 1024; // 100MB

  function getTotalSize() {
    let t = 0;
    document.querySelectorAll('input[type="file"]').forEach(inp => {
      if (inp.files[0]) t += inp.files[0].size;
    });
    return t;
  }

  function updateSizeWarning() {
    const warn = document.getElementById('size-warning');
    if (!warn) return;
    const total = getTotalSize();
    if (total > SIZE_LIMIT) {
      warn.textContent = 'Total upload size ' + (total / 1048576).toFixed(1) + 'MB exceeds 100MB limit';
      warn.style.display = 'block';
    } else {
      warn.style.display = 'none';
    }
  }

  previewInputs.forEach(name => {
    const input = document.getElementById(name + '-input');
    const preview = document.getElementById(name + '-preview');
    if (!input || !preview) return;

    input.addEventListener('change', () => {
      if (!input.files[0]) return;

      const url = URL.createObjectURL(input.files[0]);
      preview.style.display = 'inline';

      if (name === 'background') {
        // Generate a thumbnail from the selected video
        const vid = document.createElement('video');
        vid.preload = 'metadata';
        vid.src = url;
        vid.muted = true;
        vid.addEventListener(
          'loadeddata',
          () => {
            const c = document.createElement('canvas');
            c.width = vid.videoWidth;
            c.height = vid.videoHeight;
            c.getContext('2d').drawImage(vid, 0, 0, c.width, c.height);
            preview.src = c.toDataURL('image/jpeg');
            URL.revokeObjectURL(url);
          },
          { once: true }
        );
      } else {
        preview.src = url;
        preview.onload = () => URL.revokeObjectURL(url);
      }
      updateSizeWarning();
    });
  });

  // Simple progress overlay
  const overlay = document.createElement('div');
  overlay.id = 'upload-overlay';
  overlay.style.cssText = 'position:fixed;top:0;left:0;right:0;bottom:0;display:none;align-items:center;justify-content:center;background:rgba(0,0,0,0.6);flex-direction:column;color:#fff;font-size:18px;z-index:1000';
  overlay.innerHTML = `
  <div style="width:80%;background:#444">
    <div class="bar" style="height:20px;background:#4caf50;width:0%"></div>
  </div>
  <div style="margin-top:8px; display:flex; align-items:center;">
    <span id="upload-percent">0%</span>
    <span id="upload-status" style="margin-left:12px">Saving...</span>
    <button id="cancel-upload" style="margin-left:16px;">Cancel</button>
  </div>
`;
  document.body.appendChild(overlay);

  const toast = document.createElement('div');
  toast.id = 'upload-toast';
  toast.style.cssText = 'position:fixed;bottom:16px;right:16px;width:360px;max-width:90vw;background:#1e1e1e;color:#fff;box-shadow:0 10px 30px rgba(0,0,0,0.4);border-radius:10px;padding:16px;display:none;z-index:1001;font-size:14px;';
  toast.innerHTML = `
    <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:12px;">
      <div style="flex:1;min-width:0;">
        <strong data-toast-title style="font-size:15px;display:block;">Processing album…</strong>
        <div data-toast-status style="font-size:12px;opacity:0.75;margin-top:4px;">Preparing…</div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;flex-shrink:0;">
        <button type="button" data-toast-dismiss style="background:none;border:none;color:rgba(255,255,255,0.75);font-size:12px;padding:4px 0;cursor:pointer;">Dismiss</button>
        <button type="button" data-toast-toggle style="background:none;border:none;color:#fff;font-size:20px;line-height:1;cursor:pointer;" title="Expand details">+</button>
      </div>
    </div>
    <div data-toast-log style="margin-top:12px;max-height:240px;overflow:auto;padding-right:4px;"></div>
    <div data-toast-actions style="margin-top:14px;display:none;gap:8px;">
      <button type="button" data-toast-reload style="background:#4caf50;border:none;color:#fff;padding:6px 12px;border-radius:4px;cursor:pointer;">Reload page</button>
    </div>
  `;
  document.body.appendChild(toast);

  const toastTitle = toast.querySelector('[data-toast-title]');
  const toastStatus = toast.querySelector('[data-toast-status]');
  const toastLog = toast.querySelector('[data-toast-log]');
  const toastActions = toast.querySelector('[data-toast-actions]');
  const toastReload = toast.querySelector('[data-toast-reload]');
  const toastDismiss = toast.querySelector('[data-toast-dismiss]');
  const toastToggle = toast.querySelector('[data-toast-toggle]');
  const toastPreferenceKey = 'albumUploadToastCollapsed';

  toastActions.dataset.visible = 'false';

  function loadToastCollapsedPreference() {
    try {
      const stored = localStorage.getItem(toastPreferenceKey);
      if (stored === 'true' || stored === 'false') {
        return stored === 'true';
      }
    } catch (err) {
      // Access to localStorage can fail in some environments; fall back to collapsed view.
    }
    return true;
  }

  function persistToastCollapsedPreference(collapsed) {
    try {
      localStorage.setItem(toastPreferenceKey, collapsed ? 'true' : 'false');
    } catch (err) {
      // Ignore storage errors so the toast still functions even if persistence fails.
    }
  }

  let currentXhr;
  let currentJobId = null;
  let progressInterval = null;
  let lastProgressLength = 0;

  function setToastCollapsed(collapsed, options = {}) {
    toast.dataset.collapsed = collapsed ? 'true' : 'false';
    toastLog.style.display = collapsed ? 'none' : 'block';
    if (toastActions.dataset.visible === 'true') {
      toastActions.style.display = collapsed ? 'none' : 'flex';
    } else {
      toastActions.style.display = 'none';
    }
    toastToggle.textContent = collapsed ? '+' : '–';
    toastToggle.title = collapsed ? 'Expand details' : 'Collapse details';
    if (!options.skipPersist) {
      persistToastCollapsedPreference(collapsed);
    }
  }

  function setToastActionsVisible(show) {
    toastActions.dataset.visible = show ? 'true' : 'false';
    if (toast.dataset.collapsed === 'true') {
      toastActions.style.display = 'none';
    } else {
      toastActions.style.display = show ? 'flex' : 'none';
    }
  }

  setToastCollapsed(loadToastCollapsedPreference(), { skipPersist: true });

  function resetToast(jobId, statusText) {
    currentJobId = jobId;
    lastProgressLength = 0;
    toastLog.innerHTML = '';
    toast.style.display = 'block';
    toastTitle.textContent = 'Processing album…';
    toastStatus.textContent = statusText || 'Processing album…';
    setToastActionsVisible(false);
    setToastCollapsed(loadToastCollapsedPreference(), { skipPersist: true });
  }

  function appendToastMessage(message) {
    if (!message) return;
    const entry = document.createElement('div');
    entry.textContent = message;
    if (toastLog.children.length) {
      entry.style.marginTop = '6px';
    }
    toastLog.appendChild(entry);
    toastLog.scrollTop = toastLog.scrollHeight;
  }

  function handleProgressPayload(data) {
    if (!data) return;
    if (data.headline) {
      toastTitle.textContent = data.headline;
    }
    if (data.statusText) {
      toastStatus.textContent = data.statusText;
    }
    if (Array.isArray(data.steps)) {
      for (let i = lastProgressLength; i < data.steps.length; i++) {
        const step = data.steps[i];
        if (step && step.message) {
          appendToastMessage(step.message);
        }
      }
      lastProgressLength = data.steps.length;
    }
    if (data.status === 'completed') {
      setToastActionsVisible(true);
      stopProgressPolling();
    } else if (data.status === 'failed') {
      toastTitle.textContent = data.headline || 'Album save failed';
      setToastActionsVisible(true);
      stopProgressPolling();
    }
  }

  async function fetchProgressOnce() {
    if (!currentJobId) return;
    try {
      const res = await fetch(`album_progress.php?job=${encodeURIComponent(currentJobId)}&t=${Date.now()}`, {
        cache: 'no-store'
      });
      if (!res.ok) return;
      const payload = await res.json();
      handleProgressPayload(payload);
    } catch (err) {
      // network issues while polling can be ignored
    }
  }

  function startProgressPolling() {
    stopProgressPolling();
    fetchProgressOnce();
    progressInterval = setInterval(fetchProgressOnce, 2000);
  }

  function stopProgressPolling() {
    if (progressInterval) {
      clearInterval(progressInterval);
      progressInterval = null;
    }
  }

  toastReload.addEventListener('click', () => {
    location.reload();
  });

  toastDismiss.addEventListener('click', () => {
    toast.style.display = 'none';
    stopProgressPolling();
    currentJobId = null;
  });

  toastToggle.addEventListener('click', () => {
    const collapsed = toast.dataset.collapsed === 'true';
    setToastCollapsed(!collapsed);
  });

  form.addEventListener('submit', e => {
    e.preventDefault();

    stopProgressPolling();
    currentJobId = null;
    toast.style.display = 'none';
    toastLog.innerHTML = '';
    toastStatus.textContent = '';
    setToastActionsVisible(false);
    setToastCollapsed(loadToastCollapsedPreference(), { skipPersist: true });

    const total = getTotalSize();
    if (total > SIZE_LIMIT) {
      alert('Total upload size exceeds 100MB. Please upload smaller files.');
      return;
    }

    overlay.style.display = 'flex';

    const bar = overlay.querySelector('.bar');
    const pct = document.getElementById('upload-percent');
    const msgSpan = overlay.querySelector('#upload-status');
    const cancelB = document.getElementById('cancel-upload');

    const jobId = 'alb-' + Date.now().toString(36) + Math.random().toString(36).slice(2, 8);
    const formData = new FormData(form);
    formData.append('job_id', jobId);

    const xhr = new XMLHttpRequest();
    currentXhr = xhr;

    xhr.open('POST', form.action);

    let processingShown = false;

    xhr.upload.onprogress = ev => {
      if (ev.lengthComputable) {
        const percent = Math.round((ev.loaded / ev.total) * 100);
        bar.style.width = percent + '%';
        pct.textContent = percent + '%';

        if (percent === 100) {
          msgSpan.textContent = 'Upload complete, processing…';
          if (!processingShown) {
            overlay.style.display = 'none';
            resetToast(jobId, 'Processing album on server. You can continue working while we finish.');
            appendToastMessage('Upload complete. Processing on server…');
            startProgressPolling();
            processingShown = true;
          }
        }
      }
    };

    // 2) When the full request (including server processing) completes
    xhr.onload = () => {
      overlay.style.display = 'none';
      if (xhr.status >= 200 && xhr.status < 400) {
        if (!processingShown) {
          resetToast(jobId, 'Finalising album save…');
          startProgressPolling();
          processingShown = true;
        } else {
          fetchProgressOnce();
        }
      } else {
        if (xhr.status === 504) {
          if (!processingShown) {
            resetToast(jobId, 'Upload complete, processing…');
            appendToastMessage('Upload complete. Processing on server…');
            startProgressPolling();
            processingShown = true;
          } else {
            appendToastMessage('Server still processing… (received 504)');
          }
          return;
        }
        if (!processingShown) {
          resetToast(jobId, 'Upload failed');
          processingShown = true;
        }
        stopProgressPolling();
        currentJobId = null;
        toastTitle.textContent = 'Upload failed';
        let errorMessage = 'Upload failed.';
        const contentType = xhr.getResponseHeader('Content-Type') || '';
        if (contentType.includes('application/json')) {
          try {
            const data = JSON.parse(xhr.responseText || '{}');
            if (data && typeof data.message === 'string') {
              errorMessage = data.message;
            } else if (Array.isArray(data.errors) && data.errors.length) {
              errorMessage = data.errors.join('; ');
            }
          } catch (err) {
            // ignore parse errors
          }
        } else if (xhr.responseText) {
          errorMessage = 'Upload failed (' + xhr.status + ').';
        }
        toastStatus.textContent = errorMessage;
        appendToastMessage(errorMessage);
        setToastActionsVisible(true);
      }
    };

    xhr.onerror = () => {
      overlay.style.display = 'none';
      if (!processingShown) {
        resetToast(jobId, 'Network error');
        processingShown = true;
      }
      stopProgressPolling();
      currentJobId = null;
      toastTitle.textContent = 'Upload failed';
      const errorMessage = 'Network error occurred while saving the album.';
      toastStatus.textContent = errorMessage;
      appendToastMessage(errorMessage);
      setToastActionsVisible(true);
    };
    // Cancel button aborts the upload
    cancelB.onclick = () => {
      if (currentXhr) {
        currentXhr.abort();
        overlay.style.display = 'none';
        stopProgressPolling();
        currentJobId = null;
        toast.style.display = 'none';
      }
    };
    xhr.send(formData);
  });
  function updateIndexes() {
    tracks.querySelectorAll('.track').forEach((row, i) => {
      row.dataset.index = i;
      row.draggable = tracks.classList.contains('sort-mode');   // ← only draggable in sort-mode
      row.querySelector('.track-number').textContent = i + 1;
      row.querySelectorAll('input,textarea').forEach(inp => {
        if (inp.name) inp.name = inp.name.replace(/tracks\[\d+\]/, 'tracks[' + i + ']');
      });
    });
  }
  addBtn.addEventListener('click', () => {
    const i = tracks.querySelectorAll('.track').length;
    const row = document.createElement('div');
    row.className = 'track';
    //row.draggable=true;
    row.innerHTML = `<span class="track-number">${i + 1}</span>` +
      `<input type="text" name="tracks[${i}][title]" placeholder="Title">` +
      `<input type="text" name="tracks[${i}][length]" placeholder="Length">` +
      `<input type="hidden" name="tracks[${i}][existing_file]" value="">` +
      `<input type="file" name="tracks[${i}][file]">` +
      `<input type="text" name="tracks[${i}][artist]" placeholder="Artist">` +
      `<input type="text" name="tracks[${i}][year]" placeholder="Year">` +
      `<input type="text" name="tracks[${i}][genre]" placeholder="Genre">` +
      `<input type="text" name="tracks[${i}][composer]" placeholder="Composer">` +
      `<input type="text" name="tracks[${i}][comment]" placeholder="Comment">` +
      `<input type="text" name="tracks[${i}][lyricist]" placeholder="Lyricist">` +
      `<label class="explicit-field">Explicit? <input type="checkbox" name="tracks[${i}][explicit]" value="1"></label>` +
      `<textarea name="tracks[${i}][lyrics]" placeholder="Lyrics"></textarea>` +
      `<button type="button" class="remove-track">Remove</button>`;
  tracks.appendChild(row);
  const albumGenre = document.querySelector('input[name="genre"]');
  if (albumGenre && albumGenre.value) {
    const gInput = row.querySelector('input[name$="[genre]"]');
    if (gInput && !gInput.value) gInput.value = albumGenre.value;
  }
  updateSizeWarning();
});

  const albumGenreInput = document.querySelector('input[name="genre"]');
  if (albumGenreInput) {
    albumGenreInput.addEventListener('change', () => {
      tracks.querySelectorAll('.track input[name$="[genre]"]').forEach(inp => {
        if (!inp.value) inp.value = albumGenreInput.value;
      });
    });
  }
  tracks.addEventListener('click', e => {
    if (e.target.classList.contains('remove-track')) {
      e.target.parentElement.remove();
      updateIndexes();
      updateSizeWarning();
    }
  });
  let dragSrc = null;
  tracks.addEventListener('dragstart', e => {
    if (!tracks.classList.contains('sort-mode')) return;             // ← guard
    if (e.target.classList.contains('track')) {
      dragSrc = e.target;
      e.dataTransfer.effectAllowed = 'move';
      e.target.classList.add('dragging');
    }
  });
  tracks.addEventListener('dragend', e => {
    if (!tracks.classList.contains('sort-mode')) return;             // ← guard
    if (e.target.classList.contains('track')) {
      e.target.classList.remove('dragging');
    }
  });
  tracks.addEventListener('dragover', e => {
    if (!tracks.classList.contains('sort-mode')) return;             // ← guard
    if (e.target.classList.contains('track')) e.preventDefault();
  });
  tracks.addEventListener('drop', e => {
    if (!tracks.classList.contains('sort-mode')) return;             // ← guard
    if (dragSrc && e.target.classList.contains('track')) {
      e.preventDefault();
      if (dragSrc !== e.target) {
        const rect = e.target.getBoundingClientRect();
        const after = (e.clientY - rect.top) > rect.height / 2;
        tracks.insertBefore(dragSrc, after ? e.target.nextSibling : e.target);
        updateIndexes();
      }
    }
  });
  tracks.addEventListener('change', e => {
    if (e.target.type === 'file' && e.target.files[0]) {
      const row = e.target.closest('.track');
      const lenInput = row.querySelector('input[name$="[length]"]');
      const audio = new Audio();
      audio.preload = 'metadata';
      audio.src = URL.createObjectURL(e.target.files[0]);
      audio.addEventListener('loadedmetadata', () => {
        const d = Math.round(audio.duration);
        const m = String(Math.floor(d / 60)).padStart(2, '0');
        const s = String(d % 60).padStart(2, '0');
        lenInput.value = `${m}:${s}`;
        URL.revokeObjectURL(audio.src);
      });
      updateSizeWarning();
    }
  });

  toggleSort.addEventListener('click', () => {
    const active = tracks.classList.toggle('sort-mode');
    saveSort.style.display = active ? 'inline' : 'none';
    updateIndexes();  // enable/disable draggable on every track
  });

  saveSort.addEventListener('click', () => {
    // … your existing save-sort logic …
    tracks.classList.remove('sort-mode');
    saveSort.style.display = 'none';
    updateIndexes();  // disable draggable again
  });

  if (zipBtn) {
    zipBtn.addEventListener('click', () => {
      const fd = new FormData();
      fd.append('album', document.querySelector('input[name="original_name"]').value);
      fetch('generate_zips.php', {
        method: 'POST',
        body: new URLSearchParams({ album: document.querySelector('input[name="original_name"]').value })
      })
        .then(async res => {
          const text = await res.text();
          let data;
          try {
            data = JSON.parse(text);
          } catch (e) {
            throw new Error('Non-JSON response:\n' + text);
          }
          if (data.success) {
            alert(
              `✅ MP3 Zip (${data.mp3Count} files):\n${data.mp3Zip}` +
              (data.allFlac
                ? `\n✅ FLAC Zip (${data.flacCount} files):\n${data.flacZip}`
                : `\n⚠️ Not all tracks had FLACs; no FLAC zip created.`) +
              (data.allWav
                ? `\n✅ WAV Zip (${data.wavCount} files):\n${data.wavZip}`
                : `\n⚠️ Not all tracks had WAVs; no WAV zip created.`)
            );
          } else {
            throw new Error('Server error: ' + data.error);
          }
        })
        .catch(err => {
          alert('❌ Zip generation failed:\n' + err.message);
          console.error(err);
        });
    });
  }

  const bumpBtn = document.getElementById('bump-version');
  if (bumpBtn) {
    bumpBtn.addEventListener('click', () => {
      fetch('bump_version.php', { method: 'POST' })
        .then(res => {
          if (!res.ok) throw new Error('HTTP ' + res.status);
          return res.text();
        })
        .then(() => alert('Asset cache reset'))
        .catch(err => {
          alert('Failed to reset version:\n' + err.message);
          console.error(err);
        });
    });
  }
  updateSizeWarning();
});
