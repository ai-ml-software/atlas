<?php $ha_tab = 'studio'; include __DIR__ . '/_head.php'; ?>
<?php
require_once APPPATH . 'libraries/Ha_ai_studio.php';
$st = $job['status'];
$live = in_array($st, array('queued', 'running'), true);
$is_script = $job['type'] === 'lesson_script';
$is_course = $job['type'] === 'course_draft';
$is_video = in_array($job['type'], array('video_render', 'avatar_video'), true);
$action = function ($name, $label, $class, $icon, $confirm = '') {
    return '<button name="action" value="' . $name . '" class="btn btn-sm ' . $class . '"' . ($confirm ? ' onclick="return confirm(\'' . html_escape($confirm) . '\')"' : '') . '><i class="mdi ' . $icon . '"></i> ' . $label . '</button>';
};
?>
<div class="row g-3">
<div class="col-xl-8">
    <div class="card"><div class="card-body">
        <div class="d-flex flex-wrap justify-content-between gap-2 mb-2">
            <div>
                <h5 class="mb-1"><?php echo html_escape($job['title']); ?></h5>
                <div class="small text-muted"><?php echo html_escape(str_replace('_', ' ', $job['type'])); ?> · created <?php echo html_escape($job['created_at']); ?><?php echo isset($people['created_by']) ? ' by ' . html_escape($people['created_by']) : ''; ?>
                    <?php if (!empty($output['model'])): ?> · model <span class="ha-mono"><?php echo html_escape($output['model']); ?></span><?php endif; ?></div>
            </div>
            <div id="ha-status-wrap"><?php echo ha_ai_status_badge($st); ?></div>
        </div>

        <?php if ($live): ?>
            <div class="progress ha-progress mb-1" role="progressbar" aria-label="Progress" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo (int) $job['progress']; ?>"><div class="progress-bar progress-bar-striped progress-bar-animated" id="ha-bar" style="width:<?php echo (int) $job['progress']; ?>%"></div></div>
            <p class="small text-muted" id="ha-note" role="status"><?php echo html_escape((string) $job['progress_note']); ?></p>
        <?php endif; ?>
        <?php if ($job['error']): ?><div class="alert alert-danger small mb-2"><strong>Error:</strong> <?php echo html_escape($job['error']); ?></div><?php endif; ?>
        <?php if ($job['review_note']): ?><div class="alert alert-secondary small mb-2"><strong>Review note:</strong> <?php echo html_escape($job['review_note']); ?></div><?php endif; ?>
        <?php if ($lesson): ?><p class="small mb-0"><i class="mdi mdi-book-open-page-variant-outline"></i> <?php echo html_escape($lesson['course_en']); ?> › <?php echo html_escape($lesson['section_en']); ?> › <strong><?php echo html_escape($lesson['title_en']); ?></strong></p><?php endif; ?>
    </div></div>

    <?php if ($is_course && !empty($output['outline'])): $o = $output['outline']; ?>
    <div class="card"><div class="card-body">
        <div class="row g-4">
            <?php foreach (array('en' => 'ltr', 'ar' => 'rtl') as $loc => $dir): $t = $o[$loc]; ?>
            <div class="col-md-6" dir="<?php echo $dir; ?>" lang="<?php echo $loc; ?>">
                <span class="badge bg-light text-dark mb-2"><?php echo strtoupper($loc); ?></span>
                <h5><?php echo html_escape($t['title']); ?></h5>
                <p class="text-muted"><?php echo html_escape(isset($t['short_description']) ? $t['short_description'] : ''); ?></p>
                <div class="small mb-3"><?php echo nl2br(html_escape(isset($t['description']) ? $t['description'] : '')); ?></div>
                <?php if (!empty($t['outcomes'])): ?><h6><?php echo $loc === 'ar' ? 'مخرجات التعلم' : 'Outcomes'; ?></h6><ul class="small"><?php foreach ($t['outcomes'] as $x): ?><li><?php echo html_escape($x); ?></li><?php endforeach; ?></ul><?php endif; ?>
                <h6><?php echo $loc === 'ar' ? 'المحتوى' : 'Curriculum'; ?></h6>
                <ol class="small">
                    <?php foreach ($o['sections'] as $s): ?>
                        <li class="mb-1"><strong><?php echo html_escape($s[$loc]); ?></strong>
                            <ul><?php foreach ($s['lessons'] as $l): ?><li><?php echo html_escape($l[$loc]['title']); ?> <span class="text-muted">· <?php echo (int) (isset($l['minutes']) ? $l['minutes'] : 0); ?> min<?php echo isset($l['type']) && $l['type'] === 'text' ? ' · text' : ''; ?></span></li><?php endforeach; ?></ul>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <?php if (!empty($t['faqs'])): ?><h6>FAQ</h6><dl class="small"><?php foreach ($t['faqs'] as $f): ?><dt><?php echo html_escape($f['q']); ?></dt><dd><?php echo html_escape($f['a']); ?></dd><?php endforeach; ?></dl><?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="small text-muted mb-0">Code <span class="ha-mono"><?php echo html_escape($o['code']); ?></span> · level <?php echo html_escape($o['level']); ?> · category <?php echo html_escape(isset($o['category_code']) ? $o['category_code'] : '—'); ?></p>
    </div></div>
    <?php endif; ?>

    <?php if ($is_script && !empty($output['script'])): ?>
    <div class="card"><div class="card-body">
        <ul class="nav nav-tabs mb-3" role="tablist">
            <li class="nav-item" role="presentation"><button class="nav-link active" data-bs-toggle="tab" data-bs-target="#ha-en" type="button" role="tab">English</button></li>
            <li class="nav-item" role="presentation"><button class="nav-link" data-bs-toggle="tab" data-bs-target="#ha-ar" type="button" role="tab">العربية</button></li>
        </ul>
        <div class="tab-content">
        <?php foreach (array('en' => 'ltr', 'ar' => 'rtl') as $loc => $dir): $v = $output['script'][$loc]; ?>
            <div class="tab-pane fade <?php echo $loc === 'en' ? 'show active' : ''; ?>" id="ha-<?php echo $loc; ?>" role="tabpanel" dir="<?php echo $dir; ?>" lang="<?php echo $loc; ?>">
                <h5><?php echo html_escape(isset($v['title']) ? $v['title'] : ''); ?></h5>
                <p class="text-muted"><?php echo html_escape(isset($v['objective']) ? $v['objective'] : ''); ?></p>
                <h6><?php echo $loc === 'ar' ? 'الشرائح' : 'Slides'; ?> (<?php echo count($v['slides']); ?>)</h6>
                <div class="row g-2 mb-3">
                    <?php foreach ($v['slides'] as $i => $sl): ?>
                    <div class="col-md-6"><div class="ha-slide">
                        <div class="small opacity-75"><?php echo $i + 1; ?></div>
                        <h6><?php echo html_escape($sl['title']); ?></h6>
                        <?php if (!empty($sl['bullets'])): ?><ul><?php foreach ((array) $sl['bullets'] as $b): ?><li><?php echo html_escape($b); ?></li><?php endforeach; ?></ul><?php endif; ?>
                        <div class="ha-narration"><i class="mdi mdi-microphone-outline"></i> <?php echo html_escape($sl['narration']); ?></div>
                        <?php if (!empty($sl['visual'])): ?><div class="ha-narration"><i class="mdi mdi-filmstrip"></i> <?php echo html_escape($sl['visual']); ?></div><?php endif; ?>
                    </div></div>
                    <?php endforeach; ?>
                </div>
                <h6><?php echo $loc === 'ar' ? 'نص الدرس' : 'Written lesson'; ?></h6>
                <div class="border rounded p-3 mb-3"><?php echo Ha_ai_studio::clean_html($v['summary_html']); ?></div>
                <?php if (!empty($v['quiz'])): ?>
                    <h6><?php echo $loc === 'ar' ? 'أسئلة التحقق' : 'Self-check quiz'; ?></h6>
                    <ol class="small">
                        <?php foreach ($v['quiz'] as $q): ?>
                            <li class="mb-2"><?php echo html_escape($q['question']); ?>
                                <ul><?php foreach ($q['options'] as $k => $opt): ?><li class="<?php echo (int) $q['answer'] === $k ? 'fw-bold text-success' : ''; ?>"><?php echo html_escape($opt); ?></li><?php endforeach; ?></ul>
                                <?php if (!empty($q['explanation'])): ?><div class="text-muted"><?php echo html_escape($q['explanation']); ?></div><?php endif; ?>
                            </li>
                        <?php endforeach; ?>
                    </ol>
                <?php endif; ?>
            </div>
        <?php endforeach; ?>
        </div>
    </div></div>
    <?php endif; ?>

    <?php if ($is_video && !empty($output['render']['video'])): $r = $output['render']; ?>
    <div class="card"><div class="card-body">
        <video controls preload="metadata" playsinline class="w-100 rounded bg-dark" <?php echo !empty($r['poster']) ? 'poster="' . html_escape(base_url($r['poster'])) . '"' : ''; ?>>
            <source src="<?php echo html_escape(base_url($r['video'])); ?>" type="video/mp4">
            <?php if (!empty($r['captions'])): ?><track kind="captions" srclang="<?php echo html_escape($output['locale']); ?>" label="<?php echo $output['locale'] === 'ar' ? 'العربية' : 'English'; ?>" src="<?php echo html_escape(base_url($r['captions'])); ?>" default><?php endif; ?>
        </video>
        <p class="small text-muted mt-2 mb-0">
            <?php echo isset($r['seconds']) ? html_escape($r['seconds']) . ' s · ' : ''; ?>
            <?php echo isset($r['bytes']) ? number_format($r['bytes'] / 1048576, 1) . ' MB · ' : ''; ?>
            <?php echo !empty($r['narrated']) ? 'narrated' : 'silent (no narration route) with captions'; ?>
            · <a href="<?php echo html_escape(base_url($r['video'])); ?>" download>download</a>
        </p>
    </div></div>
    <?php endif; ?>

    <?php if (!empty($ha_can['approve']) && in_array($st, array('draft', 'approved'), true) && ($is_course || $is_script)): ?>
    <div class="card"><div class="card-body">
        <details>
            <summary><strong>Edit content</strong> <span class="small text-muted">(JSON; saved edits are re-validated and need approval again)</span></summary>
            <form method="post" action="<?php echo site_url('ha_ai/job_action/' . $job['id']); ?>" class="mt-2">
                <?php echo ha_csrf_field(); ?>
                <label for="ha-json" class="visually-hidden">Content JSON</label>
                <textarea id="ha-json" name="output_json" class="form-control ha-json" spellcheck="false"><?php echo html_escape(json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)); ?></textarea>
                <div class="mt-2"><?php echo $action('save', 'Save edits', 'btn-outline-primary', 'mdi-content-save'); ?></div>
            </form>
        </details>
    </div></div>
    <?php endif; ?>
