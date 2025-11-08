document.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.video-item').forEach(function (item) {
    item.addEventListener('click', function () {
      var url = item.dataset.url;
      if (!url) return;
      var overlay = document.createElement('div');
      overlay.className = 'video-overlay';
      var iframe = document.createElement('iframe');
      iframe.src = url;
      iframe.allowFullscreen = true;
      var closeBtn = document.createElement('button');
      closeBtn.className = 'close-video';
      closeBtn.innerHTML = '\u00d7';
      var fullBtn = document.createElement('button');
      fullBtn.className = 'fullscreen-video';
      fullBtn.innerHTML = '\u26F6';
      var container = document.createElement('div');
      container.style.position = 'relative';
      container.appendChild(iframe);
      container.appendChild(closeBtn);
      container.appendChild(fullBtn);
      overlay.appendChild(container);
      function close() { overlay.remove(); }
      closeBtn.addEventListener('click', close);
      overlay.addEventListener('click', function (e) {
        if (e.target === overlay) close();
      });
      fullBtn.addEventListener('click', function () {
        if (iframe.requestFullscreen) iframe.requestFullscreen();
      });
      var audio = document.querySelector('.audio-player-container.floating-player .audio-element');
      if (audio && !audio.paused) audio.pause();
      document.body.appendChild(overlay);
    });
  });
});
