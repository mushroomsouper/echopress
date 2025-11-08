(function() {
  const toggleButton = document.getElementById('dark-toggle');
  const toggleIcon   = document.getElementById('toggle-icon');
  const root         = document.documentElement; // <html>
  const storageKey   = 'darkModeEnabled';

  // 1. On load: read saved preference, apply dark-mode, and set icon
  const currentSetting = localStorage.getItem(storageKey);
  if (currentSetting === 'true') {
    root.classList.add('dark-mode');
    toggleIcon.classList.replace('fas', 'far');
  } else {
    toggleIcon.classList.replace('far', 'fas');
  }

  // 2. On click: toggle dark-mode and swap icon classes
  toggleButton.addEventListener('click', () => {
    const isDark = root.classList.toggle('dark-mode');
    if (isDark) {
      toggleIcon.classList.replace('fas', 'far');
    } else {
      toggleIcon.classList.replace('far', 'fas');
    }
    localStorage.setItem(storageKey, isDark);
  });
})();
