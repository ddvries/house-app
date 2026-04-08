(function () {
  var button = document.querySelector('.hamburger-btn');
  var nav = document.getElementById('site-nav');
  if (!button || !nav) {
    return;
  }

  var openLabel = button.getAttribute('data-label-open') || 'Open menu';
  var closeLabel = button.getAttribute('data-label-close') || 'Close menu';

  function closeMenu() {
    nav.classList.remove('is-open');
    button.setAttribute('aria-expanded', 'false');
    button.setAttribute('aria-label', openLabel);
  }

  function toggleMenu() {
    var isOpen = nav.classList.toggle('is-open');
    button.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
    button.setAttribute('aria-label', isOpen ? closeLabel : openLabel);
  }

  button.addEventListener('click', function (event) {
    event.stopPropagation();
    toggleMenu();
  });

  document.addEventListener('click', function (event) {
    if (!nav.classList.contains('is-open')) {
      return;
    }
    if (nav.contains(event.target) || button.contains(event.target)) {
      return;
    }
    closeMenu();
  });

  document.addEventListener('keydown', function (event) {
    if (event.key === 'Escape') {
      closeMenu();
    }
  });

  window.addEventListener('resize', function () {
    if (window.innerWidth > 860) {
      closeMenu();
    }
  });
})();
