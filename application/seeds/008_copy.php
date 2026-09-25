<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ha_seeder.php';

/**
 * Installs the rewritten English and Arabic website copy (page title, subtitle,
 * body, call to action and SEO metadata) from application/language/content/copy_pages.php.
 *
 * English and Arabic are the source languages an administrator edits in the
 * CMS, so this is applied once per COPY_VERSION and then left alone: running
 * the seed again never overwrites later edits. Bump COPY_VERSION to ship a new
 * version of the copy.
 *
 *   php index.php ha_cli seed copy
 */
class Seed_copy extends Ha_seeder {

    const COPY_VERSION = 1;
    const SETTING = 'site.copy_version';

    public function run($db) {
        $this->boot($db);
        $file = APPPATH . 'language/content/copy_pages.php';
        if (!is_file($file) || !$this->db->table_exists('ha_page')) {
            return 0;
        }
        $row = $this->db->get_where('ha_setting', array('scope_type' => 'global', 'scope_id' => 0, 'setting_key' => self::SETTING))->row_array();
        if ($row && (int) $row['value'] >= self::COPY_VERSION) {
            return 0;
        }
        $copy = (array) include $file;
        $n = 0;
        foreach ($copy as $locale => $pages) {
            foreach ((array) $pages as $code => $v) {
                $id = (int) $this->db->select('id')->get_where('ha_page', array('code' => $code))->row('id');
                if (!$id) {
                    continue;
                }
                $this->upsert('ha_page_translation', array('page_id' => $id, 'locale' => $locale), array(
                    'title' => $v['title'], 'subtitle' => $v['subtitle'], 'body' => $v['body'], 'cta_label' => $v['cta_label'],
                ));
                $this->upsert('ha_seo_metadata', array('entity_type' => 'page', 'entity_id' => $id, 'locale' => $locale), array(
                    'meta_title' => $v['meta_title'], 'meta_description' => $v['meta_description'], 'focus_keyword' => $v['focus_keyword'],
                ));
                $n++;
            }
        }
        $this->upsert('ha_setting', array('scope_type' => 'global', 'scope_id' => 0, 'setting_key' => self::SETTING), array('value' => (string) self::COPY_VERSION));
        return $n;
    }
}
