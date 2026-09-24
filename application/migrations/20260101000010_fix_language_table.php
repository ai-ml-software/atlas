<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Make the phrase table able to hold a second language, and stop it filling
 * with duplicates.
 *
 * Two separate problems, both visible in the shipped table:
 *
 *  - 1,098 rows for 980 distinct keys. site_phrase() looks a phrase up and
 *    inserts it when the lookup misses, with nothing between the two steps.
 *    Two requests that hit the same unseeded phrase at the same moment both
 *    miss and both insert. Parallel browser tests made this easy to
 *    reproduce; real traffic does it more slowly. A unique key on the phrase
 *    turns the race into a harmless duplicate-key error instead of a row.
 *
 *  - Only an english column existed, so get_phrase() and site_phrase() had
 *    nothing to return for any other language and the admin panel and the
 *    Academy LMS front end could not be translated at all.
 *
 * Deduplication keeps the lowest id for each key, which is the row the
 * existing lookup would have returned anyway, so nothing changes on screen.
 */
class Migration_Fix_language_table extends Ha_migration {

    public function up() {
        if (!$this->db->table_exists('language')) {
            return;
        }

        // Collapse duplicates before the unique key can reject them.
        $this->db->query("
            DELETE l FROM language l
            JOIN (
                SELECT phrase, MIN(phrase_id) AS keep_id
                FROM language GROUP BY phrase HAVING COUNT(*) > 1
            ) d ON d.phrase = l.phrase AND l.phrase_id > d.keep_id
        ");

        $indexes = $this->db->query('SHOW INDEX FROM language')->result_array();
        $names = array();
        foreach ($indexes as $i) {
            $names[] = $i['Key_name'];
        }
        if (!in_array('uq_language_phrase', $names, true)) {
            // A prefix length is required: phrase is LONGTEXT and an index
            // cannot cover it whole. 191 is well past the longest key in use.
            $this->db->query('ALTER TABLE language ADD UNIQUE KEY uq_language_phrase (phrase(191))');
        }

        $columns = $this->db->list_fields('language');
        if (!in_array('arabic', $columns, true)) {
            $this->db->query('ALTER TABLE language ADD COLUMN arabic LONGTEXT NULL AFTER english');
        }
    }

    public function down() {
        if (!$this->db->table_exists('language')) {
            return;
        }
        $indexes = $this->db->query('SHOW INDEX FROM language')->result_array();
        foreach ($indexes as $i) {
            if ($i['Key_name'] === 'uq_language_phrase') {
                $this->db->query('ALTER TABLE language DROP INDEX uq_language_phrase');
                break;
            }
        }
        $columns = $this->db->list_fields('language');
        if (in_array('arabic', $columns, true)) {
            $this->db->query('ALTER TABLE language DROP COLUMN arabic');
        }
    }
}
