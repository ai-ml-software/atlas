<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * SEO, AEO and GEO service for the public academy site.
 * Plan sections 27, 28, 29, 53.
 *
 * Admin editable metadata lives in ha_seo_metadata. This library resolves the
 * record for a page, falls back to a sensible derived value when an editor has
 * not written one, and renders the head block: title, description, canonical,
 * robots, hreflang pair, OpenGraph, Twitter card and JSON-LD.
 *
 * Nothing here requires a developer to edit source to change a title, which is
 * the explicit requirement of plan section 27.
 */
class Ha_seo {

    const BRAND_EN = 'Hospitality Academy';
    const BRAND_AR = 'أكاديمية الضيافة';

    /** @var CI_Controller */
    protected $CI;

    /** @var CI_DB_query_builder */
    protected $db;

    /** Metadata resolved for the current request. */
    protected $meta = array();

    /** Breadcrumb trail for the current request. */
    protected $breadcrumbs = array();

    /** Extra JSON-LD graphs added by a controller. */
    protected $extra_schema = array();

    /** The path of the current page, without a locale prefix. */
    protected $path = '';

    protected $locale = 'en';

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->db = $this->CI->db;
    }

    // ------------------------------------------------------------------ setup

    public function base_url() {
        return rtrim(base_url(), '/');
    }

    /** Public URL for a path in a locale. Locale is always explicit in the URL. */
    public function url($path = '', $locale = null) {
        $locale = $locale ?: $this->locale;
        $path = ltrim((string) $path, '/');
        return $this->base_url() . '/' . $locale . ($path === '' ? '' : '/' . $path);
    }

    public function locale() {
        return $this->locale;
    }

    /**
     * Rebuilds an absolute asset URL from whatever is stored.
     *
     * og:image has to be absolute, and the value in the database cannot be.
     * It is written by a CLI command, and CLI has no HTTP_HOST, so base_url()
     * there resolved to http://localhost/ and dropped the sub-directory --
     * every og:image on the site was a 404, and would have been wrong on any
     * host the seeder did not run on. Storing the relative path and resolving
     * it against the request that is actually serving the page fixes both.
     *
     * Absolute URLs pointing somewhere else are left alone, so an editor can
     * still paste a CDN or agency-hosted image into the admin field.
     */
    public function asset_url($value) {
        $value = trim((string) $value);
        if ($value === '') {
            return '';
        }
        // Keep a genuinely external URL as it is.
        if (preg_match('~^https?://~i', $value) && strpos($value, 'uploads/') === false) {
            return $value;
        }
        // Anything else is ours: keep from "uploads/" onwards and re-root it.
        if (($at = strpos($value, 'uploads/')) !== false) {
            $value = substr($value, $at);
        }
        return $this->base_url() . '/' . ltrim($value, '/');
    }

    /**
     * The ha_media row behind an image, by its stored path.
     * Returns null for an image the library does not know, which is what makes
     * the ImageObject schema and the image sitemap safe: they describe only
     * files whose author and licence were recorded at download.
     */
    public function media_for($value) {
        $value = (string) $value;
        if (($at = strpos($value, 'uploads/')) !== false) {
            $value = substr($value, $at);
        }
        $value = ltrim($value, '/');
        if ($value === '') {
            return null;
        }
        if (!isset($this->media_cache[$value])) {
            $row = $this->db->get_where('ha_media', array('file_path' => $value))->row_array();
            $this->media_cache[$value] = $row ?: null;
        }
        return $this->media_cache[$value];
    }

    /** @var array path => ha_media row|null */
    protected $media_cache = array();

    public function is_rtl() {
        return $this->locale === 'ar';
    }

    public function brand() {
        return $this->locale === 'ar' ? self::BRAND_AR : self::BRAND_EN;
    }

    /**
     * Resolves the metadata for a page.
     *
     * @param string $locale
     * @param string $path    path without locale prefix, e.g. 'courses/fo-check-in'
     * @param array  $lookup  entity_type + entity_id, or route_key
     * @param array  $fallback title, description, image, schema
     */
    public function prepare($locale, $path, array $lookup = array(), array $fallback = array()) {
        $this->locale = ($locale === 'ar') ? 'ar' : 'en';
        $this->path = ltrim((string) $path, '/');
        $this->breadcrumbs = array();
        $this->extra_schema = array();

        $record = null;
        if (!empty($lookup['entity_type']) && !empty($lookup['entity_id'])) {
            $record = $this->db->get_where('ha_seo_metadata', array(
                'entity_type' => $lookup['entity_type'],
                'entity_id'   => (int) $lookup['entity_id'],
                'locale'      => $this->locale,
            ))->row_array();
        }
        if (!$record && !empty($lookup['route_key'])) {
            $record = $this->db->get_where('ha_seo_metadata', array(
                'entity_type' => 'route',
                'route_key'   => $lookup['route_key'],
                'locale'      => $this->locale,
            ))->row_array();
        }

        $title = $this->pick($record, 'meta_title', isset($fallback['title']) ? $fallback['title'] : $this->brand());
        $description = $this->pick($record, 'meta_description', isset($fallback['description']) ? $fallback['description'] : '');

        $this->meta = array(
            'title'         => $title,
            'description'   => $this->trim_text($description, 300),
            'canonical'     => $this->pick($record, 'canonical_url', $this->url($this->path)),
            'robots'        => $this->pick($record, 'robots', 'index,follow'),
            'og_title'      => $this->pick($record, 'og_title', $title),
            'og_description'=> $this->trim_text($this->pick($record, 'og_description', $description), 300),
            'og_image'      => $this->pick($record, 'og_image', isset($fallback['image']) ? $fallback['image'] : ''),
            'twitter_card'  => $this->pick($record, 'twitter_card', 'summary_large_image'),
            'schema_json'   => $this->pick($record, 'schema_json', isset($fallback['schema']) ? $fallback['schema'] : ''),
            'focus_keyword' => $this->pick($record, 'focus_keyword', ''),
            'alternate_path'=> isset($fallback['alternate_path']) ? $fallback['alternate_path'] : $this->path,

            // og:type per page. A caller that knows it is rendering an article
            // says so; everything else is a website, which is what the whole
            // site used to claim.
            'og_type'                => isset($fallback['og_type']) ? $fallback['og_type'] : 'website',
            'article_published_time' => isset($fallback['published_at']) ? $fallback['published_at'] : '',
            'article_modified_time'  => isset($fallback['updated_at']) ? $fallback['updated_at'] : '',
            'article_author'         => isset($fallback['author']) ? $fallback['author'] : '',
        );

        return $this;
    }

    private function pick($record, $key, $default) {
        if ($record && isset($record[$key]) && $record[$key] !== null && $record[$key] !== '') {
            return $record[$key];
        }
        return $default;
    }

    private function trim_text($text, $limit) {
        $text = trim(preg_replace('/\s+/u', ' ', strip_tags((string) $text)));
        if (mb_strlen($text) <= $limit) {
            return $text;
        }
        $cut = mb_substr($text, 0, $limit);
        $last = mb_strrpos($cut, ' ');
        return rtrim(mb_substr($cut, 0, $last ?: $limit), ' ,.;:') . '.';
    }

    public function set($key, $value) {
        $this->meta[$key] = $value;
        return $this;
    }

    public function get($key, $default = '') {
        return isset($this->meta[$key]) ? $this->meta[$key] : $default;
    }

    /**
     * Sets the path the other language version of this page lives at.
     * Slugs are translated, so a caller usually passes both:
     * array('en' => 'courses/guest-check-in', 'ar' => 'courses/<arabic slug>').
     */
    public function set_alternate($path) {
        if (is_array($path)) {
            $clean = array();
            foreach ($path as $locale => $value) {
                $clean[$locale] = ltrim((string) $value, '/');
            }
            $this->meta['alternate_path'] = $clean;
        } else {
            $this->meta['alternate_path'] = ltrim((string) $path, '/');
        }
        return $this;
    }

    // ------------------------------------------------------------ breadcrumbs

    public function breadcrumb($label, $path = null) {
        $this->breadcrumbs[] = array('label' => $label, 'path' => $path);
        return $this;
    }

    public function breadcrumbs() {
        $home = $this->locale === 'ar' ? 'الرئيسية' : 'Home';
        return array_merge(array(array('label' => $home, 'path' => '')), $this->breadcrumbs);
    }

    public function add_schema(array $graph) {
        $this->extra_schema[] = $graph;
        return $this;
    }

    // --------------------------------------------------------------- rendering

    /** The complete head block for a public page. */
    public function render_head() {
        $m = $this->meta;
        $out = array();
        $out[] = '<title>' . html_escape($m['title']) . '</title>';
        if ($m['description'] !== '') {
            $out[] = '<meta name="description" content="' . html_escape($m['description']) . '">';
        }
        $out[] = '<meta name="robots" content="' . html_escape($m['robots']) . '">';
        $out[] = '<link rel="canonical" href="' . html_escape($m['canonical']) . '">';

        // hreflang pair plus x-default, which is what plan section 28 requires.
        //
        // The regional variants are listed alongside, not instead: the site is
        // written for Saudi Arabia, so en-SA and ar-SA are the precise match,
        // while bare en and ar keep an Arabic reader in the UAE or an English
        // reader anywhere else on the right language rather than falling
        // through to x-default.
        $alt = $m['alternate_path'];
        $en = $this->url($this->path_for('en', $alt), 'en');
        $ar = $this->url($this->path_for('ar', $alt), 'ar');
        foreach (array('en' => $en, 'en-SA' => $en, 'ar' => $ar, 'ar-SA' => $ar, 'x-default' => $en) as $lang => $href) {
            $out[] = '<link rel="alternate" hreflang="' . $lang . '" href="' . html_escape($href) . '">';
        }

        $out[] = '<meta name="theme-color" content="#0D1B2A">';

        // og:type is per page. It was hardcoded to "website", which told every
        // crawler that an article was a site home page.
        $out[] = '<meta property="og:type" content="' . html_escape($m['og_type']) . '">';
        $out[] = '<meta property="og:site_name" content="' . html_escape($this->brand()) . '">';
        $out[] = '<meta property="og:locale" content="' . ($this->locale === 'ar' ? 'ar_SA' : 'en_US') . '">';
        $out[] = '<meta property="og:locale:alternate" content="' . ($this->locale === 'ar' ? 'en_US' : 'ar_SA') . '">';
        $out[] = '<meta property="og:title" content="' . html_escape($m['og_title']) . '">';
        if ($m['og_description'] !== '') {
            $out[] = '<meta property="og:description" content="' . html_escape($m['og_description']) . '">';
        }
        $out[] = '<meta property="og:url" content="' . html_escape($m['canonical']) . '">';

        $image = $this->asset_url($m['og_image']);
        if ($image !== '') {
            $out[] = '<meta property="og:image" content="' . html_escape($image) . '">';
            // Dimensions let a platform reserve the space before the file
            // arrives; alt text is what a screen reader and an AI assistant
            // read when describing a shared link. Both come from ha_media,
            // so neither is guessed.
            $media = $this->media_for($m['og_image']);
            if ($media) {
                if (!empty($media['width'])) {
                    $out[] = '<meta property="og:image:width" content="' . (int) $media['width'] . '">';
                }
                if (!empty($media['height'])) {
                    $out[] = '<meta property="og:image:height" content="' . (int) $media['height'] . '">';
                }
                $alt = $this->locale === 'ar' ? $media['alt_ar'] : $media['alt_en'];
                if ($alt) {
                    $out[] = '<meta property="og:image:alt" content="' . html_escape($alt) . '">';
                }
            }
        }

        // Article signals. A crawler uses these to date the piece and to
        // attribute it; without them an article is undated content.
        if ($m['og_type'] === 'article') {
            foreach (array('published_time', 'modified_time') as $k) {
                if (!empty($m['article_' . $k])) {
                    $out[] = '<meta property="article:' . $k . '" content="'
                        . html_escape(date('c', strtotime($m['article_' . $k]))) . '">';
                }
            }
            if (!empty($m['article_author'])) {
                $out[] = '<meta property="article:author" content="' . html_escape($m['article_author']) . '">';
            }
        }

        $out[] = '<meta name="twitter:card" content="' . html_escape($m['twitter_card']) . '">';
        $out[] = '<meta name="twitter:title" content="' . html_escape($m['og_title']) . '">';
        if ($m['og_description'] !== '') {
            $out[] = '<meta name="twitter:description" content="' . html_escape($m['og_description']) . '">';
        }
        if ($image !== '') {
            $out[] = '<meta name="twitter:image" content="' . html_escape($image) . '">';
            $media = $this->media_for($m['og_image']);
            $alt = $media ? ($this->locale === 'ar' ? $media['alt_ar'] : $media['alt_en']) : '';
            if ($alt) {
                $out[] = '<meta name="twitter:image:alt" content="' . html_escape($alt) . '">';
            }
        }

        $out[] = $this->render_schema();
        return implode("\n    ", array_filter($out));
    }

    /**
     * The alternate path may differ per locale because slugs are translated.
     * A caller sets the counterpart explicitly; otherwise the same path is used.
     */
    private function path_for($locale, $alternate) {
        if (is_array($alternate)) {
            return isset($alternate[$locale]) ? $alternate[$locale] : $this->path;
        }
        return $alternate;
    }

    public function render_schema() {
        $graphs = array();

        if (!empty($this->meta['schema_json'])) {
            $decoded = json_decode($this->meta['schema_json'], true);
            if (is_array($decoded)) {
                $graphs[] = $decoded;
            }
        }

        if ($this->breadcrumbs) {
            $graphs[] = $this->breadcrumb_schema();
        }

        foreach ($this->extra_schema as $extra) {
            $graphs[] = $extra;
        }

        if (!$graphs) {
            return '';
        }

        $out = array();
        foreach ($graphs as $graph) {
            if (!isset($graph['@context'])) {
                $graph = array_merge(array('@context' => 'https://schema.org'), $graph);
            }
            $out[] = '<script type="application/ld+json">'
                . json_encode($graph, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)
                . '</script>';
        }
        return implode("\n    ", $out);
    }

    private function breadcrumb_schema() {
        $items = array();
        foreach ($this->breadcrumbs() as $i => $crumb) {
            $items[] = array(
                '@type'    => 'ListItem',
                'position' => $i + 1,
                'name'     => $crumb['label'],
                'item'     => $this->url($crumb['path'] === null ? $this->path : $crumb['path']),
            );
        }
        return array('@type' => 'BreadcrumbList', 'itemListElement' => $items);
    }

    /** Course schema, built from a catalog course row. */
    public function course_schema(array $course) {
        $schema = array(
            '@type'       => 'Course',
            'name'        => $course['title'],
            'description' => $this->trim_text(isset($course['short_description']) ? $course['short_description'] : '', 300),
            'inLanguage'  => $this->locale,
            'url'         => $this->url('courses/' . $course['slug']),
            'provider'    => array(
                '@type' => 'EducationalOrganization',
                'name'  => $this->brand(),
                'url'   => $this->base_url(),
            ),
        );
        if (!empty($course['duration_minutes'])) {
            $schema['timeRequired'] = 'PT' . (int) $course['duration_minutes'] . 'M';
        }
        if (isset($course['is_free']) && (int) $course['is_free'] === 1) {
            $schema['offers'] = array(
                '@type'        => 'Offer',
                'price'        => '0',
                'priceCurrency'=> isset($course['currency']) ? $course['currency'] : 'SAR',
                'category'     => 'Free',
            );
        }
        // A course must have at least one instance to be valid structured data.
        $schema['hasCourseInstance'] = array(array(
            '@type'            => 'CourseInstance',
            'courseMode'       => 'online',
            'courseWorkload'   => 'PT' . max(1, (int) round((int) $course['duration_minutes'])) . 'M',
        ));
        return $schema;
    }

    /** FAQ schema from question/answer rows. Only used when there are real FAQs. */
    /**
     * ItemList for a listing page.
     *
     * Tells a crawler that a listing is an ordered set of named things rather
     * than a wall of links, and gives each one its position and its URL. On a
     * paginated listing the positions continue across pages, because position
     * 1 on page 3 is not the first item in the list.
     *
     * Items are passed as array('name' => ..., 'path' => ...). Anything
     * without both is skipped rather than emitted half-formed.
     */
    public function item_list_schema(array $items, $offset = 0, $total = null) {
        $elements = array();
        foreach ($items as $i => $item) {
            if (empty($item['name']) || empty($item['path'])) {
                continue;
            }
            $elements[] = array(
                '@type'    => 'ListItem',
                'position' => $offset + count($elements) + 1,
                'name'     => $item['name'],
                'url'      => $this->url($item['path']),
            );
        }
        if (!$elements) {
            return array();
        }
        $graph = array(
            '@type'           => 'ItemList',
            'itemListOrder'   => 'https://schema.org/ItemListOrderAscending',
            'numberOfItems'   => count($elements),
            'itemListElement' => $elements,
        );
        if ($total !== null) {
            $graph['numberOfItems'] = (int) $total;
        }
        return $graph;
    }

    public function faq_schema(array $faqs) {
        if (!$faqs) {
            return null;
        }
        $entities = array();
        foreach ($faqs as $f) {
            if (empty($f['question']) || empty($f['answer'])) {
                continue;
            }
            $entities[] = array(
                '@type'          => 'Question',
                'name'           => $f['question'],
                'acceptedAnswer' => array('@type' => 'Answer', 'text' => strip_tags($f['answer'])),
            );
        }
        return $entities ? array('@type' => 'FAQPage', 'mainEntity' => $entities) : null;
    }

    public function article_schema(array $article) {
        return array(
            '@type'         => 'Article',
            'headline'      => $article['title'],
            'description'   => $this->trim_text(isset($article['excerpt']) ? $article['excerpt'] : '', 300),
            'inLanguage'    => $this->locale,
            'url'           => $this->url('articles/' . $article['slug']),
            'datePublished' => !empty($article['published_at']) ? date('c', strtotime($article['published_at'])) : null,
            'dateModified'  => !empty($article['updated_at']) ? date('c', strtotime($article['updated_at'])) : null,
            'author'        => array('@type' => 'Organization', 'name' => $this->brand()),
            'publisher'     => array(
                '@type' => 'Organization',
                'name'  => $this->brand(),
                'url'   => $this->base_url(),
            ),
        );
    }

    // ---------------------------------------------------------------- sitemap

    /**
     * Every indexable URL, in both locales, with a last modified date.
     * Plan section 28 requires a language aware sitemap.
     */
    public function sitemap_urls() {
        $urls = array();

        $add = function ($path_en, $path_ar, $lastmod = null, $priority = '0.6', $changefreq = 'weekly')
            use (&$urls) {
            $urls[] = array(
                'en'         => $this->url($path_en, 'en'),
                'ar'         => $this->url($path_ar, 'ar'),
                'lastmod'    => $lastmod ? date('Y-m-d', strtotime($lastmod)) : date('Y-m-d'),
                'priority'   => $priority,
                'changefreq' => $changefreq,
            );
        };

        // Static and system pages
        $pages = $this->db->select('p.code, p.slug_en, p.slug_ar, p.updated_at')
            ->from('ha_page p')->where('p.status', 'published')->get()->result_array();
        foreach ($pages as $p) {
            $add($p['slug_en'], $p['slug_ar'], $p['updated_at'],
                $p['code'] === 'home' ? '1.0' : '0.7', $p['code'] === 'home' ? 'daily' : 'monthly');
        }

        // Listing routes
        foreach (array('courses', 'programs', 'learning-paths', 'hospitality-topics',
                     'sop', 'articles', 'verify') as $route) {
            $add($route, $route, null, '0.8', 'daily');
        }

        // Courses
        $courses = $this->db->select('slug_en, slug_ar, updated_at')->from('ha_course')
            ->where('status', 'published')->get()->result_array();
        foreach ($courses as $c) {
            $add('courses/' . $c['slug_en'], 'courses/' . $c['slug_ar'], $c['updated_at'], '0.8', 'weekly');
        }

        // Programs
        foreach ($this->db->select('slug_en, slug_ar, updated_at')->from('ha_program')
                     ->where('status', 'published')->get()->result_array() as $p) {
            $add('programs/' . $p['slug_en'], 'programs/' . $p['slug_ar'], $p['updated_at'], '0.8', 'weekly');
        }

        // Learning paths
        foreach ($this->db->select('slug_en, slug_ar, updated_at')->from('ha_learning_path')
                     ->where('status', 'published')->get()->result_array() as $p) {
            $add('learning-paths/' . $p['slug_en'], 'learning-paths/' . $p['slug_ar'], $p['updated_at'], '0.7', 'monthly');
        }

        // Topics, including the city pages
        foreach ($this->db->select('slug_en, slug_ar, updated_at, topic_type')->from('ha_topic')
                     ->where('status', 'published')->get()->result_array() as $t) {
            $add('hospitality-topics/' . $t['slug_en'], 'hospitality-topics/' . $t['slug_ar'],
                $t['updated_at'], $t['topic_type'] === 'city' ? '0.7' : '0.8', 'monthly');
        }

        // Public SOP resources
        foreach ($this->db->select('s.slug_en, s.slug_ar, s.updated_at')->from('ha_sop_document s')
                     ->where('s.status', 'published')->where('s.visibility', 'public')
                     ->get()->result_array() as $s) {
            $add('sop/' . $s['slug_en'], 'sop/' . $s['slug_ar'], $s['updated_at'], '0.6', 'monthly');
        }

        // Articles
        foreach ($this->db->select('slug_en, slug_ar, updated_at')->from('ha_article')
                     ->where('status', 'published')
                     ->where('published_at <=', date('Y-m-d H:i:s'))->get()->result_array() as $a) {
            $add('articles/' . $a['slug_en'], 'articles/' . $a['slug_ar'], $a['updated_at'], '0.7', 'monthly');
        }

        return $urls;
    }

    public function render_sitemap() {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
            . 'xmlns:xhtml="http://www.w3.org/1999/xhtml">' . "\n";
        foreach ($this->sitemap_urls() as $u) {
            foreach (array('en', 'ar') as $locale) {
                $xml .= "  <url>\n";
                $xml .= '    <loc>' . html_escape($u[$locale]) . "</loc>\n";
                $xml .= '    <lastmod>' . $u['lastmod'] . "</lastmod>\n";
                $xml .= '    <changefreq>' . $u['changefreq'] . "</changefreq>\n";
                $xml .= '    <priority>' . $u['priority'] . "</priority>\n";
                $xml .= '    <xhtml:link rel="alternate" hreflang="en" href="' . html_escape($u['en']) . "\"/>\n";
                $xml .= '    <xhtml:link rel="alternate" hreflang="ar" href="' . html_escape($u['ar']) . "\"/>\n";
                $xml .= '    <xhtml:link rel="alternate" hreflang="x-default" href="' . html_escape($u['en']) . "\"/>\n";
                $xml .= "  </url>\n";
            }
        }
        $xml .= '</urlset>';
        return $xml;
    }

    /** Paths no crawler may have. Repeated into every group -- see render_robots. */
    protected $robots_disallow = array('/admin', '/academy-admin', '/login', '/api/');

    /**
     * Crawlers named explicitly, beyond the wildcard group.
     *
     * The AI crawlers are allowed deliberately. This site's value to a hotel
     * searching for training is increasingly delivered through an assistant
     * answering on its behalf, and a site that blocks them is absent from that
     * answer. The trade is that the content can be summarised without a visit;
     * the citation and the brand recall are what is bought with it.
     *
     * YandexBot is named because Yandex is a real search channel in this market
     * and reads its own directives.
     */
    protected $robots_agents = array(
        'GPTBot', 'OAI-SearchBot', 'ChatGPT-User',   // OpenAI
        'ClaudeBot', 'Claude-User',                   // Anthropic
        'PerplexityBot', 'Perplexity-User',           // Perplexity
        'Google-Extended',                            // Gemini / AI Overviews
        'Applebot-Extended',                          // Apple Intelligence
        'CCBot',                                      // Common Crawl
        'YandexBot', 'YandexImages',                  // Yandex
    );

    /**
     * A sitemap of the photography, with its licence attached.
     *
     * The library carries the author, licence, licence URL and source page for
     * every file, recorded at download and already shown on /credits because
     * the licence requires it. Emitting <image:license> hands a crawler the
     * same fact in machine-readable form -- which is the difference between an
     * image that can be surfaced with confidence and one that cannot. Very few
     * sites can do this, because very few know where their pictures came from.
     *
     * Only files present in ha_media appear, so nothing is described whose
     * provenance is not recorded.
     */
    public function render_image_sitemap() {
        $rows = $this->db->select('c.slug_en, c.slug_ar, c.thumbnail')->from('ha_course c')
            ->where('c.status', 'published')->where('c.thumbnail IS NOT NULL')
            ->get()->result_array();

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" '
            . 'xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        foreach ($rows as $r) {
            $media = $this->media_for($r['thumbnail']);
            if (!$media) {
                continue;
            }
            foreach (array('en', 'ar') as $locale) {
                $xml .= "  <url>\n";
                $xml .= '    <loc>' . html_escape($this->url('courses/' . $r['slug_' . $locale], $locale)) . "</loc>\n";
                $xml .= "    <image:image>\n";
                $xml .= '      <image:loc>' . html_escape($this->asset_url($media['file_path'])) . "</image:loc>\n";
                $alt = $locale === 'ar' ? $media['alt_ar'] : $media['alt_en'];
                if ($alt) {
                    $xml .= '      <image:title>' . html_escape($alt) . "</image:title>\n";
                }
                if (!empty($media['license_url'])) {
                    $xml .= '      <image:license>' . html_escape($media['license_url']) . "</image:license>\n";
                }
                $xml .= "    </image:image>\n";
                $xml .= "  </url>\n";
            }
        }
        $xml .= '</urlset>';
        return $xml;
    }

    public function render_robots() {
        // robots.txt groups do NOT inherit. A crawler obeys the most specific
        // group that names it and ignores every rule outside it, so the
        // Disallow lines have to be repeated into each one. Appending them
        // once at the end would have left every named agent -- including all
        // six AI crawlers -- with /admin, /login and /api/ wide open.
        $groups = array_merge(array('*'), $this->robots_agents);

        $lines = array();
        foreach ($groups as $agent) {
            $lines[] = 'User-agent: ' . $agent;
            $lines[] = 'Allow: /';
            foreach ($this->robots_disallow as $path) {
                $lines[] = 'Disallow: ' . $path;
            }
            $lines[] = '';
        }

        $lines[] = 'Sitemap: ' . $this->base_url() . '/sitemap.xml';
        $lines[] = 'Sitemap: ' . $this->base_url() . '/image-sitemap.xml';
        return implode("\n", $lines) . "\n";
    }

    // ------------------------------------------------------------------ llms

    /**
     * /llms.txt -- a plain-text map of the site for an assistant deciding what
     * is worth reading and citing. It is not a substitute for the sitemap: the
     * sitemap says what exists, this says what matters and what each part is.
     *
     * Everything here is counted from the catalogue as the file renders, so it
     * cannot drift from the site the way a hand-written summary would.
     */
    public function render_llms($full = false) {
        $count = function ($table, $where = array()) {
            return (int) $this->db->where(array_merge(array('status' => 'published'), $where))
                ->count_all_results($table);
        };
        $courses = $count('ha_course');
        $programs = $count('ha_program');
        $paths = $count('ha_learning_path');
        $sops_public = $count('ha_sop_document', array('visibility' => 'public'));
        $sops_total  = $count('ha_sop_document');
        $articles = $count('ha_article');
        $domains = (int) $this->db->where('status', 'active')->count_all_results('ha_category');
        $base = $this->base_url();

        $out = array();
        $out[] = '# ' . self::BRAND_EN;
        $out[] = '';
        $out[] = '> Hotel training, standard operating procedures and workforce certification '
            . 'for hospitality teams in Saudi Arabia, authored in both Arabic and English.';
        $out[] = '';
        $out[] = 'Every page exists at /en/... and /ar/... . The Arabic is authored, not '
            . 'machine-translated, and Arabic pages use Arabic URLs.';
        $out[] = '';
        $out[] = '## What is here';
        $out[] = '';
        $out[] = sprintf('- %d courses across %d professional domains', $courses, $domains);
        $out[] = sprintf('- %d programmes and %d career paths', $programs, $paths);
        // Stated precisely, because "14 procedures" and "14 readable procedures"
        // are different claims and only one of them is true.
        $out[] = sprintf('- %d standard operating procedures, each versioned with an author, '
            . 'approver and effective date. %s',
            $sops_total,
            $sops_public > 0
                ? sprintf('%d are readable in full without signing in.', $sops_public)
                : 'Their full text is scoped to the organisation that owns them and is not '
                  . 'publicly readable; the SOP pages describe structure and coverage only.');
        $out[] = sprintf('- %d articles', $articles);
        $out[] = '- Public certificate verification at /en/verify/{code}';
        $out[] = '';
        $out[] = '## Main pages';
        $out[] = '';
        foreach (array(
            'courses'            => 'Full course catalogue, filterable by professional domain and level',
            'programs'           => 'Grouped qualifications',
            'learning-paths'     => 'Role-based career progressions',
            'hospitality-topics' => 'Subject guides and Saudi city pages',
            'sop'                => 'Standard operating procedure library',
            'articles'           => 'Written guidance',
            'verify'             => 'Certificate verification',
            'about'              => 'What the academy is and how it is built',
        ) as $path => $what) {
            $out[] = sprintf('- [%s](%s/en/%s): %s', $path, $base, $path, $what);
        }
        $out[] = '';
        $out[] = '## Professional domains';
        $out[] = '';
        $rows = $this->db->select('c.code, COUNT(co.id) AS n')->select('t.name', false)
            ->from('ha_category c')
            ->join('ha_category_translation t', "t.category_id = c.id AND t.locale = 'en'", 'left')
            ->join('ha_course co', "co.category_id = c.id AND co.status = 'published'", 'left')
            ->where('c.status', 'active')
            ->group_by('c.id, c.code, c.sort_order, t.name')
            ->order_by('c.sort_order', 'ASC')->get()->result_array();
        foreach ($rows as $r) {
            $out[] = sprintf('- %s (%d courses): %s/en/courses?category=%s',
                $r['name'], (int) $r['n'], $base, $r['code']);
        }
        $out[] = '';
        $out[] = '## Using this content';
        $out[] = '';
        $out[] = '- Photography is licensed from Wikimedia Commons under licences permitting '
            . 'commercial reuse. Attribution per file is at ' . $base . '/en/credits.';
        $out[] = '- The site publishes no pass rates, employer endorsements or accreditation '
            . 'claims, because none can be evidenced to a visitor. Statistics shown are counted '
            . 'from the published catalogue as the page renders.';
        $out[] = '- Course video, where present, is embedded from third-party channels and '
            . 'credited to them; it is not the academy\'s own production.';

        if ($full) {
            $out[] = '';
            $out[] = '## Course catalogue';
            $out[] = '';
            $list = $this->db->select('c.slug_en')->select('t.title, t.short_description', false)
                ->from('ha_course c')
                ->join('ha_course_translation t', "t.course_id = c.id AND t.locale = 'en'", 'left')
                ->where('c.status', 'published')->order_by('c.code', 'ASC')->get()->result_array();
            foreach ($list as $c) {
                $out[] = sprintf('### %s', $c['title']);
                $out[] = $base . '/en/courses/' . $c['slug_en'];
                if (!empty($c['short_description'])) {
                    $out[] = '';
                    $out[] = $this->trim_text($c['short_description'], 400);
                }
                $out[] = '';
            }
        }

        return implode("\n", $out) . "\n";
    }

    // -------------------------------------------------------------- redirects

    /**
     * Looks up a managed redirect for a path and records the hit.
     * @return array|null target_path and status_code
     */
    public function redirect_for($path) {
        $path = '/' . ltrim((string) $path, '/');
        $row = $this->db->get_where('ha_redirect', array(
            'source_path' => $path,
            'is_active'   => 1,
        ))->row_array();
        if (!$row) {
            return null;
        }
        $this->db->set('hit_count', 'hit_count + 1', false)
            ->set('last_hit_at', date('Y-m-d H:i:s'))
            ->where('id', $row['id'])->update('ha_redirect');
        return array('target' => $row['target_path'], 'status' => (int) $row['status_code']);
    }
}
