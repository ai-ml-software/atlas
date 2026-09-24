<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Photography pipeline for the public academy site.
 *
 *   php index.php ha_images fetch     download and record the photo library
 *   php index.php ha_images assign    attach photos to courses, topics, etc
 *   php index.php ha_images report     coverage and licence summary
 *   php index.php ha_images build      fetch then assign
 *
 * Images come from Wikimedia Commons, which publishes a real licence for every
 * file. Only licences that permit commercial reuse are accepted, the licence
 * and author are stored beside the file, and anything requiring credit is
 * flagged so the public credits page can carry it. Search results that do not
 * declare a usable licence are skipped rather than used and hoped about.
 *
 * Files are written into uploads/academy/ at two sizes, so a listing card does
 * not download a hero-sized photograph.
 */
class Ha_images extends CI_Controller {

    const DIR = 'uploads/academy/';
    const WIDE = 1600;
    const CARD = 800;

    /** Licences that allow commercial use. Anything else is skipped. */
    private $allowed_licences = array(
        'cc0', 'cc-zero', 'public domain', 'pd', 'pd-old', 'pd-us', 'pd-self',
        'cc by 1.0', 'cc by 2.0', 'cc by 2.5', 'cc by 3.0', 'cc by 4.0',
        'cc by-sa 1.0', 'cc by-sa 2.0', 'cc by-sa 2.5', 'cc by-sa 3.0', 'cc by-sa 4.0',
        'attribution', 'fal',
    );

    /** Licences that oblige us to name the author on the page. */
    private $credit_required = array('cc by', 'cc by-sa', 'attribution', 'fal');

    private $fetched = 0;
    private $skipped = 0;

    public function __construct() {
        parent::__construct();
        if (!is_cli()) {
            show_404();
        }
        @set_time_limit(0);
        ini_set('memory_limit', '512M');
        $this->load->database();
    }

    private function out($line = '') {
        fwrite(STDOUT, $line . PHP_EOL);
    }

