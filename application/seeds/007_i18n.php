<?php
defined('BASEPATH') OR exit('No direct script access allowed');
require_once APPPATH . 'libraries/Ha_seeder.php';
require_once APPPATH . 'helpers/ha_locale_helper.php';

/**
 * Installs every enabled language into the parts of the application that keep
 * translations in the database:
 *
 *  1. Academy LMS (legacy) interface - the `language` table has one column per
 *     language (english, arabic, hindi, ...). Phrases come from the reviewed
 *     files application/language/legacy/{code}.php. A {column}.json file is
 *     written so the legacy language menu offers the language, and the RTL
 *     setting (`language_dirs`) is updated.
 *  2. Public website content - translations of pages, courses, categories,
 *     programs, learning paths, topics, articles and FAQs from
 *     application/language/content/{code}.php, matched by natural key (codes and
 *     slugs), written as normal ha_*_translation rows for that locale.
 *
 * Safe on production data and idempotent: existing rows are updated in place by
 * natural key; nothing is deleted; English and Arabic source content is never
 * overwritten by this seed.
 *
 *   php index.php ha_cli seed i18n
 */
class Seed_i18n extends Ha_seeder {

    public function run($db) {
        $this->boot($db);
        $n = $this->legacy_phrases();
        $n += $this->content();
        return $n;
    }

    // --------------------------------------------------------- legacy LMS

    protected function legacy_phrases() {
        $written = 0;
        $dirs = json_decode((string) $this->db->get_where('settings', array('key' => 'language_dirs'))->row('value'), true) ?: array();
        foreach (ha_locales() as $code) {
            $col = ha_locale_legacy_column($code);
            if (!$col || !preg_match('/^[a-z_]+$/', $col)) {
                continue;
            }
            if (!$this->db->query("SHOW COLUMNS FROM `language` LIKE " . $this->db->escape($col))->num_rows()) {
                $this->db->query("ALTER TABLE `language` ADD `$col` LONGTEXT NULL");
            }
            $dirs[$col] = ha_locale_dir($code);
            $file = APPPATH . 'language/legacy/' . $code . '.php';
            if (!is_file($file)) {
                $this->write_json($col);
                continue;
            }
            // English and Arabic already hold reviewed text: only empty cells are filled.
            // Other languages come entirely from their reviewed file.
            $fill_only = in_array($code, array('en', 'ar'), true);
            $map = (array) include $file;
            foreach ($map as $key => $text) {
                $text = trim(str_replace("\u{200B}", '', (string) $text));
                if ($key === '' || $text === '') {
                    continue;
                }
                if (!$this->db->where('phrase', $key)->count_all_results('language')) {
                    $this->db->insert('language', array('phrase' => $key));   // phrases added by new features
                }
                $db = $this->db->where('phrase', $key);
                if ($fill_only) {
                    // Empty, or still the machine fallback get_phrase() inserts ("Knowledge performance" for
                    // knowledge_performance): neither is anybody's wording, so both may be filled.
                    $auto = ucfirst(str_replace('_', ' ', $key));
                    $db->group_start()->where("`$col` IS NULL", null, false)->or_where("`$col`", '')->or_where("`$col`", $auto)->group_end();
                }
                $db->update('language', array($col => $text));
                $written += $this->db->affected_rows() > 0 ? 1 : 0;
            }
            $this->write_json($col);
        }
        $this->upsert('settings', array('key' => 'language_dirs'), array('value' => json_encode($dirs)));
        return $written;
    }

