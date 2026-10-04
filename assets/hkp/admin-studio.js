(function () {
  'use strict';
  document.querySelectorAll('[data-copy-text]').forEach(function (button) {
    button.addEventListener('click', async function () {
      var status = document.querySelector('#mcp-copy-status');
      try { await navigator.clipboard.writeText(button.dataset.copyText); if (status) status.textContent = button.dataset.copyOk; }
      catch (_) { if (status) status.textContent = button.dataset.copyError; }
    });
  });
  var side = document.querySelector('.hkp-side');
  var groups = document.querySelectorAll('[data-nav-group]');
    var primary = document.querySelector('.studio-nav-primary');
  function read(key) { try { return localStorage.getItem(key); } catch (_) { return null; } }
  function write(key, value) { try { localStorage.setItem(key, value); } catch (_) {} }
  var collapse = document.querySelector('[data-collapse-sidebar]');
  if (collapse) {
    if (read('altus.sidebar.collapsed') === 'true') document.body.classList.add('studio-collapsed');
    collapse.setAttribute('aria-expanded', String(!document.body.classList.contains('studio-collapsed')));
    collapse.addEventListener('click', function () { var state = document.body.classList.toggle('studio-collapsed'); write('altus.sidebar.collapsed', String(state)); collapse.setAttribute('aria-expanded', String(!state)); });
  }
  groups.forEach(function (g) {
    var key = 'altus.nav.' + g.dataset.navGroup;
    if (!g.querySelector('[aria-current="page"]') && read(key) !== null) g.open = read(key) === 'true';
    g.addEventListener('toggle', function () { if (!document.querySelector('#studio-nav-search').value) write(key, String(g.open)); });
  });
  var filter = document.querySelector('#studio-nav-search');
  if (filter) filter.addEventListener('input', function () {
    var q = filter.value.toLocaleLowerCase();
      document.querySelectorAll('.hkp-nav a').forEach(function(a){a.hidden=!a.textContent.toLocaleLowerCase().includes(q);});
      if (primary) primary.querySelectorAll('a').forEach(function(a){a.hidden=!a.textContent.toLocaleLowerCase().includes(q);});
    groups.forEach(function (g) { var count = 0; g.querySelectorAll('a').forEach(function (a) { a.hidden = !a.textContent.toLocaleLowerCase().includes(q); if (!a.hidden) count++; }); g.hidden = !count; if (q) g.open = true; else g.open = !!g.querySelector('[aria-current="page"]') || read('altus.nav.' + g.dataset.navGroup) === 'true' || g.dataset.navGroup === 'overview'; });
  });
  var dialog = document.querySelector('#studio-command');
  var input = document.querySelector('#studio-command-input');
  if (dialog && input) {
    function open() { dialog.showModal(); input.value = ''; input.dispatchEvent(new Event('input')); input.focus(); }
    document.querySelectorAll('[data-open-command]').forEach(function (b) { b.addEventListener('click', open); });
    document.querySelector('[data-close-command]').addEventListener('click', function () { dialog.close(); });
    dialog.addEventListener('keydown', function (e) { if (e.key === 'Escape') { e.preventDefault(); dialog.close(); } });
    dialog.addEventListener('click', function (e) { if (e.target === dialog) { var r = dialog.getBoundingClientRect(); if (e.clientX < r.left || e.clientX > r.right || e.clientY < r.top || e.clientY > r.bottom) dialog.close(); } });
    document.addEventListener('keydown', function (e) { if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') { e.preventDefault(); if (dialog.open) dialog.close(); else open(); } });
    input.addEventListener('input', function () { dialog.querySelectorAll('.studio-command-results a').forEach(function (a) { a.hidden = !a.textContent.toLocaleLowerCase().includes(input.value.toLocaleLowerCase()); }); });
    input.addEventListener('keydown', function (e) { if (e.key === 'ArrowDown' || e.key === 'Enter') { var a = dialog.querySelector('.studio-command-results a:not([hidden])'); if (a) { e.preventDefault(); if (e.key === 'Enter') a.click(); else a.focus(); } } });
    dialog.addEventListener('keydown', function (e) { if (!['ArrowDown', 'ArrowUp'].includes(e.key) || e.target === input) return; var links = Array.from(dialog.querySelectorAll('.studio-command-results a:not([hidden])')); var i = links.indexOf(document.activeElement); if (i >= 0) { e.preventDefault(); links[(i + (e.key === 'ArrowDown' ? 1 : -1) + links.length) % links.length].focus(); } });
  }
  // Helpful client filtering augments existing server pagination and permission checks.
  document.querySelectorAll('[data-studio-filter]').forEach(function (field) {
    field.addEventListener('input', function () { var q = field.value.toLocaleLowerCase(); document.querySelectorAll(field.dataset.studioFilter + ' tbody tr').forEach(function (row) { row.hidden = !row.textContent.toLocaleLowerCase().includes(q); }); });
  });
  if (side) document.addEventListener('click', function (e) { if (side.classList.contains('is-open') && !side.contains(e.target) && !e.target.closest('[data-toggle-nav]')) { side.classList.remove('is-open'); var b = document.querySelector('[data-toggle-nav]'); if (b) b.setAttribute('aria-expanded', 'false'); } });
})();
