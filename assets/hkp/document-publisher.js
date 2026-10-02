(function () {
  'use strict';
  var root = document.getElementById('publisher-workspace'); if (!root) return;
  var raw = document.getElementById('publisher-json'), visual = document.getElementById('publisher-visual'), status = document.getElementById('publisher-status');
  var payload = raw.value ? JSON.parse(raw.value) : null, imported = root.dataset.imported === '1', busy = false;
  var models = JSON.parse(document.getElementById('publisher-models').textContent), provider = document.getElementById('publisher-provider'), model = document.getElementById('publisher-model');
  if (provider) provider.addEventListener('change', function () { model.replaceChildren(); var p = models.find(function (p) { return p.slug === provider.value; }); (p ? p.models : []).forEach(function (m) { var o = document.createElement('option'); o.value = m.id; o.textContent = m.label; model.appendChild(o); }); });
  function el(tag, text, cls) { var n = document.createElement(tag); if (text) n.textContent = text; if (cls) n.className = cls; return n; }
  function sync() { raw.value = JSON.stringify(payload, null, 2); }
  function field(parent, label, object, key, multiline, numeric) { var wrap = el('label', label, 'hkp-field'); var input = el(multiline ? 'textarea' : 'input', '', 'hkp-input'); if (!multiline) input.type = numeric ? 'number' : 'text'; else input.rows = 5; input.value = object[key] === undefined ? '' : object[key]; input.disabled = imported; if (root.dataset.locale === 'ar' && !numeric) input.dir = 'rtl'; input.addEventListener('input', function () { object[key] = numeric ? Number(input.value) : input.value; sync(); }); wrap.appendChild(input); parent.appendChild(wrap); }
  function button(parent, text, fn) { if (imported) return; var b = el('button', text, 'hkp-btn hkp-btn--sm hkp-btn--ghost'); b.type = 'button'; b.addEventListener('click', fn); parent.appendChild(b); }
  function ordering(parent, list, i) { var row = el('div', '', 'hkp-actions'); button(row, '↑', function () { if (i > 0) { var t = list[i - 1]; list[i - 1] = list[i]; list[i] = t; render(); } }); button(row, '↓', function () { if (i < list.length - 1) { var t = list[i + 1]; list[i + 1] = list[i]; list[i] = t; render(); } }); button(row, 'Remove', function () { list.splice(i, 1); render(); }); parent.appendChild(row); }
  function render() {
    visual.replaceChildren(); if (!payload) { visual.appendChild(el('p', 'Generate a draft, or paste a structured package in the advanced editor.', 'hkp-small hkp-muted')); return; }
    field(visual, 'Title', payload, 'title'); field(visual, 'Summary', payload, 'summary', true);
    if (root.dataset.target === 'page') {
      (payload.sections || []).forEach(function (s, i) { var card = el('details', '', 'studio-editor-section'); card.appendChild(el('summary', 'Section ' + (i + 1) + ' · ' + s.heading)); field(card, 'Heading', s, 'heading'); field(card, 'Content (HTML supported)', s, 'body', true); ordering(card, payload.sections, i); visual.appendChild(card); });
      button(visual, 'Add section', function () { payload.sections = payload.sections || []; payload.sections.push({ heading: 'New section', body: '' }); render(); });
    } else {
      (payload.modules || []).forEach(function (m, i) { var card = el('details', '', 'studio-editor-section'); card.open = i === 0; card.appendChild(el('summary', 'Module ' + (i + 1) + ' · ' + m.title)); field(card, 'Module title', m, 'title'); ordering(card, payload.modules, i);
        (m.lessons || []).forEach(function (l, j) { var lesson = el('details', '', 'studio-editor-section'); lesson.appendChild(el('summary', 'Lesson ' + (j + 1) + ' · ' + l.title)); field(lesson, 'Lesson title', l, 'title'); field(lesson, 'Learning objective', l, 'objective'); field(lesson, 'Content (HTML supported)', l, 'body', true); field(lesson, 'Minutes', l, 'minutes', false, true); ordering(lesson, m.lessons, j); card.appendChild(lesson); });
        button(card, 'Add lesson', function () { m.lessons.push({ title: 'New lesson', objective: '', body: '', minutes: 5 }); render(); }); visual.appendChild(card);
      });
      button(visual, 'Add module', function () { payload.modules = payload.modules || []; payload.modules.push({ title: 'New module', lessons: [] }); render(); });
      field(visual, 'Passing score (%)', payload, 'passing_score', false, true);
      (payload.quiz || []).forEach(function (q, i) { var card = el('details', '', 'studio-editor-section'); card.appendChild(el('summary', 'Question ' + (i + 1) + ' · ' + q.question)); field(card, 'Question', q, 'question'); q.options.forEach(function (_, j) { field(card, 'Option ' + (j + 1), q.options, j); }); field(card, 'Correct option index (0 = first option)', q, 'correct', false, true); field(card, 'Explanation', q, 'explanation', true); ordering(card, payload.quiz, i); visual.appendChild(card); });
      button(visual, 'Add question', function () { payload.quiz = payload.quiz || []; payload.quiz.push({ question: 'New question', options: ['', '', ''], correct: 0, explanation: '' }); render(); });
    }
    sync();
  }
  document.getElementById('publisher-use-json').addEventListener('click', function () { try { var next = JSON.parse(raw.value); if (!next || Array.isArray(next) || typeof next !== 'object') throw new Error('Use a JSON object.'); payload = next; render(); status.textContent = 'Loaded. Save to validate the package.'; } catch (e) { status.textContent = 'Invalid draft: ' + e.message; } });
  async function request(action) {
    if (busy) return;
    if (action === 'import' && !window.confirm('Create this reviewed package as unpublished content?')) return;
    busy = true; root.querySelectorAll('[data-publisher-action]').forEach(function (b) { b.disabled = true; }); status.textContent = action === 'generate' ? 'Generating your structured draft… This may take a minute.' : 'Saving…';
    try {
      var data = { action: action, version: root.dataset.version };
      if (action === 'generate') { data.provider = provider.value; data.model = model.value; data.brief = document.getElementById('publisher-brief').value; }
      else { if (!payload) throw new Error('Generate or load a draft first.'); data.payload = JSON.stringify(payload); }
      if (action === 'import') {
        var saved = await window.HKP.post(root.dataset.url, { action: 'save', version: root.dataset.version, payload: JSON.stringify(payload) });
        if (!saved.ok) throw new Error(saved.error); root.dataset.version = saved.version; data.version = saved.version; data.confirmed = 'yes';
      }
      var res = await window.HKP.post(root.dataset.url, data); if (!res.ok) throw new Error(res.error || 'Request failed.');
      if (res.edit_url) { window.location.assign(res.edit_url); return; }
      root.dataset.version = res.version; payload = res.payload; render(); status.textContent = 'Draft saved. Nothing has been published.';
    } catch (e) { status.textContent = e.message; }
    finally { busy = false; root.querySelectorAll('[data-publisher-action]').forEach(function (b) { b.disabled = false; }); }
  }
  root.querySelectorAll('[data-publisher-action]').forEach(function (b) { b.addEventListener('click', function () { request(b.dataset.publisherAction); }); });
  render();
})();
