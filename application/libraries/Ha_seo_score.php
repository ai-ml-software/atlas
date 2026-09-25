<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Page optimisation score: SEO, AEO and GEO, per language.
 *
 *   SEO  classic search: title and description length, focus keyword placement,
 *        one H1, heading structure, content depth, image alt text, internal
 *        links, canonical, social image, both language versions (hreflang)
 *   AEO  answer engines (featured snippets, voice, AI overviews): question-
 *        shaped headings, FAQ with enough pairs, short direct answers, FAQ /
 *        HowTo schema, lists and steps
 *   GEO  generative engines and geography: clear entity naming, facts with
 *        numbers and sources, structured data type, local place details
 *        (region, city, coordinates), inclusion in llms.txt, Arabic parity
 *
 * Each check returns pass/fail, its weight and a concrete fix, so the score is
 * a to-do list rather than a mystery number.
 */
class Ha_seo_score {

    protected $CI;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
    }

    /** Assemble the text of a page (translation + sections) for one locale. */
    public function page_text($page_id, $loc) {
        $page = $this->CI->db->get_where('ha_page', array('id' => (int) $page_id))->row_array();
        $tr = $this->CI->db->get_where('ha_page_translation', array('page_id' => (int) $page_id, 'locale' => $loc))->row_array() ?: array();
        $sections = $this->CI->db->order_by('sort_order')->get_where('ha_page_section', array('page_id' => (int) $page_id, 'is_visible' => 1))->result_array();
        $html = isset($tr['body']) ? (string) $tr['body'] : '';
        $headings = array();
        $faq = array();
        $images = array();
        $steps = 0;
        foreach ($sections as $s) {
            $c = json_decode((string) $s['content_' . $loc], true) ?: array();
            $st = json_decode((string) $s['settings_json'], true) ?: array();
            if (!empty($c['heading'])) {
                $headings[] = $c['heading'];
            }
            foreach (array('body', 'text', 'lede', 'quote') as $k) {
                if (!empty($c[$k])) {
                    $html .= "\n<p>" . $c[$k] . '</p>';
                }
            }
            if ($s['section_type'] === 'faq') {
                foreach ((array) (isset($c['items']) ? $c['items'] : array()) as $it) {
                    if (!empty($it['q']) && !empty($it['a'])) {
                        $faq[] = $it;
                        $html .= "\n<h3>" . $it['q'] . '</h3><p>' . $it['a'] . '</p>';
                    }
                }
            } elseif (in_array($s['section_type'], array('cards', 'stats', 'steps'), true)) {
                foreach ((array) (isset($c['items']) ? $c['items'] : array()) as $it) {
                    $html .= "\n<p>" . (isset($it['title']) ? $it['title'] . ' ' : '') . (isset($it['text']) ? $it['text'] : '') . '</p>';
                    if ($s['section_type'] === 'steps') {
                        $steps++;
                    }
                }
            }
            if (!empty($st['image'])) {
                $images[] = array('src' => $st['image'], 'alt' => isset($c['image_alt']) ? $c['image_alt'] : '');
            }
        }
        if (preg_match_all('/<h[23][^>]*>(.*?)<\/h[23]>/is', $html, $m)) {
            foreach ($m[1] as $h) {
                $headings[] = strip_tags($h);
            }
        }
        if (preg_match_all('/<img[^>]*>/i', $html, $m)) {
            foreach ($m[0] as $tag) {
                preg_match('/alt="([^"]*)"/i', $tag, $a);
                $images[] = array('src' => 'inline', 'alt' => isset($a[1]) ? $a[1] : '');
            }
        }
        $internal = preg_match_all('~href="(/|' . preg_quote(base_url(), '~') . ')~i', $html);
        $plain = trim(preg_replace('/\s+/u', ' ', strip_tags($html)));
        return array('page' => $page, 'tr' => $tr, 'html' => $html, 'plain' => $plain, 'headings' => array_values(array_unique($headings)),
            'faq' => $faq, 'images' => $images, 'internal_links' => (int) $internal, 'steps' => $steps, 'sections' => $sections,
            'has_list' => (bool) preg_match('/<(ul|ol)[\s>]/i', $html) || $steps > 0);
    }

    public function score($page_id, $loc) {
        $t = $this->page_text($page_id, $loc);
        $page = $t['page'];
        $tr = $t['tr'];
        $seo = $this->CI->db->get_where('ha_seo_metadata', array('entity_type' => 'page', 'entity_id' => (int) $page_id, 'locale' => $loc))->row_array() ?: array();
        $title = isset($seo['meta_title']) && $seo['meta_title'] ? $seo['meta_title'] : (isset($tr['title']) ? $tr['title'] : '');
        $desc = isset($seo['meta_description']) && $seo['meta_description'] ? $seo['meta_description'] : (isset($tr['subtitle']) ? $tr['subtitle'] : '');
        $kw = trim((string) $page['focus_keyword_' . $loc]);
        $kwl = mb_strtolower($kw);
        $has = function ($hay) use ($kwl) { return $kwl !== '' && mb_strpos(mb_strtolower((string) $hay), $kwl) !== false; };
        $words = $t['plain'] === '' ? 0 : count(preg_split('/\s+/u', $t['plain']));
        $first = mb_substr($t['plain'], 0, 400);
        $other = $loc === 'ar' ? 'en' : 'ar';
        $other_tr = $this->CI->db->get_where('ha_page_translation', array('page_id' => (int) $page_id, 'locale' => $other))->row_array();
        $alt_missing = count(array_filter($t['images'], function ($i) { return trim((string) $i['alt']) === ''; }));
        $questions = count(array_filter($t['headings'], function ($h) { return (bool) preg_match('/(\?|؟)\s*$|^(what|how|why|when|who|which|can|do|does|is|are|ما|كيف|لماذا|متى|من|هل|أين)\b/iu', trim($h)); }));
        $short_answers = count(array_filter($t['faq'], function ($f) { $n = count(preg_split('/\s+/u', trim(strip_tags($f['a'])))); return $n >= 8 && $n <= 60; }));
        $facts = preg_match_all('/\d[\d,\.]*\s?(%|٪|sar|ريال|million|مليون|keys|rooms|غرفة|years|سنة)/iu', $t['plain']);
        $sources = preg_match('/(source|sources|المصدر|المصادر|according to|وفق)/iu', $t['plain']);
        $brand = preg_match('/(altus|ألتوس|hospitality academy|أكاديمية)/iu', $t['plain'] . ' ' . $title);
        $schema_ok = !empty($page['schema_type']) || !empty($seo['schema_json']);
        $geo_ok = !empty($page['geo_region']) && !empty($page['geo_placename']);
        $coords = $page['geo_lat'] !== null && $page['geo_lng'] !== null;
        $checks = array(
            // group, code, weight, pass, message when failing
            array('seo', 'title_length', 10, mb_strlen($title) >= 30 && mb_strlen($title) <= 60, 'Write a meta title of 30–60 characters (now ' . mb_strlen($title) . ').'),
            array('seo', 'description_length', 10, mb_strlen($desc) >= 70 && mb_strlen($desc) <= 160, 'Write a meta description of 70–160 characters (now ' . mb_strlen($desc) . ').'),
            array('seo', 'focus_keyword_set', 6, $kw !== '', 'Set a focus keyword for this language.'),
            array('seo', 'keyword_in_title', 8, $has($title), 'Use the focus keyword in the meta title.'),
            array('seo', 'keyword_in_description', 5, $has($desc), 'Use the focus keyword in the meta description.'),
            array('seo', 'keyword_in_h1', 6, $has(isset($tr['title']) ? $tr['title'] : ''), 'Use the focus keyword in the page heading (H1).'),
            array('seo', 'keyword_in_intro', 5, $has($first), 'Mention the focus keyword in the first paragraph.'),
            array('seo', 'content_depth', 10, $words >= 300, 'Add content: at least 300 words (now ' . $words . ').'),
            array('seo', 'subheadings', 6, count($t['headings']) >= 2, 'Add at least two section headings (H2/H3).'),
            array('seo', 'image_alt', 5, $alt_missing === 0, $alt_missing . ' image(s) have no alt text.'),
            array('seo', 'internal_links', 6, $t['internal_links'] >= 2, 'Link to at least two other pages on this site.'),
            array('seo', 'social_image', 4, !empty($seo['og_image']) || !empty($tr['hero_image']), 'Set a social sharing image.'),
            array('seo', 'both_languages', 8, $other_tr && trim((string) $other_tr['title']) !== '', 'Publish the ' . ($other === 'ar' ? 'Arabic' : 'English') . ' version so hreflang pairs the pages.'),
            array('seo', 'indexable', 5, !isset($seo['robots']) || strpos((string) $seo['robots'], 'noindex') === false, 'The page is set to noindex.'),
            array('aeo', 'question_headings', 20, $questions >= 2, 'Phrase at least two headings as the questions people ask.'),
            array('aeo', 'faq_pairs', 25, count($t['faq']) >= 3, 'Add an FAQ section with at least three questions and answers.'),
            array('aeo', 'direct_answers', 20, $short_answers >= 2, 'Keep FAQ answers direct: 8–60 words each.'),
            array('aeo', 'structured_lists', 15, $t['has_list'], 'Add a list or a steps section: answer engines quote lists.'),
            array('aeo', 'answer_schema', 20, count($t['faq']) >= 1 || in_array($page['schema_type'], array('FAQPage'), true), 'FAQ content produces FAQPage schema automatically; add some.'),
            array('geo', 'entity_named', 15, (bool) $brand, 'Name the organisation clearly in the content.'),
            array('geo', 'facts', 20, $facts >= 2, 'Include at least two concrete facts with numbers.'),
            array('geo', 'sources', 15, (bool) $sources, 'Cite the source of the facts you state.'),
            array('geo', 'schema_type', 15, $schema_ok, 'Choose a schema type for the page.'),
            array('geo', 'place', 15, $geo_ok, 'Set the region and place name (e.g. SA-01, Riyadh).'),
            array('geo', 'coordinates', 10, $coords, 'Add latitude and longitude for local results.'),
            array('geo', 'published', 10, $page['status'] === 'published', 'Publish the page so it appears in llms.txt and the sitemap.'),
        );
        $groups = array('seo' => array(0, 0), 'aeo' => array(0, 0), 'geo' => array(0, 0));
        $out = array();
        foreach ($checks as $c) {
            $groups[$c[0]][1] += $c[2];
            if ($c[3]) {
                $groups[$c[0]][0] += $c[2];
            }
            $out[] = array('group' => $c[0], 'code' => $c[1], 'weight' => $c[2], 'passed' => (bool) $c[3], 'fix' => $c[4]);
        }
        $scores = array();
        foreach ($groups as $g => $v) {
            $scores[$g] = $v[1] ? (int) round(100 * $v[0] / $v[1]) : 0;
        }
        $scores['overall'] = (int) round(0.5 * $scores['seo'] + 0.25 * $scores['aeo'] + 0.25 * $scores['geo']);
        $this->CI->db->where('id', (int) $page_id)->update('ha_page', array('seo_score_' . $loc => $scores['overall'], 'seo_checked_at' => date('Y-m-d H:i:s')));
        return array('scores' => $scores, 'checks' => $out, 'words' => $words, 'title' => $title, 'description' => $desc);
    }
}
