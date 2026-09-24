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
}