</div>

<div class="col-xl-4">
    <div class="card"><div class="card-body">
        <h6>Review</h6>
        <form method="post" action="<?php echo site_url('ha_ai/job_action/' . $job['id']); ?>">
            <?php echo ha_csrf_field(); ?>
            <?php if ($st === 'draft' && !empty($ha_can['approve'])): ?>
                <label for="ha-note-in" class="form-label small">Note (required to reject)</label>
                <textarea id="ha-note-in" name="note" rows="2" class="form-control form-control-sm mb-2" maxlength="500"></textarea>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <?php echo $action('approve', 'Approve', 'btn-success', 'mdi-check'); ?>
                    <?php echo $action('reject', 'Reject', 'btn-outline-danger', 'mdi-close'); ?>
                </div>
                <p class="small text-muted">Check facts, tone and the Arabic before approving: your name is recorded as the reviewer.</p>
            <?php endif; ?>
            <?php if ($st === 'approved' && !empty($ha_can['publish'])): ?>
                <div class="mb-2"><?php echo $action('publish', $is_course ? 'Publish course' : ($is_script ? 'Publish lesson text' : 'Attach video to lesson'), 'btn-primary', 'mdi-upload', 'Publish to learners now?'); ?></div>
            <?php endif; ?>
            <?php if ($is_script && in_array($st, array('approved', 'published'), true) && !empty($ha_can['approve'])): ?>
                <h6 class="mt-3">Make video</h6>
                <div class="d-flex flex-wrap gap-2 mb-1">
                    <?php echo $action('render_en', 'Slide video EN', 'btn-outline-primary', 'mdi-presentation-play'); ?>
                    <?php echo $action('render_ar', 'Slide video AR', 'btn-outline-primary', 'mdi-presentation-play'); ?>
                </div>
                <div class="d-flex flex-wrap gap-2 mb-2">
                    <?php echo $action('avatar_en', 'Presenter EN', 'btn-outline-secondary', 'mdi-account-tie-voice'); ?>
                    <?php echo $action('avatar_ar', 'Presenter AR', 'btn-outline-secondary', 'mdi-account-tie-voice'); ?>
                </div>
                <p class="small text-muted">Slide videos are drawn and encoded on this server. Presenter videos render at HeyGen, D-ID or Synthesia (route the <em>avatar</em> task).</p>
            <?php endif; ?>
            <div class="d-flex flex-wrap gap-2 mt-2">
                <?php if (in_array($st, array('failed', 'rejected'), true) && !empty($ha_can['generate'])) echo $action('retry', 'Retry', 'btn-outline-primary', 'mdi-refresh'); ?>
                <?php if ($st !== 'published' && !empty($ha_can['generate'])) echo $action('delete', 'Delete', 'btn-outline-danger', 'mdi-delete', 'Delete this job and its files?'); ?>
            </div>
        </form>
        <?php if ($job['reviewed_at']): ?><p class="small text-muted mt-2 mb-0"><?php echo html_escape(ucfirst($st === 'rejected' ? 'rejected' : 'approved')); ?> by <?php echo html_escape(isset($people['reviewed_by']) ? $people['reviewed_by'] : '—'); ?> on <?php echo html_escape($job['reviewed_at']); ?></p><?php endif; ?>
    </div></div>

    <?php if ($children): ?>
    <div class="card"><div class="card-body">
        <h6>Renders from this script</h6>
        <ul class="list-unstyled small mb-0">
            <?php foreach ($children as $c): ?><li class="mb-1"><a href="<?php echo site_url('ha_ai/job/' . $c['id']); ?>">#<?php echo (int) $c['id']; ?> <?php echo html_escape($c['title']); ?></a> <?php echo ha_ai_status_badge($c['status']); ?></li><?php endforeach; ?>
        </ul>
    </div></div>
    <?php endif; ?>
    <?php if ($job['parent_job_id']): ?>
        <p class="small"><a href="<?php echo site_url('ha_ai/job/' . $job['parent_job_id']); ?>">← Script job #<?php echo (int) $job['parent_job_id']; ?></a></p>
    <?php endif; ?>
</div>
</div>
</div>
<?php if ($live): ?>
<script>
(function poll() {
    fetch('<?php echo site_url('ha_ai/job_status/' . $job['id']); ?>', { credentials: 'same-origin', headers: { 'X-Requested-With': 'XMLHttpRequest' } })
        .then(function (r) { return r.json(); }).then(function (j) {
            if (!j.ok) return;
            if (['queued', 'running'].indexOf(j.status) === -1) { location.reload(); return; }
            document.getElementById('ha-bar').style.width = j.progress + '%';
            document.getElementById('ha-note').textContent = j.note || '';
            setTimeout(poll, 3000);
        });
})();
</script>
<?php endif; ?>
