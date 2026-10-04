<?php
/*
 * Portfolio dashboard (admin-design.png): photographic header, KPI tiles with
 * tinted icons and a change against the figure 30 days earlier where the
 * records carry that history, the cross-property table and recent activity.
 */
$delta = function ($key, $now) use ($prev) {
    $was = $prev[$key] ?? null;
    if ($was === null) { return '<span class="studio-delta is-none" title="' . hkp_e('No comparison period available') . '">—</span>'; }
    $label = $key === 'ai_week' ? hkp_t('vs previous 7 days') : hkp_t('vs 30 days ago');
    if ((int) $was === 0) {
        return (int) $now > 0 ? '<span class="studio-delta is-up" title="' . hkp_h($label) . '"><span aria-hidden="true">↗</span> ' . hkp_e('New') . '</span>' : '<span class="studio-delta is-flat" title="' . hkp_h($label) . '"><span aria-hidden="true">→</span> 0%</span>';
    }
    $pct = round(100 * ((int) $now - (int) $was) / (int) $was);
    $cls = $pct > 0 ? 'is-up' : ($pct < 0 ? 'is-down' : 'is-flat');
    $arrow = $pct > 0 ? '↗' : ($pct < 0 ? '↘' : '→');
    return '<span class="studio-delta ' . $cls . '" title="' . hkp_h($label) . '"><span aria-hidden="true">' . $arrow . '</span> <span dir="ltr">' . ($pct > 0 ? '+' : '') . $pct . '%</span><span class="hkp-visually-hidden"> ' . hkp_h($label) . '</span></span>';
};
$metrics = array(
    // icon, label, value, raw value, comparison key, link, permission, tone
    array('building', 'Organisations', hkp_number($s['organisations']), $s['organisations'], 'organisations', 'admin/crud/organizations', 'organizations.view', 'copper'),
    array('home', 'Properties', hkp_number($s['properties']), $s['properties'], 'properties', 'admin/crud/properties', 'properties.view', 'copper'),
    array('users', 'Users', hkp_number($s['users']), $s['users'], 'users', 'admin/users', 'users.view', 'blue'),
    array('pulse', 'Active in 30 days', hkp_number($s['active']), $s['active'], 'active', 'admin/users', 'users.view', 'copper'),
    array('book', 'Learners', hkp_number($s['learners']), $s['learners'], 'learners', 'admin/users', 'users.view', 'violet'),
    array('group', 'Managers', hkp_number($s['managers']), $s['managers'], 'managers', 'admin/users', 'users.view', 'copper'),
    array('library', 'Modules', hkp_number($s['courses']), $s['courses'], 'courses', 'cms/modules', 'courses.view', 'violet'),
    array('layers', 'Domains', hkp_number($s['domains']), $s['domains'], 'domains', 'admin/frameworks', 'curriculum.view', 'copper'),
    array('route', 'Tracks', hkp_number($s['tracks']), $s['tracks'], 'tracks', 'admin/frameworks', 'curriculum.view', 'blue'),
    array('file', 'Lessons', hkp_number($s['lessons']), $s['lessons'], 'lessons', 'cms/modules', 'courses.view', 'copper'),
    array('clipboard', 'Assessments', hkp_number($s['assessments']), $s['assessments'], 'assessments', 'assess', 'assessments.view', 'copper'),
    array('target', 'Competencies', hkp_number($s['competencies']), $s['competencies'], 'competencies', 'competencies', 'competencies.view', 'copper'),
    array('alert', 'Open gaps', hkp_number($s['gaps']), $s['gaps'], 'gaps', 'team/gaps', 'gaps.view', 'red'),
    array('flag', 'Open action plans', hkp_number($s['actions']), $s['actions'], 'actions', 'team/actions', 'action_plans.view', 'copper'),
    array('award', 'Certificates', hkp_number($s['certificates']), $s['certificates'], 'certificates', 'team/certifications', 'certificates.export', 'violet'),
    array('bell', 'Expiring in 30 days', hkp_number($s['expiring']), $s['expiring'], 'expiring', 'team/certifications', 'certificates.export', 'red'),
    array('list', 'Content awaiting approval', hkp_number($s['awaiting']), $s['awaiting'], 'awaiting', 'admin/content', 'sops.approve', 'copper'),
    array('spark', 'AI questions this week', hkp_number($s['ai_week']), $s['ai_week'], 'ai_week', 'admin/ai', 'ai.view', 'violet'),
    array('gauge', 'Readiness rate', hkp_pct($k['readiness_rate']), null, 'readiness', 'team/readiness', 'readiness.view', 'copper'),
    array('compass', 'Competency coverage', hkp_pct($k['competency_coverage']), null, 'coverage', 'team/gaps', 'gaps.view', 'red'),
);
?>
<header class="studio-dash-head">
    <div class="studio-dash-head__photo" aria-hidden="true" style="background-image:url('<?= hkp_h(base_url('uploads/academy/altus/hero-riyadh-terrace.webp')) ?>')"></div>
    <div class="studio-dash-head__copy">
        <p class="hkp-eyebrow"><?= hkp_e('What is happening across the portfolio?') ?></p>
        <h1><?= hkp_e('Portfolio dashboard') ?></h1>
        <p><?= hkp_e('Clients, properties and the capability evidence chain across every tenant you administer.') ?></p>
    </div>
    <div class="hkp-actions studio-dash-head__actions">
        <?php if ($this->ha_auth->has('organizations.create')): ?><a class="hkp-btn" href="<?= hkp_url('admin/crud/organizations/new') ?>"><span aria-hidden="true">+</span> <?= hkp_e('New client') ?></a><?php endif; ?>
        <?php if ($this->ha_auth->has('properties.create')): ?><a class="hkp-btn hkp-btn--ghost studio-btn-raised" href="<?= hkp_url('admin/crud/properties/new') ?>"><span aria-hidden="true">+</span> <?= hkp_e('New property') ?></a><?php endif; ?>
    </div>
