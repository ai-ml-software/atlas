<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Scheduled and background work for altus HK&P (ppt-features 146, 147).
 *
 *   php index.php hkp_cli daily     reminders, overdue sweeps, certificate expiry,
 *                                   knowledge review reminders, readiness recalculation,
 *                                   department readiness alerts, email flush
 *   php index.php hkp_cli work      email flush + queued jobs (run every few minutes)
 *   php index.php hkp_cli index     rebuild the approved-knowledge AI index
 *   php index.php hkp_cli status    counts
 *
 * Every step is independent: one failing step is logged and the rest still run.
 */
class Hkp_cli extends CI_Controller {

    public function __construct() {
        parent::__construct();
        if (!is_cli()) {
            show_404();
        }
        @set_time_limit(0);
        $this->load->database();
        $this->load->helper(array('url', 'hkp'));
        $this->load->library(array('ha_tenant', 'ha_notify'));
    }

    private function out($s) {
        fwrite(STDOUT, $s . PHP_EOL);
    }

    private function step($name, callable $fn) {
        try {
            $r = $fn();
            $this->out(str_pad($name, 34) . ' ' . (is_array($r) ? json_encode($r) : (string) $r));
            return $r;
        } catch (Exception $e) {
            log_message('error', 'hkp_cli ' . $name . ': ' . $e->getMessage());
            $this->out(str_pad($name, 34) . ' FAILED: ' . $e->getMessage());
            return null;
        }
    }

