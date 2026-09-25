<?php
$language_dir = 'ltr';
$language_dirs = get_settings('language_dirs');
$current_language = $this->session->userdata('language');
if($language_dirs){
	$language_dirs_arr = json_decode($language_dirs, true);
	if(array_key_exists($current_language, $language_dirs_arr)){
		$language_dir = $language_dirs_arr[$current_language];
	}
}

/*
 * Lesson player frame (Altus brand: navy + gold, Montserrat / Playfair Display).
 * Everything the player already did is kept: drip locks, study-plan restrictions,
 * quizzes, every video/document type, the full-width toggle and the manager links.
 * What changes is the frame: a contained brand bar with real progress, the lesson
 * as a readable article, and previous / complete / next at the end of every lesson.
 */
$this->load->helper('ha_locale');
$ap_lang = 'en';
foreach ((array) ha_locale_config()['legacy'] as $ap_code => $ap_col) {
	if ($ap_col === $current_language) { $ap_lang = $ap_code; }
}
// Mirrored academy courses are shown in the learner's language (English fallback).
$this->load->library('ha_lms_i18n');
$course_details = $this->ha_lms_i18n->course($course_details);
$sections = $this->ha_lms_i18n->sections($sections);
if (is_array($lesson_details)) { $lesson_details = $this->ha_lms_i18n->lesson($lesson_details); }
$ap_completed = array();
if (isset($watch_history) && !empty($watch_history['completed_lesson'])) {
	$ap_completed = json_decode($watch_history['completed_lesson'], true);
	$ap_completed = is_array($ap_completed) ? array_map('intval', $ap_completed) : array();
}
$ap_flat = array();
if (is_array($sections)) {
	foreach (array_values($sections) as $ap_si => $ap_s) {
		foreach ($this->ha_lms_i18n->lessons($this->crud_model->get_lessons('section', $ap_s['id'])->result_array()) as $ap_l) {
			$ap_l['section_title'] = $ap_s['title'];
			$ap_l['section_no'] = $ap_si + 1;
			$ap_flat[] = $ap_l;
		}
	}
}
$ap_total = count($ap_flat);
$ap_index = 0;
foreach ($ap_flat as $ap_i => $ap_l) {
	if (is_array($lesson_details) && (int) $ap_l['id'] === (int) $lesson_details['id']) { $ap_index = $ap_i; }
}
$ap_lesson_url = function ($l) use ($course_details, $course_id) {
	return site_url('home/lesson/' . slugify($course_details['title']) . '/' . $course_id . '/' . $l['id']);
};
$ap_prev = $ap_index > 0 ? $ap_flat[$ap_index - 1] : null;
$ap_next = $ap_index < $ap_total - 1 ? $ap_flat[$ap_index + 1] : null;
$ap_done_count = count(array_intersect($ap_completed, array_map(function ($l) { return (int) $l['id']; }, $ap_flat)));
$ap_progress = $ap_total ? (int) round(100 * $ap_done_count / $ap_total) : 0;
$ap_is_done = is_array($lesson_details) && in_array((int) $lesson_details['id'], $ap_completed, true);
$ap_type = is_array($lesson_details) ? get_lesson_type($lesson_details['id']) : '';
$ap_type_label = array('text' => 'Reading', 'quiz' => 'Quiz', 'audio_file' => 'Audio', 'pdf_file' => 'Document', 'doc_file' => 'Document', 'text_file' => 'Document', 'image_file' => 'Image');
$ap_type_name = isset($ap_type_label[$ap_type]) ? $ap_type_label[$ap_type] : (strpos((string) $ap_type, 'video') !== false ? 'Video' : 'Lesson');
// A reading lesson whose body was published into the summary reads as the article itself.
// A video lesson that carries a written chapter shows the video, then reads on as a book page.
$ap_words = is_array($lesson_details) ? count(preg_split('/\s+/u', trim(strip_tags((string) $lesson_details['summary'])), -1, PREG_SPLIT_NO_EMPTY)) : 0;
$ap_reading = is_array($lesson_details) && (
	($ap_type === 'text' && trim(strip_tags((string) $lesson_details['attachment'])) === '')
	|| (strpos((string) $ap_type, 'video') !== false && $ap_words >= 60));
