<?php
$v = function ($k) use ($l) { return $l && isset($l[$k]) ? $l[$k] : ''; };
$t = function ($loc, $k) use ($tr) { return isset($tr[$loc][$k]) ? $tr[$loc][$k] : ''; };
$drip = $l && $l['drip_days'] ? 'days' : ($l && $l['available_from'] ? 'date' : 'none');
$ai_entity = 'lesson'; $ai_id = $l ? $l['id'] : 0; $ai_insert = '#lb_en';
$ai_tasks = array('lesson' => 'Write a short applied lesson', 'improve' => 'Improve the selected text', 'translate_ar' => 'Translate to Arabic (Modern Standard Arabic)', 'translate_en' => 'Translate to English', 'quiz' => 'Write assessment questions', 'enhance_prompt' => 'Turn a rough request into a precise brief');
?>
<div class="hkp-head"><div><div class="hkp-eyebrow"><a href="<?php echo hkp_url('cms/module/' . $c['id']); ?>"><?php echo hkp_h($course_tr ? $course_tr['title'] : $c['code']); ?></a></div><h1><?php echo $l ? hkp_h($t('en', 'title')) : hkp_e('New lesson'); ?></h1>
<?php if ($l): ?><p><?php echo hkp_badge($l['status']); ?> · <a href="<?php echo hkp_url('learn/lesson/' . $l['id']); ?>"><?php echo hkp_e('Preview as learner'); ?></a></p><?php endif; ?></div></div>
<div class="hkp-grid hkp-grid--main">
<form class="hkp-card hkp-form" method="post" enctype="multipart/form-data" action="<?php echo hkp_url('cms/lesson_save/' . ($l ? $l['id'] : 0)); ?>"><?php echo ha_csrf_field(); ?><input type="hidden" name="course_id" value="<?php echo (int) $c['id']; ?>">
  <div class="hkp-row">
    <div class="hkp-field"><label for="lt"><?php echo hkp_e('Lesson type'); ?></label><select id="lt" class="hkp-select" name="lesson_type"><?php foreach (array('text', 'video', 'audio', 'pdf', 'presentation', 'external', 'checklist', 'interactive') as $ty): ?><option value="<?php echo $ty; ?>"<?php echo $v('lesson_type') === $ty ? ' selected' : ''; ?>><?php echo hkp_label($ty); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="ls"><?php echo hkp_e('Section'); ?></label><select id="ls" class="hkp-select" name="section_id"><?php foreach ($sections as $s): ?><option value="<?php echo (int) $s['id']; ?>"<?php echo (int) $v('section_id') === (int) $s['id'] ? ' selected' : ''; ?>><?php echo hkp_h(hkp_pick($s, 'title')); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="ld"><?php echo hkp_e('Duration (minutes)'); ?></label><input id="ld" class="hkp-input" type="number" name="duration_minutes" value="<?php echo $l ? round($l['duration_seconds'] / 60) : 5; ?>"></div>
    <div class="hkp-field"><label for="lst"><?php echo hkp_e('Status'); ?></label><select id="lst" class="hkp-select" name="status"><?php foreach (array('draft', 'published', 'archived') as $s): ?><option value="<?php echo $s; ?>"<?php echo $v('status') === $s ? ' selected' : ''; ?>><?php echo hkp_label($s); ?></option><?php endforeach; ?></select></div>
  </div>
  <div class="hkp-grid hkp-grid--2">
  <?php foreach (array('en' => 'English', 'ar' => 'العربية') as $loc => $ln): $dir = $loc === 'ar' ? ' dir="rtl"' : ''; ?><div class="hkp-form"><strong class="hkp-small"><?php echo $ln; ?></strong>
    <div class="hkp-field"><label for="lti_<?php echo $loc; ?>"><?php echo hkp_e('Title'); ?></label><input id="lti_<?php echo $loc; ?>" class="hkp-input" name="title_<?php echo $loc; ?>" value="<?php echo hkp_h($t($loc, 'title')); ?>"<?php echo $dir; ?><?php echo $loc === 'en' ? ' required' : ''; ?>></div>
    <div class="hkp-field"><label for="lo_<?php echo $loc; ?>"><?php echo hkp_e('Objective'); ?></label><input id="lo_<?php echo $loc; ?>" class="hkp-input" name="objective_<?php echo $loc; ?>" value="<?php echo hkp_h($t($loc, 'objective')); ?>"<?php echo $dir; ?>></div>
    <div class="hkp-field"><label for="lb_<?php echo $loc; ?>"><?php echo hkp_e('Lesson content (HTML allowed)'); ?></label><textarea id="lb_<?php echo $loc; ?>" class="hkp-input" rows="12" name="body_<?php echo $loc; ?>"<?php echo $dir; ?>><?php echo hkp_h($t($loc, 'body')); ?></textarea></div>
    <div class="hkp-field"><label for="ltr_<?php echo $loc; ?>"><?php echo hkp_e('Transcript (for video / audio)'); ?></label><textarea id="ltr_<?php echo $loc; ?>" class="hkp-input" rows="3" name="transcript_<?php echo $loc; ?>"<?php echo $dir; ?>><?php echo hkp_h($t($loc, 'transcript')); ?></textarea></div></div><?php endforeach; ?>
  </div>
  <h3><?php echo hkp_e('Media'); ?></h3>
  <div class="hkp-row">
    <div class="hkp-field"><label for="lv"><?php echo hkp_e('Video link (YouTube, Vimeo or MP4 URL)'); ?></label><input id="lv" class="hkp-input" name="video_url" value="<?php echo hkp_h($v('video_source') === 'upload' ? '' : $v('video_url')); ?>" placeholder="https://www.youtube.com/watch?v=…"></div>
    <div class="hkp-field"><label for="le"><?php echo hkp_e('Other link (article, form, resource)'); ?></label><input id="le" class="hkp-input" name="external_url" value="<?php echo hkp_h($v('external_url')); ?>" placeholder="https://"></div>
  </div><div class="hkp-row">
    <div class="hkp-field"><label for="lm"><?php echo hkp_e('Upload main media: video (MP4/WebM), audio (MP3), PDF or PowerPoint'); ?></label><input id="lm" class="hkp-input" type="file" name="media" accept=".mp4,.webm,.mp3,.m4a,.pdf,.ppt,.pptx,.doc,.docx">
      <?php if ($v('media_path')): ?><span class="hkp-help"><?php echo hkp_e('Current: {t}', array('t' => hkp_label($v('media_type')))); ?> · <a href="<?php echo hkp_h(base_url($v('media_path'))); ?>" target="_blank" rel="noopener"><?php echo hkp_e('open'); ?></a></span><?php endif; ?></div>
    <div class="hkp-field"><label for="la"><?php echo hkp_e('Add a downloadable resource'); ?></label><input id="la" class="hkp-input" type="file" name="attachment">
      <?php foreach ($attachments as $a): ?><span class="hkp-help"><a href="<?php echo hkp_h(base_url($a['file_path'])); ?>"><?php echo hkp_h($a['title_en']); ?></a></span><?php endforeach; ?></div>
  </div>
  <h3><?php echo hkp_e('Release (drip) and completion'); ?></h3>
  <div class="hkp-row">
    <div class="hkp-field"><label for="ldm"><?php echo hkp_e('Release'); ?></label><select id="ldm" class="hkp-select" name="drip_mode"><option value="none"<?php echo $drip === 'none' ? ' selected' : ''; ?>><?php echo hkp_e('Available immediately'); ?></option><option value="days"<?php echo $drip === 'days' ? ' selected' : ''; ?>><?php echo hkp_e('A number of days after enrolment'); ?></option><option value="date"<?php echo $drip === 'date' ? ' selected' : ''; ?>><?php echo hkp_e('On a date'); ?></option></select></div>
    <div class="hkp-field"><label for="ldd"><?php echo hkp_e('Days after enrolment'); ?></label><input id="ldd" class="hkp-input" type="number" min="1" name="drip_days" value="<?php echo hkp_h($v('drip_days')); ?>"></div>
    <div class="hkp-field"><label for="lfd"><?php echo hkp_e('Available from'); ?></label><input id="lfd" class="hkp-input" type="datetime-local" name="available_from" value="<?php echo $v('available_from') ? date('Y-m-d\TH:i', strtotime($v('available_from'))) : ''; ?>"></div>
  </div><div class="hkp-row">
    <div class="hkp-field"><label for="lcr"><?php echo hkp_e('Completion rule'); ?></label><select id="lcr" class="hkp-select" name="completion_rule"><?php foreach (array('open' => 'Marked complete by the learner', 'watch_percentage' => 'Watch a share of the video', 'quiz' => 'Pass the checkpoint assessment', 'acknowledge' => 'Acknowledge (read and confirm)') as $k => $lab): ?><option value="<?php echo $k; ?>"<?php echo $v('completion_rule') === $k ? ' selected' : ''; ?>><?php echo hkp_e($lab); ?></option><?php endforeach; ?></select></div>
    <div class="hkp-field"><label for="lwp"><?php echo hkp_e('Required watch share (%)'); ?></label><input id="lwp" class="hkp-input" type="number" name="required_watch_percentage" value="<?php echo hkp_h($v('required_watch_percentage') ?: 90); ?>"></div>
    <div class="hkp-field"><label for="las"><?php echo hkp_e('Checkpoint assessment'); ?></label><select id="las" class="hkp-select" name="assessment_id"><option value=""></option><?php foreach ($assessments as $a): ?><option value="<?php echo (int) $a['id']; ?>"<?php echo (int) $v('assessment_id') === (int) $a['id'] ? ' selected' : ''; ?>><?php echo hkp_h(hkp_pick($a, 'title')); ?></option><?php endforeach; ?></select></div>
  </div>
  <div class="hkp-actions"><label class="hkp-check"><input type="checkbox" name="is_mandatory" value="1"<?php echo !$l || (int) $l['is_mandatory'] ? ' checked' : ''; ?>> <?php echo hkp_e('Mandatory'); ?></label><label class="hkp-check"><input type="checkbox" name="is_preview" value="1"<?php echo $l && (int) $l['is_preview'] ? ' checked' : ''; ?>> <?php echo hkp_e('Free preview'); ?></label></div>
  <div class="hkp-actions"><button class="hkp-btn"><?php echo hkp_e('Save lesson'); ?></button></div>
</form>
<div class="hkp-grid"><?php $this->load->view('hkp/_ai_panel', compact('models', 'ai_entity', 'ai_id', 'ai_insert', 'ai_tasks')); ?>
<?php if ($l && $this->ha_auth->has('lessons.delete')): ?><form class="hkp-card" method="post" action="<?php echo hkp_url('cms/lesson_delete/' . $l['id']); ?>" data-confirm="<?php echo hkp_e('Delete this lesson? If learners have progress it is archived instead.'); ?>"><?php echo ha_csrf_field(); ?><button class="hkp-btn hkp-btn--danger hkp-btn--sm"><?php echo hkp_e('Delete lesson'); ?></button></form><?php endif; ?></div>
</div>
