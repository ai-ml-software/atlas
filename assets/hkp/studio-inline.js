/*
 * Shared Website Studio helpers: media picker, image upload and inline
 * click-to-edit binding for the private preview iframe.
 *
 * Templates mark editable elements with data-studio-field / data-studio-kind
 * (see application/helpers/ha_studio_helper.php). Text and rich-text fields
 * become contenteditable and report every change back to the editor panel;
 * image fields open the media picker. Everything is keyboard reachable:
 * Tab to a field, type to edit, Escape (or Enter on single-line text) to leave,
 * Enter/Space on an image to choose media.
 */
(function () {
  'use strict';
  function t(s) { return ((window.HKP && window.HKP.text) || {})[s] || s; }
  function base() { return (window.HKP && window.HKP.base ? window.HKP.base : '/hkp/').replace(/\/$/, ''); }
  function siteUrl(path) { if (/^https?:\/\//.test(path)) return path; return new URL(String(path).replace(/^\//, ''), base().replace(/hkp$/, '')).href; }

  function pickMedia(onPick, opener) {
    var dialog = document.createElement('dialog'); dialog.className = 'studio-media-dialog'; dialog.setAttribute('aria-label', t('Choose from media'));
    var head = document.createElement('div'); head.className = 'studio-section-head';
    var title = document.createElement('h2'); title.textContent = t('Choose from media');
    var close = document.createElement('button'); close.type = 'button'; close.className = 'hkp-btn hkp-btn--ghost hkp-btn--sm'; close.textContent = t('Close');
    var search = document.createElement('input'); search.type = 'search'; search.className = 'hkp-input'; search.placeholder = t('Search media'); search.setAttribute('aria-label', t('Search media'));
    var grid = document.createElement('div'); grid.className = 'studio-media-grid'; grid.setAttribute('role', 'list');
    head.append(title, close); dialog.append(head, search, grid); document.body.appendChild(dialog);
    function done() { dialog.close(); dialog.remove(); if (opener && opener.focus) { try { opener.focus(); } catch (_) {} } }
    close.addEventListener('click', done); dialog.addEventListener('cancel', function (e) { e.preventDefault(); done(); });
    var timer;
    async function load(q) {
      grid.replaceChildren();
      try {
        var r = await fetch(base() + '/cms/media_picker?q=' + encodeURIComponent(q || ''), { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }), d = await r.json();
        if (!d.ok) throw new Error(d.error || 'Media unavailable');
        d.items.forEach(function (item) {
          var b = document.createElement('button'), img = document.createElement('img'), label = document.createElement('span');
          b.type = 'button'; b.className = 'studio-media-choice'; b.setAttribute('role', 'listitem');
          img.src = siteUrl(item.file_path); img.alt = ''; label.textContent = item.original_name || item.file_path;
          b.append(img, label); b.addEventListener('click', function () { onPick(item.file_path); done(); }); grid.appendChild(b);
        });
        if (!d.items.length) { var p = document.createElement('p'); p.textContent = t('No images yet. Upload an image below.'); grid.appendChild(p); }
      } catch (e) { var err = document.createElement('p'); err.setAttribute('role', 'alert'); err.textContent = e.message; grid.appendChild(err); }
    }
    search.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(function () { load(search.value); }, 250); });
    dialog.showModal(); search.focus(); load('');
  }

  async function upload(file) {
    var body = new FormData(); body.append('file', file); body.append('ha_csrf', window.HKP.csrf);
    var r = await fetch(base() + '/cms/upload', { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }), d = await r.json();
    if (!d.ok) throw new Error(d.error || 'Upload failed'); return d.path;
  }

  /*
   * bind(frame, api)
   *   api.get(field)        -> current string value, or undefined when the field is not editable here
   *   api.set(field, value) -> store a value typed inline (no preview reload)
   *   api.image(field, path)-> store a chosen image (editor reloads the preview)
   *   api.focus(field)      -> reveal the matching panel control
   *   api.section(key)      -> reveal a section card
   *   api.dir               -> 'rtl' | 'ltr' for the editing language
   */
  function bind(frame, api) {
    var doc; try { doc = frame.contentDocument; } catch (_) { return; } if (!doc || !doc.head) return;
    if (!doc.getElementById('studio-inline-css')) { var css = doc.createElement('link'); css.id = 'studio-inline-css'; css.rel = 'stylesheet'; css.href = base() + '/../assets/hkp/inline-editor.css'; doc.head.appendChild(css); }
    doc.querySelectorAll('a,form').forEach(function (n) { n.addEventListener(n.tagName === 'FORM' ? 'submit' : 'click', function (e) { e.preventDefault(); }); });
    doc.querySelectorAll('[data-studio-field]').forEach(function (node) {
      if (node.dataset.studioBound) return; var field = node.dataset.studioField, kind = node.dataset.studioKind || 'text';
      if (api.get(field) === undefined) return; node.dataset.studioBound = '1';
      node.setAttribute('aria-label', t('Edit') + ' ' + field.replace(/^[sc]:[^:]+:/, '').replace(/[:_]/g, ' '));
      if (kind === 'image') {
        node.tabIndex = 0; node.setAttribute('role', 'button'); node.removeAttribute('aria-hidden');
        var choose = function (e) { e.preventDefault(); e.stopPropagation(); api.focus(field); pickMedia(function (path) { api.image(field, path); }, frame); };
        node.addEventListener('click', choose); node.addEventListener('keydown', function (e) { if (e.key === 'Enter' || e.key === ' ') choose(e); });
        return;
      }
      node.contentEditable = 'true'; node.spellcheck = true; node.dir = api.dir || 'auto'; node.setAttribute('role', 'textbox');
      if (kind !== 'html') node.setAttribute('aria-multiline', 'false');
      node.addEventListener('click', function (e) { e.preventDefault(); e.stopPropagation(); });
      node.addEventListener('focus', function () { api.focus(field, true); });
      node.addEventListener('keydown', function (e) {
        e.stopPropagation();
        if (e.key === 'Escape' || (e.key === 'Enter' && kind !== 'html' && !e.shiftKey)) { e.preventDefault(); node.blur(); }
      });
      node.addEventListener('paste', function (e) { if (kind === 'html') return; e.preventDefault(); var text = (e.clipboardData || window.clipboardData).getData('text/plain'); doc.execCommand('insertText', false, text.replace(/\s*\n\s*/g, ' ')); });
      node.addEventListener('input', function () { api.set(field, kind === 'html' ? node.innerHTML : node.innerText.replace(/ /g, ' ').replace(/\n+$/, '')); });
    });
    doc.querySelectorAll('[data-studio-section]').forEach(function (node) {
      if (node.dataset.studioSectionBound) return; node.dataset.studioSectionBound = '1';
      node.addEventListener('click', function (e) { if (e.target.closest('[data-studio-field]')) return; e.preventDefault(); api.section(node.dataset.studioSection); });
    });
  }

  /* Updates the preview element for a field typed in the panel, without reloading. */
  function sync(frame, field, value) {
    var doc; try { doc = frame.contentDocument; } catch (_) { return false; } if (!doc) return false;
    var nodes = doc.querySelectorAll('[data-studio-field="' + String(field).replace(/"/g, '') + '"]'), done = false;
    // Plain text only: rich text is re-rendered by the server (sanitised) through a preview reload.
    nodes.forEach(function (n) { if (n === doc.activeElement || n.dataset.studioKind !== 'text') return; n.textContent = value; done = true; });
    return done;
  }

  window.HKPStudio = { pickMedia: pickMedia, upload: upload, bind: bind, sync: sync };

  // Theme page: media picker and upload for the logo field.
  document.querySelectorAll('[data-theme-pick]').forEach(function (b) { b.addEventListener('click', function () { var input = document.getElementById(b.dataset.themePick); pickMedia(function (path) { input.value = path; input.dispatchEvent(new Event('input')); }, b); }); });
  document.querySelectorAll('[data-theme-upload]').forEach(function (u) { u.addEventListener('change', async function () { if (!u.files[0]) return; var input = document.getElementById(u.dataset.themeUpload); try { input.value = await upload(u.files[0]); } catch (e) { window.alert(e.message); } }); });
})();
