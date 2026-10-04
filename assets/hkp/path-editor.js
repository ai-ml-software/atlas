(function () {
  'use strict';
  var host = document.getElementById('studio-path-steps'); if (!host) return;
  var data = JSON.parse(document.getElementById('studio-path-data').textContent), steps = data.steps;
  function el(tag, text, cls) { var n = document.createElement(tag); n.textContent = text || ''; if (cls) n.className = cls; return n; }
  function sync() { document.getElementById('studio-path-json').value = JSON.stringify(steps); }
  function field(parent, label, obj, key, area) { var wrap = el('label', label, 'hkp-field'), input = el(area ? 'textarea' : 'input', '', 'hkp-input'); input.value = obj[key] || ''; if (/_ar$/.test(key)) input.dir = 'rtl'; input.addEventListener('input', function () { obj[key] = input.value; sync(); }); wrap.appendChild(input); parent.appendChild(wrap); }
  function button(parent, label, fn) { var b = el('button', label, 'hkp-btn hkp-btn--sm hkp-btn--ghost'); b.type = 'button'; b.addEventListener('click', function () { fn(); render(); }); parent.appendChild(b); }
  function render() {
    host.replaceChildren();
    steps.forEach(function (s, i) {
      var card = el('details', '', 'studio-editor-section'); card.open = true; card.appendChild(el('summary', (i + 1) + ' · ' + (s.title_en || 'New step')));
      var grid = el('div', '', 'hkp-grid hkp-grid--2');
      ['en', 'ar'].forEach(function (loc) { var part = el('div'); field(part, loc === 'en' ? 'English step title' : 'عنوان الخطوة بالعربية', s, 'title_' + loc); field(part, loc === 'en' ? 'English description' : 'الوصف بالعربية', s, 'description_' + loc, true); grid.appendChild(part); }); card.appendChild(grid);
      var actions = el('div', '', 'hkp-actions'); button(actions, '↑', function () { if (i) { var prev = steps[i - 1]; steps[i - 1] = s; steps[i] = prev; } }); button(actions, '↓', function () { if (i < steps.length - 1) { var next = steps[i + 1]; steps[i + 1] = s; steps[i] = next; } }); button(actions, 'Remove step', function () { if (window.confirm('Remove this step? Enrolled paths protect existing steps.')) steps.splice(i, 1); }); card.appendChild(actions);
      s.items = s.items || [];
      s.items.forEach(function (item, j) {
        var row = el('div', '', 'hkp-row');
        var typeLabel = el('label', 'Learning type', 'hkp-field'), type = el('select', '', 'hkp-select'); type.setAttribute('aria-label', 'Learning type');
        Object.keys(data.items).forEach(function (key) { var o = el('option', key); o.value = key; type.appendChild(o); }); type.value = item.item_type;
        type.addEventListener('change', function () { item.item_type = type.value; item.item_id = 0; render(); }); typeLabel.appendChild(type); row.appendChild(typeLabel);
        var itemLabel = el('label', 'Learning item', 'hkp-field'), select = el('select', '', 'hkp-select'); select.setAttribute('aria-label', 'Learning item'); var blank = el('option', 'Choose an item'); blank.value = '0'; select.appendChild(blank);
        (data.items[item.item_type] || []).forEach(function (r) { var o = el('option', r.title || String(r.id)); o.value = r.id; select.appendChild(o); }); select.value = item.item_id;
        if (Number(item.item_id) && !select.value) { var missing = el('option', 'Unavailable item #' + item.item_id + ' — replace before saving'); missing.value = item.item_id; select.appendChild(missing); select.value = item.item_id; }
        select.addEventListener('change', function () { item.item_id = Number(select.value); sync(); }); itemLabel.appendChild(select); row.appendChild(itemLabel);
        var required = el('label', 'Required', 'hkp-check'), check = el('input'); check.type = 'checkbox'; check.checked = Number(item.is_mandatory) === 1; check.addEventListener('change', function () { item.is_mandatory = check.checked ? 1 : 0; sync(); }); required.prepend(check); row.appendChild(required);
        var itemActions = el('div', '', 'hkp-actions');
        button(itemActions, '↑', function () { if (j) { var prev = s.items[j - 1]; s.items[j - 1] = item; s.items[j] = prev; } }); button(itemActions, '↓', function () { if (j < s.items.length - 1) { var next = s.items[j + 1]; s.items[j + 1] = item; s.items[j] = next; } }); button(itemActions, 'Remove', function () { s.items.splice(j, 1); }); row.appendChild(itemActions); card.appendChild(row);
      });
      button(card, 'Add learning item', function () { s.items.push({item_type: 'course', item_id: 0, is_mandatory: 1}); }); host.appendChild(card);
    }); sync();
  }
  document.getElementById('studio-path-add').addEventListener('click', function () { steps.push({id: 0, title_en: '', title_ar: '', description_en: '', description_ar: '', items: []}); render(); host.lastElementChild.scrollIntoView({block: 'nearest'}); });
  render();
})();
