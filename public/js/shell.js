/*
 * Sidebar behaviour shared by every portal (student, TPO, coordinator).
 * State lives on <html> so the first paint is already right (layouts/portal.blade.php restores it inline):
 *   nav-collapsed  icon rail, 768px and wider      nav-open  off-canvas menu, below 768px
 * Wiring: [data-nav-toggle] toggles, [data-nav-close] (the scrim) and a tap on a menu link close the overlay.
 */
(function () {
  'use strict';
  var root = document.documentElement;
  var overlay = function () { return window.innerWidth < 768; };

  function sync() {
    var b = document.querySelector('[data-nav-toggle]');
    if (!b) { return; }
    if (overlay()) {
      b.setAttribute('aria-label', 'Open menu');
      b.setAttribute('aria-expanded', String(root.classList.contains('nav-open')));
    } else {
      var collapsed = root.classList.contains('nav-collapsed');
      b.setAttribute('aria-label', collapsed ? 'Expand sidebar' : 'Collapse sidebar');
      b.setAttribute('aria-expanded', String(!collapsed));
    }
  }

  document.addEventListener('click', function (e) {
    var t = e.target;
    if (!t.closest) { return; }
    if (t.closest('[data-nav-toggle]')) {
      if (overlay()) { root.classList.toggle('nav-open'); }
      else {
        var c = root.classList.toggle('nav-collapsed');
        try { localStorage.setItem('tpms.nav', c ? 'collapsed' : 'expanded'); } catch (err) { /* private mode: state just is not remembered */ }
      }
      sync();
    } else if (t.closest('[data-nav-close]') || (overlay() && t.closest('.sidebar a'))) {
      root.classList.remove('nav-open');
      sync();
    }
  });

  document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && root.classList.contains('nav-open')) { root.classList.remove('nav-open'); sync(); }
  });

  window.addEventListener('resize', function () {
    if (!overlay()) { root.classList.remove('nav-open'); }
    sync();
  });

  sync();
})();