    /**
     * The subjects the site needs a photograph of, and the Commons search that
     * finds it. Keys match category codes, city names, article slugs and page
     * codes so assignment is a lookup rather than a guess.
     */
    public static function subjects() {
        return array(
            // subject => [categories], [keywords the file must match], [search fallbacks]
            'front-office' => array(
                'cat' => array('Hotel lobbies', 'Hotel reception desks', 'Reception desks'),
                'must' => array('lobby', 'reception', 'desk', 'foyer'),
                'search' => array('hotel reception desk', 'hotel lobby'),
            ),
            'housekeeping' => array(
                'cat' => array('Hotel rooms', 'Beds in hotels', 'Housekeeping'),
                'must' => array('hotel room', 'bed', 'housekeeping', 'linen', 'suite'),
                'search' => array('hotel room bed', 'housekeeping cart'),
            ),
            'food-and-beverage' => array(
                'cat' => array('Restaurant interiors', 'Interiors of restaurants', 'Restaurant tables'),
                'must' => array('restaurant', 'dining', 'table', 'bistro'),
                'search' => array('restaurant interior table', 'dining room restaurant'),
            ),
            'kitchen' => array(
                'cat' => array('Commercial kitchens', 'Restaurant kitchens', 'Chefs at work'),
                'must' => array('kitchen', 'chef', 'cook'),
                'search' => array('commercial kitchen', 'restaurant kitchen'),
            ),
            'engineering' => array(
                'cat' => array('Maintenance workers', 'Plumbers at work', 'Electricians at work',
                               'Air conditioning units', 'Mechanical rooms'),
                'must' => array('worker', 'technician', 'plumber', 'electrician', 'maintenance',
                                'air conditioning', 'pipe', 'boiler', 'mechanical room'),
                'search' => array('maintenance technician working', 'air conditioning unit building'),
            ),
            'management' => array(
                'cat' => array('Business meetings', 'Conference rooms', 'Meeting rooms'),
                'must' => array('meeting', 'conference', 'boardroom'),
                'search' => array('business meeting room', 'conference room table'),
            ),
            'digital-hospitality' => array(
                'cat' => array('Computer workstations', 'Desktop computers', 'Office workplaces'),
                'must' => array('computer', 'workstation', 'desk', 'monitor', 'laptop', 'office'),
                'search' => array('computer workstation desk', 'office desk computer'),
            ),
            'guest-experience' => array(
                'cat' => array('Hotel lounges', 'Hotel interiors', 'Lounges'),
                'must' => array('lounge', 'hotel', 'lobby', 'seating'),
                'search' => array('hotel lounge interior', 'hotel lobby seating'),
            ),
            'safety-compliance' => array(
                'cat' => array('Fire extinguishers', 'Fire safety equipment', 'Emergency exit signs'),
                'must' => array('extinguisher', 'fire', 'emergency', 'exit', 'alarm'),
                'search' => array('fire extinguisher wall', 'emergency exit sign'),
            ),

            'city-riyadh' => array(
                'cat' => array('Kingdom Centre', 'Al Faisaliah Tower', 'Skyscrapers in Riyadh',
                               'Streets in Riyadh'),
                'must' => array('riyadh', 'kingdom centre', 'faisaliah'),
                'search' => array('Kingdom Centre Riyadh tower', 'Riyadh skyline night'),
            ),
            'city-jeddah' => array(
                'cat' => array('Jeddah Corniche', 'Skyscrapers in Jeddah', 'Streets in Jeddah',
                               'Al-Balad, Jeddah'),
                'must' => array('jeddah', 'jiddah', 'corniche', 'balad'),
                'search' => array('Jeddah corniche waterfront', 'Jeddah city skyline'),
            ),
            'city-makkah' => array(
                'cat' => array('Abraj Al Bait', 'Hotels in Mecca', 'Buildings in Mecca',
                               'Mecca in the 21st century'),
                'must' => array('mecca', 'makkah', 'abraj'),
                'search' => array('Abraj Al Bait Mecca', 'Mecca hotel tower', 'Makkah clock tower'),
            ),
            'city-madinah' => array(
                'cat' => array('Medina', 'Buildings in Medina', 'Views of Medina'),
                'must' => array('medina', 'madinah', 'madina'),
                'search' => array('Medina city view', 'Madinah buildings'),
            ),
            'city-al-khobar' => array(
                'cat' => array('Khobar', 'Al Khobar', 'Buildings in Khobar'),
                'must' => array('khobar'),
                'search' => array('Khobar corniche', 'Khobar city'),
            ),
            'city-dammam' => array(
                'cat' => array('Buildings in Dammam', 'Streets in Dammam', 'Dammam'),
                'must' => array('dammam', 'corniche', 'street', 'building', 'tower', 'skyline'),
                'search' => array('Dammam corniche waterfront', 'Dammam city buildings'),
            ),
            'city-alula' => array(
                'cat' => array('Al-`Ula', 'AlUla', 'Hegra (Mada in Salih)', 'Dadan'),
                'must' => array('ula', 'ala', 'hegra', 'dadan', 'madain'),
                'search' => array('AlUla Saudi Arabia', 'Hegra Saudi Arabia'),
            ),
            'city-abha' => array(
                'cat' => array('Abha', 'Asir Region', 'Buildings in Abha'),
                'must' => array('abha', 'asir', 'aseer'),
                'search' => array('Abha city Saudi Arabia', 'Asir mountains'),
            ),

            'topic-sop' => array(
                'cat' => array('Checklists', 'Clipboards', 'Documents'),
                'must' => array('checklist', 'clipboard', 'document', 'form', 'paper'),
                'search' => array('clipboard checklist paper', 'printed document form'),
            ),
            'topic-compliance' => array(
                'cat' => array('Cleaning', 'Commercial cleaning', 'Cleaning equipment'),
                'must' => array('cleaning', 'cleaner', 'mop', 'disinfect', 'sanitis', 'sanitiz', 'hygiene'),
                'search' => array('commercial cleaning worker', 'cleaning kitchen surface'),
            ),
            'page-home' => array(
                'cat' => array('Hotel lobbies', 'Hotel atriums', 'Hotel interiors'),
                'must' => array('lobby', 'atrium', 'hotel'),
                'search' => array('hotel lobby atrium', 'luxury hotel interior'),
            ),
            'page-about' => array(
                'cat' => array('Classrooms', 'Training courses', 'Seminars'),
                'must' => array('classroom', 'training', 'seminar', 'workshop', 'lecture', 'course'),
                'search' => array('training classroom people', 'seminar room training'),
            ),
            'page-hotels' => array(
                'cat' => array('Hotel buildings', 'Hotels', 'Hotel facades'),
                'must' => array('hotel'),
                'search' => array('hotel building exterior', 'hotel facade'),
            ),
            'page-contact' => array(
                'cat' => array('Hotel reception desks', 'Reception desks', 'Concierges'),
                'must' => array('reception desk', 'concierge', 'front desk', 'hotel'),
                'search' => array('hotel concierge desk', 'hotel reception counter interior'),
            ),

            'article-frontdesk' => array(
                'cat' => array('Hotel lobbies', 'Hotel reception desks', 'Lobbies'),
                'must' => array('lobby', 'reception', 'desk', 'counter', 'foyer', 'hotel'),
                'search' => array('hotel reception area interior', 'hotel lobby desk'),
            ),
            'article-sop' => array(
                'cat' => array('Writing', 'Notebooks', 'Handwriting'),
                'must' => array('writing', 'notebook', 'pen', 'handwriting', 'notes'),
                'search' => array('writing notebook pen', 'handwriting notes desk'),
            ),
            'article-foodsafety' => array(
                'cat' => array('Food thermometers', 'Thermometers', 'Food safety'),
                'must' => array('thermometer', 'temperature', 'food safety'),
                'search' => array('food thermometer cooking', 'kitchen thermometer'),
            ),
            // Commons stores nearly every chart as SVG, which GD cannot read,
            // so this article is illustrated with the act of reviewing figures.
            'article-reporting' => array(
                'cat' => array('Office work', 'Paperwork', 'Desks'),
                'must' => array('office', 'desk', 'paperwork', 'documents', 'working', 'report'),
                'search' => array('office desk paperwork documents', 'reviewing documents desk'),
            ),
            'article-bilingual' => array(
                'cat' => array('Multilingual signs', 'Road signs in Saudi Arabia',
                               'Arabic script on signs', 'Signs in Saudi Arabia'),
                'must' => array('bilingual', 'arabic', 'multilingual', 'sign', 'signage'),
                'search' => array('bilingual sign arabic english', 'road sign Saudi Arabia arabic'),
            ),
            'article-preopening' => array(
                'cat' => array('Construction sites', 'Buildings under construction',
                               'Scaffolding', 'Renovation'),
                'must' => array('construction', 'renovation', 'interior', 'scaffold', 'building site'),
                'search' => array('building under construction interior', 'renovation scaffolding interior'),
            ),
        );
    }

