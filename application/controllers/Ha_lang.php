<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Arabic for the Academy LMS interface.
 *
 *   php index.php ha_lang translate   write Arabic for every known phrase
 *   php index.php ha_lang status      coverage, and what is still English
 *   php index.php ha_lang missing     list untranslated keys
 *
 * The public academy site carries its own Arabic in ha_*_translation tables.
 * This file covers the other half of the application: the admin panel and the
 * Academy LMS front end, both of which read their text through get_phrase()
 * and site_phrase() from the `language` table.
 *
 * Translations are written to the `arabic` column, keyed by the same
 * normalised phrase the helpers look up, so switching the interface language
 * to Arabic is all that is needed to use them.
 *
 * Two conventions, because an LMS read by hotel staff is not a place to be
 * clever with language:
 *
 *  - Modern Standard Arabic throughout, since it is what every Arabic reader
 *    in the Gulf shares. No dialect.
 *  - Interface terms follow the vocabulary Arabic software actually uses
 *    (لوحة التحكم, تسجيل الدخول, الإعدادات) rather than literal renderings,
 *    because a literal translation of a button is not a button anyone
 *    recognises.
 */
class Ha_lang extends CI_Controller {

    public function __construct() {
        parent::__construct();
        if (!is_cli()) {
            show_404();
        }
        @set_time_limit(0);
        $this->load->database();
    }

    private function out($line = '') {
        fwrite(STDOUT, $line . PHP_EOL);
    }

    /** The phrase map lives in a library so it can be reused and reviewed. */
    private function dictionary() {
        $this->load->library('ha_phrasebook');
        return $this->ha_phrasebook->arabic();
    }

    public function translate() {
        $columns = $this->db->list_fields('language');
        if (!in_array('arabic', $columns, true)) {
            $this->out('The language table has no arabic column. Run: php index.php ha_cli migrate');
            exit(1);
        }

        $dictionary = $this->dictionary();
        $written = 0;
        $unknown = 0;

        foreach ($this->db->select('phrase_id, phrase')->get('language')->result_array() as $row) {
            $key = $row['phrase'];
            if (!isset($dictionary[$key])) {
                $unknown++;
                continue;
            }
            $this->db->where('phrase_id', $row['phrase_id'])
                ->update('language', array('arabic' => $dictionary[$key]));
            $written++;
        }

        $this->out('phrases translated: ' . $written);
        $this->out('still English:      ' . $unknown);
        $this->out('dictionary entries: ' . count($dictionary));
        if ($unknown) {
            $this->out('');
            $this->out('Run "php index.php ha_lang missing" for the list.');
        }
    }

    /**
     * Write application/language/arabic.json.
     *
     * The application discovers which languages to offer by scanning that
     * directory for .json files; the text itself comes from the matching
     * column in the `language` table. So without this file Arabic is fully
     * translated in the database and still absent from the language menu,
     * which is exactly how it shipped.
     */
    public function export() {
        $rows = $this->db->select('phrase, arabic')
            ->where('arabic IS NOT NULL')->where('arabic !=', '')
            ->get('language')->result_array();

        $map = array();
        foreach ($rows as $r) {
            $map[$r['phrase']] = $r['arabic'];
        }

        $path = APPPATH . 'language/arabic.json';
        $written = file_put_contents($path,
            json_encode($map, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));

        if ($written === false) {
            $this->out('Could not write ' . $path);
            exit(1);
        }
        $this->out('wrote ' . count($map) . ' phrases to ' . $path);
        $this->out('Arabic will now appear in the language menu.');
    }

    public function status() {
        $total = $this->db->count_all('language');
        $done  = $this->db->where('arabic IS NOT NULL')->where('arabic !=', '')
            ->count_all_results('language');
        $this->out('phrases:     ' . $total);
        $this->out('translated:  ' . $done);
        $this->out('untranslated:' . ($total - $done));
        $this->out('coverage:    ' . ($total ? round($done / $total * 100, 1) : 0) . '%');
    }

    public function missing() {
        $rows = $this->db->select('phrase')
            ->group_start()->where('arabic IS NULL')->or_where('arabic', '')->group_end()
            ->order_by('phrase')->get('language')->result_array();
        foreach ($rows as $r) {
            $this->out($r['phrase']);
        }
        $this->out('');
        $this->out(count($rows) . ' untranslated');
    }
}
