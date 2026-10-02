<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Migration_Admin_studio extends Ha_migration {
    public function up() {
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_website_draft (
            page_id INT UNSIGNED NOT NULL, version INT UNSIGNED NOT NULL DEFAULT 1,
            payload_json LONGTEXT NOT NULL, base_hash CHAR(64) NOT NULL,
            updated_by INT UNSIGNED NOT NULL, updated_at DATETIME NOT NULL,
            PRIMARY KEY (page_id)
        )" . $this->engine);
        $this->db->query("CREATE TABLE IF NOT EXISTS ha_publisher_draft (
            id INT UNSIGNED NOT NULL AUTO_INCREMENT, created_by INT UNSIGNED NOT NULL,
            organization_id INT UNSIGNED NULL, source_name VARCHAR(255) NOT NULL,
            source_text MEDIUMTEXT NOT NULL, source_hash CHAR(64) NOT NULL,
            target VARCHAR(30) NOT NULL DEFAULT 'course', locale VARCHAR(12) NOT NULL DEFAULT 'en',
            payload_json LONGTEXT NULL, provider VARCHAR(60) NULL, model VARCHAR(190) NULL,
            version INT UNSIGNED NOT NULL DEFAULT 1, entity_id INT UNSIGNED NULL,
            status VARCHAR(30) NOT NULL DEFAULT 'source', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL,
            PRIMARY KEY(id), KEY ix_publisher_owner (created_by, id), KEY ix_publisher_org (organization_id, status)
        )" . $this->engine);
        // Add only missing system landing records. Preserve all existing page content.
        $routes = array('' => 'Homepage', 'courses' => 'Courses', 'programs' => 'Programs',
            'learning-paths' => 'Learning paths', 'certificates' => 'Certifications', 'sop' => 'SOP resources',
            'hospitality-topics' => 'Hospitality topics', 'articles' => 'Articles', 'verify' => 'Verify a certificate',
            'about' => 'About Academy', 'hotels' => 'For Hotels', 'contact' => 'Contact', 'credits' => 'Photo credits');
        $now = date('Y-m-d H:i:s');
        $arabic = array('' => 'الرئيسية', 'courses' => 'الدورات', 'programs' => 'البرامج', 'learning-paths' => 'مسارات التعلم',
            'certificates' => 'الشهادات', 'sop' => 'موارد إجراءات التشغيل', 'hospitality-topics' => 'مواضيع الضيافة', 'articles' => 'المقالات',
            'verify' => 'التحقق من شهادة', 'about' => 'عن الأكاديمية', 'hotels' => 'للفنادق', 'contact' => 'اتصل بنا', 'credits' => 'مصادر الصور');
        foreach ($routes as $slug => $title) {
            $code = $slug === '' ? 'home' : ($slug === 'hotels' ? 'for-hotels' : ($slug === 'certificates' ? 'certificates-info' : $slug));
            if ($this->db->group_start()->where('code', $code)->or_where('slug_en', $slug)->group_end()->count_all_results('ha_page')) { continue; }
            $this->db->insert('ha_page', array('code' => $code, 'slug_en' => $slug, 'slug_ar' => $slug,
                'template' => $slug === '' ? 'home' : 'standard', 'is_system' => 1,
                'status' => 'published', 'created_at' => $now, 'updated_at' => $now));
            $id = $this->db->insert_id();
            foreach (array('en', 'ar') as $loc) {
                $page_title = $loc === 'en' ? $title : $arabic[$slug];
                $description = $loc === 'en' ? 'Explore ' . $title . ' at ALTUS Knowledge & Performance. Practical hospitality knowledge and learning for hotel teams.' : 'استكشف ' . $page_title . ' في منصة ألتوس للمعرفة والأداء. معرفة عملية وتعلم مهني لفرق الضيافة والفنادق.';
                $this->db->insert('ha_page_translation', array('page_id' => $id, 'locale' => $loc, 'title' => $page_title, 'body' => '<p>' . $description . '</p>'));
                $this->db->insert('ha_seo_metadata', array('entity_type' => 'page', 'entity_id' => $id, 'locale' => $loc,
                    'meta_title' => $page_title . ' | Altus Gulf', 'meta_description' => mb_substr($description, 0, 160), 'created_at' => $now, 'updated_at' => $now));
            }
        }
        $footers = array('footer_learn' => array('Learn', 'التعلم', array('courses' => array('Courses', 'الدورات'), 'programs' => array('Programs', 'البرامج'), 'learning-paths' => array('Learning paths', 'مسارات التعلم'), 'certificates' => array('Certifications', 'الشهادات'))),
            'footer_resources' => array('Resources', 'الموارد', array('sop' => array('SOP resources', 'إجراءات التشغيل'), 'hospitality-topics' => array('Hospitality topics', 'مواضيع الضيافة'), 'articles' => array('Articles', 'المقالات'), 'verify' => array('Verify a certificate', 'التحقق من شهادة'))),
            'footer_company' => array('Company', 'الشركة', array('about' => array('About Academy', 'عن الأكاديمية'), 'hotels' => array('For Hotels', 'للفنادق'), 'contact' => array('Contact', 'اتصل بنا'), 'credits' => array('Photo credits', 'مصادر الصور'))));
        foreach ($footers as $code => $group) {
            if ($this->db->where('code', $code)->count_all_results('ha_menu')) { continue; }
            $this->db->insert('ha_menu', array('code' => $code, 'name_en' => 'Footer · ' . $group[0], 'name_ar' => $group[1], 'created_at' => $now, 'updated_at' => $now));
            $menu = $this->db->insert_id(); $i = 0;
            foreach ($group[2] as $url => $label) { $this->db->insert('ha_menu_item', array('menu_id' => $menu, 'label_en' => $label[0], 'label_ar' => $label[1], 'url_en' => $url, 'url_ar' => $url, 'sort_order' => $i++)); }
        }
    }
    public function down() {
        // Landing pages remain so rollback cannot delete authored content.
        $this->drop(array('ha_website_draft', 'ha_publisher_draft'));
    }
}