    // ------------------------------------------------------------------ fetch

    /** How many alternate photographs each course category keeps. */
    const VARIANTS = 3;

    /**
     * Variant subjects, so courses inside one category do not all show the
     * same photograph. They reuse the category's own search definition and are
     * stored as "<category>-2", "<category>-3" and so on.
     */
    public static function variant_subjects() {
        $out = array();
        $categories = array('front-office', 'housekeeping', 'food-and-beverage', 'kitchen',
            'engineering', 'management', 'digital-hospitality', 'guest-experience',
            'safety-compliance');
        $all = self::subjects();
        foreach ($categories as $code) {
            if (!isset($all[$code])) {
                continue;
            }
            for ($n = 2; $n <= self::VARIANTS; $n++) {
                $out[$code . '-' . $n] = $all[$code];
            }
        }
        return $out;
    }

    public function fetch_variants() {
        $dir = FCPATH . self::DIR;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        $this->out('Fetching category variants');
        $this->out(str_repeat('-', 72));
        foreach (self::variant_subjects() as $subject => $spec) {
            if ($this->db->where('subject', $subject)->count_all_results('ha_media') > 0) {
                $this->out(str_pad($subject, 24) . ' already held');
                continue;
            }
            $this->fetch_subject($subject, $spec);
        }
        $this->out(str_repeat('-', 72));
        $this->out('downloaded: ' . $this->fetched . '   skipped: ' . $this->skipped);
    }

    public function fetch($only = null) {
        $dir = FCPATH . self::DIR;
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        $this->out('Fetching academy photography from Wikimedia Commons');
        $this->out(str_repeat('-', 72));

        foreach (self::subjects() as $subject => $spec) {
            if ($only !== null && strpos($subject, $only) === false) {
                continue;
            }
            $existing = $this->db->where('subject', $subject)->count_all_results('ha_media');
            if ($existing > 0) {
                $this->out(str_pad($subject, 24) . ' already held');
                continue;
            }
            $this->fetch_subject($subject, $spec);
        }

        $this->out(str_repeat('-', 72));
        $this->out('downloaded: ' . $this->fetched . '   skipped: ' . $this->skipped);
    }

    /**
     * Categories first, free-text search only as a fallback, and every
     * candidate must mention one of the subject's keywords in its file name or
     * description. Without that gate Commons relevance ranking happily returns
     * a fort in Bahrain for "Riyadh".
     */
    private function fetch_subject($subject, array $spec) {
        $must = isset($spec['must']) ? $spec['must'] : array();

        // Membership of a Commons category is itself evidence that a file is
        // about the subject, because editors curate those by hand. The keyword
        // gate is only needed for free-text search, where ranking is by
        // relevance score and a fort in Bahrain can outrank Riyadh.
        foreach (isset($spec['cat']) ? $spec['cat'] : array() as $category) {
            $candidates = $this->category_members($category, 60);
            if (!$candidates) {
                continue;
            }
            if ($this->try_candidates($subject, $candidates, $must, 'category:' . $category, false)) {
                return true;
            }
        }

        foreach (isset($spec['search']) ? $spec['search'] : array() as $query) {
            $candidates = $this->search_commons($query, 24);
            if ($this->try_candidates($subject, $candidates, $must, 'search:' . $query, true)) {
                return true;
            }
        }

        $this->out(str_pad($subject, 24) . ' NOT FOUND');
        return false;
    }