$ap_book = $ap_reading && $ap_words >= 60;
$ap_show_media = is_array($lesson_details) && !($ap_reading && $ap_type === 'text');
$ap_toggle_url = is_array($lesson_details) ? site_url('home/update_watch_history_manually?lesson_id=' . $lesson_details['id'] . '&course_id=' . $course_details['id']) : '';
$ap_duration = function ($d) {
	if (!preg_match('/^(\d+):(\d{2})(?::(\d{2}))?$/', trim((string) $d), $m)) { return trim((string) $d); }
	$min = (int) $m[1] * 60 + (int) $m[2] + (isset($m[3]) && (int) $m[3] >= 30 ? 1 : 0);
	return $min >= 60 ? floor($min / 60) . ' h ' . ($min % 60 ? ($min % 60) . ' min' : '') : max(1, $min) . ' min';
};
$user_id = $this->session->userdata('user_id');
$is_course_instructor = $this->crud_model->is_course_instructor($course_details['id'], $user_id);
$full_page = $this->session->userdata('full_page_layout');
$ap_logo = get_frontend_settings('light_logo');
?>
<!DOCTYPE html>
<html lang="<?php echo html_escape($ap_lang); ?>" dir="<?php echo $language_dir; ?>">
<head>
	<title><?php echo html_escape((is_array($lesson_details) ? $lesson_details['title'] . ' · ' : '') . $course_details['title'] . ' | ' . get_settings('system_name')); ?></title>
	<meta charset="utf-8">
	<meta http-equiv="X-UA-Compatible" content="IE=edge">
	<meta name="viewport" content="width=device-width, initial-scale=1.0" />
	<meta name="robots" content="noindex, nofollow" />
	<meta name="theme-color" content="#0D1B2A" />
	<meta name="author" content="<?php echo html_escape(get_settings('author')); ?>" />
	<meta name="description" content="<?php echo html_escape($course_details['meta_description']); ?>" />
	<link name="favicon" type="image/x-icon" href="<?php echo base_url('uploads/system/'.get_frontend_settings('favicon')); ?>" rel="shortcut icon" />

	<?php include 'includes_top.php';?>
	<link rel="stylesheet" href="<?php echo base_url('assets/playing-page/css/altus-player.css?v=' . @filemtime(FCPATH . 'assets/playing-page/css/altus-player.css')); ?>">
</head>

<body class="ap<?php echo $full_page ? ' ap--wide' : ''; ?><?php echo $language_dir === 'rtl' ? ' ap--rtl' : ''; ?>">
<a class="ap-skip" href="#ap-lesson"><?php echo get_phrase('Skip to lesson'); ?></a>

<header class="ap-bar" role="banner">
	<a class="ap-bar__brand" href="<?php echo site_url(); ?>" aria-label="<?php echo html_escape(get_settings('system_name')); ?>">
		<?php if ($ap_logo): ?><img src="<?php echo base_url('uploads/system/' . $ap_logo); ?>" alt="" height="40"><?php else: ?><span><?php echo html_escape(get_settings('system_name')); ?></span><?php endif; ?>
	</a>
	<div class="ap-bar__course">
		<a class="ap-bar__title" href="<?php echo site_url('home/course/'.slugify($course_details['title']).'/'.$course_details['id']); ?>"><?php echo html_escape($course_details['title']); ?></a>
		<?php if ($ap_total): ?>
		<div class="ap-progress" role="progressbar" aria-label="<?php echo get_phrase('Course progress'); ?>" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?php echo $ap_progress; ?>">
			<span class="ap-progress__track"><span class="ap-progress__fill" style="inline-size: <?php echo $ap_progress; ?>%"></span></span>
			<span class="ap-progress__text"><?php echo $ap_progress; ?>% · <?php echo $ap_done_count; ?>/<?php echo $ap_total; ?> <?php echo get_phrase('Completed'); ?></span>
		</div>
		<?php endif; ?>
	</div>
	<nav class="ap-bar__actions" aria-label="<?php echo get_phrase('Course'); ?>">
		<button type="button" class="ap-iconbtn" onclick="actionTo('<?php echo site_url('home/course_playing_page_layout'); ?>')" aria-label="<?php echo $full_page ? get_phrase('Show course content') : get_phrase('Focus mode'); ?>" title="<?php echo $full_page ? get_phrase('Show course content') : get_phrase('Focus mode'); ?>">
			<i class="fas <?php echo $full_page ? 'fa-arrows-alt' : 'fa-arrows-alt-h'; ?>" aria-hidden="true"></i>
		</button>
		<a class="ap-btn ap-btn--ghost ap-hide-sm" href="<?php echo site_url('hkp'); ?>"><i class="fas fa-th-large" aria-hidden="true"></i><span><?php echo get_phrase('knowledge_performance'); ?></span></a>
		<?php if($this->session->userdata('admin_login')): ?>
			<a class="ap-btn ap-btn--light" href="<?php echo site_url('admin/course_form/course_edit/'.$course_details['id']); ?>"><span><?php echo get_phrase('Course Manager'); ?></span><i class="fas fa-angle-right" aria-hidden="true"></i></a>
		<?php elseif($is_course_instructor): ?>
			<a class="ap-btn ap-btn--light" href="<?php echo site_url('user/course_form/course_edit/'.$course_details['id']); ?>"><span><?php echo get_phrase('Course Manager'); ?></span><i class="fas fa-angle-right" aria-hidden="true"></i></a>
		<?php else: ?>
			<a class="ap-btn ap-btn--light" href="<?php echo site_url('home/my_courses'); ?>"><span><?php echo get_phrase('My Courses'); ?></span><i class="fas fa-angle-right" aria-hidden="true"></i></a>
		<?php endif; ?>
	</nav>
