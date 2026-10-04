(function () {
  'use strict';
  var root = document.getElementById('live-editor'); if (!root) return;
  var payload = JSON.parse(document.getElementById('live-payload').textContent), types = JSON.parse(document.getElementById('live-types').textContent), fields = document.getElementById('live-fields'), status = document.getElementById('live-status'), frame = document.getElementById('live-preview');
  var locale = root.dataset.locale, timer, dirty = false, saving = false, queued = false, editSequence = 0, publishing = false, reloadAfterSave = false, inlineCheckpoint = null;
  function tr(s) { return (window.HKP.text || {})[s] || s; }
  function el(tag, text, cls) { var n = document.createElement(tag); if (text) n.textContent = tr(text); if (cls) n.className = cls; return n; }
  var history = [], future = [];
  function checkpoint() { history.push(JSON.stringify(payload)); if (history.length > 30) history.shift(); future = []; }
  // reload=false for inline edits: the preview already shows the typed text, so it is not reloaded under the caret.
  function changed(reload) { dirty = true; editSequence++; if (reload !== false) reloadAfterSave = true; status.textContent = tr('Unsaved changes'); clearTimeout(timer); timer = setTimeout(save, 1200); }

  /* ---------- field addressing shared by the panel and the inline preview ---------- */
  function section(key) { return payload.sections.find(function (s) { return (s.studio_key || ('section-' + s.id)) === key; }); }
  function itemKeys(s) { var spec = (types[s.section_type] || [0, []])[1].find(function (f) { return f.indexOf('items[]:') === 0; }); return spec ? spec.replace(/\*$/, '').slice(8).split('|') : []; }
  function itemsText(content, keys) { if (Array.isArray(content.items)) content.items = content.items.map(function (it) { return keys.map(function (k) { return it[k] || ''; }).join(' | '); }).join('\n'); return content.items || ''; }
  function resolve(name) {
    var m;
    if ((m = /^c:(\d+):(title|body)$/.exec(name))) { var row = (payload.corporate || {})[m[1]]; if (!row) return null; var k = m[2] + '_' + locale; return { get: function () { return row[k] || ''; }, set: function (v) { row[k] = v; }, panel: name }; }
    if ((m = /^s:([A-Za-z0-9_-]+):items:(\d+):(\w+)$/.exec(name))) {
      var s = section(m[1]); if (!s) return null; s[locale] = s[locale] || {}; var c = s[locale], keys = itemKeys(s), col = keys.indexOf(m[3]), idx = Number(m[2]); if (col < 0) return null;
      var cells = function () { var lines = itemsText(c, keys).split('\n'); var cellsOf = (lines[idx] || '').split('|').map(function (x) { return x.trim(); }); while (cellsOf.length < keys.length) cellsOf.push(''); return { lines: lines, row: cellsOf }; };
      return { get: function () { return cells().row[col]; }, set: function (v) { var x = cells(); x.row[col] = v.replace(/\|/g, '/'); x.lines[idx] = x.row.join(' | '); c.items = x.lines.join('\n'); }, panel: 's:' + m[1] + ':items' };
    }
    if ((m = /^s:([A-Za-z0-9_-]+):(\w+)$/.exec(name))) {
      var sec = section(m[1]); if (!sec) return null;
      if (m[2] === 'image') { sec.settings = sec.settings || {}; return { get: function () { return sec.settings.image || ''; }, set: function (v) { sec.settings.image = v; }, panel: name }; }
      sec[locale] = sec[locale] || {}; var cc = sec[locale], key = m[2];
      return { get: function () { return cc[key] || ''; }, set: function (v) { cc[key] = v; }, panel: name };
    }
    if (['title', 'subtitle', 'body', 'hero_image', 'cta_label', 'cta_url'].indexOf(name) >= 0) { payload.tr[locale] = payload.tr[locale] || {}; var t = payload.tr[locale]; return { get: function () { return t[name] || ''; }, set: function (v) { t[name] = v; }, panel: name }; }
    return null;
  }
  function panelInput(name) { return fields.querySelector('[data-field="' + name + '"]'); }

  function field(parent, label, object, key, multiline, id) {
    var wrap = el('label', label, 'hkp-field'), input = el(multiline ? 'textarea' : 'input', '', 'hkp-input'); if (!multiline) input.type = 'text'; else input.rows = 4;
    input.dataset.field = id || key; input.value = object[key] || ''; input.dir = locale === 'ar' ? 'rtl' : 'ltr';
    input.addEventListener('focus', function () { inlineCheckpoint = null; });
    input.addEventListener('input', function () { checkpoint(); object[key] = input.value; if (window.HKPStudio && HKPStudio.sync(frame, id || key, input.value) && !/image|url|items/.test(id || key)) changed(false); else changed(); });
    wrap.appendChild(input);
    if (/(^|:)(hero_image|image)$/.test(id || key)) {
      var pick = el('button', 'Choose from media', 'hkp-btn hkp-btn--sm hkp-btn--ghost'); pick.type = 'button';
      pick.addEventListener('click', function () { HKPStudio.pickMedia(function (path) { checkpoint(); object[key] = path; input.value = path; changed(); }, pick); }); wrap.appendChild(pick);
      var upload = el('input'); upload.type = 'file'; upload.accept = '.jpg,.jpeg,.png,.webp'; upload.setAttribute('aria-label', tr('Upload image'));
      upload.addEventListener('change', async function () { if (!upload.files[0]) return; status.textContent = tr('Uploading image…'); try { var path = await HKPStudio.upload(upload.files[0]); checkpoint(); object[key] = path; input.value = path; changed(); } catch (e) { status.textContent = e.message; } }); wrap.appendChild(upload);
    }
    parent.appendChild(wrap);
  }
  function action(parent, label, fn, aria) { var b = el('button', label, 'hkp-btn hkp-btn--sm hkp-btn--ghost'); b.type = 'button'; if (aria) b.setAttribute('aria-label', tr(aria)); b.addEventListener('click', function () { checkpoint(); fn(); changed(); render(); }); parent.appendChild(b); }
  function render() {
    fields.replaceChildren(); payload.tr[locale] = payload.tr[locale] || {}; var t = payload.tr[locale];
    field(fields, 'Page title', t, 'title'); field(fields, 'Subtitle', t, 'subtitle', true); field(fields, 'Page content', t, 'body', true); field(fields, 'Hero image path', t, 'hero_image'); field(fields, 'Button label', t, 'cta_label'); field(fields, 'Button URL', t, 'cta_url');
    if (payload.corporate && Object.keys(payload.corporate).length) {
      var group = el('details', '', 'studio-editor-section'); group.id = 'live-corporate'; group.appendChild(el('summary', 'Homepage blocks'));
      Object.keys(payload.corporate).forEach(function (bid) {
        var row = payload.corporate[bid], box = el('fieldset', '', 'studio-fieldset'), legend = el('legend'); legend.textContent = row.section.replace(/_/g, ' ') + ' · ' + row.code; box.appendChild(legend);
        field(box, 'Title', row, 'title_' + locale, false, 'c:' + bid + ':title'); field(box, 'Text', row, 'body_' + locale, true, 'c:' + bid + ':body'); group.appendChild(box);
      });
      fields.appendChild(group);
    }
    payload.sections.forEach(function (s, i) {
      var key = s.studio_key || ('section-' + s.id), card = el('details', '', 'studio-editor-section'); card.dataset.index = i; card.dataset.key = key; card.draggable = true;
      card.appendChild(el('summary', (i + 1) + ' · ' + types[s.section_type][0] + (s.is_visible ? '' : ' · ' + tr('Hidden'))));
      s[locale] = s[locale] || {}; var content = s[locale];
      types[s.section_type][1].forEach(function (f) {
        var k = f.replace(/\*$/, ''), multiline = k === 'body' || k === 'lede';
        if (k.indexOf('items[]:') === 0) { var keys = k.slice(8).split('|'); itemsText(content, keys); field(card, tr('Items') + ': ' + keys.join(' | ') + ' (' + tr('one per line') + ')', content, 'items', true, 's:' + key + ':items'); }
        else field(card, k.replace(/_/g, ' '), content, k, multiline, 's:' + key + ':' + k);
      });
      s.settings = s.settings || {};
      types[s.section_type][2].forEach(function (k) { field(card, k.replace(/_/g, ' '), s.settings, k, false, 's:' + key + ':' + k); });
      var row = el('div', '', 'hkp-actions');
      action(row, '↑', function () { if (i) { var x = payload.sections[i - 1]; payload.sections[i - 1] = s; payload.sections[i] = x; } }, 'Move section up');
      action(row, '↓', function () { if (i < payload.sections.length - 1) { var x = payload.sections[i + 1]; payload.sections[i + 1] = s; payload.sections[i] = x; } }, 'Move section down');
      action(row, s.is_visible ? 'Hide' : 'Show', function () { s.is_visible = s.is_visible ? 0 : 1; });
      action(row, 'Duplicate', function () { var copy = JSON.parse(JSON.stringify(s)); copy.studio_key = 'section-' + crypto.randomUUID(); delete copy.id; payload.sections.splice(i + 1, 0, copy); });
      action(row, 'Delete', function () { payload.sections.splice(i, 1); }); card.appendChild(row);
      card.addEventListener('dragstart', function (e) { if (e.target !== card) return; e.dataTransfer.setData('text/plain', String(i)); }); card.addEventListener('dragover', function (e) { e.preventDefault(); });
      card.addEventListener('drop', function (e) { e.preventDefault(); var from = Number(e.dataTransfer.getData('text/plain')); if (Number.isInteger(from) && from >= 0 && from < payload.sections.length) { checkpoint(); payload.sections.splice(i, 0, payload.sections.splice(from, 1)[0]); changed(); render(); } });
      fields.appendChild(card);
    });
  }
  function preview() { var url = new URL(root.dataset.preview); url.pathname = url.pathname.replace(/\/(en|ar)(?=\/|$)/, '/' + locale); if (url.searchParams.has('edit')) url.searchParams.set('edit', locale); url.searchParams.set('studio_preview', root.dataset.page); url.searchParams.set('draft_version', root.dataset.version); frame.src = url.href; }
  async function save() {
    clearTimeout(timer); if (publishing) return false;
    if (saving) { queued = true; return false; }
    if (!dirty && Number(root.dataset.version)) return true;
    saving = true; var sequence = editSequence, reload = reloadAfterSave; reloadAfterSave = false; status.textContent = tr('Saving private draft…');
    try { var d = await window.HKP.post(root.dataset.url, { action: 'save', payload: JSON.stringify(payload), version: root.dataset.version, base_hash: root.dataset.hash }); if (!d.ok) throw new Error(d.error); root.dataset.version = d.version; if (sequence === editSequence) dirty = false; status.textContent = tr('Draft saved') + ' · ' + new Date().toLocaleTimeString(); if (reload) preview(); return !dirty; }
    catch (e) { reloadAfterSave = reloadAfterSave || reload; status.textContent = e.message; return false; }
    finally { saving = false; if (queued) { queued = false; timer = setTimeout(save, 150); } }
  }
  document.getElementById('live-save').addEventListener('click', save);
  var discard = document.getElementById('live-discard');
  discard.addEventListener('click', async function () {
    if (saving || publishing || !Number(root.dataset.version) || !window.confirm(tr('Discard this private draft and reload the published page? Download your draft first if you need to keep the edits.'))) return;
    clearTimeout(timer); publishing = true; discard.disabled = true;
    try { var d = await window.HKP.post(root.dataset.url, {action: 'discard', version: root.dataset.version}); if (!d.ok) throw new Error(d.error); dirty = false; window.location.reload(); }
    catch (e) { publishing = false; discard.disabled = false; status.textContent = e.message; }
  });
  document.getElementById('live-export').addEventListener('click', function () { var url = URL.createObjectURL(new Blob([JSON.stringify(payload, null, 2)], {type: 'application/json'})), a = el('a'); a.href = url; a.download = 'altus-page-' + root.dataset.page + '-draft.json'; a.click(); setTimeout(function () { URL.revokeObjectURL(url); }, 1000); });
  setInterval(function () { if (!publishing) discard.disabled = !Number(root.dataset.version) || saving; }, 500);
  var publish = document.getElementById('live-publish'); if (publish) publish.addEventListener('click', async function () { if (saving || publishing || !window.confirm(tr('Publish this reviewed page draft to the website?'))) return; reloadAfterSave = true; if (!await save()) return; publishing = true; publish.disabled = true; try { var d = await window.HKP.post(root.dataset.url, { action: 'publish', version: root.dataset.version }); if (!d.ok) throw new Error(d.error); root.dataset.version = d.version; root.dataset.hash = d.base_hash; status.textContent = tr('Published') + ' · ' + new Date().toLocaleTimeString(); preview(); } catch (e) { status.textContent = e.message; } finally { publishing = false; publish.disabled = false; } });
  var share = document.getElementById('live-share'); if (share) share.addEventListener('click', async function () { try { var d = await window.HKP.post(share.dataset.url, { kind: 'page', id: root.dataset.page, locale: locale }); if (!d.ok) throw new Error(d.error); var out = document.getElementById('live-share-url'); out.value = d.url; out.hidden = false; out.select(); try { await navigator.clipboard.writeText(d.url); status.textContent = tr('Review link copied. It expires in 24 hours and requires an editor sign-in.'); } catch (_) { status.textContent = tr('Review link ready. It expires in 24 hours and requires an editor sign-in.'); } } catch (e) { status.textContent = e.message; } });
  document.getElementById('live-add').addEventListener('click', function () { checkpoint(); payload.sections.push({ studio_key: 'section-' + crypto.randomUUID(), section_type: document.getElementById('live-add-type').value, is_visible: 1, en: {}, ar: {}, settings: {} }); changed(); render(); var cards = fields.querySelectorAll('details.studio-editor-section[data-key]'); var last = cards[cards.length - 1]; last.open = true; last.scrollIntoView({ block: 'nearest' }); });
  document.getElementById('live-locale').addEventListener('change', function (e) { locale = e.target.value; render(); preview(); });
  document.querySelectorAll('[data-live-device]').forEach(function (b) { b.addEventListener('click', function () { document.getElementById('live-preview-wrap').dataset.device = b.dataset.liveDevice; document.querySelectorAll('[data-live-device]').forEach(function (x) { x.setAttribute('aria-pressed', String(x === b)); }); }); });
  window.addEventListener('beforeunload', function (e) { if (dirty || saving) { e.preventDefault(); e.returnValue = ''; } });

  function reveal(name, fromInline) {
    var r = resolve(name), input = panelInput(r ? r.panel : name); if (!input) return;
    var card = input.closest('details'); if (card) card.open = true;
    var outer = card && card.parentElement ? card.parentElement.closest('details') : null; if (outer) outer.open = true;
    input.scrollIntoView({ block: 'nearest' }); input.classList.add('is-linked'); setTimeout(function () { input.classList.remove('is-linked'); }, 1200);
    if (!fromInline) input.focus();
  }
  function bindPreview() {
    if (!window.HKPStudio) return;
    HKPStudio.bind(frame, {
      dir: locale === 'ar' ? 'rtl' : 'ltr',
      get: function (name) { var r = resolve(name); return r ? r.get() : undefined; },
      set: function (name, value) {
        var r = resolve(name); if (!r) return;
        if (inlineCheckpoint !== name) { checkpoint(); inlineCheckpoint = name; }
        r.set(value); var input = panelInput(r.panel); if (input) input.value = r.panel === name ? value : resolve(r.panel) ? resolve(r.panel).get() : input.value;
        if (input && /:items$/.test(r.panel)) { var s = section(r.panel.split(':')[1]); input.value = (s && s[locale] && s[locale].items) || ''; }
        changed(false);
      },
      image: function (name, path) { var r = resolve(name); if (!r) return; checkpoint(); r.set(path); var input = panelInput(r.panel); if (input) input.value = path; changed(); save(); },
      focus: function (name, fromInline) { reveal(name, fromInline); },
      section: function (key) { var card = Array.from(fields.querySelectorAll('details[data-key]')).find(function (c) { return c.dataset.key === key; }); if (card) { card.open = true; card.scrollIntoView({block: 'nearest'}); var f = card.querySelector('input,textarea'); if (f) f.focus(); } }
    });
  }
  frame.addEventListener('load', bindPreview); if (frame.contentDocument && frame.contentDocument.readyState === 'complete') bindPreview();
  document.getElementById('live-undo').addEventListener('click', function () { if (!history.length) return; future.push(JSON.stringify(payload)); payload = JSON.parse(history.pop()); inlineCheckpoint = null; changed(); render(); });
  document.getElementById('live-redo').addEventListener('click', function () { if (!future.length) return; history.push(JSON.stringify(payload)); payload = JSON.parse(future.pop()); inlineCheckpoint = null; changed(); render(); });
  render();
})();