    private function try_candidates($subject, array $candidates, array $must, $origin, $require_keyword = true) {
        $scored = array();
        foreach ($candidates as $c) {
            if (!$this->looks_photographic($c)) {
                $this->skipped++;
                continue;
            }
            $score = $this->relevance($c, $must);
            if ($require_keyword && $score <= 0) {
                continue;
            }
            $c['score'] = $score + $this->shape_bonus($c);
            $scored[] = $c;
        }
        usort($scored, function ($a, $b) {
            return $b['score'] <=> $a['score'];
        });

        foreach ($scored as $c) {
            if (!$this->licence_allowed(strtolower(trim($c['license'])))) {
                $this->skipped++;
                continue;
            }
            if ($c['width'] < 900 || $c['height'] < 600) {
                $this->skipped++;
                continue;
            }
            if ($this->store($subject, $c)) {
                $this->out(str_pad($subject, 24) . ' ok  '
                    . str_pad($c['license'], 16)
                    . str_pad(mb_substr($c['author'], 0, 26), 28)
                    . $origin);
                $this->fetched++;
                return true;
            }
        }
        return false;
    }

    /**
     * Words that mark a file as a drawing, map, diagram or archival plate
     * rather than a photograph of the subject.
     */
    private $not_photographic = array(
        'drawing', 'diagram', 'schematic', 'blueprint', 'plan of', 'floor plan',
        'map of', ' map', 'lithograph', 'engraving', 'etching', 'illustration',
        'aerial', 'satellite', 'chart of', 'logo', 'icon', 'noun project',
        'coat of arms', 'painting', 'poster', 'stamp', 'banknote', 'seal of',
        'portrait of', 'sketch', 'cross section', 'elevation of',
        // Off brand for a hospitality academy: armed forces imagery, and
        // anything featuring children, which does not belong on a page about
        // training hotel employees.
        'navy', 'army', 'air force', 'marine corps', 'soldier', 'sailor',
        'military', 'troops', 'regiment', 'squadron', 'uss ', 'recruit',
        'child', 'baby', 'toddler', 'infant', 'kindergarten',
    );

    private function looks_photographic(array $c) {
        $haystack = strtolower($c['title'] . ' ' . $c['description']);
        foreach ($this->not_photographic as $bad) {
            if (strpos($haystack, $bad) !== false) {
                return false;
            }
        }
        return true;
    }

    /** A candidate must actually be about the subject, by name or description. */
    private function relevance(array $c, array $must) {
        if (!$must) {
            return 1;
        }
        $haystack = strtolower($c['title'] . ' ' . $c['description']);
        $score = 0;
        foreach ($must as $keyword) {
            if (strpos($haystack, strtolower($keyword)) !== false) {
                $score += 2;
            }
        }
        return $score;
    }

    /** Landscape photographs suit a hero and a card; portraits crop badly. */
    private function shape_bonus(array $c) {
        if ($c['height'] < 1) {
            return 0;
        }
        $ratio = $c['width'] / $c['height'];
        if ($ratio >= 1.3 && $ratio <= 2.2) {
            return 3;
        }
        if ($ratio > 1.0) {
            return 1;
        }
        return 0;
    }

    /** Files held in a Commons category, which editors curate by subject. */
    private function category_members($category, $limit = 50) {
        $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query(array(
            'action'      => 'query',
            'generator'   => 'categorymembers',
            'gcmtitle'    => 'Category:' . $category,
            'gcmtype'     => 'file',
            'gcmlimit'    => $limit,
            'prop'        => 'imageinfo',
            'iiprop'      => 'url|size|extmetadata|mime',
            'iiurlwidth'  => self::WIDE,
            'format'      => 'json',
        ));
        return $this->parse_image_response($this->http_get($url));
    }

    private function licence_allowed($licence) {
        if ($licence === '' || $licence === '?') {
            return false;
        }
        foreach ($this->allowed_licences as $ok) {
            if (strpos($licence, $ok) !== false) {
                return true;
            }
        }
        return false;
    }

    private function needs_credit($licence) {
        $licence = strtolower($licence);
        if (strpos($licence, 'cc0') !== false || strpos($licence, 'public domain') !== false) {
            return false;
        }
        foreach ($this->credit_required as $c) {
            if (strpos($licence, $c) !== false) {
                return true;
            }
        }
        return false;
    }

