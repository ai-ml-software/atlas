(function () {
  'use strict';
  var root = document.getElementById('publisher-workspace'); if (!root) return;
  var raw = document.getElementById('publisher-json'), visual = document.getElementById('publisher-visual'), status = document.getElementById('publisher-status');
  var payload = raw.value ? JSON.parse(raw.value) : null, imported = root.dataset.imported === '1', busy = false, selection = null;
  var models = JSON.parse(document.getElementById('publisher-models').textContent), provider = document.getElementById('publisher-provider'), model = document.getElementById('publisher-model');
  var sopFields = ['purpose', 'scope', 'responsibilities', 'required_tools', 'procedure', 'checklist', 'safety_notes', 'quality_standard', 'escalation', 'related_documents'];
  if (provider) { provider.addEventListener('change', function () { model.replaceChildren(); var p = models.find(function (p) { return p.slug === provider.value; }); (p ? p.models : []).forEach(function (m) { var o = document.createElement('option'); o.value = m.id; o.textContent = m.label; model.appendChild(o); }); model.disabled = !model.children.length; }); provider.disabled = false; }
  function el(tag, text, cls) { var n = document.createElement(tag); if (text) n.textContent = (window.HKP.text || {})[text] || text; if (cls) n.className = cls; return n; }
  function sync() { raw.value = JSON.stringify(payload, null, 2); }
  function field(parent, label, object, key, multiline, numeric) { var wrap = el('label', label, 'hkp-field'); var input = el(multiline ? 'textarea' : 'input', '', 'hkp-input'); if (!multiline) input.type = numeric ? 'number' : 'text'; else input.rows = 5; input.value = object[key] === undefined ? '' : object[key]; input.disabled = imported; if (root.dataset.locale === 'ar' && !numeric) input.dir = 'rtl'; input.addEventListener('input', function () { object[key] = numeric ? Number(input.value) : input.value; sync(); }); wrap.appendChild(input); parent.appendChild(wrap); }
  // Source page references: shown as "1, 3" and stored as a list of page numbers.
  function pagesField(parent, object) {
    var wrap = el('label', 'Source pages', 'hkp-field studio-source-pages'); var input = el('input', '', 'hkp-input'); input.type = 'text'; input.inputMode = 'numeric'; input.placeholder = 'e.g. 1, 3';
    input.value = (object.source_pages || []).join(', '); input.disabled = imported;
    input.addEventListener('input', function () { object.source_pages = input.value.split(/[^0-9]+/).filter(Boolean).map(Number); sync(); });
    wrap.appendChild(input); if (!(object.source_pages || []).length) wrap.appendChild(el('span', 'No source page cited - review against the source.', 'hkp-small hkp-muted'));
    parent.appendChild(wrap);
  }
  function refs(object) { var p = object.source_pages || []; return p.length ? ' [p. ' + p.join(', ') + ']' : ''; }
  function button(parent, text, fn) { if (imported) return; var b = el('button', text, 'hkp-btn hkp-btn--sm hkp-btn--ghost'); b.type = 'button'; b.addEventListener('click', fn); parent.appendChild(b); }
  function ordering(parent, list, i) { var row = el('div', '', 'hkp-actions'); button(row, '↑', function () { if (i > 0) { var t = list[i - 1]; list[i - 1] = list[i]; list[i] = t; render(); } }); button(row, '↓', function () { if (i < list.length - 1) { var t = list[i + 1]; list[i + 1] = list[i]; list[i] = t; render(); } }); button(row, 'Remove', function () { list.splice(i, 1); render(); }); parent.appendChild(row); }
  function render() {
    visual.replaceChildren(); if (!payload) { visual.appendChild(el('p', 'Generate a draft, or paste a structured package in the advanced editor.', 'hkp-small hkp-muted')); return; }
    field(visual, 'Title', payload, 'title'); field(visual, 'Summary', payload, 'summary', true);
    if (root.dataset.target === 'sop') {
      payload.sop = payload.sop || {}; var sop = el('details', '', 'studio-editor-section'); sop.open = true; sop.appendChild(el('summary', 'SOP fields'));
      sopFields.forEach(function (k) { field(sop, k.replace(/_/g, ' ').replace(/^./, function (c) { return c.toUpperCase(); }) + ' (HTML)', payload.sop, k, true); });
      visual.appendChild(sop);
    }
    if (root.dataset.target !== 'course') {
      (payload.sections || []).forEach(function (s, i) { var card = el('details', '', 'studio-editor-section'); card.appendChild(el('summary', 'Section ' + (i + 1) + ' · ' + s.heading + refs(s))); field(card, 'Heading', s, 'heading'); field(card, 'Content (HTML supported)', s, 'body', true); pagesField(card, s); button(card,'Regenerate section',function(){selection='sections:'+i;request('generate');}); ordering(card, payload.sections, i); visual.appendChild(card); });
      button(visual, 'Add section', function () { payload.sections = payload.sections || []; payload.sections.push({ heading: 'New section', body: '', source_pages: [] }); render(); });
    } else {
      (payload.modules || []).forEach(function (m, i) { var card = el('details', '', 'studio-editor-section'); card.open = i === 0; card.appendChild(el('summary', 'Module ' + (i + 1) + ' · ' + m.title)); field(card, 'Module title', m, 'title'); button(card,'Regenerate module',function(){selection='modules:'+i;request('generate');}); ordering(card, payload.modules, i);
        (m.lessons || []).forEach(function (l, j) { var lesson = el('details', '', 'studio-editor-section'); lesson.appendChild(el('summary', 'Lesson ' + (j + 1) + ' · ' + l.title + refs(l))); field(lesson, 'Lesson title', l, 'title'); field(lesson, 'Learning objective', l, 'objective'); field(lesson, 'Content (HTML supported)', l, 'body', true); field(lesson, 'Minutes', l, 'minutes', false, true); pagesField(lesson, l); button(lesson,'Regenerate lesson',function(){selection='lesson:'+i+':'+j;request('generate');}); ordering(lesson, m.lessons, j); card.appendChild(lesson); });
        button(card, 'Add lesson', function () { m.lessons.push({ title: 'New lesson', objective: '', body: '', minutes: 5, source_pages: [] }); render(); }); visual.appendChild(card);
      });
      button(visual, 'Add module', function () { payload.modules = payload.modules || []; payload.modules.push({ title: 'New module', lessons: [] }); render(); });
      field(visual, 'Passing score (%)', payload, 'passing_score', false, true);
      (payload.quiz || []).forEach(function (q, i) { var card = el('details', '', 'studio-editor-section'); card.appendChild(el('summary', 'Question ' + (i + 1) + ' · ' + q.question + refs(q))); field(card, 'Question', q, 'question'); q.options.forEach(function (_, j) { field(card, 'Option ' + (j + 1), q.options, j); }); field(card, 'Correct option index (0 = first option)', q, 'correct', false, true); field(card, 'Explanation', q, 'explanation', true); pagesField(card, q); button(card,'Regenerate question',function(){selection='quiz:'+i;request('generate');}); ordering(card, payload.quiz, i); visual.appendChild(card); });
      button(visual, 'Add question', function () { payload.quiz = payload.quiz || []; payload.quiz.push({ question: 'New question', options: ['', '', ''], correct: 0, explanation: '', source_pages: [] }); render(); });
    }
    sync();
  }
  document.getElementById('publisher-use-json').addEventListener('click', function () { try { var next = JSON.parse(raw.value); if (!next || Array.isArray(next) || typeof next !== 'object') throw new Error('Use a JSON object.'); payload = next; render(); status.textContent = 'Loaded. Save to validate the package.'; } catch (e) { status.textContent = 'Invalid draft: ' + e.message; } });
  function value(id) { var n = document.getElementById(id); return n ? n.value : ''; }
  async function request(action) {
    if (busy) return;
    var submitReview = document.getElementById('publisher-submit-review');
    if (action === 'import' && submitReview && submitReview.checked && !value('publisher-reviewer')) { status.textContent = 'Choose a reviewer before submitting the SOP for internal review.'; return; }
    if (action === 'import' && !window.confirm('Create this reviewed package as unpublished content?')) return;
    busy = true; root.querySelectorAll('[data-publisher-action]').forEach(function (b) { b.disabled = true; }); status.textContent = action === 'generate' ? 'Generating your structured draft… This may take a minute.' : 'Saving…';
    try {
      var data = { action: action, version: root.dataset.version };
      if (action === 'generate' || action === 'translate') { data.provider = provider.value; data.model = model.value; data.brief = document.getElementById('publisher-brief').value; if(selection)data.selection=selection; if(payload && (selection || action==='translate')){var saved=await window.HKP.post(root.dataset.url,{action:'save',version:root.dataset.version,payload:JSON.stringify(payload)});if(!saved.ok)throw new Error(saved.error);root.dataset.version=saved.version;data.version=saved.version;} }
      else { if (!payload) throw new Error('Generate or load a draft first.'); data.payload = JSON.stringify(payload); }
      if (action === 'import') {
        var saved = await window.HKP.post(root.dataset.url, { action: 'save', version: root.dataset.version, payload: JSON.stringify(payload) });
        if (!saved.ok) throw new Error(saved.error); root.dataset.version = saved.version; data.version = saved.version; data.confirmed = 'yes';
        if (submitReview) { data.reviewer_user_id = value('publisher-reviewer'); data.approver_user_id = value('publisher-approver'); data.submit_review = submitReview.checked ? '1' : '0'; }
      }
      var res = await window.HKP.post(root.dataset.url, data); if (!res.ok) throw new Error(res.error || 'Request failed.');
      if (res.edit_url) { window.location.assign(res.edit_url); return; }
      root.dataset.version = res.version; payload = res.payload; render(); status.textContent = 'Draft saved. Nothing has been published.';
    } catch (e) { status.textContent = e.message; }
    finally { selection=null; busy = false; root.querySelectorAll('[data-publisher-action]').forEach(function (b) { b.disabled = false; }); }
  }
  root.querySelectorAll('[data-publisher-action]').forEach(function (b) { b.addEventListener('click', function () { request(b.dataset.publisherAction); }); });
  var sourceBox = document.getElementById('publisher-source-correction');
  var correct = document.getElementById('publisher-source-save'); if (correct) correct.addEventListener('click', async function () { try { var d=await window.HKP.post(root.dataset.url,{action:'source',version:root.dataset.version,source:sourceBox.value}); if (!d.ok) throw new Error(d.error); root.dataset.version=d.version; status.textContent='Source corrections saved. Review existing generated content against the updated source.'; } catch(e) { status.textContent=e.message; } });
  // Provenance: jump to a page marker in the source text.
  document.querySelectorAll('[data-jump-page]').forEach(function (b) { b.addEventListener('click', function () { if (!sourceBox) return; var marker = '[Page ' + b.dataset.jumpPage + ']', at = sourceBox.value.indexOf(marker); if (at < 0) return; sourceBox.focus(); sourceBox.setSelectionRange(at, at + marker.length); var lines = sourceBox.value.slice(0, at).split('\n').length; sourceBox.scrollTop = Math.max(0, (lines - 2) * (sourceBox.scrollHeight / Math.max(1, sourceBox.value.split('\n').length))); }); });
  // Featured image from the platform media library.
  var media = document.getElementById('publisher-media');
  async function setMedia(id) { var d = await window.HKP.post(root.dataset.url, { action: 'media', media_id: String(id || 0) }); if (!d.ok) { status.textContent = d.error; return; } window.location.reload(); }
  if (media) {
    var choose = document.getElementById('publisher-media-choose'), clear = document.getElementById('publisher-media-clear'), picker = document.getElementById('publisher-media-picker'), search = document.getElementById('publisher-media-search');
    async function load() { var grid = picker.querySelector('[data-media-grid]'); grid.replaceChildren(el('span', 'Loading…', 'hkp-small hkp-muted')); try { var r = await fetch(root.dataset.mediaUrl + '?q=' + encodeURIComponent(search.value), { credentials: 'same-origin' }), d = await r.json(); if (!d.ok) throw new Error(d.error); grid.replaceChildren(); if (!d.items.length) grid.appendChild(el('span', 'No images found. Upload images in the media library.', 'hkp-small hkp-muted'));
      d.items.forEach(function (m) { var b = el('button', '', 'hkp-btn hkp-btn--ghost'); b.type = 'button'; b.dataset.mediaId = m.id; b.title = m.original_name; var img = document.createElement('img'); img.src = root.dataset.baseUrl + m.file_path; img.alt = m.alt_en || m.original_name; img.loading = 'lazy'; img.style.cssText = 'width:100%;height:80px;object-fit:cover;border-radius:6px'; b.appendChild(img); b.addEventListener('click', function () { setMedia(m.id); }); grid.appendChild(b); }); } catch (e) { grid.replaceChildren(el('span', e.message, 'hkp-small')); } }
    if (choose) choose.addEventListener('click', function () { picker.hidden = !picker.hidden; if (!picker.hidden) load(); });
    if (search) { var t; search.addEventListener('input', function () { clearTimeout(t); t = setTimeout(load, 250); }); }
    if (clear) clear.addEventListener('click', function () { setMedia(0); });
  }
  var progress=document.getElementById('document-progress'); if (progress) {
    root.querySelectorAll('[data-publisher-action]').forEach(function(b){b.disabled=true;});
    var attempts = progress.querySelector('[data-job-attempts]');
    async function poll() { try { var r=await fetch(progress.dataset.statusUrl,{credentials:'same-origin'}), d=await r.json(); if (!d.ok) throw new Error(d.error); var j=d.job; progress.querySelector('progress').value=j.progress; progress.querySelector('p').textContent=j.error || (j.status+' · '+j.progress+'%'); if (attempts) attempts.textContent='Attempts: '+j.attempts+' / '+j.attempt_limit+(j.status==='queued'&&j.next_attempt_at?' · next attempt '+j.next_attempt_at+' UTC':''); if (j.status==='completed') { window.location.reload(); return; } if (j.status==='queued'||j.status==='processing') setTimeout(poll,2000); } catch(e){progress.querySelector('p').textContent=e.message;} }
    progress.querySelectorAll('[data-document-action]').forEach(function(b){b.addEventListener('click',async function(){var d=await window.HKP.post(root.dataset.url,{action:b.dataset.documentAction}); if(!d.ok){progress.querySelector('p').textContent=d.error;return;} poll();});}); poll();
  }
  render();
})();
