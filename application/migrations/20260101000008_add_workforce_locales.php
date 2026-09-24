<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * The languages the workforce actually reads.
 *
 * Every translated table was built as enum('en','ar'), which matches the
 * languages a Saudi hotel publishes in and not the languages its line staff
 * read. Housekeeping, stewarding, laundry and much of food and beverage in
 * the Gulf are staffed largely by expatriate workers whose working language
 * is Hindi, Urdu, Tagalog, Bengali, Nepali or Malayalam. A housekeeping SOP
 * that exists only in English and Arabic is not training for the person who
 * has to follow it.
 *
 * Two deliberate decisions are encoded here rather than left to convention:
 *
 *  - ha_locale carries a review_state per language. A machine translation of
 *    a fire evacuation or food safety procedure is a safety hazard, not a
 *    typo, so the schema records whether a human has checked a language and
 *    the application can refuse to certify from an unreviewed one.
 *  - is_rtl travels with the locale, because Arabic and Urdu both need it and
 *    hardcoding "ar means RTL" breaks the moment Urdu is added.
 *
 * Widening an enum is additive and does not touch existing rows.
 */
class Migration_Add_workforce_locales extends Ha_migration {

    /** Tables whose locale enum has to learn the new languages. */
    private $translated = array(
        'ha_article_translation', 'ha_category_translation', 'ha_course_faq',
        'ha_course_outcome', 'ha_course_translation', 'ha_lead',
        'ha_lesson_translation', 'ha_organization', 'ha_page_translation',
        'ha_profile', 'ha_program_translation', 'ha_seo_metadata',
        'ha_sop_version_translation',
    );

    private $locales = array('en', 'ar', 'hi', 'ur', 'tl', 'bn', 'ne', 'ml');

    public function up() {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS ha_locale (
                code            VARCHAR(8) NOT NULL,
                name_en         VARCHAR(80) NOT NULL,
                name_native     VARCHAR(80) NOT NULL,
                is_rtl          TINYINT(1) NOT NULL DEFAULT 0,
                is_published    TINYINT(1) NOT NULL DEFAULT 0,
                review_state    ENUM('none','machine','human_reviewed') NOT NULL DEFAULT 'none',
                certifiable     TINYINT(1) NOT NULL DEFAULT 0,
                sort_order      INT NOT NULL DEFAULT 0,
                created_at      DATETIME NOT NULL,
                updated_at      DATETIME NOT NULL,
                PRIMARY KEY (code),
                KEY ix_ha_locale_published (is_published)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");

        $enum = "enum('" . implode("','", $this->locales) . "')";
        foreach ($this->translated as $table) {
            if (!$this->db->table_exists($table)) {
                continue;
            }
            // NOT NULL with no default matches how these columns were created;
            // restating it keeps the widening from silently relaxing them.
            $this->db->query("ALTER TABLE {$table} MODIFY COLUMN locale {$enum} NOT NULL");
        }

        // certifiable is deliberately 0 for every machine-translated language.
        // A certificate asserts the holder understood the procedure, and that
        // claim cannot rest on an unreviewed translation.
        $now = date('Y-m-d H:i:s');
        $rows = array(
            array('en', 'English',   'English',   0, 1, 'human_reviewed', 1, 1),
            array('ar', 'Arabic',    'العربية',    1, 1, 'human_reviewed', 1, 2),
            array('hi', 'Hindi',     'हिन्दी',       0, 0, 'none', 0, 3),
            array('ur', 'Urdu',      'اردو',       1, 0, 'none', 0, 4),
            array('tl', 'Tagalog',   'Tagalog',   0, 0, 'none', 0, 5),
            array('bn', 'Bengali',   'বাংলা',       0, 0, 'none', 0, 6),
            array('ne', 'Nepali',    'नेपाली',      0, 0, 'none', 0, 7),
            array('ml', 'Malayalam', 'മലയാളം',   0, 0, 'none', 0, 8),
        );
        foreach ($rows as $r) {
            $exists = $this->db->where('code', $r[0])->count_all_results('ha_locale');
            if ($exists) {
                continue;
            }
            $this->db->insert('ha_locale', array(
                'code' => $r[0], 'name_en' => $r[1], 'name_native' => $r[2],
                'is_rtl' => $r[3], 'is_published' => $r[4], 'review_state' => $r[5],
                'certifiable' => $r[6], 'sort_order' => $r[7],
                'created_at' => $now, 'updated_at' => $now,
            ));
        }
    }

    public function down() {
        foreach ($this->translated as $table) {
            if (!$this->db->table_exists($table)) {
                continue;
            }
            // Rows in a language being removed would violate the narrower
            // enum, so they go first. This is destructive by nature, which is
            // why it only ever runs on a rollback the operator asked for.
            $this->db->where_not_in('locale', array('en', 'ar'))->delete($table);
            $this->db->query("ALTER TABLE {$table} MODIFY COLUMN locale enum('en','ar') NOT NULL");
        }
        $this->db->query("DROP TABLE IF EXISTS ha_locale");
    }
}
