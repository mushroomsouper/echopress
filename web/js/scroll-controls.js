// Scroll arrows for album lists
window.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.scroll-arrows').forEach(function (wrapper) {
    const section = wrapper.closest('section');
    if (!section) return;
    const list = section.querySelector('.album-list');
    if (!list) return;
    const left = wrapper.querySelector('.left');
    const right = wrapper.querySelector('.right');
    const items = list.querySelectorAll('.album-item');
    const autoHeight = section.hasAttribute('data-autoheight');

    function amount() {
      return list.clientWidth * 0.8;
    }

    function updateHeight() {
      if (!autoHeight) return;
      const index = Math.round(list.scrollLeft / list.clientWidth);
      const item = items[index];
      if (item) {
        list.style.height = item.offsetHeight + 'px';
      }
    }

    if (left) {
      left.addEventListener('click', function () {
        list.scrollBy({ left: -amount(), behavior: 'smooth' });
      });
    }
    if (right) {
      right.addEventListener('click', function () {
        list.scrollBy({ left: amount(), behavior: 'smooth' });
      });
    }

    if (autoHeight) {
      list.addEventListener('scroll', function () {
        requestAnimationFrame(updateHeight);
      });
      window.addEventListener('resize', updateHeight);
      updateHeight();
    }
  });
});
