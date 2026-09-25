<section class="hkp-card" style="max-width:640px">
  <h1><?php echo hkp_e('Access denied'); ?></h1>
  <p><?php echo hkp_e('Your role does not include this area. If you need it, ask your administrator.'); ?></p>
  <p class="hkp-small hkp-muted"><?php echo hkp_e('Required permission: {p}', array('p' => $permission)); ?></p>
  <a class="hkp-btn" href="<?php echo hkp_url(); ?>"><?php echo hkp_e('Back to dashboard'); ?></a>
</section>
