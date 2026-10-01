<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ha_seeder.php';

/**
 * Corporate content in the site's other languages (hi, ur, bn, tl, ne, ml), from
 * application/seeds/i18n/corporate.<locale>.json (ids as in corporate.en.json).
 *
 * English and Arabic live in the records' own *_en / *_ar columns; every other
 * language is an ha_i18n_text overlay, which Ha_corporate and Ha_catalog read.
 * Rows are written with source='ai' and a row a person has saved (source='human')
 * is never overwritten.
 *
 *   php index.php ha_cli seed i18n_corporate
 */
class Seed_i18n_corporate extends Ha_seeder {

    /** id prefix => [table, key column, entity name used by the overlay readers] */
    private $map = array(
        'corporate_block' => array('ha_corporate_block', 'code', 'corporate_block'),
        'service'         => array('ha_service', 'code', 'service'),
        'sector'          => array('ha_sector', 'code', 'sector'),
        'case_study'      => array('ha_case_study', 'slug', 'case_study'),
        'case_metric'     => array('ha_case_study', 'slug', 'case_study'),
        'leader'          => array('ha_leadership_profile', 'slug', 'leadership_profile'),
        'menu'            => array('ha_menu_item', 'url_en', 'menu_item'),
    );
    private $ids = array();

    public function run($db) {
        $this->boot($db);
        if (!$this->db->table_exists('ha_i18n_text')) {
            return 0;
        }
        $n = 0;
        foreach (glob(APPPATH . 'seeds/i18n/corporate.*.json') as $file) {
            if (!preg_match('/corporate\.([a-z]{2})\.json$/', $file, $m) || in_array($m[1], array('en', 'ar'), true)) {
                continue;
            }
            $locale = $m[1];
            $rows = json_decode(file_get_contents($file), true);
            foreach ((array) $rows as $key => $value) {
                $parts = explode(':', $key, 3);
                if (count($parts) !== 3 || !isset($this->map[$parts[0]]) || trim((string) $value) === '') {
                    continue;
                }
                list($kind, $natural, $field) = $parts;
                list($table, $col, $entity) = $this->map[$kind];
                $id = $this->id_of($table, $col, $natural, $kind === 'menu');
                if (!$id) {
                    continue;
                }
                if ($kind === 'case_metric') {
                    $field = 'metric_' . (int) $field;
                }
                $n += $this->put($entity, $id, $field, $locale, (string) $value);
            }
        }
        return $n;
    }

    private function id_of($table, $col, $natural, $menu) {
        $k = $table . '|' . $natural;
        if (!array_key_exists($k, $this->ids)) {
            $db = $this->db->select('id')->where($col, $natural);
            if ($menu) {
                $hm = $this->db->get_where('ha_menu', array('code' => 'public_header'))->row_array();
                $db = $this->db->select('id')->where($col, $natural)->where('menu_id', $hm ? (int) $hm['id'] : 0);
            }
            $row = $db->get($table)->row_array();
            $this->ids[$k] = $row ? (int) $row['id'] : 0;
        }
        return $this->ids[$k];
    }

    private function put($entity, $id, $field, $locale, $value) {
        $match = array('entity' => $entity, 'entity_id' => $id, 'field' => $field, 'locale' => $locale);
        $row = $this->db->get_where('ha_i18n_text', $match)->row_array();
        if ($row && $row['source'] === 'human') {
            return 0;
        }
        if ($row) {
            $this->db->where('id', $row['id'])->update('ha_i18n_text', array('value' => $value, 'source' => 'ai', 'updated_at' => $this->now));
        } else {
            $this->db->insert('ha_i18n_text', $match + array('value' => $value, 'source' => 'ai', 'updated_at' => $this->now));
        }
        return 1;
    }
}
