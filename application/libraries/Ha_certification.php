<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Rule-based certification (ppt-features 23, 61, 112, 113).
 *
 * A certificate is issued only when every rule of its programme is satisfied:
 * modules completed, theory scores reached, practical rubrics passed,
 * competency levels held and, when the programme asks for it, the holder being
 * operationally ready (evaluated with the certification check itself skipped,
 * so the two do not wait on each other). The evidence each rule used is frozen
 * into the certificate row at issue time, so the register can later show what
 * the certificate was based on even after the underlying records move on.
 *
 * Certificates render as branded HTML, a PNG and a single-page PDF, each with a
 * QR code pointing at the public verification page, which shows validity and
 * the minimum identity needed to confirm it and nothing else.
 */
class Ha_certification {

    protected $CI;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->helper(array('url', 'hkp'));
        $this->CI->load->library(array('ha_audit', 'ha_notify', 'ha_competency', 'ha_tenant'));
    }

    public function program($id) {
        return $this->CI->db->get_where('ha_certification_program', array('id' => (int) $id))->row_array();
    }

    public function rules(array $program) {
        $r = json_decode((string) $program['rules_json'], true);
        return (is_array($r) ? $r : array()) + array('tracks' => array(), 'courses' => array(), 'assessments' => array(),
            'rubrics' => array(), 'competencies' => array(), 'require_readiness' => null);
    }

    /** Every rule with passed/failed and the evidence behind it. */
    public function check(array $program, $user_id) {
        $rules = $this->rules($program);
        $out = array();
        $db = $this->CI->db;

        $course_ids = array_map('intval', (array) $rules['courses']);
        foreach ((array) $rules['tracks'] as $tid) {
            foreach ($db->select('course_id')->get_where('ha_track_module', array('track_id' => (int) $tid, 'is_mandatory' => 1))->result_array() as $m) {
                $course_ids[] = (int) $m['course_id'];
            }
        }
        foreach (array_unique($course_ids) as $cid) {
            $e = $db->get_where('ha_enrollment', array('user_id' => (int) $user_id, 'course_id' => $cid))->row_array();
            $t = $db->select('title')->get_where('ha_course_translation', array('course_id' => $cid, 'locale' => 'en'))->row_array();
            $out[] = array('rule' => 'module', 'label' => ($t ? $t['title'] : '#' . $cid), 'passed' => $e && $e['status'] === 'completed',
                'detail' => $e ? ($e['status'] === 'completed' ? 'Completed ' . substr((string) $e['completed_at'], 0, 10) : round($e['progress_percentage']) . '% complete') : 'Not started',
                'evidence' => $e ? array('enrollment_id' => (int) $e['id']) : null);
        }
        foreach ((array) $rules['assessments'] as $a) {
            $aid = (int) (is_array($a) ? $a['id'] : $a);
            $min = is_array($a) && isset($a['min_score']) ? (float) $a['min_score'] : null;
            $as = $db->get_where('ha_assessment', array('id' => $aid))->row_array();
            $best = $db->select_max('percentage')->where(array('assessment_id' => $aid, 'user_id' => (int) $user_id, 'status' => 'graded', 'passed' => 1))
                ->get('ha_assessment_attempt')->row_array();
            $score = $best && $best['percentage'] !== null ? (float) $best['percentage'] : null;
            $need = $min !== null ? $min : ($as ? (float) $as['pass_percentage'] : 70);
            $out[] = array('rule' => 'theory', 'label' => $as ? $as['title_en'] : '#' . $aid, 'passed' => $score !== null && $score >= $need,
                'detail' => $score === null ? 'No passing attempt' : 'Best ' . $score . '% (needs ' . $need . '%)', 'evidence' => array('assessment_id' => $aid, 'score' => $score));
        }
        foreach ((array) $rules['rubrics'] as $rid) {
            $rub = $db->get_where('ha_rubric', array('id' => (int) $rid))->row_array();
            $last = $db->where(array('rubric_id' => (int) $rid, 'user_id' => (int) $user_id, 'status' => 'submitted'))
                ->order_by('submitted_at', 'DESC')->order_by('id', 'DESC')->limit(1)->get('ha_practical_assessment')->row_array();
            $ok = $last && in_array($last['outcome'], array('competent', 'exceeds'), true);
            $out[] = array('rule' => 'practical', 'label' => $rub ? $rub['title_en'] : '#' . $rid, 'passed' => $ok,
                'detail' => $last ? 'Latest: ' . str_replace('_', ' ', $last['outcome']) . ' (' . $last['weighted_score'] . '%)' : 'Not assessed',
                'evidence' => $last ? array('practical_id' => (int) $last['id']) : null);
        }
        foreach ((array) $rules['competencies'] as $c) {
            $sid = (int) $c['skill_id'];
            $s = $db->get_where('ha_skill', array('id' => $sid))->row_array();
            $cur = $this->CI->ha_competency->current_level($user_id, $sid);
            $out[] = array('rule' => 'competency', 'label' => $s ? $s['name_en'] : '#' . $sid, 'passed' => $cur >= (int) $c['level'],
                'detail' => 'Level ' . $cur . ' of ' . (int) $c['level'], 'evidence' => array('skill_id' => $sid, 'level' => $cur));
        }
        if (!empty($rules['require_readiness'])) {
            $this->CI->load->library('ha_readiness');
            $r = $this->CI->ha_readiness->evaluate($user_id, array('skip' => array('certification')));
            $need = $rules['require_readiness'];
            $ok = $r['status'] === 'ready' || ($need === 'conditional' && $r['status'] === 'conditional');
            $failed = array();
            foreach ($r['checks'] as $ch) {
                if (!$ch['passed']) {
                    $failed[] = $ch['detail'];
                }
            }
            $out[] = array('rule' => 'readiness', 'label' => 'Operational readiness (' . str_replace('_', ' ', $need) . ')', 'passed' => $ok,
                'detail' => $ok ? 'Readiness: ' . str_replace('_', ' ', $r['status']) : implode('; ', $failed), 'evidence' => array('status' => $r['status']));
        }
        return $out;
    }

    public function eligible(array $program, $user_id) {
        $checks = $this->check($program, $user_id);
        if (!$checks) {
            return false;   // a programme with no rules certifies nothing
        }
        foreach ($checks as $c) {
            if (!$c['passed']) {
                return false;
            }
        }
        return true;
    }

    public function valid_certificate($program_id, $user_id) {
        return $this->CI->db->where(array('program_id' => (int) $program_id, 'user_id' => (int) $user_id, 'status' => 'issued'))
            ->group_start()->where('expires_at IS NULL', null, false)->or_where('expires_at >', date('Y-m-d H:i:s'))->group_end()
            ->get('ha_certificate')->row_array();
    }

    /**
     * Issues the certificate. Throws with the unmet rules when not eligible;
     * a certificate is never issued "because the course was clicked complete".
     */
    public function issue($program_id, $user_id, $actor_id = null, array $opts = array()) {
        $program = $this->program($program_id);
        if (!$program || $program['status'] !== 'published') {
            throw new InvalidArgumentException('That certification programme is not published.');
        }
        $existing = $this->valid_certificate($program_id, $user_id);
        if ($existing) {
            return (int) $existing['id'];
        }
        $checks = $this->check($program, $user_id);
        $unmet = array();
        foreach ($checks as $c) {
            if (!$c['passed']) {
                $unmet[] = $c['label'] . ': ' . $c['detail'];
            }
        }
        if (!$checks || $unmet) {
            throw new RuntimeException('Certification requirements are not met. ' . implode(' | ', $unmet ?: array('The programme has no rules.')));
        }
        $user = $this->CI->db->select('u.id, u.first_name, u.last_name, p.full_name_ar, p.organization_id, p.property_id, p.job_role_id')
            ->from('users u')->join('ha_profile p', 'p.user_id = u.id', 'left')->where('u.id', (int) $user_id)->get()->row_array();
        $role = $user['job_role_id'] ? $this->CI->db->get_where('ha_job_role', array('id' => $user['job_role_id']))->row_array() : null;
        $domain = $program['domain_id'] ? $this->CI->db->get_where('ha_domain', array('id' => $program['domain_id']))->row_array() : null;
        $now = date('Y-m-d H:i:s');

        $this->CI->db->trans_start();
        $number = $this->next_number($program['number_prefix']);
        $this->CI->db->insert('ha_certificate', array(
            'certificate_no' => $number, 'verification_code' => bin2hex(random_bytes(16)),
            'template_id' => $program['template_id'], 'user_id' => (int) $user_id, 'program_id' => (int) $program_id,
            'organization_id' => $user['organization_id'], 'property_id' => $user['property_id'],
            'subject_title_en' => $program['title_en'], 'subject_title_ar' => $program['title_ar'],
            'recipient_name_en' => trim($user['first_name'] . ' ' . $user['last_name']), 'recipient_name_ar' => $user['full_name_ar'],
            'role_title_en' => $role ? $role['title_en'] : null, 'role_title_ar' => $role ? $role['title_ar'] : null,
            'domain_title_en' => $domain ? $domain['name_en'] : null, 'domain_title_ar' => $domain ? $domain['name_ar'] : null,
            'issuer_en' => $program['issuing_authority_en'], 'issuer_ar' => $program['issuing_authority_ar'],
            'locale' => isset($opts['locale']) && in_array($opts['locale'], array('en', 'ar', 'bilingual'), true) ? $opts['locale'] : 'bilingual',
            'evidence_json' => json_encode($checks, JSON_UNESCAPED_UNICODE),
            'issued_at' => $now, 'expires_at' => (int) $program['validity_months'] > 0 ? date('Y-m-d H:i:s', strtotime('+' . (int) $program['validity_months'] . ' months')) : null,
            'status' => 'issued', 'issued_by' => $actor_id ? (int) $actor_id : null, 'created_at' => $now, 'updated_at' => $now,
        ));
        $id = (int) $this->CI->db->insert_id();
        $this->CI->db->trans_complete();

        $this->CI->ha_audit->log('certificate_issue', 'certificate', $id, array('description' => $number . ' issued to user ' . (int) $user_id . ' for ' . $program['code'],
            'organization_id' => $user['organization_id'], 'property_id' => $user['property_id'], 'user_id' => $actor_id ?: null));
        $this->CI->ha_notify->send($user_id, 'certificate.issued', array('certificate_name' => $program['title_en'], 'certificate_number' => $number,
            'url' => hkp_url('certificates/view/' . $id), 'related_type' => 'certificate', 'related_id' => $id, '_no_manager' => 1));
        return $id;
    }

    /** ALTUS-FOA-2026-000152: prefix, year, six-digit sequence per prefix and year. */
    public function next_number($prefix) {
        $prefix = strtoupper(preg_replace('/[^A-Za-z0-9\-]/', '', (string) $prefix)) ?: 'ALTUS';
        $stem = $prefix . '-' . date('Y') . '-';
        $row = $this->CI->db->query('SELECT MAX(CAST(SUBSTRING(certificate_no, ?) AS UNSIGNED)) AS n FROM ha_certificate WHERE certificate_no LIKE ? FOR UPDATE',
            array(strlen($stem) + 1, $stem . '%'))->row_array();
        return $stem . str_pad((string) ((int) $row['n'] + 1), 6, '0', STR_PAD_LEFT);
    }

    /** Issues every published programme for the user's role that they now satisfy. Returns how many. */
    public function auto_issue($user_id) {
        $p = $this->CI->ha_competency->profile_row($user_id);
        if (!$p || !$p['job_role_id']) {
            return 0;
        }
        $n = 0;
        $programs = $this->CI->db->get_where('ha_certification_program', array('job_role_id' => (int) $p['job_role_id'], 'status' => 'published'))->result_array();
        foreach ($programs as $prog) {
            if ($this->valid_certificate($prog['id'], $user_id) || !$this->eligible($prog, $user_id)) {
                continue;
            }
            try {
                $this->issue($prog['id'], $user_id, null);
                $n++;
            } catch (Exception $e) {
                log_message('error', 'auto certification: ' . $e->getMessage());
            }
        }
        return $n;
    }

    public function revoke($id, $reason, $actor_id) {
        $c = $this->CI->db->get_where('ha_certificate', array('id' => (int) $id))->row_array();
        if (!$c || $c['status'] !== 'issued') {
            throw new InvalidArgumentException('Only an issued certificate can be revoked.');
        }
        if (trim((string) $reason) === '') {
            throw new InvalidArgumentException('Record why the certificate is revoked.');
        }
        $this->CI->db->where('id', (int) $id)->update('ha_certificate', array('status' => 'revoked', 'revoked_reason' => mb_substr($reason, 0, 500),
            'revoked_by' => (int) $actor_id, 'revoked_at' => date('Y-m-d H:i:s'), 'updated_at' => date('Y-m-d H:i:s')));
        $this->CI->ha_audit->log('certificate_revoke', 'certificate', (int) $id, array('description' => $c['certificate_no'] . ' revoked: ' . $reason,
            'property_id' => $c['property_id'], 'organization_id' => $c['organization_id']));
    }

    /** Marks expired certificates and warns holders whose expiry is near. */
    public function sweep_expiry() {
        $now = date('Y-m-d H:i:s');
        $expired = $this->CI->db->where('status', 'issued')->where('expires_at IS NOT NULL', null, false)->where('expires_at <=', $now)->get('ha_certificate')->result_array();
        foreach ($expired as $c) {
            $this->CI->db->where('id', $c['id'])->update('ha_certificate', array('status' => 'expired', 'updated_at' => $now));
        }
        $days = (int) $this->CI->ha_tenant->get('certificate.expiry_warning_days');
        $soon = $this->CI->db->where('status', 'issued')->where('expires_at >', $now)->where('expires_at <=', date('Y-m-d H:i:s', strtotime('+' . $days . ' days')))
            ->get('ha_certificate')->result_array();
        $warned = 0;
        foreach ($soon as $c) {
            $key = 'cert_expiring:' . $c['id'];
            if ($this->CI->db->where('dedupe_key', $key)->count_all_results('ha_alert')) {
                continue;
            }
            $this->CI->ha_notify->send($c['user_id'], 'certificate.expiring', array('certificate_name' => $c['subject_title_en'],
                'certificate_number' => $c['certificate_no'], 'expiry_date' => substr($c['expires_at'], 0, 10),
                'url' => hkp_url('certificates/view/' . $c['id']), 'related_type' => 'certificate', 'related_id' => $c['id']));
            $this->CI->ha_notify->alert(array('type' => 'certificate_expiring', 'severity' => 'warning', 'dedupe_key' => $key,
                'organization_id' => $c['organization_id'], 'property_id' => $c['property_id'],
                'title_en' => 'Certificate expiring: ' . $c['certificate_no'], 'title_ar' => 'شهادة قاربت على الانتهاء: ' . $c['certificate_no'],
                'entity_type' => 'certificate', 'entity_id' => $c['id'], 'url' => hkp_url('team/certifications')));
            $warned++;
        }
        return array('expired' => count($expired), 'warned' => $warned);
    }

    // --------------------------------------------------------------- verify

    public function verify_url(array $cert) {
        return site_url('verify/' . rawurlencode($cert['certificate_no']));
    }

    /**
     * Public verification by certificate number or verification code. Returns
     * only what is needed to confirm a certificate: never email, employee
     * number or assessment detail.
     */
    public function verify($code) {
        $code = trim((string) $code);
        $cert = null;
        if ($code !== '') {
            $cert = $this->CI->db->where('certificate_no', $code)->or_where('verification_code', $code)->get('ha_certificate')->row_array();
        }
        $result = 'not_found';
        if ($cert) {
            if ($cert['status'] === 'revoked') {
                $result = 'revoked';
            } elseif ($cert['status'] === 'expired' || ($cert['expires_at'] && strtotime($cert['expires_at']) < time())) {
                $result = 'expired';
            } else {
                $result = 'valid';
            }
        }
        $this->CI->db->insert('ha_certificate_verification', array('certificate_id' => $cert ? (int) $cert['id'] : null,
            'submitted_code' => mb_substr($code, 0, 64), 'result' => $result,
            'ip_address' => isset($_SERVER['REMOTE_ADDR']) ? substr($_SERVER['REMOTE_ADDR'], 0, 64) : (is_cli() ? 'cli' : null),
            'user_agent' => isset($_SERVER['HTTP_USER_AGENT']) ? substr($_SERVER['HTTP_USER_AGENT'], 0, 255) : null, 'verified_at' => date('Y-m-d H:i:s')));
        if (!$cert) {
            return array('result' => 'not_found');
        }
        $prop = $cert['property_id'] ? $this->CI->db->select('name_en, name_ar')->get_where('ha_property', array('id' => $cert['property_id']))->row_array() : null;
        return array(
            'result' => $result, 'certificate_no' => $cert['certificate_no'],
            'title_en' => $cert['subject_title_en'], 'title_ar' => $cert['subject_title_ar'],
            'holder_en' => $cert['recipient_name_en'], 'holder_ar' => $cert['recipient_name_ar'] ?: $cert['recipient_name_en'],
            'property_en' => $prop ? $prop['name_en'] : null, 'property_ar' => $prop ? $prop['name_ar'] : null,
            'issued_at' => substr($cert['issued_at'], 0, 10), 'expires_at' => $cert['expires_at'] ? substr($cert['expires_at'], 0, 10) : null,
            'issuer_en' => $cert['issuer_en'] ?: 'Altus Advisory', 'issuer_ar' => $cert['issuer_ar'] ?: 'ألتوس للاستشارات',
        );
    }

    // --------------------------------------------------------------- render

    /** Draws the certificate as a GD image (A4 landscape, 150 dpi) with the property's brand and a QR code. */
    public function image(array $cert) {
        if (!function_exists('imagettftext')) {
            throw new RuntimeException('GD with FreeType is required to render certificates.');
        }
        $this->CI->load->library(array('ha_video_renderer', 'ha_qr'));
        require_once APPPATH . 'libraries/Ha_arabic.php';
        $brand = $this->CI->ha_tenant->brand($cert['property_id'], $cert['organization_id']);
        $W = 1754;
        $H = 1240;
        $im = imagecreatetruecolor($W, $H);
        $rgb = function ($hex) use ($im) {
            $hex = ltrim($hex, '#');
            return imagecolorallocate($im, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
        };
        $surface = $rgb($brand['color_surface']);
        $ink = $rgb($brand['color_secondary']);
        $primary = $rgb($brand['color_primary']);
        $accent = $rgb($brand['color_accent']);
        $muted = $rgb('#5B6470');
        imagefilledrectangle($im, 0, 0, $W, $H, $surface);
        imagesetthickness($im, 6);
        imagerectangle($im, 40, 40, $W - 41, $H - 41, $primary);
        imagesetthickness($im, 2);
        imagerectangle($im, 58, 58, $W - 59, $H - 59, $accent);
        imagefilledrectangle($im, 58, 58, $W - 59, 150, $primary);

        $latin = $this->CI->ha_video_renderer->font(false);
        $arabic = $this->CI->ha_video_renderer->font(true) ?: $latin;
        $brand_name = $brand['show_altus'] === 'client' || $brand['source'] !== 'platform' ? $brand['brand_name_en'] : 'ALTUS ADVISORY';
        $this->text($im, $brand_name, $latin, 26, $W / 2, 118, imagecolorallocate($im, 255, 255, 255), 'center');

        $logo = $this->logo_image($brand['logo_path']);
        if ($logo) {
            $lw = imagesx($logo);
            $lh = imagesy($logo);
            $th = 90;
            $tw = (int) round($lw * $th / max(1, $lh));
            imagecopyresampled($im, $logo, 110, 190, 0, 0, $tw, $th, $lw, $lh);
        }

        $this->text($im, 'CERTIFICATE OF COMPETENCE', $latin, 22, $W / 2, 330, $accent, 'center');
        $this->text($im, 'شهادة كفاءة', $arabic, 26, $W / 2, 385, $accent, 'center');
        $this->text($im, 'This certifies that', $latin, 18, $W / 2, 460, $muted, 'center');
        $this->text($im, $cert['recipient_name_en'], $latin, 44, $W / 2, 540, $ink, 'center');
        if (!empty($cert['recipient_name_ar'])) {
            $this->text($im, $cert['recipient_name_ar'], $arabic, 34, $W / 2, 600, $ink, 'center');
        }
        $this->text($im, 'has demonstrated the knowledge, practical competency and readiness required for', $latin, 17, $W / 2, 665, $muted, 'center');
        $this->text($im, $cert['subject_title_en'], $latin, 32, $W / 2, 730, $primary, 'center');
        $this->text($im, $cert['subject_title_ar'], $arabic, 28, $W / 2, 785, $primary, 'center');
        $line = array();
        if ($cert['role_title_en']) {
            $line[] = $cert['role_title_en'];
        }
        if ($cert['domain_title_en']) {
            $line[] = $cert['domain_title_en'];
        }
        $prop = $cert['property_id'] ? $this->CI->db->select('name_en, name_ar')->get_where('ha_property', array('id' => $cert['property_id']))->row_array() : null;
        if ($prop) {
            $line[] = $prop['name_en'];
        }
        if ($line) {
            $this->text($im, implode('  ·  ', $line), $latin, 17, $W / 2, 845, $ink, 'center');
        }

        $y = 980;
        $this->text($im, 'Certificate No.', $latin, 13, 140, $y, $muted);
        $this->text($im, $cert['certificate_no'], $latin, 19, 140, $y + 34, $ink);
        $this->text($im, 'Issued', $latin, 13, 520, $y, $muted);
        $this->text($im, substr($cert['issued_at'], 0, 10), $latin, 19, 520, $y + 34, $ink);
        $this->text($im, 'Valid until', $latin, 13, 760, $y, $muted);
        $this->text($im, $cert['expires_at'] ? substr($cert['expires_at'], 0, 10) : 'No expiry', $latin, 19, 760, $y + 34, $ink);
        $this->text($im, 'Issuing authority', $latin, 13, 1010, $y, $muted);
        $this->text($im, $cert['issuer_en'] ?: 'Altus Advisory', $latin, 19, 1010, $y + 34, $ink);
        if ($brand['signatory_name_en']) {
            $this->text($im, $brand['signatory_name_en'] . ($brand['signatory_title_en'] ? ', ' . $brand['signatory_title_en'] : ''), $latin, 15, 1010, $y + 72, $muted);
        }

        $qr = $this->CI->ha_qr->gd_image($this->verify_url($cert), 7, 2, 'M');
        $qs = imagesx($qr);
        imagecopy($im, $qr, $W - 110 - $qs, $H - 110 - $qs, 0, 0, $qs, $qs);
        $this->text($im, 'Scan to verify', $latin, 12, $W - 110 - $qs / 2, $H - 90, $muted, 'center');
        return $im;
    }

    protected function text($im, $text, $font, $size, $x, $y, $color, $align = 'left') {
        $text = (string) $text;
        if ($text === '' || !$font) {
            return;
        }
        if (Ha_arabic::has_arabic($text)) {
            $text = Ha_arabic::visual(Ha_arabic::shape($text));
        }
        $box = imagettfbbox($size, 0, $font, $text);
        $w = abs($box[2] - $box[0]);
        if ($align === 'center') {
            $x = $x - $w / 2;
        } elseif ($align === 'right') {
            $x = $x - $w;
        }
        imagettftext($im, $size, 0, (int) round($x), (int) round($y), $color, $font, $text);
    }

    protected function logo_image($path) {
        if (!$path) {
            return null;
        }
        $file = FCPATH . ltrim($path, '/');
        if (!is_file($file)) {
            return null;
        }
        $info = @getimagesize($file);
        if (!$info) {
            return null;
        }
        switch ($info[2]) {
            case IMAGETYPE_PNG:  return @imagecreatefrompng($file) ?: null;
            case IMAGETYPE_JPEG: return @imagecreatefromjpeg($file) ?: null;
            case IMAGETYPE_WEBP: return function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($file) ?: null) : null;
        }
        return null;
    }

    public function png(array $cert) {
        $im = $this->image($cert);
        ob_start();
        imagepng($im);
        $bin = ob_get_clean();
        imagedestroy($im);
        return $bin;
    }

    public function pdf(array $cert) {
        $this->CI->load->library('ha_pdf');
        $im = $this->image($cert);
        $bin = $this->CI->ha_pdf->from_gd($im, 841.89, 595.28, 90, array('title' => $cert['subject_title_en'] . ' — ' . $cert['certificate_no'],
            'author' => $cert['issuer_en'] ?: 'Altus Advisory', 'subject' => 'Certificate ' . $cert['certificate_no']));
        imagedestroy($im);
        return $bin;
    }

    /** Certification dashboard numbers for a set of users (section 61). */
    public function dashboard(array $user_ids) {
        $user_ids = $user_ids ?: array(0);
        $db = $this->CI->db;
        $now = date('Y-m-d H:i:s');
        $days = (int) $this->CI->ha_tenant->get('certificate.expiry_warning_days');
        $issued = (int) $db->where_in('user_id', $user_ids)->where('status', 'issued')->count_all_results('ha_certificate');
        $expiring = (int) $db->where_in('user_id', $user_ids)->where('status', 'issued')->where('expires_at >', $now)
            ->where('expires_at <=', date('Y-m-d H:i:s', strtotime('+' . $days . ' days')))->count_all_results('ha_certificate');
        $expired = (int) $db->where_in('user_id', $user_ids)->where('status', 'expired')->count_all_results('ha_certificate');
        $holders = (int) $db->select('COUNT(DISTINCT user_id) n', false)->where_in('user_id', $user_ids)->where('status', 'issued')->get('ha_certificate')->row()->n;
        $by_property = $db->select('p.name_en, p.name_ar, COUNT(c.id) n', false)->from('ha_certificate c')->join('ha_property p', 'p.id = c.property_id', 'left')
            ->where_in('c.user_id', $user_ids)->where('c.status', 'issued')->group_by(array('c.property_id', 'p.name_en', 'p.name_ar'))->get()->result_array();
        $by_role = $db->select('c.role_title_en AS name_en, c.role_title_ar AS name_ar, COUNT(c.id) n', false)->from('ha_certificate c')
            ->where_in('c.user_id', $user_ids)->where('c.status', 'issued')->group_by(array('c.role_title_en', 'c.role_title_ar'))->get()->result_array();
        $by_dept = $db->select('d.name_en, d.name_ar, COUNT(c.id) n', false)->from('ha_certificate c')->join('ha_profile pr', 'pr.user_id = c.user_id')
            ->join('ha_department d', 'd.id = pr.department_id', 'left')->where_in('c.user_id', $user_ids)->where('c.status', 'issued')
            ->group_by(array('pr.department_id', 'd.name_en', 'd.name_ar'))->get()->result_array();
        $verifications = $db->select('v.result, COUNT(*) n', false)->from('ha_certificate_verification v')->join('ha_certificate c', 'c.id = v.certificate_id')
            ->where_in('c.user_id', $user_ids)->where('v.verified_at >=', date('Y-m-d H:i:s', strtotime('-90 days')))->group_by('v.result')->get()->result_array();
        return array('issued' => $issued, 'expiring' => $expiring, 'expired' => $expired, 'holders' => $holders,
            'rate' => count($user_ids) ? round(100 * $holders / count($user_ids), 1) : 0,
            'by_property' => $by_property, 'by_role' => $by_role, 'by_department' => $by_dept, 'verifications' => $verifications);
    }
}