    /** The legacy app lists a language in its menus only when {column}.json exists. */
    protected function write_json($col) {
        $rows = $this->db->select("phrase, `$col` AS t", false)->where("`$col` IS NOT NULL", null, false)->where("`$col` !=", '')->get('language')->result_array();
        $map = array();
        foreach ($rows as $r) {
            $map[$r['phrase']] = $r['t'];
        }
        if ($map) {
            @file_put_contents(APPPATH . 'language/' . $col . '.json', json_encode($map, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
        }
    }

    // --------------------------------------------------------- website content

    /**
     * application/language/content/{code}.php returns array(section => array(natural key => fields)):
     *   courses     code      => title, short_description, description, requirements, outcomes[], faqs[{question, answer}]
     *   categories  code      => name, description
     *   programs    code      => title, short_description, description, outcomes, prerequisites
     *   articles    slug_en   => title, excerpt, body
     *   pages       code      => title, subtitle, body, cta_label, meta_title, meta_description, focus_keyword
     *   path        code / path_step "pathcode#sort" / topic code / faq "scope:topiccode#sort" / menu_item "menucode|url"
     *               => translated through the ha_i18n_text overlay (these records keep text in en/ar columns)
     * English and Arabic are never touched here; each language only ever writes its own rows.
     */
    protected function content() {
        $n = 0;
        foreach (ha_locales() as $code) {
            if (in_array($code, array('en', 'ar'), true)) {
                continue;
            }
            $file = APPPATH . 'language/content/' . $code . '.php';
            if (!is_file($file)) {
                continue;
            }
            $c = (array) include $file;
            $n += $this->translate($c, 'pages', 'ha_page', 'code', 'ha_page_translation', 'page_id', $code, array('title', 'subtitle', 'body', 'cta_label'));
            $n += $this->page_seo($c, $code);
            $n += $this->translate($c, 'courses', 'ha_course', 'code', 'ha_course_translation', 'course_id', $code, array('title', 'short_description', 'description', 'requirements'));
            $n += $this->course_lists($c, $code);
            $n += $this->translate($c, 'categories', 'ha_category', 'code', 'ha_category_translation', 'category_id', $code, array('name', 'description'));
            $n += $this->translate($c, 'programs', 'ha_program', 'code', 'ha_program_translation', 'program_id', $code, array('title', 'short_description', 'description', 'outcomes', 'prerequisites'));
            $n += $this->translate($c, 'articles', 'ha_article', 'slug_en', 'ha_article_translation', 'article_id', $code, array('title', 'excerpt', 'body'));
            $n += $this->overlays($c, $code);
        }
        return $n;
    }

    protected function translate(array $c, $key, $table, $natural, $tr_table, $fk, $code, array $fields) {
        if (empty($c[$key]) || !$this->db->table_exists($table) || !$this->db->table_exists($tr_table)) {
            return 0;
        }
        $cols = $this->db->list_fields($tr_table);
        $n = 0;
        foreach ($c[$key] as $natural_value => $values) {
            $row = $this->db->select('id')->get_where($table, array($natural => $natural_value))->row_array();
            if (!$row) {
                continue;
            }
            $data = array();
            foreach ($fields as $f) {
                if (isset($values[$f]) && !is_array($values[$f]) && in_array($f, $cols, true)) {
                    $data[$f] = $values[$f];
                }
            }
            if ($data) {
                $this->upsert($tr_table, array($fk => (int) $row['id'], 'locale' => $code), $data);
                $n++;
            }
        }
        return $n;
    }

    /** Course outcomes and FAQs are rows per locale: replace this locale's rows, English untouched. */
    protected function course_lists(array $c, $code) {
        if (empty($c['courses'])) {
            return 0;
        }
        $n = 0;
        foreach ($c['courses'] as $course_code => $v) {
            $id = (int) $this->db->select('id')->get_where('ha_course', array('code' => $course_code))->row('id');
            if (!$id) {
                continue;
            }
            if (!empty($v['outcomes']) && is_array($v['outcomes'])) {
                $this->db->delete('ha_course_outcome', array('course_id' => $id, 'locale' => $code));
                foreach (array_values($v['outcomes']) as $i => $body) {
                    $this->db->insert('ha_course_outcome', array('course_id' => $id, 'locale' => $code, 'body' => $body, 'sort_order' => $i + 1));
                    $n++;
                }
            }
            if (!empty($v['faqs']) && is_array($v['faqs'])) {
                $this->db->delete('ha_course_faq', array('course_id' => $id, 'locale' => $code));
                foreach (array_values($v['faqs']) as $i => $qa) {
                    $this->db->insert('ha_course_faq', array('course_id' => $id, 'locale' => $code, 'question' => $qa['question'], 'answer' => $qa['answer'], 'sort_order' => $i + 1));
                    $n++;
                }
            }
        }
        return $n;
    }

    protected function page_seo(array $c, $code) {
        if (empty($c['pages'])) {
            return 0;
        }
        $n = 0;
        foreach ($c['pages'] as $page_code => $v) {
            if (empty($v['meta_title']) && empty($v['meta_description'])) {
                continue;
            }
            $id = (int) $this->db->select('id')->get_where('ha_page', array('code' => $page_code))->row('id');
            if (!$id) {
                continue;
            }
            $this->upsert('ha_seo_metadata', array('entity_type' => 'page', 'entity_id' => $id, 'locale' => $code),
                array_filter(array('meta_title' => isset($v['meta_title']) ? $v['meta_title'] : null,
                    'meta_description' => isset($v['meta_description']) ? $v['meta_description'] : null,
                    'focus_keyword' => isset($v['focus_keyword']) ? $v['focus_keyword'] : null)));
            $n++;
        }
        return $n;
    }

    /** Records whose text lives in en/ar columns get their other languages through ha_i18n_text. */
    protected function overlays(array $c, $code) {
        if (!$this->db->table_exists('ha_i18n_text')) {
            return 0;
        }
        $resolve = array(
            'path'      => function ($k) { return $this->db->select('id')->get_where('ha_learning_path', array('code' => $k))->row('id'); },
            'topic'     => function ($k) { return $this->db->select('id')->get_where('ha_topic', array('code' => $k))->row('id'); },
            'path_step' => function ($k) {
                list($path, $sort) = array_pad(explode('#', $k, 2), 2, null);
                return $this->db->select('s.id')->from('ha_path_step s')->join('ha_learning_path p', 'p.id = s.path_id')
                    ->where(array('p.code' => $path, 's.sort_order' => (int) $sort))->get()->row('id');
            },
            'faq'       => function ($k) {
                if (!preg_match('/^(\w+):([^#]*)#(\d+)$/', $k, $m)) { return null; }
                $db = $this->db->select('f.id')->from('ha_faq f')->where(array('f.scope_type' => $m[1], 'f.sort_order' => (int) $m[3]));
                if ($m[2] !== '') { $db->join('ha_topic t', 't.id = f.scope_id')->where('t.code', $m[2]); } else { $db->where('f.scope_id IS NULL', null, false); }
                return $db->get()->row('id');
            },
            'menu_item' => function ($k) {
                list($menu, $url) = array_pad(explode('|', $k, 2), 2, '');
                return $this->db->select('i.id')->from('ha_menu_item i')->join('ha_menu m', 'm.id = i.menu_id')
                    ->where(array('m.code' => $menu, 'i.url_en' => $url))->get()->row('id');
            },
        );
        $n = 0;
        $now = date('Y-m-d H:i:s');
        foreach ($resolve as $entity => $find) {
            if (empty($c[$entity])) {
                continue;
            }
            foreach ($c[$entity] as $key => $fields) {
                $id = (int) $find((string) $key);
                if (!$id) {
                    continue;
                }
                foreach ((array) $fields as $field => $value) {
                    if (is_array($value) || trim((string) $value) === '') {
                        continue;
                    }
                    $this->upsert('ha_i18n_text', array('entity' => $entity, 'entity_id' => $id, 'field' => $field, 'locale' => $code),
                        array('value' => $value, 'source' => 'human', 'updated_at' => $now));
                    $n++;
                }
            }
        }
        return $n;
    }
}
