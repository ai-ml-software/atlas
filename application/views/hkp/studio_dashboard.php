<div class="hkp-head"><div><div class="hkp-eyebrow"><?php echo hkp_e('Create · Curate · Publish'); ?></div><h1><?php echo hkp_e('Content Studio'); ?></h1><p><?php echo hkp_e('Turn operational knowledge into a better learning experience. Your content, website and review tools in one place.'); ?></p></div><?php if ($this->ha_auth->has_all(array('ai.generate', 'courses.create'))): ?><a class="hkp-btn" href="<?php echo hkp_url('cms/publisher'); ?>"><?php echo hkp_icon('spark'); ?> <?php echo hkp_e('Generate from a document'); ?></a><?php endif; ?></div>
<div class="studio-hub-grid">
<?php foreach (array(
    array('book', 'Courses & lessons', 'Build courses, order modules and lessons, add resources, and manage the learner experience.', 'cms/modules', array('courses.create', 'courses.update')),
    array('layers', 'Programs & learning paths', 'Organise the curriculum by domain, track, role and department.', 'admin/curriculum', 'curriculum.view'),
    array('layers', 'Programs', 'Edit bilingual programs and order the courses learners will take.', 'cms/catalogue/programs', 'programs.view'),
    array('route', 'Learning paths', 'Edit the public learning path catalogue and its bilingual descriptions.', 'cms/catalogue/paths', 'learning_paths.view'),
    array('file', 'Articles', 'Write and publish articles with independent English and Arabic content.', 'cms/catalogue/articles', 'articles.view'),
    array('library', 'Hospitality topics', 'Manage the topics, introductions and images in your public knowledge library.', 'cms/catalogue/topics', 'cms_pages.view'),
    array('clipboard', 'Assessments & question bank', 'Build quizzes, choose questions and set pass marks with the existing assessment engine.', 'admin/assessments', 'assessments.create'),
    array('library', 'SOPs & knowledge', 'Create articles, SOPs and operational knowledge. Review and approve each version.', 'admin/content', array('knowledge.create', 'knowledge.review')),
    array('pen', 'Website pages', 'Edit every public landing page, reorder sections, preview, and restore revisions.', 'cms', 'cms_pages.view'),
    array('route', 'Navigation & footer', 'Manage bilingual header and footer links, labels, order and visibility.', 'cms/navigation', 'cms_pages.update'),
    array('spark', 'AI Publisher', 'Upload a PDF or Office document, generate structured drafts, and review against the source.', 'cms/publisher', 'ai.generate'),
    array('target', 'Competencies', 'Connect training to real operational standards and evidence of capability.', 'admin/competencies', 'competencies.create'),
    array('upload', 'Imports', 'Validate, preview and import your organisation data with rollback.', 'admin/imports', 'imports.run')
) as $card): if (!$this->ha_auth->has($card[4]) || ((strpos($card[3], 'cms/catalogue/') === 0 || $card[3] === 'cms/navigation') && !$this->ha_auth->is_system_scoped())) continue; ?>
<a class="hkp-card studio-hub-card" href="<?php echo hkp_url($card[3]); ?>"><span class="studio-metric-icon"><?php echo hkp_icon($card[0]); ?></span><h2><?php echo hkp_e($card[1]); ?></h2><p><?php echo hkp_e($card[2]); ?></p><span class="studio-tag"><?php echo hkp_e('Open workspace'); ?> <?php echo hkp_icon('arrow'); ?></span></a>
<?php endforeach; ?>
</div>