    public function daily() {
        $report = array('started' => date('c'));
        $report['learning'] = $this->step('learning reminders / overdue', function () {
            $this->load->library('ha_learning');
            return $this->ha_learning->sweep();
        });
        $report['actions'] = $this->step('action plans overdue', function () {
            $this->load->library('ha_action_plans');
            return $this->ha_action_plans->sweep_overdue();
        });
        $report['certificates'] = $this->step('certificate expiry', function () {
            $this->load->library('ha_certification');
            return $this->ha_certification->sweep_expiry();
        });
        $report['knowledge'] = $this->step('knowledge review due', function () {
            $days = (int) $this->ha_tenant->get('knowledge.review_warning_days');
            $rows = $this->db->select('d.id, d.owner_user_id, v.version_label, v.review_date, t.title')->from('ha_sop_document d')
                ->join('ha_sop_version v', 'v.id = d.current_version_id')->join('ha_sop_version_translation t', "t.version_id = v.id AND t.locale = 'en'", 'left')
                ->where('d.status', 'published')->where('d.owner_user_id IS NOT NULL', null, false)->where('v.review_date <=', date('Y-m-d', strtotime('+' . $days . ' days')))->get()->result_array();
            $n = 0;
            foreach ($rows as $r) {
                $key = 'review:' . $r['id'] . ':' . $r['review_date'];
                if ($this->db->where('dedupe_key', $key)->count_all_results('ha_alert')) {
                    continue;
                }
                $this->ha_notify->send($r['owner_user_id'], 'knowledge.review_due', array('title' => $r['title'], 'review_date' => $r['review_date'],
                    'url' => hkp_url('admin/content/edit/' . $r['id']), '_no_manager' => 1));
                $this->ha_notify->alert(array('type' => 'knowledge_review', 'severity' => 'info', 'dedupe_key' => $key,
                    'title_en' => 'Review due: ' . $r['title'], 'title_ar' => 'موعد مراجعة: ' . $r['title'], 'url' => hkp_url('admin/content/edit/' . $r['id'])));
                $n++;
            }
            return $n;
        });
        $report['readiness'] = $this->step('readiness recalculation', function () {
            $this->load->library(array('ha_readiness', 'ha_auth'));
            $ids = array_map('intval', array_column($this->db->select('user_id')->where('status', 'active')->where('job_role_id IS NOT NULL', null, false)->get('ha_profile')->result_array(), 'user_id'));
            return $this->ha_readiness->recalculate_all($ids);
        });
        $report['department_alerts'] = $this->step('department readiness alerts', function () {
            $rows = $this->db->query("SELECT p.property_id, p.department_id, d.name_en, d.name_ar, pr.organization_id, COUNT(*) total, SUM(rr.status = 'ready') ready
                FROM ha_profile p JOIN ha_department d ON d.id = p.department_id JOIN ha_property pr ON pr.id = p.property_id
                LEFT JOIN ha_readiness_record rr ON rr.user_id = p.user_id AND rr.is_current = 1
                WHERE p.status = 'active' AND p.job_role_id IS NOT NULL GROUP BY p.property_id, p.department_id, d.name_en, d.name_ar, pr.organization_id")->result_array();
            $n = 0;
            foreach ($rows as $r) {
                $pct = $r['total'] ? 100 * $r['ready'] / $r['total'] : 0;
                $key = 'dept_ready:' . $r['property_id'] . ':' . $r['department_id'];
                if ($pct < (float) $this->ha_tenant->get('readiness.department_alert_below', $r['property_id'])) {
                    $this->ha_notify->alert(array('type' => 'department_readiness', 'severity' => 'warning', 'dedupe_key' => $key,
                        'organization_id' => $r['organization_id'], 'property_id' => $r['property_id'], 'department_id' => $r['department_id'],
                        'title_en' => $r['name_en'] . ' readiness ' . round($pct) . '%', 'title_ar' => 'جاهزية ' . $r['name_ar'] . ' ' . round($pct) . '%',
                        'url' => hkp_url('team/readiness')));
                    $n++;
                } else {
                    $this->ha_notify->resolve_alert($key);
                }
            }
            return $n;
        });
        $report['email'] = $this->step('email flush', function () { return $this->ha_notify->flush_email(200); });
        $report['finished'] = date('c');
        @file_put_contents(APPPATH . 'cache/hkp_daily.json', json_encode($report));
    }

    public function work() {
        $this->step('email flush', function () { return $this->ha_notify->flush_email(100); });
        $this->step('queued jobs', function () {
            $n = 0;
            foreach ($this->db->where('status', 'queued')->where('available_at <=', date('Y-m-d H:i:s'))->order_by('id')->limit(20)->get('ha_queue_job')->result_array() as $j) {
                $this->db->where(array('id' => $j['id'], 'status' => 'queued'))->update('ha_queue_job', array('status' => 'running', 'started_at' => date('Y-m-d H:i:s'), 'attempts' => $j['attempts'] + 1));
                if (!$this->db->affected_rows()) {
                    continue;
                }
                try {
                    $p = json_decode((string) $j['payload_json'], true) ?: array();
                    $this->load->library('ha_governed_ai');
                    switch ($j['job_type']) {
                        case 'ai_index_knowledge': $this->ha_governed_ai->index_knowledge((int) $p['id']); break;
                        case 'ai_index_course':    $this->ha_governed_ai->index_course((int) $p['id']); break;
                        case 'ai_reindex':         $this->ha_governed_ai->reindex_all(); break;
                        default: throw new RuntimeException('Unknown job type ' . $j['job_type']);
                    }
                    $this->db->where('id', $j['id'])->update('ha_queue_job', array('status' => 'done', 'finished_at' => date('Y-m-d H:i:s')));
                    $n++;
                } catch (Exception $e) {
                    $this->db->where('id', $j['id'])->update('ha_queue_job', array('status' => $j['attempts'] + 1 >= 3 ? 'failed' : 'queued',
                        'last_error' => $e->getMessage(), 'available_at' => date('Y-m-d H:i:s', time() + 300)));
                }
            }
            return $n;
        });
    }

    public function index() {
        $this->load->library('ha_governed_ai');
        $this->step('reindex', function () { return $this->ha_governed_ai->reindex_all(); });
    }

    public function status() {
        foreach (array('ha_competency_gap', 'ha_action_plan', 'ha_readiness_record', 'ha_certificate', 'ha_ai_chunk', 'ha_ai_query', 'ha_notification', 'ha_alert', 'ha_queue_job') as $t) {
            $this->out(str_pad($t, 28) . $this->db->count_all($t));
        }
    }
}
