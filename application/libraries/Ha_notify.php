<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Central notification service (ppt-features 34, 118, 119, 139).
 *
 * Every event goes through send(): the organisation's rule decides whether it
 * fires and on which channels, the recipient's preference can switch a channel
 * off, the template is rendered in the recipient's language with {{variables}},
 * and one ha_notification row is stored per channel so there is a record of
 * every notice whether or not delivery succeeded.
 *
 * In-app delivery is immediate. Email is sent when the host has mail
 * configured and otherwise left pending for the queue; SMS and WhatsApp are
 * architected (rows, templates, preferences) and recorded as skipped until a
 * provider is connected, which is stated rather than pretended.
 */
class Ha_notify {

    protected $CI;

    /** event_code => category, default channels, notify manager */
    public static function events() {
        return array(
            'assignment.new'          => array('training', 'in_app,email', 0),
            'assignment.due_soon'     => array('training', 'in_app,email', 0),
            'assignment.overdue'      => array('training', 'in_app,email', 1),
            'assessment.passed'       => array('assessment', 'in_app', 0),
            'assessment.failed'       => array('assessment', 'in_app', 0),
            'assessment.repeated_fail'=> array('alert', 'in_app,email', 1),
            'practical.result'        => array('competency', 'in_app', 0),
            'gap.critical'            => array('competency', 'in_app,email', 1),
            'action.assigned'         => array('action', 'in_app,email', 0),
            'action.submitted'        => array('action', 'in_app', 0),
            'action.completed'        => array('action', 'in_app', 0),
            'action.rejected'         => array('action', 'in_app,email', 0),
            'action.overdue'          => array('action', 'in_app,email', 1),
            'reassessment.available'  => array('competency', 'in_app', 0),
            'readiness.changed'       => array('readiness', 'in_app', 0),
            'certificate.issued'      => array('certificates', 'in_app,email', 0),
            'certificate.expiring'    => array('certificates', 'in_app,email', 1),
            'knowledge.mandatory'     => array('knowledge', 'in_app,email', 0),
            'knowledge.review_due'    => array('knowledge', 'in_app,email', 0),
            'knowledge.submitted'     => array('knowledge', 'in_app', 0),
            'knowledge.rejected'      => array('knowledge', 'in_app', 0),
            'manager.alert'           => array('alert', 'in_app,email', 0),
        );
    }

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
    }

    /**
     * @param int    $user_id
     * @param string $event   one of events()
     * @param array  $vars    template variables; url, related_type, related_id are also read from here
     * @return int number of notification rows written
     */
    public function send($user_id, $event, array $vars = array()) {
        $events = self::events();
        if (!isset($events[$event])) {
            throw new InvalidArgumentException('Unknown notification event: ' . $event);
        }
        $user = $this->CI->db->select('u.id, u.email, u.first_name, u.last_name, p.locale, p.organization_id, p.manager_user_id')
            ->from('users u')->join('ha_profile p', 'p.user_id = u.id', 'left')
            ->where('u.id', (int) $user_id)->get()->row_array();
        if (!$user) {
            return 0;
        }
        $org = (int) $user['organization_id'];
        $rule = $this->rule($event, $org);
        if (!$rule['enabled']) {
            return 0;
        }
        $vars += array('employee_name' => trim($user['first_name'] . ' ' . $user['last_name']));
        $written = $this->deliver($user, $event, $vars, $rule['channels']);

        if ($rule['notify_manager'] && !empty($user['manager_user_id']) && empty($vars['_no_manager'])) {
            $manager = $this->CI->db->select('u.id, u.email, u.first_name, u.last_name, p.locale, p.organization_id')
                ->from('users u')->join('ha_profile p', 'p.user_id = u.id', 'left')
                ->where('u.id', (int) $user['manager_user_id'])->get()->row_array();
            if ($manager) {
                $written += $this->deliver($manager, $event, $vars + array('manager_copy' => 1), $rule['channels']);
            }
        }
        return $written;
    }

    public function rule($event, $organization_id = 0) {
        $events = self::events();
        $default = array('enabled' => 1, 'channels' => explode(',', $events[$event][1]), 'notify_manager' => (int) $events[$event][2]);
        foreach (array((int) $organization_id, 0) as $org) {
            $row = $this->CI->db->get_where('ha_notification_rule', array('event_code' => $event, 'organization_id' => $org))->row_array();
            if ($row) {
                return array('enabled' => (int) $row['enabled'], 'channels' => array_filter(explode(',', $row['channels'])),
                    'notify_manager' => (int) $row['notify_manager']);
            }
        }
        return $default;
    }

    protected function deliver(array $user, $event, array $vars, array $channels) {
        $events = self::events();
        $pref = $this->CI->db->get_where('ha_notification_preference', array('user_id' => $user['id'], 'event_code' => $event))->row_array();
        $count = 0;
        $en = $this->render($event, 'in_app', 'en', (int) $user['organization_id'], $vars);
        $ar = $this->render($event, 'in_app', 'ar', (int) $user['organization_id'], $vars);
        foreach ($channels as $channel) {
            $channel = trim($channel);
            if ($pref && isset($pref[$channel]) && !(int) $pref[$channel]) {
                continue;
            }
            $row = array(
                'user_id' => (int) $user['id'], 'category' => $events[$event][0], 'event_code' => $event,
                'title_en' => mb_substr($en['subject'], 0, 255), 'title_ar' => mb_substr($ar['subject'], 0, 255),
                'body_en' => $en['body'], 'body_ar' => $ar['body'],
                'action_url' => isset($vars['url']) ? mb_substr((string) $vars['url'], 0, 500) : null,
                'related_type' => isset($vars['related_type']) ? $vars['related_type'] : null,
                'related_id' => isset($vars['related_id']) ? (int) $vars['related_id'] : null,
                'channel' => $channel, 'created_at' => date('Y-m-d H:i:s'),
            );
            if ($channel === 'in_app') {
                $row['delivery_status'] = 'sent';
                $row['sent_at'] = $row['created_at'];
            } elseif ($channel === 'email') {
                $row['delivery_status'] = 'pending';
            } else {
                $row['delivery_status'] = 'skipped';
                $row['delivery_error'] = 'No ' . $channel . ' provider is connected yet.';
            }
            $this->CI->db->insert('ha_notification', $row);
            $count++;
        }
        return $count;
    }

    /** Renders the template for an event, falling back to the built-in wording. */
    public function render($event, $channel, $locale, $organization_id, array $vars) {
        $tpl = null;
        foreach (array((int) $organization_id, 0) as $org) {
            foreach (array($channel, 'in_app') as $ch) {
                $tpl = $this->CI->db->get_where('ha_notification_template',
                    array('event_code' => $event, 'channel' => $ch, 'locale' => $locale, 'organization_id' => $org))->row_array();
                if ($tpl) {
                    break 2;
                }
            }
        }
        if (!$tpl) {
            $defaults = self::default_templates();
            $d = isset($defaults[$event][$locale]) ? $defaults[$event][$locale] : array($event, '');
            $tpl = array('subject' => $d[0], 'body' => $d[1]);
        }
        return array('subject' => $this->fill($tpl['subject'], $vars), 'body' => $this->fill($tpl['body'], $vars));
    }

    public function fill($text, array $vars) {
        return preg_replace_callback('/\{\{\s*([a-z0-9_]+)\s*\}\}/i', function ($m) use ($vars) {
            return isset($vars[$m[1]]) && !is_array($vars[$m[1]]) ? (string) $vars[$m[1]] : '';
        }, (string) $text);
    }

    /** Sends pending email rows. Called by the scheduler; safe to run repeatedly. */
    public function flush_email($limit = 50) {
        $rows = $this->CI->db->select('n.*, u.email, p.locale')->from('ha_notification n')
            ->join('users u', 'u.id = n.user_id')->join('ha_profile p', 'p.user_id = n.user_id', 'left')
            ->where('n.channel', 'email')->where('n.delivery_status', 'pending')->order_by('n.id')->limit($limit)->get()->result_array();
        if (!$rows) {
            return array('sent' => 0, 'failed' => 0);
        }
        // A development copy (the local database override is present) never emails real people.
        $this->CI->load->library('ha_tenant');
        $mode = (string) $this->CI->ha_tenant->get('email.delivery');
        if ($mode === 'log' || ($mode === 'auto' && is_file(APPPATH . 'config/database.local.php'))) {
            $ids = array_column($rows, 'id');
            $this->CI->db->where_in('id', $ids)->update('ha_notification', array('delivery_status' => 'skipped',
                'delivery_error' => 'Email delivery is off on this installation (email.delivery = ' . $mode . ').'));
            return array('sent' => 0, 'failed' => 0, 'skipped' => count($ids));
        }
        $get = function ($k) { return function_exists('get_settings') ? (string) get_settings($k) : ''; };
        $protocol = $get('protocol') ?: 'mail';
        $smtp_host = $get('smtp_host');
        $this->CI->load->library('email');
        $cfg = array('protocol' => $protocol, 'mailtype' => 'html', 'charset' => 'utf-8', 'newline' => "\r\n", 'crlf' => "\r\n");
        if ($protocol === 'smtp') {
            $port = (int) $get('smtp_port') ?: 587;
            $cfg += array('smtp_host' => $smtp_host, 'smtp_port' => $port, 'smtp_user' => $get('smtp_user'), 'smtp_pass' => $get('smtp_pass'),
                'smtp_crypto' => $port === 465 ? 'ssl' : ($port === 587 ? 'tls' : ''), 'smtp_timeout' => 15);
        }
        $this->CI->email->initialize($cfg);
        $sent = 0;
        $failed = 0;
        foreach ($rows as $r) {
            if (!$smtp_host && $protocol === 'smtp') {
                $this->CI->db->where('id', $r['id'])->update('ha_notification', array('delivery_status' => 'failed', 'delivery_error' => 'SMTP is selected but no SMTP host is configured.'));
                $failed++;
                continue;
            }
            $ar = $r['locale'] === 'ar';
            $subject = $ar ? $r['title_ar'] : $r['title_en'];
            $body = $ar ? $r['body_ar'] : $r['body_en'];
            $ok = false;
            $error = null;
            try {
                $this->CI->email->clear();
                $from = function_exists('get_settings') ? get_settings('system_email') : 'no-reply@localhost';
                $this->CI->email->from($from ?: 'no-reply@localhost');
                $this->CI->email->to($r['email']);
                $this->CI->email->subject($subject);
                $this->CI->email->message('<div dir="' . ($ar ? 'rtl' : 'ltr') . '">' . nl2br(htmlspecialchars($body, ENT_QUOTES, 'UTF-8')) . '</div>');
                $ok = @$this->CI->email->send(false);
                if (!$ok) {
                    $error = 'The mail transport refused the message.';
                }
            } catch (Exception $e) {
                $error = $e->getMessage();
            }
            $this->CI->db->where('id', $r['id'])->update('ha_notification', $ok
                ? array('delivery_status' => 'sent', 'sent_at' => date('Y-m-d H:i:s'))
                : array('delivery_status' => 'failed', 'delivery_error' => mb_substr((string) $error, 0, 500)));
            $ok ? $sent++ : $failed++;
        }
        return array('sent' => $sent, 'failed' => $failed);
    }

    public function unread_count($user_id) {
        return $this->CI->db->where(array('user_id' => (int) $user_id, 'channel' => 'in_app'))
            ->where('read_at IS NULL', null, false)->count_all_results('ha_notification');
    }

    public function inbox($user_id, $limit = 50) {
        return $this->CI->db->where(array('user_id' => (int) $user_id, 'channel' => 'in_app'))
            ->order_by('id', 'DESC')->limit($limit)->get('ha_notification')->result_array();
    }

    public function mark_read($user_id, $id = null) {
        $this->CI->db->where(array('user_id' => (int) $user_id, 'channel' => 'in_app'))->where('read_at IS NULL', null, false);
        if ($id) {
            $this->CI->db->where('id', (int) $id);
        }
        $this->CI->db->update('ha_notification', array('read_at' => date('Y-m-d H:i:s')));
    }

    /** Opens (or refreshes) a manager alert; dedupe_key stops the same alert stacking up. */
    public function alert(array $a) {
        $key = mb_substr($a['dedupe_key'], 0, 190);
        $existing = $this->CI->db->get_where('ha_alert', array('dedupe_key' => $key))->row_array();
        $row = array(
            'organization_id' => isset($a['organization_id']) ? $a['organization_id'] : null,
            'property_id' => isset($a['property_id']) ? $a['property_id'] : null,
            'department_id' => isset($a['department_id']) ? $a['department_id'] : null,
            'alert_type' => $a['type'], 'severity' => isset($a['severity']) ? $a['severity'] : 'warning',
            'title_en' => mb_substr($a['title_en'], 0, 255), 'title_ar' => mb_substr($a['title_ar'], 0, 255),
            'detail' => isset($a['detail']) ? mb_substr($a['detail'], 0, 500) : null,
            'entity_type' => isset($a['entity_type']) ? $a['entity_type'] : null,
            'entity_id' => isset($a['entity_id']) ? (int) $a['entity_id'] : null,
            'url' => isset($a['url']) ? $a['url'] : null,
        );
        if ($existing) {
            if ($existing['status'] === 'resolved') {
                $row['status'] = 'open';
                $row['resolved_at'] = null;
                $row['created_at'] = date('Y-m-d H:i:s');
            }
            $this->CI->db->where('id', $existing['id'])->update('ha_alert', $row);
            return (int) $existing['id'];
        }
        $row['dedupe_key'] = $key;
        $row['status'] = 'open';
        $row['created_at'] = date('Y-m-d H:i:s');
        $this->CI->db->insert('ha_alert', $row);
        return (int) $this->CI->db->insert_id();
    }

    public function resolve_alert($dedupe_key) {
        $this->CI->db->where('dedupe_key', $dedupe_key)->where('status !=', 'resolved')
            ->update('ha_alert', array('status' => 'resolved', 'resolved_at' => date('Y-m-d H:i:s')));
    }

    /** Built-in bilingual wording, used until an administrator edits a template. */
    public static function default_templates() {
        return array(
            'assignment.new' => array(
                'en' => array('New learning assigned: {{course_name}}', 'You have been assigned {{course_name}}. Due {{due_date}}.'),
                'ar' => array('تعلم جديد مسند إليك: {{course_name}}', 'أُسند إليك {{course_name}}. تاريخ الاستحقاق {{due_date}}.')),
            'assignment.due_soon' => array(
                'en' => array('Due soon: {{course_name}}', '{{course_name}} is due on {{due_date}}.'),
                'ar' => array('يقترب موعد: {{course_name}}', 'موعد استحقاق {{course_name}} هو {{due_date}}.')),
            'assignment.overdue' => array(
                'en' => array('Overdue: {{course_name}}', '{{employee_name}} has not completed {{course_name}}, due {{due_date}}.'),
                'ar' => array('متأخر: {{course_name}}', 'لم يكمل {{employee_name}} {{course_name}} المستحق في {{due_date}}.')),
            'assessment.passed' => array(
                'en' => array('Assessment passed: {{assessment_name}}', 'You scored {{score}}%.'),
                'ar' => array('اجتزت التقييم: {{assessment_name}}', 'حصلت على {{score}}%.')),
            'assessment.failed' => array(
                'en' => array('Assessment not passed: {{assessment_name}}', 'You scored {{score}}%. The pass mark is {{pass_mark}}%.'),
                'ar' => array('لم تجتز التقييم: {{assessment_name}}', 'حصلت على {{score}}%. درجة النجاح {{pass_mark}}%.')),
            'assessment.repeated_fail' => array(
                'en' => array('Repeated fail: {{assessment_name}}', '{{employee_name}} has failed {{assessment_name}} {{attempts}} times.'),
                'ar' => array('إخفاق متكرر: {{assessment_name}}', 'أخفق {{employee_name}} في {{assessment_name}} {{attempts}} مرات.')),
            'practical.result' => array(
                'en' => array('Practical assessment recorded: {{competency_name}}', 'Outcome: {{outcome}}.'),
                'ar' => array('سُجل التقييم العملي: {{competency_name}}', 'النتيجة: {{outcome}}.')),
            'gap.critical' => array(
                'en' => array('Critical competency gap: {{competency_name}}', '{{employee_name}} is at level {{current_level}} of {{required_level}} required.'),
                'ar' => array('فجوة كفاءة حرجة: {{competency_name}}', 'مستوى {{employee_name}} هو {{current_level}} من {{required_level}} مطلوب.')),
            'action.assigned' => array(
                'en' => array('Action plan assigned: {{action_title}}', 'Due {{due_date}}.'),
                'ar' => array('خطة تحسين مسندة: {{action_title}}', 'الاستحقاق {{due_date}}.')),
            'action.submitted' => array(
                'en' => array('Evidence submitted: {{action_title}}', '{{employee_name}} submitted evidence for review.'),
                'ar' => array('تم تقديم الأدلة: {{action_title}}', 'قدّم {{employee_name}} الأدلة للمراجعة.')),
            'action.completed' => array(
                'en' => array('Action plan completed: {{action_title}}', 'A reassessment can now be scheduled.'),
                'ar' => array('اكتملت خطة التحسين: {{action_title}}', 'يمكن الآن جدولة إعادة التقييم.')),
            'action.rejected' => array(
                'en' => array('Action plan returned: {{action_title}}', 'Reason: {{reason}}'),
                'ar' => array('أعيدت خطة التحسين: {{action_title}}', 'السبب: {{reason}}')),
            'action.overdue' => array(
                'en' => array('Action plan overdue: {{action_title}}', '{{employee_name}} — due {{due_date}}.'),
                'ar' => array('خطة تحسين متأخرة: {{action_title}}', '{{employee_name}} — الاستحقاق {{due_date}}.')),
            'reassessment.available' => array(
                'en' => array('Reassessment approved: {{competency_name}}', 'Your supervisor will reassess you.'),
                'ar' => array('اعتُمدت إعادة التقييم: {{competency_name}}', 'سيعيد مشرفك تقييمك.')),
            'readiness.changed' => array(
                'en' => array('Readiness updated: {{status}}', '{{reason}}'),
                'ar' => array('تحديث الجاهزية: {{status}}', '{{reason}}')),
            'certificate.issued' => array(
                'en' => array('Certificate issued: {{certificate_name}}', 'Certificate number {{certificate_number}}.'),
                'ar' => array('صدرت الشهادة: {{certificate_name}}', 'رقم الشهادة {{certificate_number}}.')),
            'certificate.expiring' => array(
                'en' => array('Certificate expiring: {{certificate_name}}', '{{certificate_number}} expires on {{expiry_date}}.'),
                'ar' => array('شهادة قاربت على الانتهاء: {{certificate_name}}', 'تنتهي الشهادة {{certificate_number}} في {{expiry_date}}.')),
            'knowledge.mandatory' => array(
                'en' => array('New mandatory content: {{title}}', 'Please read and acknowledge version {{version}}.'),
                'ar' => array('محتوى إلزامي جديد: {{title}}', 'يرجى قراءة الإصدار {{version}} والإقرار به.')),
            'knowledge.review_due' => array(
                'en' => array('Review due: {{title}}', 'Review date {{review_date}}.'),
                'ar' => array('موعد المراجعة: {{title}}', 'تاريخ المراجعة {{review_date}}.')),
            'knowledge.submitted' => array(
                'en' => array('Awaiting review: {{title}}', 'Version {{version}} was submitted by {{author}}.'),
                'ar' => array('بانتظار المراجعة: {{title}}', 'قدّم {{author}} الإصدار {{version}}.')),
            'knowledge.rejected' => array(
                'en' => array('Changes requested: {{title}}', '{{comment}}'),
                'ar' => array('مطلوب تعديلات: {{title}}', '{{comment}}')),
            'manager.alert' => array(
                'en' => array('{{title}}', '{{detail}}'),
                'ar' => array('{{title}}', '{{detail}}')),
        );
    }
}
