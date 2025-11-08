(function () {
  document.addEventListener('DOMContentLoaded', () => {
    const trackList = document.querySelector('#available-tracks');
    const selectedList = document.querySelector('#selected-tracks');
    const emptyState = document.querySelector('#selected-tracks-empty');
    const searchInput = document.querySelector('#track-search');
    const shuffleBtn = document.querySelector('#shuffle-tracks');
    if (!trackList || !selectedList) {
      return;
    }

    const dataNode = document.querySelector('#available-tracks-data');
    const trackData = dataNode ? JSON.parse(dataNode.textContent || '[]') : [];
    const trackMap = new Map(trackData.map(item => [item.id, item]));

    const selectedSet = new Set(Array.from(selectedList.querySelectorAll('.selected-track')).map(el => Number(el.dataset.trackId)));

    function updateEmptyState() {
      if (!emptyState) return;
      emptyState.hidden = selectedList.children.length > 0;
    }

    function findCheckbox(id) {
      return trackList.querySelector(`input[type="checkbox"][value="${id}"]`);
    }

    function createTrackItem(id) {
      const info = trackMap.get(id);
      if (!info) {
        // Fallback using DOM label if JSON missing
        const label = trackList.querySelector(`.track-option[data-track-id="${id}"]`);
        if (!label) return null;
        const title = label.querySelector('.track-option__title')?.textContent || `Track ${id}`;
        const meta = label.querySelector('.track-option__meta')?.textContent || '';
        return templateFromStrings(id, title, meta);
      }
      const parts = [];
      parts.push(info.albumTitle);
      if (Number.isFinite(info.trackNumber)) {
        parts.push(`Track ${info.trackNumber}`);
      }
      if (info.length) {
        parts.push(info.length);
      }
      if (info.explicit) {
        parts.push('Explicit');
      }
      const metaText = parts.join(' · ');
      return templateFromStrings(id, info.title, metaText);
    }

    function templateFromStrings(id, title, meta) {
      const li = document.createElement('li');
      li.className = 'selected-track';
      li.dataset.trackId = String(id);
      li.innerHTML = `
        <input type="hidden" name="tracks[]" value="${id}">
        <div class="selected-track__info">
          <span class="selected-track__title"></span>
          <span class="selected-track__meta"></span>
        </div>
        <div class="selected-track__actions">
          <button type="button" class="selected-track__btn" data-action="move-up" aria-label="Move up"><i class="fa-solid fa-chevron-up"></i></button>
          <button type="button" class="selected-track__btn" data-action="move-down" aria-label="Move down"><i class="fa-solid fa-chevron-down"></i></button>
          <button type="button" class="selected-track__btn" data-action="remove" aria-label="Remove"><i class="fa-solid fa-xmark"></i></button>
        </div>`;
      li.querySelector('.selected-track__title').textContent = title;
      li.querySelector('.selected-track__meta').textContent = meta;
      return li;
    }

    function addTrack(id) {
      if (selectedSet.has(id)) return;
      const item = createTrackItem(id);
      if (!item) return;
      selectedList.appendChild(item);
      selectedSet.add(id);
      updateEmptyState();
    }

    function removeTrack(id) {
      const item = selectedList.querySelector(`.selected-track[data-track-id="${id}"]`);
      if (item) {
        selectedList.removeChild(item);
      }
      selectedSet.delete(id);
      const checkbox = findCheckbox(id);
      if (checkbox) {
        checkbox.checked = false;
      }
      updateEmptyState();
    }

    function moveItem(item, direction) {
      if (!item) return;
      if (direction === -1) {
        const prev = item.previousElementSibling;
        if (prev) {
          selectedList.insertBefore(item, prev);
        }
      } else if (direction === 1) {
        const next = item.nextElementSibling;
        if (next) {
          selectedList.insertBefore(next, item);
        }
      }
    }

    function shuffleSelected() {
      const items = Array.from(selectedList.querySelectorAll('.selected-track'));
      if (items.length < 2) return;
      const shuffled = items.slice();
      for (let i = shuffled.length - 1; i > 0; i -= 1) {
        const j = Math.floor(Math.random() * (i + 1));
        [shuffled[i], shuffled[j]] = [shuffled[j], shuffled[i]];
      }
      shuffled.forEach((item) => {
        selectedList.appendChild(item);
      });
      updateEmptyState();
    }

    trackList.addEventListener('change', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLInputElement) || target.type !== 'checkbox') return;
      const id = Number(target.value);
      if (!id) return;
      if (target.checked) {
        addTrack(id);
      } else {
        removeTrack(id);
      }
    });

    selectedList.addEventListener('click', (event) => {
      const target = event.target;
      if (!(target instanceof HTMLElement)) return;
      const button = target.closest('button[data-action]');
      if (!button) return;
      const action = button.getAttribute('data-action');
      const item = button.closest('.selected-track');
      if (!item) return;
      const id = Number(item.dataset.trackId);
      switch (action) {
        case 'move-up':
          moveItem(item, -1);
          break;
        case 'move-down':
          moveItem(item, 1);
          break;
        case 'remove':
          removeTrack(id);
          break;
      }
    });

    if (searchInput) {
      searchInput.addEventListener('input', () => {
        const query = searchInput.value.trim().toLowerCase();
        const options = trackList.querySelectorAll('.track-option');
        options.forEach((option) => {
          const title = option.dataset.title || '';
          const album = option.dataset.album || '';
          const match = !query || title.includes(query) || album.includes(query);
          option.style.display = match ? '' : 'none';
        });
      });
    }

    shuffleBtn?.addEventListener('click', shuffleSelected);

    updateEmptyState();
  });
}());
