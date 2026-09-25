<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base class for Hospitality Academy migrations.
 *
 * Deliberately not CI_Migration: that class is driven by the bundled
 * Migration library, which cannot run on PHP 8 (see Ha_cli). This base gives
 * migrations the same $this->db / $this->dbforge surface without the library.
 */
abstract class Ha_migration {

    /** @var CI_DB_query_builder */
    protected $db;

    /** @var CI_DB_forge */
    protected $dbforge;

    /** Shared table options for every Hospitality Academy table. */
    protected $engine = ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci';

    public function __construct() {
        $CI =& get_instance();
        $CI->load->database();
        $CI->load->dbforge();
        $this->db = $CI->db;
        $this->dbforge = $CI->dbforge;
    }

    abstract public function up();

    abstract public function down();

    /** Drops the given tables in order with foreign key checks suspended. */
    protected function drop(array $tables) {
        $this->db->query('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $t) {
            $this->db->query('DROP TABLE IF EXISTS ' . $t);
        }
        $this->db->query('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * Adds columns to an existing table, skipping any that already exist, so a
     * migration that extends a shipped table can be re-run after a partial
     * failure without erroring. $columns maps column name => DDL after the name.
     */
    protected function add_columns($table, array $columns) {
        $existing = $this->db->list_fields($table);
        foreach ($columns as $name => $ddl) {
            if (!in_array($name, $existing, true)) {
                $this->db->query('ALTER TABLE ' . $table . ' ADD COLUMN `' . $name . '` ' . $ddl);
            }
        }
    }

    /** Reverse of add_columns(): drops the columns that exist. */
    protected function drop_columns($table, array $names) {
        if (!$this->db->table_exists($table)) {
            return;
        }
        $existing = $this->db->list_fields($table);
        foreach ($names as $name) {
            if (in_array($name, $existing, true)) {
                $this->db->query('ALTER TABLE ' . $table . ' DROP COLUMN `' . $name . '`');
            }
        }
    }
}
