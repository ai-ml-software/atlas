<?php
/* Editor for one section. $s (row or null), $type, $def (label, fields, settings), $page_id */
$val = function ($loc, $f) use ($s) {
    if (!$s) { return ''; }
    $c = $s[$loc];
    if (strpos($f, 'items[]:') === 0) {
        return Ha_page_builder::items_to_text(isset($c['items']) ? (array) $c['items'] : array(), explode('|', substr($f, 8)));
    }
    return isset($c[$f]) ? $c[$f] : '';
};
$settings = $s ? $s['settings'] : array();
$uid = $s ? 's' . $s['id'] : 'n' . $type;
?>
<form class="hkp-form" method="post" enctype="multipart/form-data" action="<?php echo hkp_url('cms/section/' . (int) $page_id . '/' . ($s ? (int) $s['id'] : 0)); ?>" style="margin-top:.8rem"><?php echo ha_csrf_field(); ?>
  <input type="hidden" name="section_type" value="<?php echo hkp_h($type); ?>"><input type="hidden" name="do" value="save">
  <div class="hkp-grid hkp-grid--2">
  <?php foreach (array('en' => 'English', 'ar' => 'العربية') as $loc => $lname): $dir = $loc === 'ar' ? ' dir="rtl"' : ''; ?>
    <div class="hkp-form"><strong class="hkp-small"><?php echo $lname; ?></strong>
    <?php foreach ($def[1] as $f): $req = substr($f, -1) === '*' && $loc === 'en'; $f = rtrim($f, '*'); $is_items = strpos($f, 'items[]:') === 0; $name = $is_items ? 'items' : $f; $id = $uid . '_' . $loc . '_' . $name; ?>
      <div class="hkp-field"><label for="<?php echo $id; ?>"><?php echo $is_items ? hkp_e('Items, one per line: {f}', array('f' => str_replace('|', ' | ', substr($f, 8)))) : hkp_label($f); ?><?php echo $req ? ' *' : ''; ?></label>
      <?php if ($is_items || in_array($f, array('body', 'lede', 'quote'), true)): ?><textarea id="<?php echo $id; ?>" class="hkp-input" rows="<?php echo $is_items ? 5 : 4; ?>" name="<?php echo $loc; ?>[<?php echo $name; ?>]"<?php echo $dir; ?><?php echo $req ? ' required' : ''; ?>><?php echo hkp_h($val($loc, $f)); ?></textarea>
      <?php else: ?><input id="<?php echo $id; ?>" class="hkp-input" name="<?php echo $loc; ?>[<?php echo $name; ?>]" value="<?php echo hkp_h($val($loc, $f)); ?>"<?php echo $dir; ?><?php echo $req ? ' required' : ''; ?>><?php endif; ?></div>
    <?php endforeach; ?></div>
  <?php endforeach; ?>
  </div>
  <?php if (in_array('image', $def[2], true)): ?><div class="hkp-row"><div class="hkp-field"><label for="<?php echo $uid; ?>_img"><?php echo hkp_e('Image'); ?></label><input id="<?php echo $uid; ?>_img" class="hkp-input" type="file" name="image" accept="image/*">
    <input class="hkp-input" name="settings[image]" value="<?php echo hkp_h(isset($settings['image']) ? $settings['image'] : ''); ?>" placeholder="uploads/…" aria-label="<?php echo hkp_e('Image path'); ?>"><?php if (!empty($settings['image'])): ?><img src="<?php echo hkp_h(base_url($settings['image'])); ?>" alt="" style="max-height:80px;border-radius:8px"><?php endif; ?></div>
    <?php if (in_array('image_side', $def[2], true)): ?><div class="hkp-field"><label for="<?php echo $uid; ?>_side"><?php echo hkp_e('Image side'); ?></label><select id="<?php echo $uid; ?>_side" class="hkp-select" name="settings[image_side]"><option value="start"><?php echo hkp_e('Start'); ?></option><option value="end"<?php echo isset($settings['image_side']) && $settings['image_side'] === 'end' ? ' selected' : ''; ?>><?php echo hkp_e('End'); ?></option></select></div><?php endif; ?></div><?php endif; ?>
  <?php if (in_array('columns', $def[2], true)): ?><div class="hkp-field" style="max-width:160px"><label for="<?php echo $uid; ?>_col"><?php echo hkp_e('Columns'); ?></label><select id="<?php echo $uid; ?>_col" class="hkp-select" name="settings[columns]"><?php foreach (array(2, 3, 4) as $n): ?><option<?php echo isset($settings['columns']) && (int) $settings['columns'] === $n ? ' selected' : ''; ?>><?php echo $n; ?></option><?php endforeach; ?></select></div><?php endif; ?>
  <div><button class="hkp-btn hkp-btn--sm"><?php echo $s ? hkp_e('Save section') : hkp_e('Add this section'); ?></button></div>
</form>