</header>

<main class="ap-main" id="ap-lesson">
	<?php if($course_details['course_type'] == 'general'): ?>
		<?php if(!is_array($lesson_details)): ?>
			<div class="ap-empty">
				<h1><?php echo get_phrase('Course content not found') ?></h1>
				<p><?php echo get_phrase('Please ensure that your course has at least one section and one lesson.'); ?></p>
			</div>
		<?php endif; ?>

		<?php // The sidebar decides which lessons are drip-locked / outside the study plan, so it runs first.
		ob_start(); include "sidebar.php"; $ap_sidebar_html = ob_get_clean(); ?>
		<div class="ap-layout">
			<div class="ap-stage">
				<?php if(is_array($lesson_details)): ?>
				<?php $ap_locked = in_array($lesson_details['id'], $locked_lesson_ids) && $course_details['enable_drip_content'];
				      $ap_restricted = in_array($lesson_details['section_id'], $restricted_section_ids); ?>

				<?php // The next lesson stays locked (drip) until this one - or its quiz - is complete.
				      $ap_next_locked = $ap_next && $course_details['enable_drip_content'] && !$ap_is_done && !$is_course_instructor && !$this->session->userdata('admin_login'); ?>

				<?php if ($ap_show_media && !$ap_locked && !$ap_restricted): ?>
					<div class="ap-media<?php echo $ap_type === 'quiz' ? ' ap-media--quiz' : ''; ?>">
						<?php include $course_details['course_type'].'_course_content_body.php'; ?>
					</div>
				<?php endif; ?>

				<article class="ap-article<?php echo $ap_book ? ' ap-article--book' : ''; ?>" aria-labelledby="ap-title">
					<p class="ap-eyebrow">
						<?php if (!empty($ap_flat[$ap_index]['section_no'])): ?><span class="ap-eyebrow__no"><?php echo get_phrase('Chapter'); ?> <?php echo (int) $ap_flat[$ap_index]['section_no']; ?></span><?php endif; ?>
						<span><?php echo html_escape($ap_flat[$ap_index]['section_title'] ?? ''); ?></span>
					</p>
					<h1 class="ap-title" id="ap-title"><?php echo html_escape($lesson_details['title']); ?></h1>
					<ul class="ap-meta" role="list">
						<li><i class="far <?php echo $ap_type === 'quiz' ? 'fa-question-circle' : ($ap_type_name === 'Video' ? 'fa-play-circle' : 'fa-file-alt'); ?>" aria-hidden="true"></i><?php echo get_phrase($ap_type_name); ?></li>
						<?php if (!empty($lesson_details['duration']) && trim($lesson_details['duration'], '0:') !== ''): ?><li><i class="far fa-clock" aria-hidden="true"></i><?php echo html_escape($ap_duration($lesson_details['duration'])); ?></li><?php endif; ?>
						<?php if ($ap_total): ?><li><?php echo get_phrase('Lesson'); ?> <?php echo $ap_index + 1; ?> / <?php echo $ap_total; ?></li><?php endif; ?>
						<?php if ($ap_is_done): ?><li class="ap-meta__done"><i class="fas fa-check-circle" aria-hidden="true"></i><?php echo get_phrase('Completed'); ?></li><?php endif; ?>
					</ul>

					<?php if ($ap_locked): ?>
						<div class="ap-notice ap-notice--lock"><i class="fas fa-lock" aria-hidden="true"></i><div><?php echo remove_js(htmlspecialchars_decode_($drip_content_settings['locked_lesson_message'])); ?></div></div>
					<?php elseif ($ap_restricted): ?>
						<div class="ap-notice ap-notice--lock"><i class="fas fa-calendar-alt" aria-hidden="true"></i><div><strong><?php echo get_phrase('This section is not included in the current study plan'); ?></strong></div></div>
					<?php elseif ($ap_reading): ?>
						<div class="ap-prose<?php echo $ap_book ? ' ap-book' : ''; ?>"><?php echo htmlspecialchars_decode_($lesson_details['summary']); ?></div>
						<?php if ($ap_next && $ap_flat[$ap_index + 1]['lesson_type'] === 'quiz'): ?>
							<p class="ap-quiz-hint"><i class="far fa-question-circle" aria-hidden="true"></i><span><?php echo get_phrase('A short quiz follows this lesson. Pass it to unlock the next lesson.'); ?></span></p>
						<?php endif; ?>
					<?php endif; ?>

					<?php if (!$ap_locked && !$ap_restricted): ?>
					<div class="ap-next" data-ap-next>
						<?php if ($ap_prev): ?>
							<a class="ap-btn ap-btn--ghost-dark" href="<?php echo $ap_lesson_url($ap_prev); ?>" rel="prev"><i class="fas fa-arrow-left ap-flip" aria-hidden="true"></i><span><?php echo get_phrase('Previous'); ?></span></a>
						<?php else: ?><span></span><?php endif; ?>
						<?php if ($ap_type !== 'quiz' && $this->session->userdata('user_login')): ?>
							<button type="button" class="ap-btn <?php echo $ap_is_done ? 'ap-btn--done' : 'ap-btn--primary'; ?>" data-ap-complete
								data-url="<?php echo html_escape($ap_toggle_url); ?>"
								data-next="<?php echo $ap_next ? html_escape($ap_lesson_url($ap_next)) : ''; ?>"
								data-done="<?php echo $ap_is_done ? '1' : '0'; ?>">
								<i class="fas <?php echo $ap_is_done ? 'fa-check' : 'fa-check-circle'; ?>" aria-hidden="true"></i>
								<span><?php echo $ap_is_done ? ($ap_next ? get_phrase('Continue') : get_phrase('Completed')) : ($ap_next ? get_phrase('Complete and continue') : get_phrase('Mark as Complete')); ?></span>
							</button>
						<?php endif; ?>
						<?php if ($ap_next && $ap_next_locked): ?>
							<span class="ap-btn ap-btn--ghost-dark is-locked" aria-disabled="true" data-ap-next-locked title="<?php echo html_escape($ap_type === 'quiz' ? get_phrase('Pass the quiz to unlock the next lesson') : get_phrase('Complete this lesson to continue')); ?>"><i class="fas fa-lock" aria-hidden="true"></i><span><?php echo get_phrase('Next'); ?></span></span>
						<?php elseif ($ap_next): ?>
							<a class="ap-btn ap-btn--ghost-dark" href="<?php echo $ap_lesson_url($ap_next); ?>" rel="next"><span><?php echo get_phrase('Next'); ?></span><i class="fas fa-arrow-right ap-flip" aria-hidden="true"></i></a>
						<?php else: ?><span></span><?php endif; ?>
					</div>
					<?php endif; ?>
				</article>

				<?php $ap_summary_in_article = $ap_reading; ob_start(); include "bottom_tabs.php"; $ap_tabs = ob_get_clean();
				if (strpos($ap_tabs, 'class="nav-item"') !== false): ?>
				<section class="ap-extras" aria-label="<?php echo get_phrase('Lesson resources'); ?>"><?php echo $ap_tabs; ?></section>
				<?php else: echo $ap_tabs; endif; ?>
				<?php endif; ?>
			</div>

			<aside class="ap-side" aria-label="<?php echo get_phrase('Course Content'); ?>">
				<?php echo $ap_sidebar_html; ?>
			</aside>
		</div>
	<?php else: ?>
		<div class="ap-stage ap-stage--full">
			<?php include $course_details['course_type'].'_course_content_body.php'; ?>
			<section class="ap-extras"><?php include "bottom_tabs.php"; ?></section>
		</div>
	<?php endif; ?>
</main>

<?php include "includes_bottom.php"; ?>
<?php include APPPATH."views/frontend/default-new/common_scripts.php"; ?>
<?php include APPPATH."views/frontend/default-new/init.php"; ?>
<script>
/* Complete (and continue): the same toggle the sidebar checkbox uses, then move on. */
(function () {
	var btn = document.querySelector('[data-ap-complete]');
	if (!btn || !window.jQuery) { return; }
	btn.addEventListener('click', function () {
		var url = btn.getAttribute('data-url'), next = btn.getAttribute('data-next'), done = btn.getAttribute('data-done') === '1';
		// Already complete: the endpoint toggles, so posting again would undo it. Just move on.
		if (done) { if (next) { window.location.href = next; } return; }
		var q = url.split('?')[1] || '', data = {};
		q.split('&').forEach(function (p) { var kv = p.split('='); if (kv[0]) { data[kv[0]] = decodeURIComponent(kv[1] || ''); } });
		btn.disabled = true; btn.classList.add('is-busy');
		jQuery.post(url, data).always(function () {
			if (!done && next) { window.location.href = next; } else { window.location.reload(); }
		});
	});
})();
</script>
</body>
</html>
