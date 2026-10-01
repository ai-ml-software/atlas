<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ha_seeder.php';

/**
 * The catalogue is the Dyafa service-standards library only (the 40 courses built
 * from the training decks, seed 009, codes dy-*).
 *
 * Every other published academy course is ARCHIVED and every other active course
 * in the classic LMS is set to DRAFT. Nothing is deleted: enrolments, progress and
 * certificates are kept, and an administrator can republish any course. Runs after
 * 009 (filename order), so the library itself is never touched here.
 *
 *   php index.php ha_cli seed library_only
 */
class Seed_library_only extends Ha_seeder {

    public function run($db) {
        $this->boot($db);
        // Catalogue pruning is an explicit legacy operation. Ordinary seeding
        // must respect the instruction to leave non-PDF courses unchanged.
        if (getenv('HA_LIBRARY_HIDE_OTHER_COURSES') !== '1') { return 0; }
        $n = 0;
        if ($this->db->table_exists('ha_course')) {
            $this->db->where('status', 'published')->not_like('code', 'dy-', 'after')
                ->update('ha_course', array('status' => 'archived', 'updated_at' => $this->now));
            $n += $this->db->affected_rows();
        }
        if ($this->db->table_exists('course')) {
            // Classic LMS copies: the library's are tagged ha:dy-<slug> by Ha_bridge.
            $this->db->where('status', 'active')->group_start()
                    ->where('meta_keywords IS NULL', null, false)->or_not_like('meta_keywords', 'ha:dy-', 'after')
                ->group_end()->update('course', array('status' => 'draft'));
            $n += $this->db->affected_rows();
        }
        return $n;
    }
}
