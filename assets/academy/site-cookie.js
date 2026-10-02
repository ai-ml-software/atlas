(function () {
  'use strict';
  const notice = document.querySelector('[data-site-cookie]');
  if (!notice) return;
  try { if (localStorage.getItem('accept_cookie_academy')) return; } catch (_) { /* Consent still works for this page. */ }
  notice.hidden = false;
  notice.querySelector('[data-cookie-accept]').addEventListener('click', () => {
    try {
      localStorage.setItem('accept_cookie_academy', 'true');
      localStorage.setItem('accept_cookie_time', new Date().toISOString());
    } catch (_) { /* Storage may be disabled. */ }
    notice.hidden = true;
  });
})();
