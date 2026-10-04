/* MCP & AI connections console. No framework; progressive enhancement over server-rendered tabs. */
(function () {
  'use strict';
  var root = document.getElementById('mcpc'); if (!root) return;
  var T = {}; try { T = JSON.parse(document.getElementById('mcpc-i18n').textContent); } catch (e) {}
  var live = document.getElementById('mcpc-live');
  var say = function (m) { if (live) { live.textContent = ''; setTimeout(function () { live.textContent = m; }, 30); } };
  var esc = function (s) { return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); };
  var $ = function (s, el) { return (el || document).querySelector(s); };
  var $$ = function (s, el) { return Array.prototype.slice.call((el || document).querySelectorAll(s)); };
  var headers = { 'Content-Type': 'application/json', 'X-HA-CSRF': root.dataset.csrf, 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' };
  function api(url, body) {
    return fetch(url, body === undefined ? { headers: headers, credentials: 'same-origin' } : { method: 'POST', headers: headers, credentials: 'same-origin', body: JSON.stringify(body) })
      .then(function (r) { return r.json().catch(function () { return { ok: false, error: 'HTTP ' + r.status }; }).then(function (j) { if (!r.ok && j.ok !== false) j.ok = false; return j; }); });
  }
  function pretty(v) {
    var json = JSON.stringify(v, null, 2); if (json === undefined) return '';
    return esc(json).replace(/(&quot;(?:\\.|[^&]|&(?!quot;))*?&quot;)(\s*:)?|\b(true|false|null)\b|(-?\d+(?:\.\d+)?(?:[eE][+-]?\d+)?)/g, function (m, str, colon, bool, num) {
      if (str) return colon ? '<span class="k">' + str + '</span>' + colon : '<span class="s">' + str + '</span>';
      if (bool) return '<span class="b">' + bool + '</span>';
      return '<span class="n">' + num + '</span>';
    });
  }

  // ---------------------------------------------------------------- copy + confirm
  document.addEventListener('click', function (e) {
    var b = e.target.closest('.mcpc-copy'); if (!b) return;
    var text = b.getAttribute('data-copy');
    var done = function () { say(T.copied); var s = b.querySelector('span'); if (s) { var old = s.textContent; s.textContent = '✓'; setTimeout(function () { s.textContent = old; }, 1400); } };
    if (navigator.clipboard && window.isSecureContext) navigator.clipboard.writeText(text).then(done, function () { fallback(); });
    else fallback();
    function fallback() { var t = document.createElement('textarea'); t.value = text; t.setAttribute('readonly', ''); t.style.position = 'fixed'; t.style.opacity = '0'; document.body.appendChild(t); t.select(); try { document.execCommand('copy'); done(); } catch (x) { say(T.copy_failed); } document.body.removeChild(t); }
  });
  $$('form[data-confirm]').forEach(function (f) { f.addEventListener('submit', function (e) { if (!window.confirm(f.getAttribute('data-confirm'))) e.preventDefault(); }); });

  // ---------------------------------------------------------------- overview: health
  var checks = document.getElementById('mcpc-checks');
  function runHealth() {
    if (!checks) return; checks.setAttribute('aria-busy', 'true');
    checks.innerHTML = '<li class="mcpc-skeleton"></li><li class="mcpc-skeleton"></li><li class="mcpc-skeleton"></li>';
    var sum = document.getElementById('mcpc-health-summary'); if (sum) sum.textContent = T.checking;
    api(root.dataset.urlHealth).then(function (j) {
      checks.setAttribute('aria-busy', 'false');
      if (!j.checks) { checks.innerHTML = '<li><span class="mcpc-dot mcpc-dot--bad">!</span><div>' + esc(j.error || T.network) + '</div><span></span></li>'; return; }
      checks.innerHTML = j.checks.map(function (c) {
        return '<li><span class="mcpc-dot' + (c.ok ? '' : ' mcpc-dot--bad') + '" aria-hidden="true">' + (c.ok ? '✓' : '!') + '</span><div><b>' + esc(c.label) + '</b><small dir="ltr">' + esc(c.url) + '</small></div>' +
          '<span class="mcpc-status mcpc-status--' + (c.ok ? 'ok' : 'error') + '">' + esc((c.mode === 'http' ? T.http : T.in_process) + ' · ' + c.status + ' · ' + c.ms + ' ms') + '</span></li>';
      }).join('');
      if (sum) sum.textContent = (j.ok ? T.healthy : T.unhealthy) + ' · ' + new Date(j.checked_at).toLocaleTimeString();
      say(j.ok ? T.healthy : T.unhealthy);
    }, function () { checks.setAttribute('aria-busy', 'false'); checks.innerHTML = '<li><span class="mcpc-dot mcpc-dot--bad">!</span><div>' + esc(T.network) + '</div><span></span></li>'; });
  }
  if (checks) { runHealth(); var hb = $('[data-health-run]'); if (hb) hb.addEventListener('click', runHealth); }

  // ---------------------------------------------------------------- levels: custom scopes + secret focus
  var ptForm = $('[data-pt-form]');
  if (ptForm) {
    var custom = $('[data-pt-custom]', ptForm);
    var sync = function () { var v = (ptForm.querySelector('input[name=level]:checked') || {}).value; custom.hidden = v !== 'custom'; };
    $$('input[name=level]', ptForm).forEach(function (r) { r.addEventListener('change', sync); }); sync();
  }
  var secret = document.getElementById('mcpc-secret'); if (secret) secret.focus();

  // ---------------------------------------------------------------- activity drawer
  var drawer = document.getElementById('mcpc-drawer');
  if (drawer) {
    var body = document.getElementById('mcpc-drawer-body'), opener = null;
    $$('[data-call]').forEach(function (b) {
      b.addEventListener('click', function () {
        opener = b; body.innerHTML = '<p class="mcpc-muted">' + esc(T.loading) + '</p>';
        if (drawer.showModal) drawer.showModal(); else drawer.setAttribute('open', '');
        api(root.dataset.urlCall + b.getAttribute('data-call')).then(function (j) {
          if (!j.ok) { body.innerHTML = '<p>' + esc(j.error || T.network) + '</p>'; return; }
          var c = j.call, rows = [[T.f_time, c.created_at + ' UTC'], [T.f_method, c.method], [T.tool, c.tool || '—'], [T.f_source, c.source], [T.f_client, (c.client_name || c.client_id || '—')], [T.f_user, c.email || '—'],
            [T.f_status, '<span class="mcpc-status mcpc-status--' + esc(c.status) + '">' + esc(T[c.status] || c.status) + '</span>' + (c.dry_run === '1' || c.dry_run === 1 ? ' <span class="mcpc-pill">' + esc(T.dry_badge) + '</span>' : '')],
            [T.f_code, c.error_code || '—'], [T.f_latency, c.ms + ' ms'], [T.f_request, '<code dir="ltr">' + esc(c.request_id || '—') + '</code>'], [T.f_summary, '<code dir="ltr">' + esc(c.summary || '—') + '</code>']];
          body.innerHTML = '<dl>' + rows.map(function (r, i) { return '<dt>' + esc(r[0]) + '</dt><dd>' + (i === 6 || i >= 9 ? r[1] : esc(r[1])) + '</dd>'; }).join('') + '</dl>' +
            (c.audit && c.audit.length ? '<h3>' + esc(T.f_audit) + '</h3><pre class="mcpc-json" dir="ltr">' + pretty(c.audit) + '</pre>' : '');
        }, function () { body.innerHTML = '<p>' + esc(T.network) + '</p>'; });
      });
    });
    var close = function () { if (drawer.close) drawer.close(); else drawer.removeAttribute('open'); };
    $('[data-close]', drawer).addEventListener('click', close);
    drawer.addEventListener('click', function (e) { if (e.target === drawer) close(); });
    drawer.addEventListener('close', function () { if (opener) opener.focus(); });
  }

  // ---------------------------------------------------------------- test console
  var listEl = document.getElementById('mcpc-tool-list'); if (!listEl) return;
  var catalogue = []; try { catalogue = JSON.parse(document.getElementById('mcpc-catalogue').textContent); } catch (e) {}
  var state = { tool: null, mode: 'form', available: null, seq: 1, last: null, rtab: 'result' };
  var level = function () { return (document.querySelector('input[name=mcpc-level]:checked') || {}).value || 'read'; };
  var dry = function () { var d = document.getElementById('mcpc-dry'); return !d || d.checked; };
  var argsForm = document.getElementById('mcpc-args'), raw = document.getElementById('mcpc-raw'), exec = document.getElementById('mcpc-exec');
  var q = document.getElementById('mcpc-tool-q');
  var icon = function (n) { return '<svg class="hkp-icon" aria-hidden="true"><use href="#i-' + n + '"></use></svg>'; };

  function renderList() {
    var term = (q.value || '').toLowerCase().trim(), groups = { read: [], write: [], full: [] };
    catalogue.forEach(function (t) { if (!term || (t.name + ' ' + t.title + ' ' + t.description).toLowerCase().indexOf(term) !== -1) groups[t.group].push(t); });
    var html = '';
    ['read', 'write', 'full'].forEach(function (g) {
      if (!groups[g].length) return;
      html += '<div class="mcpc-tool-group" role="listitem"><h3>' + esc(T['group_' + g]) + ' · ' + groups[g].length + '</h3>' + groups[g].map(function (t) {
        var locked = state.available && state.available.indexOf(t.name) === -1;
        return '<button type="button" class="mcpc-tool' + (locked ? ' is-locked' : '') + '" data-tool="' + esc(t.name) + '"' + (state.tool && state.tool.name === t.name ? ' aria-current="true"' : '') + ' title="' + esc(locked ? T.locked : T.available) + '">' +
          '<b>' + esc(t.title) + '</b>' + (locked ? icon('lock') : '') + '<code dir="ltr">' + esc(t.name) + '</code></button>';
      }).join('') + '</div>';
    });
    listEl.innerHTML = html || '<p class="mcpc-muted">' + esc(T.no_match) + '</p>';
  }
  q.addEventListener('input', renderList);
  listEl.addEventListener('click', function (e) { var b = e.target.closest('[data-tool]'); if (b) select(b.getAttribute('data-tool')); });

  function typesOf(s) { return [].concat(s && s.type ? s.type : 'string'); }
  function fieldHtml(name, s, required) {
    var id = 'arg-' + name, types = typesOf(s), label = '<label for="' + id + '">' + esc(name) + (required ? ' <span class="mcpc-muted">· ' + esc(T.required) + '</span>' : '') + '</label>';
    var help = s.description ? '<small>' + esc(s.description) + '</small>' : '', input;
    if (s.enum) input = '<select id="' + id + '" data-arg="' + esc(name) + '" data-kind="enum"><option value=""></option>' + s.enum.map(function (v) { return '<option>' + esc(v) + '</option>'; }).join('') + '</select>';
    else if (types[0] === 'boolean') input = '<select id="' + id + '" data-arg="' + esc(name) + '" data-kind="boolean"><option value=""></option><option value="true">true</option><option value="false">false</option></select>';
    else if (types[0] === 'object' || (types[0] === 'array' && !(s.items && /string|integer/.test(String(s.items.type))))) input = '<textarea id="' + id + '" data-arg="' + esc(name) + '" data-kind="json" dir="ltr" placeholder="' + (types[0] === 'array' ? '[]' : '{}') + '"></textarea>';
    else if (types[0] === 'array') input = '<input id="' + id + '" data-arg="' + esc(name) + '" data-kind="list-' + (s.items.type === 'integer' ? 'int' : 'str') + '" dir="ltr" placeholder="a, b, c">';
    else if (types[0] === 'integer' || types[0] === 'number') input = '<input id="' + id + '" type="number" data-arg="' + esc(name) + '" data-kind="number" dir="ltr"' + (s.minimum != null ? ' min="' + s.minimum + '"' : '') + (s.maximum != null ? ' max="' + s.maximum + '"' : '') + '>';
    else if (types.indexOf('integer') !== -1) input = '<input id="' + id + '" data-arg="' + esc(name) + '" data-kind="intish" dir="ltr">';
    else if ((s.maxLength || 0) > 300) input = '<textarea id="' + id + '" data-arg="' + esc(name) + '" data-kind="string"></textarea>';
    else input = '<input id="' + id + '" data-arg="' + esc(name) + '" data-kind="string" dir="auto"' + (s.maxLength ? ' maxlength="' + s.maxLength + '"' : '') + '>';
    var wide = /json|string/.test(input) && (types[0] === 'object' || (s.maxLength || 0) > 300 || types[0] === 'array');
    return '<div class="mcpc-field' + (wide || s.description && s.description.length > 80 ? ' mcpc-field--wide' : '') + '">' + label + input + help + '</div>';
  }
  function select(name) {
    var t = catalogue.filter(function (x) { return x.name === name; })[0]; if (!t) return;
    state.tool = t; renderList();
    var a = t.annotations || {}, badges = [];
    badges.push('<span class="mcpc-level mcpc-level--' + t.group + '">' + esc(T['level_' + t.group]) + '</span>');
    badges.push('<span class="mcpc-pill">' + esc(a.readOnlyHint ? T.read_only : T.writes) + '</span>');
    if (a.destructiveHint) badges.push('<span class="mcpc-status mcpc-status--error">' + esc(T.destructive) + '</span>');
    if (a.idempotentHint) badges.push('<span class="mcpc-pill">' + esc(T.idempotent) + '</span>');
    if (t.dry_run_unsafe) badges.push('<span class="mcpc-status mcpc-status--denied">' + esc(T.dry_unsafe) + '</span>');
    badges.push('<code class="mcpc-pill" dir="ltr">' + esc(t.scope) + '</code>');
    document.getElementById('mcpc-runner-sub').innerHTML = '<code dir="ltr">' + esc(t.name) + '</code>';
    document.getElementById('mcpc-tool-meta').innerHTML = '<div class="mcpc-badges">' + badges.join('') + '</div><p>' + esc(t.description) + '</p>' +
      '<details><summary class="mcpc-muted">inputSchema</summary><pre class="mcpc-json" dir="ltr">' + pretty(t.inputSchema) + '</pre></details>';
    var props = t.inputSchema.properties || {}, req = t.inputSchema.required || [];
    argsForm.innerHTML = Object.keys(props).map(function (k) { return fieldHtml(k, props[k], req.indexOf(k) !== -1); }).join('') || '<p class="mcpc-muted">{}</p>';
    raw.value = '{}'; exec.disabled = false; flag();
    var first = argsForm.querySelector('[data-arg]'); if (first) first.focus();
  }
  function collect() {
    var out = {};
    $$('[data-arg]', argsForm).forEach(function (el) {
      var v = el.value.trim(), k = el.getAttribute('data-arg'), kind = el.getAttribute('data-kind'); if (v === '') return;
      if (kind === 'number') out[k] = Number(v);
      else if (kind === 'intish') out[k] = /^-?\d+$/.test(v) ? parseInt(v, 10) : v;
      else if (kind === 'boolean') out[k] = v === 'true';
      else if (kind === 'json') { out[k] = JSON.parse(v); }
      else if (kind === 'list-int') out[k] = v.split(',').map(function (x) { return parseInt(x, 10); }).filter(function (x) { return !isNaN(x); });
      else if (kind === 'list-str') out[k] = v.split(',').map(function (x) { return x.trim(); }).filter(Boolean);
      else out[k] = v;
    });
    return out;
  }
  function fill(obj) {
    $$('[data-arg]', argsForm).forEach(function (el) {
      var k = el.getAttribute('data-arg'), kind = el.getAttribute('data-kind'), v = obj[k];
      if (v === undefined) { el.value = ''; return; }
      el.value = kind === 'json' ? JSON.stringify(v, null, 2) : (Array.isArray(v) ? v.join(', ') : String(v));
    });
  }
  $$('[data-mode]').forEach(function (b) {
    b.addEventListener('click', function () {
      var m = b.getAttribute('data-mode'); if (m === state.mode) return;
      try { if (m === 'raw') raw.value = JSON.stringify(collect(), null, 2); else fill(JSON.parse(raw.value || '{}')); }
      catch (e) { say(T.invalid_json); return; }
      state.mode = m; raw.hidden = m !== 'raw'; argsForm.hidden = m === 'raw';
      $$('[data-mode]').forEach(function (x) { var on = x === b; x.classList.toggle('is-on', on); x.setAttribute('aria-pressed', on ? 'true' : 'false'); });
    });
  });
  function flag() {
    var f = document.getElementById('mcpc-write-flag'); if (!f) return;
    if (!state.tool || !state.tool.write) { f.innerHTML = ''; return; }
    f.innerHTML = dry() ? '<span class="mcpc-pill">' + esc(T.dry_badge) + '</span>' : '<span class="mcpc-status mcpc-status--error">' + esc(T.live_badge) + '</span>';
  }
  var dryBox = document.getElementById('mcpc-dry'); if (dryBox) dryBox.addEventListener('change', flag);
  $$('input[name=mcpc-level]').forEach(function (r) { r.addEventListener('change', function () { state.available = null; renderList(); listTools(true); }); });

  function showResult(j) {
    var box = document.getElementById('mcpc-result'); box.hidden = false; state.last = j;
    var res = j.response || {}, r = res.result || {}, st, label;
    if (!j.ok && !j.response) { st = 'error'; label = j.error || T.network; }
    else if (res.error) { st = 'error'; label = T.error + ' ' + res.error.code + ': ' + res.error.message; }
    else if (r.isError) { var code = (r.structuredContent && r.structuredContent.error && r.structuredContent.error.code) || 'error'; st = code === 'insufficient_scope' ? 'denied' : 'error'; label = (st === 'denied' ? T.blocked : T.error) + ' · ' + code; }
    else { st = 'ok'; label = T.ok + (r.structuredContent && r.structuredContent.dry_run ? ' · ' + T.dry_badge : ''); }
    document.getElementById('mcpc-result-status').innerHTML = '<span class="mcpc-status mcpc-status--' + st + '">' + esc(label) + '</span>';
    document.getElementById('mcpc-result-ms').textContent = j.ms != null ? j.ms + ' ms' : '';
    document.getElementById('mcpc-result-scopes').textContent = j.effective_scopes ? T.effective + ': ' + (j.effective_scopes.join(' ') || T.none) : '';
    paint(); say(label);
  }
  function paint() {
    var j = state.last || {}, res = j.response || {}, r = res.result || {}, v;
    if (state.rtab === 'request') v = j.request; else if (state.rtab === 'response') v = j.response || j;
    else if (state.rtab === 'structured') v = r.structuredContent !== undefined ? r.structuredContent : (res.error || null);
    else { v = r.content && r.content[0] && r.content[0].type === 'text' ? (function () { try { return JSON.parse(r.content[0].text); } catch (e) { return r.content[0].text; } })() : (res.result || res.error || j.error); }
    var p = document.getElementById('rp'); p.innerHTML = typeof v === 'string' ? esc(v) : pretty(v); p.setAttribute('aria-labelledby', 'rt-' + state.rtab);
  }
  var rtabs = $$('[data-rtab]');
  rtabs.forEach(function (b, i) {
    b.addEventListener('click', function () { state.rtab = b.getAttribute('data-rtab'); rtabs.forEach(function (x) { var on = x === b; x.setAttribute('aria-selected', on ? 'true' : 'false'); x.tabIndex = on ? 0 : -1; }); paint(); });
    b.addEventListener('keydown', function (e) { var d = e.key === 'ArrowRight' ? 1 : e.key === 'ArrowLeft' ? -1 : 0; if (!d) return; if (document.dir === 'rtl') d = -d; var n = rtabs[(i + d + rtabs.length) % rtabs.length]; n.focus(); n.click(); });
  });
  function rpc(method, params) {
    var msg = { jsonrpc: '2.0', id: state.seq++, method: method }; if (params) msg.params = params;
    return api(root.dataset.urlRpc, { level: level(), dry_run: dry(), message: msg });
  }
  function busy(btn, on) { if (!btn) return; btn.disabled = on; btn.setAttribute('aria-busy', on ? 'true' : 'false'); }
  function listTools(silent) {
    return rpc('tools/list', {}).then(function (j) {
      var tools = j.response && j.response.result && j.response.result.tools; if (!tools) { if (!silent) showResult(j); return; }
      state.available = tools.map(function (t) { return t.name; }); renderList();
      document.getElementById('mcpc-tools-count').textContent = state.available.length + ' / ' + catalogue.length + ' ' + T.tools + ' · ' + T['level_' + level()];
      if (!silent) showResult(j);
    });
  }
  $$('[data-rpc-quick]').forEach(function (b) {
    b.addEventListener('click', function () {
      var m = b.getAttribute('data-rpc-quick'); busy(b, true);
      var p = m === 'initialize' ? rpc('initialize', { protocolVersion: '2025-06-18', capabilities: {}, clientInfo: { name: 'altus-console', version: '1.0' } }).then(showResult) : listTools(false);
      p.then(function () { busy(b, false); }, function () { busy(b, false); say(T.network); });
    });
  });
  exec.addEventListener('click', function () {
    if (!state.tool) return; var args;
    try { args = state.mode === 'raw' ? JSON.parse(raw.value || '{}') : collect(); } catch (e) { say(T.invalid_json); showResult({ ok: false, error: T.invalid_json }); return; }
    if (state.tool.write && !dry() && !window.confirm(T.confirm_live)) return;
    busy(exec, true); var label = exec.querySelector('span'), old = label.textContent; label.textContent = T.running;
    rpc('tools/call', { name: state.tool.name, arguments: args }).then(function (j) { showResult(j); }, function () { showResult({ ok: false, error: T.network }); })
      .then(function () { busy(exec, false); label.textContent = old; });
  });

  // smoke test
  var smokeBtn = $('[data-smoke]');
  if (smokeBtn) smokeBtn.addEventListener('click', function () {
    busy(smokeBtn, true); var s = smokeBtn.querySelector('span'), old = s.textContent; s.textContent = T.running;
    api(root.dataset.urlSmoke, {}).then(function (j) {
      busy(smokeBtn, false); s.textContent = old;
      var box = document.getElementById('mcpc-smoke'); box.hidden = false;
      if (!j.rows) { document.getElementById('mcpc-smoke-sum').textContent = j.error || T.network; return; }
      var tools = []; j.rows.forEach(function (r) { if (tools.indexOf(r.tool) === -1) tools.push(r.tool); });
      var lv = ['read', 'write', 'full'];
      var head = '<thead><tr><th>' + esc(T.tool) + '</th>' + lv.map(function (l) { var x = j.levels[l]; return '<th>' + esc(T['level_' + l]) + '<small>' + x.tools + ' ' + esc(T.tools) + (x.initialized ? ' · ' + esc(T.session_ok) : '') + '</small></th>'; }).join('') + '</tr></thead>';
      var bodyHtml = tools.map(function (t) {
        return '<tr><td><code dir="ltr">' + esc(t) + '</code></td>' + lv.map(function (l) {
          var r = j.rows.filter(function (x) { return x.tool === t && x.level === l; })[0];
          return '<td><span class="mcpc-status mcpc-status--' + (r.outcome === 'ok' ? 'ok' : r.outcome) + '">' + (r.pass ? '✓ ' : '✗ ') + esc(T[r.outcome] || r.outcome) + '</span><small>' + esc(T.expected) + ': ' + esc(T[r.expect]) + (r.dry_run ? ' · ' + esc(T.dry_badge) : '') + ' · ' + r.ms + ' ms</small></td>';
        }).join('') + '</tr>';
      }).join('');
      document.getElementById('mcpc-matrix').innerHTML = head + '<tbody>' + bodyHtml + '</tbody>';
      document.getElementById('mcpc-smoke-sum').innerHTML = '<span class="mcpc-score"><span class="mcpc-status mcpc-status--' + (j.ok ? 'ok' : 'error') + '">' + j.passed + '/' + j.total + ' ' + esc(T.passed) + '</span></span> · ' + j.ms + ' ms';
      box.focus(); say(j.passed + '/' + j.total + ' ' + T.passed);
    }, function () { busy(smokeBtn, false); s.textContent = old; say(T.network); });
  });

  renderList(); listTools(true);
})();
