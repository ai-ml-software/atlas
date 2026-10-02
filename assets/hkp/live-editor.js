(function () {
  'use strict';
  var root = document.getElementById('live-editor'); if (!root) return;
  var payload = JSON.parse(document.getElementById('live-payload').textContent), types = JSON.parse(document.getElementById('live-types').textContent), fields = document.getElementById('live-fields'), status = document.getElementById('live-status'), frame = document.getElementById('live-preview');
  var locale = root.dataset.locale, timer, dirty = false, saving = false, queued = false, editSequence = 0, publishing = false;
  function el(tag, text, cls) { var n = document.createElement(tag); if (text) n.textContent = text; if (cls) n.className = cls; return n; }
  function changed() { dirty = true; editSequence++; status.textContent = 'Unsaved changes'; clearTimeout(timer); timer = setTimeout(save, 1200); }
  function field(parent, label, object, key, multiline) { var wrap = el('label', label, 'hkp-field'), input = el(multiline ? 'textarea' : 'input', '', 'hkp-input'); if (!multiline) input.type = 'text'; else input.rows = 4; input.value = object[key] || ''; input.dir = locale === 'ar' ? 'rtl' : 'ltr'; input.addEventListener('input', function () { object[key] = input.value; changed(); }); wrap.appendChild(input); parent.appendChild(wrap); }
  function action(parent, label, fn) { var b = el('button', label, 'hkp-btn hkp-btn--sm hkp-btn--ghost'); b.type = 'button'; b.addEventListener('click', function () { fn(); changed(); render(); }); parent.appendChild(b); }
  function render() {
    fields.replaceChildren(); payload.tr[locale] = payload.tr[locale] || {}; var tr = payload.tr[locale];
    field(fields, 'Page title', tr, 'title'); field(fields, 'Subtitle', tr, 'subtitle', true); field(fields, 'Page content', tr, 'body', true); field(fields, 'Hero image path', tr, 'hero_image'); field(fields, 'Button label', tr, 'cta_label'); field(fields, 'Button URL', tr, 'cta_url');
    payload.sections.forEach(function (s, i) {
      var card = el('details', '', 'studio-editor-section'); card.dataset.index = i; card.draggable = true;
      card.appendChild(el('summary', (i + 1) + ' · ' + types[s.section_type][0] + (s.is_visible ? '' : ' · Hidden')));
      s[locale] = s[locale] || {}; var content = s[locale];
      types[s.section_type][1].forEach(function (f) {
        var key = f.replace(/\*$/, ''), multiline = key === 'body' || key === 'lede';
        if (key.indexOf('items[]:') === 0) { var keys = key.slice(8).split('|'); if (Array.isArray(content.items)) content.items = content.items.map(function (it) { return keys.map(function (k) { return it[k] || ''; }).join(' | '); }).join('\n'); field(card, 'Items: ' + keys.join(' | ') + ' (one per line)', content, 'items', true); }
        else field(card, key.replace(/_/g, ' '), content, key, multiline);
      });
      s.settings = s.settings || {};
      types[s.section_type][2].forEach(function (key) { field(card, key.replace(/_/g, ' '), s.settings, key); });
      if (types[s.section_type][2].includes('image')) {
        var label = el('label', 'Upload image', 'hkp-field'), upload = el('input'); upload.type = 'file'; upload.accept = '.jpg,.jpeg,.png,.webp';
        upload.addEventListener('change', async function () { if (!upload.files[0]) return; var body = new FormData(); body.append('file', upload.files[0]); body.append('ha_csrf', window.HKP.csrf); status.textContent = 'Uploading image…'; try { var r = await fetch(window.HKP.base.replace(/\/$/, '') + '/cms/upload', { method: 'POST', body: body, credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } }); var d = await r.json(); if (!d.ok) throw new Error(d.error); s.settings.image = d.path; changed(); render(); } catch (e) { status.textContent = e.message; } }); label.appendChild(upload); card.appendChild(label);
      }
      var row = el('div', '', 'hkp-actions'); action(row, '↑', function () { if (i) { var t = payload.sections[i - 1]; payload.sections[i - 1] = s; payload.sections[i] = t; } }); action(row, '↓', function () { if (i < payload.sections.length - 1) { var t = payload.sections[i + 1]; payload.sections[i + 1] = s; payload.sections[i] = t; } }); action(row, s.is_visible ? 'Hide' : 'Show', function () { s.is_visible = s.is_visible ? 0 : 1; }); action(row, 'Duplicate', function () { payload.sections.splice(i + 1, 0, JSON.parse(JSON.stringify(s))); }); action(row, 'Delete', function () { payload.sections.splice(i, 1); }); card.appendChild(row);
      card.addEventListener('dragstart', function (e) { if (e.target !== card) return; e.dataTransfer.setData('text/plain', String(i)); }); card.addEventListener('dragover', function (e) { e.preventDefault(); }); card.addEventListener('drop', function (e) { e.preventDefault(); var from = Number(e.dataTransfer.getData('text/plain')); if (Number.isInteger(from) && from >= 0 && from < payload.sections.length) { payload.sections.splice(i, 0, payload.sections.splice(from, 1)[0]); changed(); render(); } }); fields.appendChild(card);
    });
  }
  function preview() { var url = new URL(root.dataset.preview); url.pathname = url.pathname.replace(/\/(en|ar)(?=\/|$)/, '/' + locale); if (url.searchParams.has('edit')) url.searchParams.set('edit', locale); url.searchParams.set('studio_preview', root.dataset.page); url.searchParams.set('draft_version', root.dataset.version); frame.src = url.href; }
  async function save() {
    clearTimeout(timer); if (publishing) return false;
    if (saving) { queued = true; return false; }
    if (!dirty && Number(root.dataset.version)) return true;
    saving = true; var sequence = editSequence; status.textContent = 'Saving private draft…';
    try { var d = await window.HKP.post(root.dataset.url, { action: 'save', payload: JSON.stringify(payload), version: root.dataset.version, base_hash: root.dataset.hash }); if (!d.ok) throw new Error(d.error); root.dataset.version = d.version; if (sequence === editSequence) dirty = false; status.textContent = 'Draft saved · ' + new Date().toLocaleTimeString(); preview(); return !dirty; }
    catch (e) { status.textContent = e.message; return false; }
    finally { saving = false; if (queued) { queued = false; timer = setTimeout(save, 150); } }
  }
  document.getElementById('live-save').addEventListener('click', save);
  var publish = document.getElementById('live-publish'); if (publish) publish.addEventListener('click', async function () { if (saving || publishing || !window.confirm('Publish this reviewed page draft to the website?')) return; if (!await save()) return; publishing = true; publish.disabled = true; try { var d = await window.HKP.post(root.dataset.url, { action: 'publish', version: root.dataset.version }); if (!d.ok) throw new Error(d.error); root.dataset.version = d.version; root.dataset.hash = d.base_hash; status.textContent = 'Published · ' + new Date().toLocaleTimeString(); preview(); } catch (e) { status.textContent = e.message; } finally { publishing = false; publish.disabled = false; } });
  document.getElementById('live-add').addEventListener('click', function () { payload.sections.push({ section_type: document.getElementById('live-add-type').value, is_visible: 1, en: {}, ar: {}, settings: {} }); changed(); render(); var cards = fields.querySelectorAll('details'); var last = cards[cards.length - 1]; last.open = true; last.scrollIntoView({ block: 'nearest' }); });
  document.getElementById('live-locale').addEventListener('change', function (e) { locale = e.target.value; render(); preview(); });
  document.querySelectorAll('[data-live-device]').forEach(function (b) { b.addEventListener('click', function () { document.getElementById('live-preview-wrap').dataset.device = b.dataset.liveDevice; }); });
  window.addEventListener('beforeunload', function (e) { if (dirty || saving) { e.preventDefault(); e.returnValue = ''; } });
  render();
})();