    /** @return array of candidate images with licence metadata */
    private function search_commons($query, $limit = 10) {
        $url = 'https://commons.wikimedia.org/w/api.php?' . http_build_query(array(
            'action'       => 'query',
            'generator'    => 'search',
            'gsrsearch'    => $query,
            'gsrlimit'     => $limit,
            'gsrnamespace' => 6,
            'prop'         => 'imageinfo',
            'iiprop'       => 'url|size|extmetadata|mime',
            'iiurlwidth'   => self::WIDE,
            'format'       => 'json',
        ));

        return $this->parse_image_response($this->http_get($url));
    }

    private function parse_image_response($json) {
        if (!$json) {
            return array();
        }
        $data = json_decode($json, true);
        if (!isset($data['query']['pages'])) {
            return array();
        }

        $out = array();
        foreach ($data['query']['pages'] as $page) {
            if (empty($page['imageinfo'][0])) {
                continue;
            }
            $ii = $page['imageinfo'][0];
            if (!isset($ii['mime']) || strpos($ii['mime'], 'image/') !== 0) {
                continue;
            }
            if (in_array($ii['mime'], array('image/svg+xml', 'image/tiff', 'image/gif'), true)) {
                continue;
            }
            $meta = isset($ii['extmetadata']) ? $ii['extmetadata'] : array();
            $out[] = array(
                'title'       => $page['title'],
                'url'         => isset($ii['thumburl']) ? $ii['thumburl'] : $ii['url'],
                'descurl'     => $ii['descriptionurl'],
                'width'       => (int) $ii['width'],
                'height'      => (int) $ii['height'],
                'mime'        => $ii['mime'],
                'license'     => $this->meta($meta, 'LicenseShortName'),
                'license_url' => $this->meta($meta, 'LicenseUrl'),
                'author'      => $this->clean_author($this->meta($meta, 'Artist')),
                'description' => $this->clean_author($this->meta($meta, 'ImageDescription')),
            );
        }
        return $out;
    }

    private function meta($meta, $key) {
        return isset($meta[$key]['value']) ? trim($meta[$key]['value']) : '';
    }

    /** Commons returns HTML in the author and description fields. */
    private function clean_author($html) {
        $text = html_entity_decode(strip_tags((string) $html), ENT_QUOTES, 'UTF-8');
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        return mb_substr($text, 0, 240);
    }