</header>

<div class="studio-metrics" role="list" aria-label="<?= hkp_e('Portfolio figures') ?>">
<?php foreach ($metrics as $m): $allowed = $this->ha_auth->has($m[6]); $tag = $allowed ? 'a' : 'div'; ?>
    <<?= $tag ?><?= $allowed ? ' href="' . hkp_url($m[5]) . '"' : '' ?> class="hkp-card studio-metric studio-tone--<?= $m[7] ?>" role="listitem">
        <span class="studio-metric-icon"><?= hkp_icon($m[0]) ?></span>
        <div class="studio-metric-body"><span class="studio-metric-label"><?= hkp_e($m[1]) ?></span><strong class="studio-metric-value"><?= $m[2] ?></strong></div>
        <?= $m[3] === null ? $delta('__none', 0) : $delta($m[4], $m[3]) ?>
    </<?= $tag ?>>
<?php endforeach; ?>
</div>
<p class="studio-comparison-note"><?= hkp_e('Changes compare each figure with its value 30 days ago (AI questions: the previous 7 days) where the records keep that history.') ?> <?= hkp_e('Figures reflect your permitted portfolio.') ?></p>

<section class="hkp-card studio-dash-card">
    <div class="studio-section-head"><div class="studio-card-title"><span class="studio-card-icon"><?= hkp_icon('building') ?></span><div><h2><?= hkp_e('Cross-property comparison') ?></h2><p class="hkp-small hkp-muted"><?= hkp_e('Compare performance and progress across your properties.') ?></p></div></div>
        <?php if ($this->ha_auth->has('properties.view')): ?><a class="hkp-btn hkp-btn--ghost hkp-btn--sm" href="<?= hkp_url('admin/crud/properties') ?>"><?= hkp_e('View all properties') ?> <span aria-hidden="true" class="studio-flip">→</span></a><?php endif; ?></div>
    <div class="hkp-table-wrap"><table class="hkp-table"><caption class="hkp-visually-hidden"><?= hkp_e('Cross-property comparison') ?></caption><thead><tr><th scope="col"><?= hkp_e('Property') ?></th><th scope="col"><?= hkp_e('Status') ?></th><th scope="col" class="hkp-num"><?= hkp_e('Staff') ?></th><th scope="col"><?= hkp_e('Learning completion') ?></th><th scope="col" class="hkp-num"><?= hkp_e('Ready') ?></th><th scope="col" class="hkp-num"><?= hkp_e('Critical gaps') ?></th><th scope="col" class="hkp-num"><?= hkp_e('Certificates') ?></th></tr></thead><tbody>
    <?php foreach ($compare as $c): ?><tr><td><strong><?= hkp_h(hkp_pick($c, 'name')) ?></strong></td><td><?= hkp_badge($c['operational_status'] === 'operational' ? 'active' : 'warning', hkp_label($c['operational_status'])) ?></td><td class="hkp-num"><?= (int) $c['staff'] ?></td><td style="min-width:180px"><?php if ((int) $c['staff']): ?><span class="studio-progress-inline"><?= hkp_bar($c['completion']) ?> <span class="hkp-small"><?= hkp_pct($c['completion']) ?></span></span><?php else: ?><span class="hkp-muted">—</span><?php endif; ?></td><td class="hkp-num"><?= (int) $c['staff'] ? hkp_pct($c['ready_pct']) : '—' ?></td><td class="hkp-num"><?= $c['critical'] ? hkp_badge('critical', $c['critical']) : 0 ?></td><td class="hkp-num"><?= (int) $c['certs'] ?></td></tr><?php endforeach; ?>
    <?php if (!$compare): ?><tr><td colspan="7"><p class="hkp-muted"><?= hkp_e('No properties in your current scope. Add a property to start tracking performance.') ?></p></td></tr><?php endif; ?></tbody></table></div>
</section>

<section class="hkp-card studio-dash-card">
    <div class="studio-section-head"><div class="studio-card-title"><span class="studio-card-icon"><?= hkp_icon('pulse') ?></span><div><h2><?= hkp_e('Recent activity') ?></h2><p class="hkp-small hkp-muted"><?= hkp_e('Latest updates across your portfolio.') ?></p></div></div>
        <?php if ($this->ha_auth->has('audit_logs.view')): ?><a class="hkp-btn hkp-btn--ghost hkp-btn--sm" href="<?= hkp_url('admin/audit') ?>"><?= hkp_e('View all activity') ?> <span aria-hidden="true" class="studio-flip">→</span></a><?php endif; ?></div>
    <ul class="studio-activity">
    <?php foreach ($recent as $r): ?><li><span class="studio-activity__icon"><?= hkp_icon('file') ?></span><span class="studio-activity__text"><strong><?= hkp_h($r['actor_name'] ?: hkp_t('System')) ?></strong> <?= hkp_h($r['description'] ?: $r['action']) ?></span><time class="hkp-small hkp-muted" datetime="<?= hkp_h(date('c', strtotime($r['created_at']))) ?>"><?= hkp_date($r['created_at'], true) ?></time></li><?php endforeach; ?>
    <?php if (!$recent): ?><li><?= hkp_e('Your team’s activity will appear here.') ?></li><?php endif; ?>
    </ul>
</section>
