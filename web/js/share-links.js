const SHARE_SUCCESS_TIMEOUT = 1500;
const SHARE_ERROR_TIMEOUT = 2000;
const shareMenuControllers = new Map();

function copyToClipboard(text) {
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
}

function resetOptionFeedback(option) {
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
}

function showOptionFeedback(option, status) {
  if (!option) return;
  const srFeedback = option.querySelector('.share-feedback-sr');
  if (srFeedback && !srFeedback.dataset.defaultText) {
    srFeedback.dataset.defaultText = srFeedback.textContent || '';
  }
  const timeout = status === 'success' ? SHARE_SUCCESS_TIMEOUT : SHARE_ERROR_TIMEOUT;
  if (srFeedback) {
    srFeedback.textContent = status === 'success'
      ? 'Link copied to clipboard.'
      : 'Copy failed. Please try again.';
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
}

function initAlbumShareMenu() {
  const shareMenus = Array.from(document.querySelectorAll('.share-menu'));
  if (!shareMenus.length) return;

  shareMenus.forEach((shareMenu) => {
    if (shareMenu.dataset.shareMenuInit === '1') {
      return;
    }
    shareMenu.dataset.shareMenuInit = '1';

    const trigger = shareMenu.querySelector('.share-btn');
    const overlay = shareMenu.querySelector('.share-overlay');
    const panel = overlay ? overlay.querySelector('.share-panel') : null;
    const closeBtn = shareMenu.querySelector('.share-close');
    const options = Array.from(shareMenu.querySelectorAll('.share-option'));

    options.forEach((option) => {
      const srFeedback = option.querySelector('.share-feedback-sr');
      if (srFeedback && !srFeedback.dataset.defaultText) {
        srFeedback.dataset.defaultText = srFeedback.textContent || 'Share link ready';
      }
    });

    let lastActive = null;

    const handleEscape = (event) => {
      if (event.key === 'Escape' && shareMenu.classList.contains('open')) {
        setOpenState(false, { returnFocus: true });
      }
    };

    const setOpenState = (isOpen, { returnFocus } = { returnFocus: false }) => {
      if (!overlay || !trigger) return;

      if (isOpen) {
        if (shareMenu.classList.contains('open')) return;
        shareMenuControllers.forEach((controller, menuEl) => {
          if (menuEl !== shareMenu) {
            controller.close({ returnFocus: false });
          }
        });
        lastActive = document.activeElement;
        shareMenu.classList.add('open');
        overlay.setAttribute('aria-hidden', 'false');
        trigger.setAttribute('aria-expanded', 'true');
        document.body.classList.add('share-menu-open');
        document.addEventListener('keydown', handleEscape);
        window.setTimeout(() => {
          if (panel) {
            panel.focus({ preventScroll: true });
          } else if (options[0]) {
            options[0].focus({ preventScroll: true });
          }
        }, 50);
      } else {
        if (!shareMenu.classList.contains('open')) return;
        shareMenu.classList.remove('open');
        overlay.setAttribute('aria-hidden', 'true');
        trigger.setAttribute('aria-expanded', 'false');
        document.removeEventListener('keydown', handleEscape);
        options.forEach(resetOptionFeedback);
        if (returnFocus && trigger) {
          trigger.focus({ preventScroll: true });
        } else if (returnFocus && lastActive && typeof lastActive.focus === 'function') {
          lastActive.focus({ preventScroll: true });
        }
        lastActive = null;
        if (!document.querySelector('.share-menu.open')) {
          document.body.classList.remove('share-menu-open');
        }
      }
    };

    shareMenuControllers.set(shareMenu, {
      close: (options = { returnFocus: false }) => setOpenState(false, options)
    });

    if (trigger) {
      trigger.addEventListener('click', (event) => {
        event.preventDefault();
        event.stopPropagation();
        const shouldOpen = !shareMenu.classList.contains('open');
        setOpenState(shouldOpen, { returnFocus: !shouldOpen });
      });
    }

    if (closeBtn) {
      closeBtn.addEventListener('click', (event) => {
        event.stopPropagation();
        setOpenState(false, { returnFocus: true });
      });
    }

    if (overlay) {
      overlay.addEventListener('click', (event) => {
        if (!panel || panel.contains(event.target)) {
          return;
        }
        setOpenState(false, { returnFocus: true });
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
  });
}

function initTrackShareButtons() {
  const activePopovers = new Map();

  const closePopover = (btn) => {
    const existing = activePopovers.get(btn);
    if (existing) {
      existing.remove();
      activePopovers.delete(btn);
    }
  };

  document.querySelectorAll('.share-track').forEach((btn) => {
    const url = btn.dataset.url;
    if (!url) return;

    btn.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();

      const existing = activePopovers.get(btn);
      if (existing) {
        closePopover(btn);
        return;
      }

      const popover = document.createElement('div');
      popover.className = 'share-popover';

      const input = document.createElement('input');
      input.type = 'text';
      input.readOnly = true;
      input.value = url;
      input.setAttribute('aria-label', 'Share link');

      const copyBtn = document.createElement('button');
      copyBtn.type = 'button';
      copyBtn.className = 'copy-url-btn';
      copyBtn.setAttribute('aria-label', 'Copy link');
      copyBtn.innerHTML = '<i class="fas fa-copy" aria-hidden="true"></i>';

      copyBtn.addEventListener('click', async () => {
        try {
          await copyToClipboard(url);
          copyBtn.classList.add('copied');
          setTimeout(() => copyBtn.classList.remove('copied'), 1200);
        } catch (err) {
          console.error('Copy to clipboard failed', err);
          copyBtn.classList.add('copy-error');
          setTimeout(() => copyBtn.classList.remove('copy-error'), 1600);
        }
      });

      popover.appendChild(input);
      popover.appendChild(copyBtn);

      popover.addEventListener('click', (ev) => ev.stopPropagation());

      btn.insertAdjacentElement('afterend', popover);
      activePopovers.set(btn, popover);

      const handleClickAway = (ev) => {
        if (!popover.contains(ev.target) && ev.target !== btn) {
          closePopover(btn);
          document.removeEventListener('click', handleClickAway);
        }
      };

      setTimeout(() => document.addEventListener('click', handleClickAway), 0);
    });
  });
}

function initShareFeatures() {
  initTrackShareButtons();
  initAlbumShareMenu();
}

if (document.readyState === 'loading') {
  document.addEventListener('DOMContentLoaded', initShareFeatures);
} else {
  initShareFeatures();
}