    private function http_get($url, $binary = false) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 45,
            CURLOPT_USERAGENT      => 'HospitalityAcademy/1.0 (training platform; local build)',
        ));
        $body = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        return ($status === 200 && $body !== false) ? $body : null;
    }

    /** Downloads, resizes to two widths, and records the file with its licence. */
    private function store($subject, array $c) {
        $bytes = $this->http_get($c['url'], true);
        if (!$bytes || strlen($bytes) < 8000) {
            return false;
        }

        // The same photograph under two subjects reads as a stock-image site.
        $checksum = sha1($bytes);
        if ($this->db->where('checksum', $checksum)->count_all_results('ha_media') > 0) {
            return false;
        }

        $source = @imagecreatefromstring($bytes);
        if (!$source) {
            return false;
        }

        $base = $subject;
        $wide = self::DIR . $base . '.webp';
        $card = self::DIR . $base . '-card.webp';

        $ok = $this->write_resized($source, FCPATH . $wide, self::WIDE)
            && $this->write_resized($source, FCPATH . $card, self::CARD);
        imagedestroy($source);

        if (!$ok) {
            return false;
        }

        $size = @getimagesize(FCPATH . $wide);
        $this->db->insert('ha_media', array(
            'disk'            => 'public',
            'file_path'       => $wide,
            'original_name'   => str_replace('File:', '', $c['title']),
            'mime_type'       => 'image/webp',
            'extension'       => 'webp',
            'file_size'       => filesize(FCPATH . $wide),
            'width'           => $size ? $size[0] : null,
            'height'          => $size ? $size[1] : null,
            'alt_en'          => $this->alt_text($subject, 'en'),
            'alt_ar'          => $this->alt_text($subject, 'ar'),
            'subject'         => $subject,
            'source'          => 'wikimedia_commons',
            'source_page'     => $c['descurl'],
            'source_file'     => $c['url'],
            'author'          => $c['author'] ?: 'Unknown',
            'license'         => $c['license'],
            'license_url'     => $c['license_url'],
            'credit_required' => $this->needs_credit($c['license']) ? 1 : 0,
            'checksum'        => $checksum,
            'created_at'      => date('Y-m-d H:i:s'),
        ));
        return true;
    }

    private function write_resized($source, $path, $target_width) {
        $w = imagesx($source);
        $h = imagesy($source);
        if ($w < 1 || $h < 1) {
            return false;
        }
        $scale = min(1, $target_width / $w);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $canvas = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($canvas, $source, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = imagewebp($canvas, $path, 82);
        imagedestroy($canvas);
        return $ok;
    }

    /**
     * Alt text describes what the photograph shows, which is what a screen
     * reader user needs. It is written per subject rather than reusing a title.
     */
    private function alt_text($subject, $locale) {
        // "kitchen-2" describes the same thing as "kitchen".
        $subject = preg_replace('/-\d+$/', '', $subject);
        $map = array(
            'front-office'        => array('A hotel reception desk in a lobby', 'مكتب استقبال في بهو فندق'),
            'housekeeping'        => array('A hotel room being cleaned and prepared', 'غرفة فندقية أثناء التنظيف والتجهيز'),
            'food-and-beverage'   => array('A restaurant table laid for service', 'طاولة مطعم مجهزة للخدمة'),
            'kitchen'             => array('A professional kitchen during preparation', 'مطبخ احترافي أثناء التحضير'),
            'engineering'         => array('Building maintenance equipment', 'معدات صيانة المباني'),
            'management'          => array('A hotel management team meeting', 'اجتماع فريق إدارة الفندق'),
            'digital-hospitality' => array('A workstation running hotel systems', 'محطة عمل تشغّل أنظمة الفندق'),
            'guest-experience'    => array('Guests in a hotel lounge', 'ضيوف في صالة الفندق'),
            'safety-compliance'   => array('Fire safety equipment in a building', 'معدات السلامة من الحريق في مبنى'),
            'topic-sop'           => array('A printed checklist on a clipboard', 'قائمة تحقق مطبوعة على حافظة'),
            'topic-certification' => array('A printed certificate', 'شهادة مطبوعة'),
            'topic-compliance'    => array('A safety inspection being recorded', 'تسجيل نتائج تفتيش السلامة'),
            'page-home'           => array('A hotel lobby interior', 'التصميم الداخلي لبهو فندق'),
            'page-about'          => array('A training session in progress', 'جلسة تدريبية قائمة'),
            'page-hotels'         => array('The exterior of a hotel building', 'واجهة مبنى فندقي'),
            'page-contact'        => array('A hotel concierge desk', 'مكتب الكونسيرج في فندق'),
            'page-certificates'   => array('A certificate presented at a ceremony', 'شهادة تُقدَّم في حفل'),
            'article-frontdesk'   => array('A receptionist welcoming a guest', 'موظف استقبال يرحب بضيف'),
            'article-sop'         => array('Writing a procedure at a desk', 'كتابة إجراء على مكتب'),
            'article-foodsafety'  => array('A thermometer checking food temperature', 'مقياس حرارة يفحص درجة حرارة الطعام'),
            'article-reporting'   => array('Charts showing reported figures', 'رسوم بيانية تعرض أرقام التقارير'),
            'article-bilingual'   => array('Arabic and English text side by side', 'نص عربي وإنجليزي جنباً إلى جنب'),
            'article-preopening'  => array('A hotel interior being finished before opening', 'تشطيب داخلي لفندق قبل الافتتاح'),
        );
        if (strpos($subject, 'city-') === 0) {
            $city = ucwords(str_replace(array('city-', '-'), array('', ' '), $subject));
            return $locale === 'ar'
                ? 'مشهد من مدينة ' . $city . ' في السعودية'
                : 'A view of ' . $city . ', Saudi Arabia';
        }
        if (!isset($map[$subject])) {
            return $locale === 'ar' ? 'صورة توضيحية' : 'Illustrative photograph';
        }
        return $locale === 'ar' ? $map[$subject][1] : $map[$subject][0];
    }

    // ----------------------------------------------------------------- assign

    /**
     * Attaches the library to the content. Assignment is deterministic: the
     * same course always gets the same photograph, so a rebuild does not
     * reshuffle the whole site.
     */
    /** Subjects with no photograph of their own borrow a related one. */
    private $fallbacks = array(
        'topic-certification' => 'page-about',
        'page-certificates'   => 'page-about',

        // Taxonomy realignment to the published professional domains.
        //
        // The photo library is keyed by SUBJECT, which is a separate namespace
        // from the category codes -- each file carries an author, a licence and
        // a source page recorded at download. Re-fetching under new names would
        // re-acquire and re-credit images already held, so the new domains
        // borrow the subject they were split or renamed from instead. Curating
        // dedicated photography for them is a later, additive job; until then
        // a split domain shows the photograph its parent already carried.
        'sales-and-marketing'      => 'digital-hospitality',
        'revenue-and-reservations' => 'digital-hospitality',
        'quality-and-audit'        => 'management',
        'security-and-safety'      => 'safety-compliance',
    );

    public function assign() {
        $media = array();
        foreach ($this->db->get('ha_media')->result_array() as $m) {
            $media[$m['subject']] = $m;
        }
        foreach ($this->fallbacks as $missing => $stand_in) {
            if (!isset($media[$missing]) && isset($media[$stand_in])) {
                $media[$missing] = $media[$stand_in];
                // Carry the variants across too. Without this a borrowed
                // subject has a rotation pool of one, and every course in the
                // domain shows the same photograph -- which is the failure
                // .lab/design_check.py reports as "cards all show the same
                // picture".
                for ($n = 2; $n <= self::VARIANTS; $n++) {
                    if (isset($media[$stand_in . '-' . $n])) {
                        $media[$missing . '-' . $n] = $media[$stand_in . '-' . $n];
                    }
                }
            }
        }
        if (!$media) {
            fwrite(STDERR, 'ERROR: no media rows. Run "ha_images fetch" first.' . PHP_EOL);
            exit(1);
        }

        $this->out('Assigning photography to content');
        $this->out(str_repeat('-', 72));

        // Courses take their category photograph.
        $courses = $this->db->select('c.id, c.code, cat.code AS category_code')
            ->from('ha_course c')->join('ha_category cat', 'cat.id = c.category_id', 'left')
            ->get()->result_array();
        $done = 0;
        $seen = array();
        foreach ($courses as $c) {
            $key = $c['category_code'];
            if (!$key || !isset($media[$key])) {
                continue;
            }
            // Rotate through whatever variants the category actually has, so
            // a listing of ten front office courses is not ten copies of one
            // photograph. The rotation is by position, so it is stable.
            $pool = array($media[$key]['file_path']);
            for ($n = 2; $n <= self::VARIANTS; $n++) {
                if (isset($media[$key . '-' . $n])) {
                    $pool[] = $media[$key . '-' . $n]['file_path'];
                }
            }
            $index = isset($seen[$key]) ? $seen[$key] : 0;
            $seen[$key] = $index + 1;

            $this->db->where('id', $c['id'])->update('ha_course',
                array('thumbnail' => $pool[$index % count($pool)]));
            $done++;
        }
        $this->out(str_pad('courses', 20) . $done . ' (' . count($seen) . ' categories)');

        // Categories mirror the same photograph, so a category tile matches
        // the courses inside it.
        $done = 0;
        foreach ($this->db->get('ha_category')->result_array() as $cat) {
            if (isset($media[$cat['code']])) {
                $this->db->where('id', $cat['id'])->update('ha_category',
                    array('icon' => $media[$cat['code']]['file_path']));
                $done++;
            }
        }
        $this->out(str_pad('categories', 20) . $done);

        // Topics: cities get their own city photograph, pillars get a subject.
        $pillar_map = array(
            'front-office-training'    => 'front-office',
            'housekeeping-training'    => 'housekeeping',
            'food-safety-training'     => 'kitchen',
            'hotel-sop-training'       => 'topic-sop',
            'hospitality-certification'=> 'topic-certification',
            'hotel-compliance-training'=> 'topic-compliance',
        );
        $done = 0;
        foreach ($this->db->get('ha_topic')->result_array() as $topic) {
            $key = null;
            if ($topic['topic_type'] === 'city' && $topic['city']) {
                $key = 'city-' . strtolower(str_replace(' ', '-', $topic['city']));
            } elseif (isset($pillar_map[$topic['code']])) {
                $key = $pillar_map[$topic['code']];
            }
            if ($key && isset($media[$key])) {
                $this->db->where('id', $topic['id'])->update('ha_topic',
                    array('hero_image' => $media[$key]['file_path']));
                $done++;
            }
        }
        $this->out(str_pad('topics', 20) . $done);

        // Articles
        $article_map = array(
            'how-to-train-a-new-front-desk-agent'             => 'article-frontdesk',
            'writing-a-hotel-sop-people-actually-follow'      => 'article-sop',
            'hotel-food-safety-records-that-survive-an-inspection' => 'article-foodsafety',
            'measuring-hotel-training-completion-honestly'    => 'article-reporting',
            'training-hotel-teams-in-arabic-and-english'      => 'article-bilingual',
            'pre-opening-hotel-training-checklist'            => 'article-preopening',
        );
        $done = 0;
        foreach ($this->db->get('ha_article')->result_array() as $a) {
            if (!isset($article_map[$a['slug_en']])) {
                continue;
            }
            $key = $article_map[$a['slug_en']];
            if (!isset($media[$key])) {
                continue;
            }
            $this->db->where('id', $a['id'])->update('ha_article', array(
                'cover_image'        => $media[$key]['file_path'],
                'cover_image_alt_en' => $media[$key]['alt_en'],
                'cover_image_alt_ar' => $media[$key]['alt_ar'],
            ));
            $done++;
        }
        $this->out(str_pad('articles', 20) . $done);

        // Pages
        $page_map = array(
            'home'              => 'page-home',
            'about'             => 'page-about',
            'for-hotels'        => 'page-hotels',
            'hotels-training'   => 'page-about',
            'contact'           => 'page-contact',
            'certificates-info' => 'page-certificates',
        );
        $done = 0;
        foreach ($this->db->get('ha_page')->result_array() as $p) {
            if (!isset($page_map[$p['code']]) || !isset($media[$page_map[$p['code']]])) {
                continue;
            }
            $path = $media[$page_map[$p['code']]]['file_path'];
            $this->db->where('page_id', $p['id'])->update('ha_page_translation',
                array('hero_image' => $path));
            $done++;
        }
        $this->out(str_pad('pages', 20) . $done);

        // Social sharing images, so a shared link is not a blank card.
        $done = 0;
        foreach ($this->db->get('ha_seo_metadata')->result_array() as $row) {
            $path = $this->og_image_for($row, $media);
            if (!$path) {
                continue;
            }
            $this->db->where('id', $row['id'])->update('ha_seo_metadata',
                // Stored RELATIVE, resolved to an absolute URL at render time.
                //
                // base_url() here is whatever CLI can work out, and CLI has no
                // HTTP_HOST: it resolved to http://localhost/ and dropped the
                // sub-directory, so every og:image on the site was a 404. The
                // same would happen on any deployment where the seeder runs
                // before, or on a different host to, the web server. Ha_seo
                // rebuilds the absolute URL from the request that is actually
                // serving the page.
                array('og_image' => $path));
            $done++;
        }
        $this->out(str_pad('social images', 20) . $done);

        $this->out(str_repeat('-', 72));
        $this->out('Assignment complete.');
    }

    private function og_image_for(array $row, array $media) {
        $fallback = isset($media['page-home']) ? $media['page-home']['file_path'] : null;

        if ($row['entity_type'] === 'article' && $row['entity_id']) {
            $a = $this->db->select('cover_image')->get_where('ha_article', array('id' => $row['entity_id']))->row_array();
            return ($a && $a['cover_image']) ? $a['cover_image'] : $fallback;
        }
        if ($row['entity_type'] === 'topic' && $row['entity_id']) {
            $t = $this->db->select('hero_image')->get_where('ha_topic', array('id' => $row['entity_id']))->row_array();
            return ($t && $t['hero_image']) ? $t['hero_image'] : $fallback;
        }
        if ($row['entity_type'] === 'course' && $row['entity_id']) {
            $c = $this->db->select('thumbnail')->get_where('ha_course', array('id' => $row['entity_id']))->row_array();
            return ($c && $c['thumbnail']) ? $c['thumbnail'] : $fallback;
        }
        return $fallback;
    }

    // ----------------------------------------------------------------- report

    public function report() {
        $this->out('Photo library');
        $this->out(str_repeat('-', 72));
        $rows = $this->db->order_by('subject', 'ASC')->get('ha_media')->result_array();
        foreach ($rows as $r) {
            $this->out(str_pad($r['subject'], 24)
                . str_pad($r['license'], 16)
                . ($r['credit_required'] ? 'credit  ' : '        ')
                . $r['author']);
        }
        $this->out(str_repeat('-', 72));
        $this->out('images: ' . count($rows));

        $coverage = array(
            'courses with a photo'  => array('ha_course', 'thumbnail'),
            'topics with a photo'   => array('ha_topic', 'hero_image'),
            'articles with a cover' => array('ha_article', 'cover_image'),
        );
        foreach ($coverage as $label => $spec) {
            $total = $this->db->count_all_results($spec[0]);
            $with = $this->db->where($spec[1] . ' IS NOT NULL')->where($spec[1] . ' !=', '')
                ->count_all_results($spec[0]);
            $this->out(str_pad($label, 24) . $with . ' / ' . $total);
        }
    }

    /** Removes a subject's image so it can be fetched again. */
    public function purge($subject = null) {
        $rows = $subject
            ? $this->db->like('subject', $subject)->get('ha_media')->result_array()
            : $this->db->get('ha_media')->result_array();
        foreach ($rows as $r) {
            foreach (array($r['file_path'], str_replace('.webp', '-card.webp', $r['file_path'])) as $f) {
                if ($f && file_exists(FCPATH . $f)) {
                    unlink(FCPATH . $f);
                }
            }
            $this->db->where('id', $r['id'])->delete('ha_media');
        }
        $this->out('purged ' . count($rows) . ' image(s)');
    }

    public function build() {
        $this->fetch();
        $this->assign();
    }
}
