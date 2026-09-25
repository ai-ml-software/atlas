<div class="hkp-head">
  <div><div class="hkp-eyebrow"><a href="<?php echo hkp_url('learn/module/' . $c['id']); ?>"><?php echo hkp_h($c['title']); ?></a></div>
  <h1><?php echo hkp_h($l['title']); ?></h1>
  <?php if ($l['objective']): ?><p><?php echo hkp_h($l['objective']); ?></p><?php endif; ?></div>
  <?php echo hkp_badge($progress['status'] === 'completed' ? 'completed' : 'in_progress'); ?>
</div>
<?php if (!$l['translated']): ?><div class="hkp-flash hkp-flash--error"><?php echo hkp_e('This lesson is not yet available in Arabic; the English version is shown.'); ?></div><?php endif; ?>

<article class="hkp-card" data-lesson-track="<?php echo hkp_url('learn/track_time/' . $l['id']); ?>" data-resume="<?php echo (int) $progress['last_position_seconds']; ?>">
  <?php if ($video): ?>
    <div class="hkp-video" style="margin-bottom:1rem">
      <?php if ($video['type'] === 'embed'): ?>
        <iframe src="<?php echo hkp_h($video['src']); ?>" title="<?php echo hkp_h($l['title']); ?>" allow="encrypted-media; picture-in-picture" allowfullscreen loading="lazy"></iframe>
      <?php else: ?>
        <video src="<?php echo hkp_h($video['src']); ?>" controls preload="metadata" playsinline<?php echo $l['captions_url'] ? '' : ''; ?>>
          <?php if ($l['captions_url']): ?><track kind="captions" src="<?php echo hkp_h(base_url(ltrim($l['captions_url'], '/'))); ?>" srclang="<?php echo hkp_locale(); ?>" default><?php endif; ?>
        </video>
      <?php endif; ?>
    </div>
  <?php endif; ?>
  <?php if (!empty($l['media_path']) && $l['media_type'] !== 'video'): $mu = base_url(ltrim($l['media_path'], '/')); ?>
    <div style="margin-bottom:1rem">
      <?php if ($l['media_type'] === 'pdf'): ?><iframe src="<?php echo hkp_h($mu); ?>#view=FitH" title="<?php echo hkp_h($l['title']); ?>" style="width:100%;height:70vh;border:1px solid var(--line);border-radius:12px"></iframe>
      <?php elseif ($l['media_type'] === 'audio'): ?><audio src="<?php echo hkp_h($mu); ?>" controls preload="metadata" style="width:100%"></audio><?php endif; ?>
      <p><a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_h($mu); ?>" download><?php echo hkp_icon('download'); ?> <?php echo $l['media_type'] === 'presentation' ? hkp_e('Download the slides') : ($l['media_type'] === 'pdf' ? hkp_e('Download the PDF') : hkp_e('Download the file')); ?></a></p>
    </div>
  <?php endif; ?>
  <?php if (!empty($l['external_url'])): ?><p><a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_h($l['external_url']); ?>" target="_blank" rel="noopener noreferrer"><?php echo hkp_e('Open the linked resource'); ?> ↗</a></p><?php endif; ?>
  <div class="hkp-lesson">
    <?php if ($l['blocks']): foreach ($l['blocks'] as $b): ?>
      <?php if (in_array($b['block_type'], array('callout', 'scenario', 'checkpoint'), true)): ?>
        <div class="hkp-callout"><?php if ($b['title']): ?><strong><?php echo hkp_h($b['title']); ?></strong><br><?php endif; ?><?php echo hkp_safe_html($b['content']); ?></div>
      <?php elseif ($b['block_type'] === 'image' && $b['media_path']): ?>
        <figure><img src="<?php echo hkp_h(base_url(ltrim($b['media_path'], '/'))); ?>" alt="<?php echo hkp_h($b['title']); ?>" loading="lazy"><?php if ($b['title']): ?><figcaption class="hkp-small hkp-muted"><?php echo hkp_h($b['title']); ?></figcaption><?php endif; ?></figure>
      <?php elseif (in_array($b['block_type'], array('pdf', 'download'), true) && $b['media_path']): ?>
        <p><a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_h(base_url(ltrim($b['media_path'], '/'))); ?>" download><?php echo hkp_icon('download'); ?> <?php echo hkp_h($b['title'] ?: basename($b['media_path'])); ?></a></p>
      <?php else: ?>
        <?php if ($b['title']): ?><h3><?php echo hkp_h($b['title']); ?></h3><?php endif; ?><?php echo hkp_safe_html($b['content']); ?>
      <?php endif; ?>
    <?php endforeach; else: ?>
      <?php echo hkp_safe_html($l['body']); ?>
    <?php endif; ?>
  </div>
  <?php if ($l['attachments']): ?>
    <h3><?php echo hkp_e('Resources'); ?></h3>
    <ul><?php foreach ($l['attachments'] as $at): ?><li><a href="<?php echo hkp_h(base_url(ltrim($at['file_path'], '/'))); ?>"><?php echo hkp_h(hkp_pick($at, 'title')); ?></a></li><?php endforeach; ?></ul>
  <?php endif; ?>
</article>

<div class="hkp-actions" style="margin-top:1rem;justify-content:space-between">
  <span><?php if ($prev): ?><a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_url('learn/lesson/' . $prev); ?>">← <?php echo hkp_e('Previous'); ?></a><?php endif; ?></span>
  <form method="post" action="<?php echo hkp_url('learn/complete/' . $l['id']); ?>"><?php echo ha_csrf_field(); ?>
    <?php if ($l['completion_rule'] === 'acknowledge'): ?><label class="hkp-check"><input type="checkbox" required> <?php echo hkp_e('I have read and will follow this checklist.'); ?></label><?php endif; ?>
    <button class="hkp-btn"><?php echo hkp_icon('check'); ?> <?php echo $progress['status'] === 'completed' ? hkp_e('Completed — continue') : hkp_e('Mark lesson complete'); ?></button>
  </form>
  <span><?php if ($next): ?><a class="hkp-btn hkp-btn--ghost" href="<?php echo hkp_url('learn/lesson/' . $next); ?>"><?php echo hkp_e('Next'); ?> →</a><?php endif; ?></span>
</div>
