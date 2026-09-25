/* altus HK&P workspace behaviour: small, dependency-free, progressive. */
(function () {
  'use strict';
  var H = window.HKP || {};

  function post(url, data) {
    var body = new URLSearchParams(data || {});
    body.append('ha_csrf', H.csrf);
    return fetch(url, { method: 'POST', body: body, credentials: 'same-origin',
      headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' } }).then(function (r) { return r.json(); });
  }
  H.post = post;

  // Mobile navigation
  var toggle = document.querySelector('[data-toggle-nav]');
  var side = document.getElementById('hkp-side');
  if (toggle && side) {
    toggle.addEventListener('click', function () {
      var open = side.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', open ? 'true' : 'false');
    });
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape') { side.classList.remove('is-open'); toggle.setAttribute('aria-expanded', 'false'); } });
    side.addEventListener('click', function (e) { if (e.target === side) { side.classList.remove('is-open'); } });
  }

  // Instant search suggestions (permission-aware on the server)
  var q = document.getElementById('hkp-q');
  if (q) {
    var box = q.parentNode.querySelector('.hkp-suggest');
    var timer = null;
    q.addEventListener('input', function () {
      clearTimeout(timer);
      var term = q.value.trim();
      if (term.length < 2) { box.hidden = true; return; }
      timer = setTimeout(function () {
        fetch(q.getAttribute('data-suggest') + '?q=' + encodeURIComponent(term), { credentials: 'same-origin', headers: { 'Accept': 'application/json' } })
          .then(function (r) { return r.json(); }).then(function (d) {
            box.innerHTML = '';
            (d.results || []).forEach(function (s) {
              var a = document.createElement('a');
              a.href = s.url; a.setAttribute('role', 'option');
              a.textContent = s.title;
              var sm = document.createElement('small'); sm.textContent = s.kind; a.appendChild(sm);
              box.appendChild(a);
            });
            box.hidden = !box.children.length;
          }).catch(function () { box.hidden = true; });
      }, 180);
    });
    q.addEventListener('blur', function () { setTimeout(function () { box.hidden = true; }, 200); });
  }

  // Lesson time tracking: time spent and last position, sent every 30 s and on leave.
  var lesson = document.querySelector('[data-lesson-track]');
  if (lesson) {
    var url = lesson.getAttribute('data-lesson-track');
    var started = Date.now();
    var video = lesson.querySelector('video');
    var send = function (useBeacon) {
      var secs = Math.round((Date.now() - started) / 1000);
      started = Date.now();
      if (secs < 2) { return; }
      var data = { seconds: secs, position: video ? Math.round(video.currentTime) : 0 };
      if (useBeacon && navigator.sendBeacon) {
        var fd = new FormData(); fd.append('seconds', data.seconds); fd.append('position', data.position); fd.append('ha_csrf', H.csrf);
        navigator.sendBeacon(url, fd);
      } else { post(url, data); }
    };
    setInterval(function () { if (!document.hidden) { send(false); } }, 30000);
    document.addEventListener('visibilitychange', function () { if (document.hidden) { send(true); } else { started = Date.now(); } });
    window.addEventListener('pagehide', function () { send(true); });
    var resume = parseInt(lesson.getAttribute('data-resume') || '0', 10);
    if (video && resume > 5) { video.addEventListener('loadedmetadata', function () { video.currentTime = resume; }, { once: true }); }
  }

  // Drag-and-drop sortable lists (ordering questions, curriculum, page sections). Keyboard: Alt+Up/Down.
  document.querySelectorAll('[data-sortable]').forEach(function (list) {
    var dragging = null;
    var sync = function (initial) {
      var target = list.getAttribute('data-sortable');
      var input = target ? document.getElementById(target) : null;
      if (input) { input.value = Array.prototype.map.call(list.children, function (li) { return li.getAttribute('data-id'); }).join(','); }
      if (initial !== true) { list.dispatchEvent(new CustomEvent('hkp:sorted', { bubbles: true })); }
    };
    Array.prototype.forEach.call(list.children, function (li) {
      li.draggable = true;
      li.tabIndex = 0;
      li.addEventListener('dragstart', function () { dragging = li; li.classList.add('dragging'); });
      li.addEventListener('dragend', function () { li.classList.remove('dragging'); dragging = null; sync(); });
      li.addEventListener('keydown', function (e) {
        if (!e.altKey) { return; }
        if (e.key === 'ArrowUp' && li.previousElementSibling) { list.insertBefore(li, li.previousElementSibling); li.focus(); sync(); e.preventDefault(); }
        if (e.key === 'ArrowDown' && li.nextElementSibling) { list.insertBefore(li.nextElementSibling, li); li.focus(); sync(); e.preventDefault(); }
      });
    });
    list.addEventListener('dragover', function (e) {
      e.preventDefault();
      if (!dragging) { return; }
      var after = Array.prototype.slice.call(list.children).filter(function (c) { return c !== dragging; }).find(function (c) {
        var r = c.getBoundingClientRect(); return e.clientY < r.top + r.height / 2;
      });
      if (after) { list.insertBefore(dragging, after); } else { list.appendChild(dragging); }
    });
    sync(true);
  });

  // Governed assistant
  var chat = document.querySelector('[data-assistant]');
  if (chat) {
    var form = chat.querySelector('form');
    var log = chat.querySelector('.hkp-chat');
    var add = function (cls, text) {
      var d = document.createElement('div'); d.className = 'hkp-msg ' + cls; d.textContent = text; log.appendChild(d); d.scrollIntoView({ block: 'end' }); return d;
    };
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      var input = form.querySelector('textarea');
      var question = input.value.trim();
      if (!question) { return; }
      add('hkp-msg--me', question);
      input.value = '';
      var wait = add('hkp-msg--ai', chat.getAttribute('data-thinking'));
      post(form.action, { question: question }).then(function (r) {
        wait.remove();
        if (!r.ok) { add('hkp-msg--ai hkp-msg--warn', r.error || 'Error'); return; }
        var m = add('hkp-msg--ai' + (r.coverage === 'answered' ? '' : ' hkp-msg--warn'), r.answer);
        if (r.sources && r.sources.length) {
          var s = document.createElement('div'); s.className = 'hkp-sources';
          s.appendChild(document.createTextNode(chat.getAttribute('data-sources') + ' '));
          r.sources.forEach(function (src) {
            var a = document.createElement('a'); a.href = src.url;
            a.textContent = '[' + src.ref + '] ' + src.title + (src.version ? ' v' + src.version : '') + ' (' + src.locale.toUpperCase() + ')';
            s.appendChild(a);
          });
          m.appendChild(s);
        }
      }).catch(function () { wait.textContent = 'Network error'; });
    });
    var ta = form.querySelector('textarea');
    ta.addEventListener('keydown', function (e) { if (e.key === 'Enter' && !e.shiftKey) { e.preventDefault(); form.requestSubmit(); } });
  }

  // Confirm dangerous actions
  document.addEventListener('submit', function (e) {
    var msg = e.target.getAttribute('data-confirm');
    if (msg && !window.confirm(msg)) { e.preventDefault(); }
  });

  // Live rubric preview: shows the running weighted score while rating.
  var rubric = document.querySelector('[data-rubric]');
  if (rubric) {
    var fractions = JSON.parse(rubric.getAttribute('data-fractions'));
    var out = rubric.querySelector('[data-score]');
    var calc = function () {
      var total = 0, earned = 0;
      rubric.querySelectorAll('[data-weight]').forEach(function (row) {
        var w = parseFloat(row.getAttribute('data-weight')); total += w;
        var picked = row.querySelector('input[type=radio]:checked');
        if (picked) { earned += w * (fractions[picked.value] || 0); }
      });
      if (out) { out.textContent = total ? (Math.round(1000 * earned / total) / 10) + '%' : '—'; }
    };
    rubric.addEventListener('change', calc);
    calc();
  }

  // AI writing panel: provider/model picker, enhance prompt, generate, insert.
  document.querySelectorAll('[data-ai-panel]').forEach(function (panel) {
    var data = panel.querySelector('[data-ai-models]');
    if (!data) { return; }
    var models = JSON.parse(data.textContent || '[]');
    var prov = panel.querySelector('[data-ai-provider]');
    var model = panel.querySelector('[data-ai-model]');
    var out = panel.querySelector('[data-ai-output]');
    var status = panel.querySelector('[data-ai-status]');
    var fill = function () {
      model.innerHTML = '';
      var p = models.filter(function (m) { return m.slug === prov.value; })[0];
      (p ? p.models : []).forEach(function (m) { var o = document.createElement('option'); o.value = m.id; o.textContent = m.label; model.appendChild(o); });
    };
    prov.addEventListener('change', fill); fill();
    var call = function (task, done) {
      status.textContent = '…';
      post(panel.getAttribute('data-endpoint'), {
        task: task, provider: prov.value, model: model.value, locale: panel.querySelector('[data-ai-locale]').value,
        prompt: panel.querySelector('[data-ai-prompt]').value, context: panel.querySelector('[data-ai-context]').value,
        entity_type: panel.getAttribute('data-entity'), entity_id: panel.getAttribute('data-entity-id')
      }).then(function (r) {
        if (!r.ok) { status.textContent = r.error || 'Error'; return; }
        status.textContent = r.provider + ' / ' + r.model + ' · ' + r.tokens + ' tokens';
        done(r);
      }).catch(function () { status.textContent = 'Network error'; });
    };
    panel.querySelector('[data-ai-enhance]').addEventListener('click', function () {
      call('enhance_prompt', function (r) { panel.querySelector('[data-ai-prompt]').value = r.text; });
    });
    panel.querySelector('[data-ai-run]').addEventListener('click', function () {
      call(panel.querySelector('[data-ai-task]').value, function (r) {
        if (r.json) {
          var j = r.json, lines = [];
          if (j.meta_title) { lines.push(j.meta_title, j.meta_description); }
          if (j.heading) { lines.push(j.heading); }
          if (j.body) { lines.push(j.body); }
          (j.items || []).forEach(function (it) { lines.push(it.q ? it.q + ' | ' + it.a : (it.title || '') + ' | ' + (it.text || '')); });
          (j.questions || []).forEach(function (q) { lines.push(q.q + ' → ' + q.options.join(' / ') + ' [' + q.correct + ']'); });
          out.value = lines.join('\n');
          out.setAttribute('data-json', JSON.stringify(j));
        } else { out.value = r.text; out.removeAttribute('data-json'); }
      });
    });
    panel.querySelector('[data-ai-copy]').addEventListener('click', function () { out.select(); try { document.execCommand('copy'); } catch (e) {} });
    var ins = panel.querySelector('[data-ai-insert]');
    if (ins) {
      ins.addEventListener('click', function () {
        var target = document.querySelector(ins.getAttribute('data-ai-insert'));
        if (!target) { return; }
        var j = out.getAttribute('data-json') ? JSON.parse(out.getAttribute('data-json')) : null;
        target.value = j && j.body ? j.body : out.value;
        target.dispatchEvent(new Event('input', { bubbles: true }));
        target.focus();
      });
    }
  });

  // Installable app (PWA)
  if ('serviceWorker' in navigator && H.sw) {
    window.addEventListener('load', function () { navigator.serviceWorker.register(H.sw, { scope: H.base + '/' }).catch(function () {}); });
  }
})();
