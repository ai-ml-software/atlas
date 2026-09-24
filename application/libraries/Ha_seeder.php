<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Base class for Hospitality Academy seeders.
 *
 * Every seeder must be idempotent: running it twice leaves the same rows,
 * updated in place, never duplicated. That is what lets `ha_cli seed` be run
 * safely against an existing database.
 */
abstract class Ha_seeder {

    /** @var CI_DB_query_builder */
    protected $db;

    protected $now;

    /** @return int number of rows written */
    abstract public function run($db);

    protected function boot($db) {
        $this->db = $db;
        $this->now = date('Y-m-d H:i:s');
    }

    /**
     * Insert or update a row matched by $match. Returns the row id.
     */
    protected function upsert($table, array $match, array $values) {
        $row = $this->db->get_where($table, $match)->row_array();
        if ($row) {
            $values['updated_at'] = isset($values['updated_at']) ? $values['updated_at'] : $this->now;
            $clean = $this->only_existing($table, $values);
            unset($clean['created_at']);
            if ($clean) {
                $this->db->where($match)->update($table, $clean);
            }
            return (int) $row['id'];
        }
        $values = array_merge($match, $values);
        if (!isset($values['created_at'])) {
            $values['created_at'] = $this->now;
        }
        if (!isset($values['updated_at'])) {
            $values['updated_at'] = $this->now;
        }
        $this->db->insert($table, $this->only_existing($table, $values));
        return (int) $this->db->insert_id();
    }

    /** Insert a link row only when the exact pair is absent. */
    protected function link($table, array $match, array $extra = array()) {
        $existing = $this->db->get_where($table, $match)->row_array();
        if ($existing) {
            return false;
        }
        $this->db->insert($table, $this->only_existing($table, array_merge($match, $extra)));
        return true;
    }

    /** Strips keys that are not real columns so seeders stay tolerant of schema drift. */
    protected function only_existing($table, array $values) {
        $fields = $this->db->list_fields($table);
        return array_intersect_key($values, array_flip($fields));
    }

    protected function slugify($text) {
        $text = trim(strtolower($text));
        $text = preg_replace('/[^a-z0-9\x{0600}-\x{06FF}]+/u', '-', $text);
        return trim($text, '-');
    }
}
