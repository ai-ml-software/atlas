<?php $ha_tab = 'studio'; include __DIR__ . '/_head.php'; ?>
<?php
$ready = isset($routes['course_outline']) && isset($routes['lesson_script']);
?>
<?php if (!$ready): ?>
    <div class="alert alert-warning">Route a model for <strong>course_outline</strong> and <strong>lesson_script</strong> first
        <?php if (!empty($ha_can['configure'])): ?>(<a href="<?php echo site_url('ha_ai/routes'); ?>">Task routing</a>)<?php else: ?>(ask an administrator)<?php endif; ?>.
        Jobs can be queued now but will fail until then.</div>
<?php endif; ?>

<div class="row g-3">
<div class="col-xl-5">
    <div class="card"><div class="card-body">
        <h5 class="card-title"><i class="mdi mdi-book-plus-outline"></i> Generate a new course</h5>
        <form method="post" action="<?php echo site_url('ha_ai/queue_course'); ?>">
            <?php echo ha_csrf_field(); ?>
            <div class="mb-2">
                <label for="ha-topic" class="form-label">Topic and scope</label>
                <textarea id="ha-topic" name="topic" rows="3" class="form-control" required minlength="8" maxlength="2000"
                          placeholder="e.g. Handling guest arrivals during Hajj and Umrah peaks for front office teams in Makkah hotels"></textarea>
            </div>
            <div class="row g-2 mb-2">
                <div class="col-sm-6">
                    <label for="ha-cat" class="form-label">Department</label>
                    <select id="ha-cat" name="category_code" class="form-select">
                        <option value="">Let the model choose</option>
                        <?php foreach ($categories as $c): ?><option value="<?php echo html_escape($c['code']); ?>"><?php echo html_escape($c['name']); ?></option><?php endforeach; ?>
                    </select>
                </div>
                <div class="col-sm-3">
                    <label for="ha-level" class="form-label">Level</label>
                    <select id="ha-level" name="level" class="form-select">
                        <option>foundation</option><option>intermediate</option><option>advanced</option><option>leadership</option>
                    </select>
                </div>
                <div class="col-sm-3">
                    <label for="ha-lessons" class="form-label">Lessons</label>
                    <input id="ha-lessons" name="lessons" type="number" min="3" max="40" value="8" class="form-control">
                </div>
            </div>
            <div class="mb-2">
                <label for="ha-aud" class="form-label">Audience <span class="text-muted small">(optional)</span></label>
                <input id="ha-aud" name="audience" class="form-control" maxlength="500" placeholder="New receptionists in their first month">
            </div>
            <div class="mb-2">
                <label for="ha-notes" class="form-label">Notes for the author model <span class="text-muted small">(optional)</span></label>
                <textarea id="ha-notes" name="notes" rows="2" class="form-control" maxlength="2000" placeholder="Must cover our PMS workflow; avoid alcohol service"></textarea>
            </div>
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="auto_scripts" value="1" id="ha-auto">
                <label class="form-check-label" for="ha-auto">After publishing, queue a video script for every video lesson</label>
            </div>
            <button class="btn btn-primary"><i class="mdi mdi-creation"></i> Generate outline</button>
            <div class="form-text">Creates a bilingual (EN + AR) draft for review. Nothing is published until you approve it.</div>
        </form>
    </div></div>
</div>

<div class="col-xl-7">
    <div class="card"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h5 class="card-title mb-0"><i class="mdi mdi-movie-open-plus-outline"></i> Video lessons with no video <span class="badge bg-light text-dark"><?php echo count($gap); ?></span></h5>
            <form method="get" action="<?php echo site_url('ha_ai/studio'); ?>" class="d-flex gap-2">
                <label for="ha-course" class="visually-hidden">Course</label>
                <select id="ha-course" name="course" class="form-select form-select-sm" onchange="this.form.submit()">
                    <option value="">All courses</option>
                    <?php foreach ($courses as $c): ?><option value="<?php echo (int) $c['id']; ?>" <?php echo $course_id === (int) $c['id'] ? 'selected' : ''; ?>><?php echo html_escape($c['code'] . ' — ' . $c['title']); ?></option><?php endforeach; ?>
                </select>
            </form>
        </div>
        <form method="post" action="<?php echo site_url('ha_ai/queue_scripts'); ?>">
            <?php echo ha_csrf_field(); ?>
            <div style="max-height:360px;overflow:auto" class="border rounded mb-2">
                <table class="table table-sm table-hover mb-0 align-middle">
                    <thead class="table-light" style="position:sticky;top:0"><tr>
                        <th style="width:32px"><input type="checkbox" class="form-check-input" id="ha-all" aria-label="Select all"></th>
                        <th>Lesson</th><th>Course</th><th>Script</th></tr></thead>
                    <tbody>
                    <?php foreach ($gap as $l): ?>
                        <tr>
                            <td><input type="checkbox" class="form-check-input ha-pick" name="lesson_ids[]" value="<?php echo (int) $l['id']; ?>" aria-label="Select <?php echo html_escape($l['title']); ?>"></td>
                            <td><?php echo html_escape($l['title']); ?></td>
                            <td class="small text-muted"><?php echo html_escape($l['code']); ?></td>
                            <td><?php echo $l['script_job_id'] ? '<a href="' . site_url('ha_ai/job/' . $l['script_job_id']) . '" class="small">#' . (int) $l['script_job_id'] . '</a>' : '<span class="small text-muted">—</span>'; ?></td>
                        </tr>
                    <?php endforeach; ?>
                    <?php if (!$gap): ?><tr><td colspan="4" class="text-muted p-3">Every video lesson in this selection has a video.</td></tr><?php endif; ?>
                    </tbody>
                </table>
            </div>
            <div class="row g-2 align-items-end">
                <div class="col-sm-3">
                    <label for="ha-min" class="form-label small">Minutes per lesson</label>
                    <input id="ha-min" name="minutes" type="number" min="2" max="20" value="5" class="form-control form-control-sm">
                </div>
                <div class="col-sm-9">
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="then_render" value="1" id="ha-render" checked>
                        <label class="form-check-label small" for="ha-render">Render EN + AR slide videos automatically once a script is approved</label>
                    </div>
                </div>
                <div class="col-12">
                    <label for="ha-snotes" class="form-label small">Notes for every script <span class="text-muted">(optional)</span></label>
                    <input id="ha-snotes" name="notes" class="form-control form-control-sm" maxlength="2000">
                </div>
                <div class="col-12">
                    <button class="btn btn-primary btn-sm" id="ha-queue" disabled><i class="mdi mdi-script-text-outline"></i> Generate scripts for <span id="ha-n">0</span> lesson(s)</button>
                </div>
            </div>
        </form>
    </div></div>
