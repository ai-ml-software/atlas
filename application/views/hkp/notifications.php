<div class="hkp-head"><div><h1><?php echo hkp_e('Notifications'); ?></h1></div>
<form method="post" action="<?php echo hkp_url('notifications/read'); ?>"><?php echo ha_csrf_field(); ?><button class="hkp-btn hkp-btn--ghost"><?php echo hkp_e('Mark all as read'); ?></button></form></div>
<section class="hkp-card">
<?php if (!$rows): ?><div class="hkp-empty"><?php echo hkp_e('No notifications.'); ?></div><?php endif; ?>
<ul class="hkp-list"><?php foreach ($rows as $n): ?>
  <li style="<?php echo $n['read_at'] ? '' : 'font-weight:600'; ?>"><div><?php if ($n['action_url']): ?><a href="<?php echo hkp_h($n['action_url']); ?>"><?php echo hkp_h(hkp_pick($n, 'title')); ?></a><?php else: ?><?php echo hkp_h(hkp_pick($n, 'title')); ?><?php endif; ?>
    <div class="hkp-small hkp-muted" style="font-weight:400"><?php echo hkp_h(hkp_pick($n, 'body')); ?></div></div>
    <span class="hkp-small hkp-muted" style="white-space:nowrap"><?php echo hkp_date($n['created_at'], true); ?></span></li>
<?php endforeach; ?></ul>
</section>
