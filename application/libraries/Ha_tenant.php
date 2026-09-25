<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Tenant context, layered settings and white-label branding for altus
 * Hospitality Knowledge & Performance (ppt-features 5, 6, 31, 68).
 *
 * Settings resolve property -> organisation -> global -> registry default, so
 * a property can override a threshold without touching the master value. Every
 * threshold the engines use is registered here with its default and meaning,
 * which is what lets an administrator see and change the rule instead of it
 * being a number buried in PHP (sections 71, 79, 107, 179).
 *
 * Branding resolves the same way. Tenant-facing screens never hardcode a
 * brand: the platform row is Altus, and a client or property row overrides it.
 */
class Ha_tenant {

    protected $CI;
    protected $cache = array();
    protected $brand_cache = array();

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
    }

    /** key => array(default, type, group, description) */
    public static function registry() {
        return array(
            'locale.default'                 => array('en', 'enum:en,ar', 'general', 'Default interface language for new users.'),
            'general.timezone'               => array('Asia/Riyadh', 'string', 'general', 'Timezone used for due dates and reports.'),
            'general.date_format'            => array('Y-m-d', 'string', 'general', 'Date format on screens and exports.'),
            'general.currency'               => array('SAR', 'string', 'general', 'Currency for financial KPIs.'),
            'email.delivery'                 => array('auto', 'enum:auto,send,log', 'general', 'auto sends email only on a production install (no database.local.php); log records it without sending; send always sends.'),
            'files.max_mb'                   => array(25, 'int', 'files', 'Largest file a user may upload, in megabytes.'),
            'files.allowed_ext'              => array('pdf,jpg,jpeg,png,webp,mp4,mov,docx,xlsx,pptx,csv,txt', 'string', 'files', 'File extensions accepted as evidence or attachments.'),
            'theory.default_pass'            => array(70, 'int', 'assessment', 'Pass mark (%) for a theory assessment that does not set its own.'),
            'theory.repeat_fail_alert'       => array(2, 'int', 'assessment', 'Failed attempts on the same assessment before the manager is alerted.'),
            'practical.fraction.not_demonstrated' => array(0, 'float', 'assessment', 'Share of a criterion weight earned when rated Not demonstrated.'),
            'practical.fraction.developing'  => array(0.4, 'float', 'assessment', 'Share of a criterion weight earned when rated Developing.'),
            'practical.fraction.competent'   => array(0.8, 'float', 'assessment', 'Share of a criterion weight earned when rated Competent.'),
            'practical.fraction.exceeds'     => array(1.0, 'float', 'assessment', 'Share of a criterion weight earned when rated Exceeds standard.'),
            'practical.level.not_demonstrated' => array(1, 'int', 'competency', 'Competency level recorded for a Not demonstrated practical outcome.'),
            'practical.level.developing'     => array(2, 'int', 'competency', 'Competency level recorded for a Developing practical outcome.'),
            'practical.level.competent'      => array(3, 'int', 'competency', 'Competency level recorded for a Competent practical outcome.'),
            'practical.level.exceeds'        => array(4, 'int', 'competency', 'Competency level recorded for an Exceeds standard practical outcome.'),
            'gap.minor_max'                  => array(1, 'int', 'competency', 'A gap of up to this many levels is Minor.'),
            'gap.moderate_max'               => array(2, 'int', 'competency', 'A gap of up to this many levels is Moderate; larger gaps are Critical.'),
            'gap.critical_competency_escalates' => array(1, 'bool', 'competency', 'Any gap on a competency marked critical for the role is treated as Critical.'),
            'readiness.department_alert_below' => array(80, 'int', 'readiness', 'Alert managers when a department\'s ready share (%) falls below this.'),
            'opening.conditional_min_pct'    => array(80, 'int', 'readiness', 'Opening readiness: a category at or above this share of its target is Conditional rather than Not ready.'),
            'certificate.expiry_warning_days' => array(30, 'int', 'certification', 'Days before expiry that holders and managers are warned.'),
            'knowledge.review_warning_days'  => array(30, 'int', 'knowledge', 'Days before a knowledge item\'s review date that its owner is reminded.'),
            'knowledge.unused_days'          => array(90, 'int', 'knowledge', 'A published item nobody opened for this many days is listed as unused.'),
            'training.reminder_days_before'  => array(3, 'int', 'learning', 'Days before a due date that learners are reminded.'),
            'ai.enabled'                     => array(1, 'bool', 'ai', 'The governed AI assistant is available.'),
            'ai.max_sources'                 => array(6, 'int', 'ai', 'Most approved passages sent to the model for one question.'),
            'ai.min_relevance'               => array(0.34, 'float', 'ai', 'Share of the question\'s key terms a passage must contain to count as a source.'),
            'ai.max_answer_tokens'           => array(700, 'int', 'ai', 'Longest answer the model may write.'),
            'ai.daily_limit_per_user'        => array(60, 'int', 'ai', 'Questions one person may ask per day.'),
            'ai.system_instructions'         => array('', 'text', 'ai', 'Extra instructions appended to the governed system prompt. Cannot relax the governance rules.'),
            'security.idle_minutes'          => array(60, 'int', 'security', 'Minutes of inactivity before the workspace asks the user to sign in again.'),
            'retention.years'                => array(7, 'int', 'governance', 'Years historical assessment, competency and certification records are kept before archival review.'),
        );
    }

    // --------------------------------------------------------------- context

    /** Organisation and property the current request acts in, from the profile. */
    public function context() {
        $CI = $this->CI;
        $CI->load->library('ha_auth');
        $p = $CI->ha_auth->profile();
        $org = $p && $p['organization_id'] ? (int) $p['organization_id'] : null;
        $prop = $p && $p['property_id'] ? (int) $p['property_id'] : null;
        if (!is_cli() && isset($CI->session)) {
            $chosen = (int) $CI->session->userdata('hkp_property');
            if ($chosen && $CI->ha_auth->can_property($chosen)) {
                $row = $CI->db->select('organization_id')->get_where('ha_property', array('id' => $chosen))->row_array();
                if ($row) {
                    $prop = $chosen;
                    $org = (int) $row['organization_id'];
                }
            }
        }
        if (!$org) {
            $org = $CI->ha_auth->default_organization_id();
        }
        return array('organization_id' => $org, 'property_id' => $prop);
    }

    // -------------------------------------------------------------- settings

    public function get($key, $property_id = null, $organization_id = null) {
        $reg = self::registry();
        $ck = $key . '|' . (int) $property_id . '|' . (int) $organization_id;
        if (array_key_exists($ck, $this->cache)) {
            return $this->cache[$ck];
        }
        if ($property_id && !$organization_id) {
            $row = $this->CI->db->select('organization_id')->get_where('ha_property', array('id' => (int) $property_id))->row_array();
            $organization_id = $row ? (int) $row['organization_id'] : null;
        }
        $layers = array();
        if ($property_id) {
            $layers[] = array('property', (int) $property_id);
        }
        if ($organization_id) {
            $layers[] = array('organization', (int) $organization_id);
        }
        $layers[] = array('global', 0);
        $value = null;
        $found = false;
        foreach ($layers as $l) {
            $row = $this->CI->db->select('value')->get_where('ha_setting',
                array('scope_type' => $l[0], 'scope_id' => $l[1], 'setting_key' => $key))->row_array();
            if ($row) {
                $value = $row['value'];
                $found = true;
                break;
            }
        }
        if (!$found) {
            $value = isset($reg[$key]) ? $reg[$key][0] : null;
        }
        $value = $this->cast($key, $value);
        return $this->cache[$ck] = $value;
    }

    protected function cast($key, $value) {
        $reg = self::registry();
        $type = isset($reg[$key]) ? $reg[$key][1] : 'string';
        switch ($type) {
            case 'int':   return (int) $value;
            case 'float': return (float) $value;
            case 'bool':  return (bool) (int) $value;
            default:      return $value === null ? null : (string) $value;
        }
    }

    public function set($key, $value, $scope_type = 'global', $scope_id = 0, $actor_id = null) {
        $reg = self::registry();
        if (!isset($reg[$key])) {
            throw new InvalidArgumentException('Unknown setting: ' . $key);
        }
        $type = $reg[$key][1];
        if (strpos($type, 'enum:') === 0 && !in_array((string) $value, explode(',', substr($type, 5)), true)) {
            throw new InvalidArgumentException($key . ' must be one of ' . substr($type, 5) . '.');
        }
        if (in_array($type, array('int', 'float'), true) && !is_numeric($value)) {
            throw new InvalidArgumentException($key . ' must be a number.');
        }
        $match = array('scope_type' => $scope_type, 'scope_id' => (int) $scope_id, 'setting_key' => $key);
        $before = $this->CI->db->get_where('ha_setting', $match)->row_array();
        $row = array('value' => (string) $value, 'updated_by' => $actor_id, 'updated_at' => date('Y-m-d H:i:s'));
        if ($before) {
            $this->CI->db->where('id', $before['id'])->update('ha_setting', $row);
        } else {
            $this->CI->db->insert('ha_setting', $match + $row);
        }
        $this->cache = array();
        $this->CI->load->library('ha_audit');
        $this->CI->ha_audit->log('update', 'ha_setting', null, array(
            'description' => 'Setting ' . $key . ' (' . $scope_type . ' ' . (int) $scope_id . ')',
            'before' => $before ? array('value' => $before['value']) : null, 'after' => array('value' => (string) $value),
        ));
    }

    /** Removes an override so the next layer (or the default) applies again. */
    public function reset($key, $scope_type, $scope_id) {
        $this->CI->db->where(array('scope_type' => $scope_type, 'scope_id' => (int) $scope_id, 'setting_key' => $key))->delete('ha_setting');
        $this->cache = array();
    }

    // -------------------------------------------------------------- branding

    public static function platform_defaults() {
        return array(
            'brand_name_en' => 'altus Hospitality Knowledge & Performance',
            'brand_name_ar' => 'ألتوس للمعرفة والأداء الفندقي',
            'logo_path' => 'logo.png',
            'favicon_path' => null,
            'color_primary' => '#0F3D3E',
            'color_secondary' => '#0D1B2A',
            'color_accent' => '#C89D4F',
            'color_surface' => '#F7F6F2',
            'font_latin' => 'Montserrat',
            'font_arabic' => 'Cairo',
            'login_headline_en' => 'The right knowledge, to the right person, at the right time.',
            'login_headline_ar' => 'المعرفة الصحيحة، للشخص المناسب، في الوقت المناسب.',
            'welcome_en' => 'Knowledge that stays with your institution, evidence that shows it is working.',
            'welcome_ar' => 'معرفة تبقى في مؤسستك، وأدلة تثبت أنها تعمل.',
            'email_footer_en' => 'altus Hospitality Knowledge & Performance · Altus Advisory, Riyadh',
            'email_footer_ar' => 'ألتوس للمعرفة والأداء الفندقي · ألتوس للاستشارات، الرياض',
            'signatory_name_en' => null, 'signatory_name_ar' => null,
            'signatory_title_en' => null, 'signatory_title_ar' => null,
            'signature_path' => null, 'custom_domain' => null,
            'show_altus' => 'both',
        );
    }

    /**
     * Effective brand for a property (or organisation). Each non-empty field of
     * the more specific row wins; empty fields inherit, so a client can upload a
     * logo and keep the rest of the house style.
     */
    public function brand($property_id = null, $organization_id = null) {
        $ck = (int) $property_id . '|' . (int) $organization_id;
        if (isset($this->brand_cache[$ck])) {
            return $this->brand_cache[$ck];
        }
        if ($property_id && !$organization_id) {
            $row = $this->CI->db->select('organization_id')->get_where('ha_property', array('id' => (int) $property_id))->row_array();
            $organization_id = $row ? (int) $row['organization_id'] : null;
        }
        $brand = self::platform_defaults();
        $layers = array(array('platform', 0));
        if ($organization_id) {
            $layers[] = array('organization', (int) $organization_id);
        }
        if ($property_id) {
            $layers[] = array('property', (int) $property_id);
        }
        $brand['source'] = 'platform';
        foreach ($layers as $l) {
            $row = $this->CI->db->get_where('ha_branding', array('scope_type' => $l[0], 'scope_id' => $l[1]))->row_array();
            if (!$row) {
                continue;
            }
            foreach ($brand as $k => $v) {
                if (array_key_exists($k, $row) && $row[$k] !== null && $row[$k] !== '') {
                    $brand[$k] = $row[$k];
                }
            }
            $brand['source'] = $l[0];
        }
        foreach (array('color_primary', 'color_secondary', 'color_accent', 'color_surface') as $c) {
            if (!preg_match('/^#[0-9a-fA-F]{6}$/', (string) $brand[$c])) {
                $defaults = self::platform_defaults();
                $brand[$c] = $defaults[$c];
            }
        }
        $brand['organization_id'] = $organization_id;
        $brand['property_id'] = $property_id;
        return $this->brand_cache[$ck] = $brand;
    }

    /** Which property a custom domain belongs to, for white-label hostnames. */
    public function brand_for_host($host) {
        $host = strtolower(trim((string) $host));
        if ($host === '') {
            return null;
        }
        $row = $this->CI->db->get_where('ha_branding', array('custom_domain' => $host))->row_array();
        return $row ?: null;
    }

    public function save_brand($scope_type, $scope_id, array $input, $actor_id = null) {
        if (!in_array($scope_type, array('platform', 'organization', 'property'), true)) {
            throw new InvalidArgumentException('Unknown branding scope.');
        }
        $fields = array_keys(self::platform_defaults());
        $row = array();
        foreach ($fields as $f) {
            if (array_key_exists($f, $input)) {
                $v = is_string($input[$f]) ? trim($input[$f]) : $input[$f];
                $row[$f] = $v === '' ? null : $v;
            }
        }
        foreach (array('color_primary', 'color_secondary', 'color_accent', 'color_surface') as $c) {
            if (!empty($row[$c]) && !preg_match('/^#[0-9a-fA-F]{6}$/', $row[$c])) {
                throw new InvalidArgumentException(ucfirst(str_replace('_', ' ', $c)) . ' must be a colour such as #0F3D3E.');
            }
        }
        if (isset($row['show_altus']) && !in_array($row['show_altus'], array('altus', 'client', 'both'), true)) {
            $row['show_altus'] = 'both';
        }
        if (!empty($row['custom_domain'])) {
            $row['custom_domain'] = strtolower($row['custom_domain']);
            if (!preg_match('/^[a-z0-9]([a-z0-9\-\.]*[a-z0-9])?$/', $row['custom_domain'])) {
                throw new InvalidArgumentException('The custom domain is not a valid host name.');
            }
            $taken = $this->CI->db->where('custom_domain', $row['custom_domain'])
                ->where('NOT (scope_type = ' . $this->CI->db->escape($scope_type) . ' AND scope_id = ' . (int) $scope_id . ')', null, false)
                ->count_all_results('ha_branding');
            if ($taken) {
                throw new InvalidArgumentException('That domain is already used by another brand.');
            }
        }
        $match = array('scope_type' => $scope_type, 'scope_id' => (int) $scope_id);
        $before = $this->CI->db->get_where('ha_branding', $match)->row_array();
        $row['updated_by'] = $actor_id;
        $row['updated_at'] = date('Y-m-d H:i:s');
        if ($before) {
            $this->CI->db->where('id', $before['id'])->update('ha_branding', $row);
        } else {
            $row['created_at'] = $row['updated_at'];
            $this->CI->db->insert('ha_branding', $match + $row);
        }
        $this->brand_cache = array();
        $this->CI->load->library('ha_audit');
        $this->CI->ha_audit->log('update', 'ha_branding', $before ? (int) $before['id'] : (int) $this->CI->db->insert_id(), array(
            'description' => 'Branding saved for ' . $scope_type . ' ' . (int) $scope_id,
            'property_id' => $scope_type === 'property' ? (int) $scope_id : null,
            'organization_id' => $scope_type === 'organization' ? (int) $scope_id : null,
        ));
    }

    /** CSS custom properties for the effective brand. Values are validated hex colours only. */
    public function css_vars(array $brand) {
        $font_latin = preg_replace('/[^A-Za-z0-9 \-]/', '', (string) $brand['font_latin']) ?: 'Montserrat';
        $font_ar = preg_replace('/[^A-Za-z0-9 \-]/', '', (string) $brand['font_arabic']) ?: 'Cairo';
        return ':root{--brand:' . $brand['color_primary'] . ';--brand-ink:' . $brand['color_secondary']
            . ';--accent:' . $brand['color_accent'] . ';--surface-brand:' . $brand['color_surface']
            . ';--font-latin:"' . $font_latin . '";--font-arabic:"' . $font_ar . '";}';
    }
}
