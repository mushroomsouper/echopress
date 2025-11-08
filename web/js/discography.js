window.addEventListener('DOMContentLoaded', function () {
  document.querySelectorAll('.discography-page .section-header').forEach(function (header) {
    var section = header.closest('section');
    if (!section) return;
    header.addEventListener('click', function (e) {
      if (e.target.closest('.scroll-arrows')) return;
      section.classList.toggle('collapsed');
    });
  });
});