</div>

<div class="col-12">
    <div class="card"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2">
            <h5 class="card-title mb-0">Jobs</h5>
            <div class="btn-group btn-group-sm" role="group" aria-label="Filter jobs">
                <?php foreach (array('' => 'All', 'draft' => 'To review', 'queued' => 'Queued', 'running' => 'Running', 'approved' => 'Approved', 'published' => 'Published', 'failed' => 'Failed') as $k => $label): ?>
                    <a class="btn btn-outline-secondary <?php echo (string) $this->input->get('status') === $k ? 'active' : ''; ?>" href="<?php echo site_url('ha_ai/studio' . ($k ? '?status=' . $k : '')); ?>"><?php echo $label; ?></a>
                <?php endforeach; ?>
            </div>
        </div>
        <div class="table-responsive"><table class="table table-sm align-middle mb-0">
            <thead><tr><th>#</th><th>Job</th><th>Type</th><th style="min-width:160px">Progress</th><th>Status</th><th>By</th><th>Updated</th></tr></thead>
            <tbody>
            <?php foreach ($jobs as $j): ?>
                <tr data-job="<?php echo (int) $j['id']; ?>" data-live="<?php echo in_array($j['status'], array('queued', 'running'), true) ? 1 : 0; ?>">
                    <td><?php echo (int) $j['id']; ?></td>
                    <td><a href="<?php echo site_url('ha_ai/job/' . $j['id']); ?>"><?php echo html_escape($j['title']); ?></a></td>
                    <td class="small"><?php echo html_escape(str_replace('_', ' ', $j['type'])); ?></td>
                    <td>
                        <div class="progress ha-progress" role="progressbar" aria-label="Progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $j['progress']; ?>">
                            <div class="progress-bar" style="width:<?php echo (int) $j['progress']; ?>%"></div>
                        </div>
                        <div class="small text-muted ha-note"><?php echo html_escape((string) $j['progress_note']); ?></div>
                    </td>
                    <td class="ha-status-cell"><?php echo ha_ai_status_badge($j['status']); ?></td>
                    <td class="small"><?php echo html_escape(trim($j['first_name'] . ' ' . $j['last_name'])); ?></td>
                    <td class="small text-muted"><?php echo html_escape($j['updated_at']); ?></td>
                </tr>
            <?php endforeach; ?>
            <?php if (!$jobs): ?><tr><td colspan="7" class="text-muted">No jobs yet.</td></tr><?php endif; ?>
            </tbody>
        </table></div>
    </div></div>
</div>
</div>
</div>
<script>
(function () {
    var picks = document.querySelectorAll('.ha-pick'), all = document.getElementById('ha-all');
    function count() {
        var n = document.querySelectorAll('.ha-pick:checked').length;
        document.getElementById('ha-n').textContent = n;
        document.getElementById('ha-queue').disabled = n === 0;
    }
    picks.forEach(function (p) { p.addEventListener('change', count); });
    all && all.addEventListener('change', function () { picks.forEach(function (p) { p.checked = all.checked; }); count(); });

    // Poll live jobs so progress moves without a reload.
    function poll() {
        var rows = document.querySelectorAll('tr[data-live="1"]');
        if (!rows.length) return;
        rows.forEach(function (tr) {
            fetch('<?php echo site_url('ha_ai/job_status/'); ?>' + tr.dataset.job, { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
                .then(function (r) { return r.json(); }).then(function (j) {
                    if (!j.ok) return;
                    tr.querySelector('.progress-bar').style.width = j.progress + '%';
                    tr.querySelector('.progress').setAttribute('aria-valuenow', j.progress);
                    tr.querySelector('.ha-note').textContent = j.error && j.status === 'failed' ? j.error : (j.note || '');
                    var badge = tr.querySelector('.ha-status');
                    if (badge.dataset.status !== j.status) {
                        badge.dataset.status = j.status; badge.textContent = j.status;
                        badge.className = 'badge ha-status bg-' + ({queued:'secondary',running:'info',draft:'warning',approved:'primary',published:'success',rejected:'dark',failed:'danger'}[j.status] || 'light');
                    }
                    if (['queued', 'running'].indexOf(j.status) === -1) tr.dataset.live = '0';
                });
        });
        setTimeout(poll, 4000);
    }
    setTimeout(poll, 3000);
})();
</script>
