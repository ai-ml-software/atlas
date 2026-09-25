<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Any language, not a fixed list.
 *
 *  - Every ha_* `locale` column that was an ENUM of a few languages becomes
 *    VARCHAR(12) holding a BCP 47 / ISO 639 code, so content can be authored in
 *    any language enabled in config/ha_locales.php without a schema change.
 *    Existing values (en, ar, hi, ... and special values such as 'both' or
 *    'bilingual') are kept as they are.
 *  - ha_page_section.content_i18n: section content for languages other than
 *    English and Arabic, as JSON {locale: {field: value}}.
 *  - ha_profile.country / ha_property.country_code: ISO 3166 country, used for
 *    phone numbers, currency and time zone defaults.
 *
 * down() restores the new columns only; locale columns stay VARCHAR because
 * narrowing them back to an ENUM could destroy rows written in other languages.
 */
class Migration_Open_locales extends Ha_migration {

    public function up() {
        $cols = $this->db->query("SELECT table_name AS t, column_name AS c, is_nullable AS n, column_default AS d
            FROM information_schema.columns
            WHERE table_schema = DATABASE() AND table_name LIKE 'ha\\_%' AND column_name = 'locale' AND data_type = 'enum'")->result_array();
        foreach ($cols as $col) {
            $null = $col['n'] === 'YES' ? 'NULL' : 'NOT NULL';
            $default = $col['d'] !== null ? ' DEFAULT ' . $this->db->escape($col['d']) : ($col['n'] === 'YES' ? ' DEFAULT NULL' : '');
            $this->db->query('ALTER TABLE `' . $col['t'] . '` MODIFY `locale` VARCHAR(12) ' . $null . $default);
        }
        $this->add_columns('ha_page_section', array('content_i18n' => 'LONGTEXT NULL AFTER content_ar'));
        $this->add_columns('ha_profile', array('country' => 'CHAR(2) NULL'));
        $this->add_columns('ha_property', array('country_code' => 'CHAR(2) NULL'));
        // Existing Saudi properties: the country column held free text; record the ISO code where it is unambiguous.
        $this->db->query("UPDATE ha_property SET country_code = 'SA' WHERE country_code IS NULL AND (country IS NULL OR country IN ('SA','Saudi Arabia','KSA','المملكة العربية السعودية','السعودية'))");
    }

    public function down() {
        $this->drop_columns('ha_page_section', array('content_i18n'));
        $this->drop_columns('ha_profile', array('country'));
        $this->drop_columns('ha_property', array('country_code'));
    }
}
