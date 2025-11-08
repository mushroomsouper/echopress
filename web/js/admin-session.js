(function () {
  if (window.__adminSessionMonitorActive) {
    return;
  }
  window.__adminSessionMonitorActive = true;

  const PING_URL = '/admin/session_status.php';
  const NORMAL_INTERVAL = 2 * 60 * 1000; // 2 minutes
  const RETRY_INTERVAL = 30 * 1000; // retry faster when logged out or on error
  let lastTimer = null;
  let warningVisible = false;

  function clearScheduled() {
    if (lastTimer !== null) {
      window.clearTimeout(lastTimer);
      lastTimer = null;
    }
  }

  function schedule(nextDelay) {
    clearScheduled();
    lastTimer = window.setTimeout(checkSession, nextDelay);
  }

  function ensureWarning() {
    if (warningVisible) {
      return;
    }

    warningVisible = true;

    let banner = document.getElementById('admin-session-warning');
    if (!banner) {
      banner = document.createElement('div');
      banner.id = 'admin-session-warning';
      banner.style.position = 'fixed';
      banner.style.top = '0';
      banner.style.left = '0';
      banner.style.right = '0';
      banner.style.background = '#b00020';
      banner.style.color = '#ffffff';
      banner.style.padding = '1rem';
      banner.style.zIndex = '2147483647';
      banner.style.textAlign = 'center';
      banner.style.boxShadow = '0 2px 6px rgba(0,0,0,0.4)';

      const message = document.createElement('div');
      message.style.fontSize = '1rem';
      message.style.marginBottom = '0.5rem';
      message.textContent = 'Your admin session has expired. Open the login page in a new tab, sign in, and then come back here before saving.';
      banner.appendChild(message);

      const links = document.createElement('div');

      const loginLink = document.createElement('a');
      loginLink.href = '/admin/login.php';
      loginLink.target = '_blank';
      loginLink.rel = 'noopener';
      loginLink.style.color = '#ffe082';
      loginLink.style.fontWeight = 'bold';
      loginLink.style.marginRight = '1.5rem';
      loginLink.textContent = 'Open login page';
      links.appendChild(loginLink);

      const retryButton = document.createElement('button');
      retryButton.type = 'button';
      retryButton.style.background = '#ffffff';
      retryButton.style.color = '#b00020';
      retryButton.style.border = 'none';
      retryButton.style.padding = '0.5rem 1rem';
      retryButton.style.borderRadius = '4px';
      retryButton.style.cursor = 'pointer';
      retryButton.textContent = "I'm logged in again";
      retryButton.addEventListener('click', function () {
        checkSession(true);
      });
      links.appendChild(retryButton);

      banner.appendChild(links);
      document.body.appendChild(banner);
    }
  }

  function hideWarning() {
    warningVisible = false;
    const banner = document.getElementById('admin-session-warning');
    if (banner) {
      banner.remove();
    }
  }

  async function checkSession(forceImmediate) {
    clearScheduled();

    if (!window.fetch) {
      // Browser too old; nothing we can do.
      return;
    }

    try {
      const response = await fetch(PING_URL, {
        method: 'GET',
        credentials: 'same-origin',
        cache: 'no-store'
      });

      if (response.ok) {
        hideWarning();
        schedule(NORMAL_INTERVAL);
        return;
      }

      if (response.status === 401) {
        ensureWarning();
        schedule(RETRY_INTERVAL);
        return;
      }

      // Other non-OK responses – retry after a bit
      schedule(RETRY_INTERVAL);
    } catch (err) {
      // Network error – retry quickly
      schedule(RETRY_INTERVAL);
    }
  }

  document.addEventListener('visibilitychange', function () {
    if (document.visibilityState === 'visible') {
      checkSession(true);
    }
  });

  document.addEventListener('submit', function (event) {
    if (warningVisible) {
      event.preventDefault();
      ensureWarning();
      alert('You have been logged out. Please log in again in a new tab, then come back and save.');
    }
  }, true);

  if (document.readyState === 'complete' || document.readyState === 'interactive') {
    schedule(0);
  } else {
    document.addEventListener('DOMContentLoaded', function () {
      schedule(0);
    });
  }
})();
