<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Learning engine (ppt-features 13-15, 98-101): assignments, role-based plans,
 * enrolment, lesson tracking, prerequisites, exemptions, reminders.
 *
 *   Role -> role requirements -> "role plan" assignment -> enrolments
 *   Manager -> assignment (user / role / department / property / organisation /
 *              cohort targets) -> recipients -> enrolments
 *
 * Recipients are resolved inside the assigning manager's own scope: a property
 * manager who targets "the Front Office department" reaches the Front Office
 * staff of their property, never those of another hotel.
 *
 * Learning progress is tracked per lesson (start, last position, time spent,
 * completion) and rolled up to the module and the assignment. Finishing
 * lessons completes a module; it never awards competency (Ha_competency).
 */
class Ha_learning {

    protected $CI;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->load->database();
        $this->CI->load->helper(array('url', 'hkp'));
        $this->CI->load->library(array('ha_auth', 'ha_audit', 'ha_notify', 'ha_tenant'));
    }

    // ------------------------------------------------------------- catalogue

    /** Can the user take this module? Published, and global or belonging to their organisation/property. */
    public function course_visible($course_id, $user_id) {
        $c = $this->CI->db->get_where('ha_course', array('id' => (int) $course_id))->row_array();
        if (!$c || $c['status'] !== 'published') {
            return false;
        }
        if (!$c['organization_id'] && !$c['property_id']) {
            return true;
        }
        $p = $this->CI->db->get_where('ha_profile', array('user_id' => (int) $user_id))->row_array();
        if ($this->CI->ha_auth->id() === (int) $user_id && $this->CI->ha_auth->is_system_scoped()) {
            return true;
        }
        if ($c['property_id']) {
            return $p && (int) $p['property_id'] === (int) $c['property_id'];
        }
        return $p && (int) $p['organization_id'] === (int) $c['organization_id'];
    }

    public function course($course_id, $locale = null) {
        $loc = $locale ?: hkp_locale();
        $c = $this->CI->db->select('c.*, t.title, t.short_description, t.description, te.title AS title_en')
            ->from('ha_course c')->join('ha_course_translation t', 't.course_id = c.id AND t.locale = ' . $this->CI->db->escape($loc), 'left')
            ->join('ha_course_translation te', "te.course_id = c.id AND te.locale = 'en'", 'left')
            ->where('c.id', (int) $course_id)->get()->row_array();
        if (!$c) {
            return null;
        }
        $c['title'] = $c['title'] ?: $c['title_en'];
        $c['sections'] = $this->CI->db->order_by('sort_order')->get_where('ha_course_section', array('course_id' => (int) $course_id))->result_array();
        $c['lessons'] = $this->CI->db->select('l.*, t.title, te.title AS title_en, t.objective')->from('ha_lesson l')
            ->join('ha_lesson_translation t', 't.lesson_id = l.id AND t.locale = ' . $this->CI->db->escape($loc), 'left')
            ->join('ha_lesson_translation te', "te.lesson_id = l.id AND te.locale = 'en'", 'left')
            ->where('l.course_id', (int) $course_id)->where('l.status', 'published')->order_by('l.sort_order')->get()->result_array();
        $c['assessments'] = $this->CI->db->where(array('course_id' => (int) $course_id, 'status' => 'published'))->get('ha_assessment')->result_array();
        $c['prerequisites'] = $this->CI->db->select('p.prerequisite_course_id, t.title')->from('ha_course_prerequisite p')
            ->join('ha_course_translation t', "t.course_id = p.prerequisite_course_id AND t.locale = 'en'", 'left')
            ->where('p.course_id', (int) $course_id)->get()->result_array();
        return $c;
    }

    /** Unmet prerequisites (module titles) for a user. */
    public function unmet_prerequisites($course_id, $user_id) {
        $rows = $this->CI->db->select('p.prerequisite_course_id AS id, t.title')->from('ha_course_prerequisite p')
            ->join('ha_course_translation t', "t.course_id = p.prerequisite_course_id AND t.locale = 'en'", 'left')
            ->where('p.course_id', (int) $course_id)->get()->result_array();
        $out = array();
        foreach ($rows as $r) {
            $done = $this->CI->db->where(array('user_id' => (int) $user_id, 'course_id' => (int) $r['id'], 'status' => 'completed'))->count_all_results('ha_enrollment');
            if (!$done) {
                $out[] = $r['title'] ?: '#' . $r['id'];
            }
        }
        return $out;
    }

    // ------------------------------------------------------------ enrolment

    public function enroll($user_id, $course_id, $source = 'self', array $opts = array()) {
        if (!$this->course_visible($course_id, $user_id)) {
            throw new RuntimeException('That module is not available to this person.');
        }
        $existing = $this->CI->db->get_where('ha_enrollment', array('user_id' => (int) $user_id, 'course_id' => (int) $course_id))->row_array();
        $due = isset($opts['due_at']) && $opts['due_at'] ? date('Y-m-d H:i:s', strtotime($opts['due_at'])) : null;
        if ($existing) {
            $upd = array('updated_at' => date('Y-m-d H:i:s'));
            if ($due && (!$existing['due_at'] || strtotime($due) < strtotime($existing['due_at'])) && $existing['status'] !== 'completed') {
                $upd['due_at'] = $due;
            }
            if (!empty($opts['assignment_id']) && !$existing['training_assignment_id']) {
                $upd['training_assignment_id'] = (int) $opts['assignment_id'];
            }
            if ($existing['status'] === 'cancelled') {
                $upd['status'] = 'active';
            }
            $this->CI->db->where('id', $existing['id'])->update('ha_enrollment', $upd);
            return (int) $existing['id'];
        }
        $total = (int) $this->CI->db->where(array('course_id' => (int) $course_id, 'status' => 'published'))->count_all_results('ha_lesson');
        $now = date('Y-m-d H:i:s');
        $this->CI->db->insert('ha_enrollment', array('user_id' => (int) $user_id, 'course_id' => (int) $course_id,
            'training_assignment_id' => !empty($opts['assignment_id']) ? (int) $opts['assignment_id'] : null,
            'source' => in_array($source, array('self', 'assigned', 'program', 'path', 'purchase', 'import'), true) ? $source : 'assigned',
            'status' => 'active', 'lessons_total' => $total, 'due_at' => $due, 'created_at' => $now, 'updated_at' => $now));
        return (int) $this->CI->db->insert_id();
    }

    // -------------------------------------------------------------- lessons

    public function lesson($lesson_id, $locale = null) {
        $loc = $locale ?: hkp_locale();
        $l = $this->CI->db->select('l.*, t.title, t.objective, t.body, t.transcript, t.captions_url, te.title AS title_en, te.body AS body_en, te.objective AS objective_en')
            ->from('ha_lesson l')->join('ha_lesson_translation t', 't.lesson_id = l.id AND t.locale = ' . $this->CI->db->escape($loc), 'left')
            ->join('ha_lesson_translation te', "te.lesson_id = l.id AND te.locale = 'en'", 'left')
            ->where('l.id', (int) $lesson_id)->get()->row_array();
        if (!$l) {
            return null;
        }
        $l['translated'] = trim((string) $l['body']) !== '' || $loc === 'en';
        $l['title'] = $l['title'] ?: $l['title_en'];
        $l['objective'] = $l['objective'] ?: $l['objective_en'];
        $l['body'] = trim((string) $l['body']) !== '' ? $l['body'] : $l['body_en'];
        $l['blocks'] = $this->CI->db->where('lesson_id', (int) $lesson_id)->where_in('locale', array('both', $loc))->order_by('sort_order')->get('ha_lesson_block')->result_array();
        $l['attachments'] = $this->CI->db->get_where('ha_lesson_attachment', array('lesson_id' => (int) $lesson_id))->result_array();
        return $l;
    }

    /** Opens a lesson for a learner: enrols if needed, marks it started, records the view. */
    public function open_lesson($user_id, $lesson_id) {
        $l = $this->CI->db->get_where('ha_lesson', array('id' => (int) $lesson_id))->row_array();
        if (!$l || $l['status'] !== 'published' || !$this->course_visible($l['course_id'], $user_id)) {
            throw new RuntimeException('That lesson is not available.');
        }
        $unmet = $this->unmet_prerequisites($l['course_id'], $user_id);
        if ($unmet) {
            throw new RuntimeException('Complete these modules first: ' . implode(', ', $unmet) . '.');
        }
        $eid = $this->enroll($user_id, $l['course_id'], 'self');
        $release = $this->release_at($l, $eid);
        if ($release && strtotime($release) > time()) {
            throw new RuntimeException('This lesson opens on ' . substr($release, 0, 16) . '.');
        }
        $now = date('Y-m-d H:i:s');
        $lp = $this->CI->db->get_where('ha_lesson_progress', array('enrollment_id' => $eid, 'lesson_id' => (int) $lesson_id))->row_array();
        if (!$lp) {
            $this->CI->db->insert('ha_lesson_progress', array('enrollment_id' => $eid, 'user_id' => (int) $user_id, 'lesson_id' => (int) $lesson_id,
                'status' => 'in_progress', 'created_at' => $now, 'updated_at' => $now));
            $lp = $this->CI->db->get_where('ha_lesson_progress', array('id' => $this->CI->db->insert_id()))->row_array();
        }
        $e = $this->CI->db->get_where('ha_enrollment', array('id' => $eid))->row_array();
        if (!$e['started_at']) {
            $this->CI->db->where('id', $eid)->update('ha_enrollment', array('started_at' => $now, 'updated_at' => $now));
        }
        $this->CI->db->insert('ha_content_view', array('user_id' => (int) $user_id, 'entity_type' => 'lesson', 'entity_id' => (int) $lesson_id, 'created_at' => $now));
        return array('enrollment_id' => $eid, 'progress' => $lp);
    }

    /**
     * Drip release: when a lesson opens for this enrolment, from a fixed date
     * or a number of days after enrolment, whichever is later. Null = open now.
     */
    public function release_at(array $lesson, $enrollment_id) {
        $times = array();
        if (!empty($lesson['available_from'])) {
            $times[] = strtotime($lesson['available_from']);
        }
        if (!empty($lesson['drip_days'])) {
            $e = $this->CI->db->select('created_at')->get_where('ha_enrollment', array('id' => (int) $enrollment_id))->row_array();
            if ($e) {
                $times[] = strtotime($e['created_at'] . ' +' . (int) $lesson['drip_days'] . ' days');
            }
        }
        return $times ? date('Y-m-d H:i:s', max($times)) : null;
    }

    /** Heartbeat from the player: time spent and last viewed position. */
    public function track($user_id, $lesson_id, $seconds, $position = null) {
        $seconds = max(0, min(600, (int) $seconds));
        $lp = $this->CI->db->get_where('ha_lesson_progress', array('user_id' => (int) $user_id, 'lesson_id' => (int) $lesson_id))->row_array();
        if (!$lp) {
            return false;
        }
        $upd = array('watched_seconds' => (int) $lp['watched_seconds'] + $seconds, 'updated_at' => date('Y-m-d H:i:s'));
        if ($position !== null) {
            $upd['last_position_seconds'] = max(0, (int) $position);
        }
        $l = $this->CI->db->select('duration_seconds')->get_where('ha_lesson', array('id' => (int) $lesson_id))->row_array();
        if ($l && (int) $l['duration_seconds'] > 0) {
            $upd['watched_percentage'] = min(100, round(100 * $upd['watched_seconds'] / (int) $l['duration_seconds'], 2));
        }
        $this->CI->db->where('id', $lp['id'])->update('ha_lesson_progress', $upd);
        $this->CI->db->set('time_spent_seconds', 'time_spent_seconds + ' . $seconds, false)->where('id', $lp['enrollment_id'])->update('ha_enrollment');
        return true;
    }

    /**
     * Completes a lesson when its completion rule is satisfied: an "assessment"
     * lesson needs a passed attempt; a "watch" lesson needs the required share.
     */
    public function complete_lesson($user_id, $lesson_id) {
        $l = $this->CI->db->get_where('ha_lesson', array('id' => (int) $lesson_id))->row_array();
        $lp = $this->CI->db->get_where('ha_lesson_progress', array('user_id' => (int) $user_id, 'lesson_id' => (int) $lesson_id))->row_array();
        if (!$l || !$lp) {
            throw new RuntimeException('Open the lesson before completing it.');
        }
        if ($l['completion_rule'] === 'quiz' && $l['assessment_id']) {
            $passed = $this->CI->db->where(array('assessment_id' => $l['assessment_id'], 'user_id' => (int) $user_id, 'passed' => 1, 'status' => 'graded'))
                ->count_all_results('ha_assessment_attempt');
            if (!$passed) {
                throw new RuntimeException('Pass the lesson checkpoint to complete this lesson.');
            }
        }
        if ($l['completion_rule'] === 'watch_percentage' && (int) $l['duration_seconds'] > 0
            && (float) $lp['watched_percentage'] < (float) $l['required_watch_percentage']) {
            throw new RuntimeException('Watch at least ' . (int) $l['required_watch_percentage'] . '% of the lesson first.');
        }
        $now = date('Y-m-d H:i:s');
        if ($lp['status'] !== 'completed') {
            $this->CI->db->where('id', $lp['id'])->update('ha_lesson_progress', array('status' => 'completed', 'completed_at' => $now,
                'acknowledged_at' => $l['completion_rule'] === 'acknowledge' ? $now : $lp['acknowledged_at'], 'updated_at' => $now));
        }
        return $this->refresh_enrollment($lp['enrollment_id']);
    }

    /** Recomputes a module's progress; completes it when every mandatory lesson and its assessments are done. */
    public function refresh_enrollment($enrollment_id) {
        $e = $this->CI->db->get_where('ha_enrollment', array('id' => (int) $enrollment_id))->row_array();
        if (!$e) {
            return null;
        }
        $lessons = $this->CI->db->select('id, is_mandatory')->get_where('ha_lesson', array('course_id' => $e['course_id'], 'status' => 'published'))->result_array();
        $done = array_map('intval', array_column($this->CI->db->select('lesson_id')->get_where('ha_lesson_progress',
            array('enrollment_id' => (int) $enrollment_id, 'status' => 'completed'))->result_array(), 'lesson_id'));
        $total = count($lessons);
        $mand_left = 0;
        $completed = 0;
        foreach ($lessons as $l) {
            if (in_array((int) $l['id'], $done, true)) {
                $completed++;
            } elseif ((int) $l['is_mandatory']) {
                $mand_left++;
            }
        }
        $pct = $total ? round(100 * $completed / $total, 2) : 0;
        $now = date('Y-m-d H:i:s');
        $upd = array('lessons_total' => $total, 'lessons_completed' => $completed, 'progress_percentage' => $pct, 'updated_at' => $now);
        // A module assessment must be passed for the module to count as complete.
        $open_assessments = 0;
        foreach ($this->CI->db->select('id')->get_where('ha_assessment', array('course_id' => $e['course_id'], 'status' => 'published'))->result_array() as $a) {
            if (!$this->CI->db->where(array('assessment_id' => $a['id'], 'user_id' => $e['user_id'], 'passed' => 1, 'status' => 'graded'))->count_all_results('ha_assessment_attempt')) {
                $open_assessments++;
            }
        }
        $became_complete = false;
        if ($total > 0 && $mand_left === 0 && $open_assessments === 0 && $e['status'] !== 'completed') {
            $upd['status'] = 'completed';
            $upd['completed_at'] = $now;
            $became_complete = true;
        }
        $this->CI->db->where('id', (int) $enrollment_id)->update('ha_enrollment', $upd);
        $this->refresh_recipients($e['user_id']);
        if ($became_complete) {
            $this->CI->ha_audit->log('complete', 'enrollment', (int) $enrollment_id, array('user_id' => $e['user_id'],
                'description' => 'Module ' . $e['course_id'] . ' completed by user ' . $e['user_id']));
            $this->CI->load->library('ha_readiness');
            $this->CI->ha_readiness->calculate($e['user_id']);
        }
        return $this->CI->db->get_where('ha_enrollment', array('id' => (int) $enrollment_id))->row_array() + array('assessments_left' => $open_assessments);
    }

    // ----------------------------------------------------------- assignments

    /**
     * Creates and dispatches an assignment.
     * $data: title_en, title_ar, instructions_en, due_at, is_mandatory, priority, property_id,
     *        items => array(array(type, id)), targets => array(array(type, id))
     */
    public function assign(array $data, $actor_id, $source = 'manual') {
        $auth = $this->CI->ha_auth;
        if ($source === 'manual' && !$auth->has(array('training_assignments.assign', 'training_assignments.create'))) {
            throw new RuntimeException('You do not have permission to assign learning.');
        }
        $items = isset($data['items']) ? (array) $data['items'] : array();
        $targets = isset($data['targets']) ? (array) $data['targets'] : array();
        if (!$items) {
            throw new InvalidArgumentException('Choose at least one track or module to assign.');
        }
        if (!$targets) {
            throw new InvalidArgumentException('Choose who the learning is for.');
        }
        $title = trim((string) (isset($data['title_en']) ? $data['title_en'] : ''));
        if ($title === '') {
            $title = 'Learning assignment ' . date('Y-m-d');
        }
        $org = $auth->default_organization_id();
        if (!empty($data['property_id'])) {
            $prow = $this->CI->db->select('organization_id')->get_where('ha_property', array('id' => (int) $data['property_id']))->row_array();
            $org = $prow ? (int) $prow['organization_id'] : $org;
        }
        $now = date('Y-m-d H:i:s');
        $due = !empty($data['due_at']) && strtotime($data['due_at']) ? date('Y-m-d 23:59:59', strtotime($data['due_at'])) : date('Y-m-d 23:59:59', strtotime('+30 days'));
        $this->CI->db->trans_start();
        $this->CI->db->insert('ha_training_assignment', array(
            'title_en' => mb_substr($title, 0, 190), 'title_ar' => mb_substr(trim((string) (isset($data['title_ar']) ? $data['title_ar'] : '')) ?: $title, 0, 190),
            'instructions_en' => isset($data['instructions_en']) ? $data['instructions_en'] : null,
            'organization_id' => $org, 'property_id' => !empty($data['property_id']) ? (int) $data['property_id'] : null,
            'created_by' => $actor_id ? (int) $actor_id : null, 'is_mandatory' => isset($data['is_mandatory']) ? (int) (bool) $data['is_mandatory'] : 1,
            'priority' => isset($data['priority']) && in_array($data['priority'], array('low', 'medium', 'high', 'critical'), true) ? $data['priority'] : 'medium',
            'auto_enrol' => 1, 'source' => $source, 'starts_at' => $now, 'due_at' => $due,
            'reminder_days_before' => (int) $this->CI->ha_tenant->get('training.reminder_days_before'),
            'status' => 'active', 'dispatched_at' => $now, 'created_at' => $now, 'updated_at' => $now));
        $aid = (int) $this->CI->db->insert_id();
        $n = 0;
        foreach ($items as $it) {
            $type = is_array($it) ? $it[0] : null;
            $id = is_array($it) ? (int) $it[1] : 0;
            if (!in_array($type, array('course', 'track', 'sop', 'assessment', 'rubric'), true) || !$id) {
                continue;
            }
            $this->CI->db->insert('ha_training_item', array('assignment_id' => $aid, 'item_type' => $type, 'item_id' => $id, 'sort_order' => $n++));
        }
        foreach ($targets as $t) {
            $type = is_array($t) ? $t[0] : null;
            if (in_array($type, array('user', 'department', 'property', 'job_role', 'organization', 'cohort'), true)) {
                $this->CI->db->query('INSERT IGNORE INTO ha_training_target (assignment_id, target_type, target_id) VALUES (?, ?, ?)', array($aid, $type, (int) $t[1]));
            }
        }
        $this->CI->db->trans_complete();
        $count = $this->dispatch($aid, $source === 'manual');
        $this->CI->ha_audit->log('assign', 'training_assignment', $aid, array('description' => 'Assignment "' . $title . '" dispatched to ' . $count . ' people',
            'organization_id' => $org, 'property_id' => !empty($data['property_id']) ? (int) $data['property_id'] : null));
        return array('id' => $aid, 'recipients' => $count);
    }

    /** Resolves targets to people inside the actor's scope, then enrols each in every module. */
    public function dispatch($assignment_id, $enforce_scope = true) {
        $a = $this->CI->db->get_where('ha_training_assignment', array('id' => (int) $assignment_id))->row_array();
        $targets = $this->CI->db->get_where('ha_training_target', array('assignment_id' => (int) $assignment_id))->result_array();
        $users = array();
        foreach ($targets as $t) {
            $db = $this->CI->db->select('p.user_id')->from('ha_profile p')->where('p.status', 'active');
            switch ($t['target_type']) {
                case 'user':         $db->where('p.user_id', (int) $t['target_id']); break;
                case 'department':   $db->where('p.department_id', (int) $t['target_id']); break;
                case 'property':     $db->where('p.property_id', (int) $t['target_id']); break;
                case 'job_role':     $db->where('p.job_role_id', (int) $t['target_id']); break;
                case 'organization': $db->where('p.organization_id', (int) $t['target_id']); break;
                case 'cohort':       $db->join('ha_cohort_member cm', 'cm.user_id = p.user_id')->where('cm.cohort_id', (int) $t['target_id']); break;
            }
            if ($a['property_id']) {
                $db->where('p.property_id', (int) $a['property_id']);
            }
            foreach ($db->get()->result_array() as $r) {
                $users[(int) $r['user_id']] = true;
            }
        }
        $users = array_keys($users);
        if ($enforce_scope && !$this->CI->ha_auth->is_system_scoped()) {
            $allowed = array_flip($this->CI->ha_auth->visible_user_ids());
            $users = array_values(array_filter($users, function ($u) use ($allowed) { return isset($allowed[$u]); }));
        }
        $courses = $this->courses_of_assignment($assignment_id);
        $now = date('Y-m-d H:i:s');
        $count = 0;
        foreach ($users as $uid) {
            $exists = $this->CI->db->get_where('ha_training_recipient', array('assignment_id' => (int) $assignment_id, 'user_id' => $uid))->row_array();
            if (!$exists) {
                $this->CI->db->insert('ha_training_recipient', array('assignment_id' => (int) $assignment_id, 'user_id' => $uid, 'status' => 'assigned',
                    'due_at' => $a['due_at'], 'created_at' => $now, 'updated_at' => $now));
                $count++;
            }
            foreach ($courses as $cid) {
                try {
                    $this->enroll($uid, $cid, 'assigned', array('due_at' => $a['due_at'], 'assignment_id' => $assignment_id));
                } catch (Exception $e) {
                    // A module outside this person's tenant is skipped rather than failing the whole assignment.
                }
            }
            if (!$exists) {
                $this->CI->ha_notify->send($uid, 'assignment.new', array('course_name' => $a['title_en'], 'due_date' => substr((string) $a['due_at'], 0, 10),
                    'url' => hkp_url('learn'), 'related_type' => 'assignment', 'related_id' => (int) $assignment_id, '_no_manager' => 1));
            }
        }
        $total = (int) $this->CI->db->where('assignment_id', (int) $assignment_id)->count_all_results('ha_training_recipient');
        $this->CI->db->where('id', (int) $assignment_id)->update('ha_training_assignment', array('recipients_total' => $total, 'updated_at' => $now));
        return $count;
    }

    public function courses_of_assignment($assignment_id) {
        $ids = array();
        foreach ($this->CI->db->get_where('ha_training_item', array('assignment_id' => (int) $assignment_id))->result_array() as $it) {
            if ($it['item_type'] === 'course') {
                $ids[] = (int) $it['item_id'];
            } elseif ($it['item_type'] === 'track') {
                foreach ($this->CI->db->select('course_id')->order_by('sort_order')->get_where('ha_track_module', array('track_id' => $it['item_id']))->result_array() as $m) {
                    $ids[] = (int) $m['course_id'];
                }
            }
        }
        return array_values(array_unique($ids));
    }

    /** Rolls module completion up into every assignment the user holds. */
    public function refresh_recipients($user_id) {
        $recips = $this->CI->db->get_where('ha_training_recipient', array('user_id' => (int) $user_id))->result_array();
        $now = date('Y-m-d H:i:s');
        foreach ($recips as $r) {
            if ($r['status'] === 'waived') {
                continue;
            }
            $courses = $this->courses_of_assignment($r['assignment_id']);
            if (!$courses) {
                continue;
            }
            $rows = $this->CI->db->where('user_id', (int) $user_id)->where_in('course_id', $courses)->get('ha_enrollment')->result_array();
            $sum = 0;
            $done = 0;
            $started = false;
            foreach ($rows as $e) {
                $sum += (float) $e['progress_percentage'];
                if ($e['status'] === 'completed') {
                    $done++;
                }
                if ($e['started_at']) {
                    $started = true;
                }
            }
            $pct = round($sum / count($courses), 2);
            $status = $done === count($courses) ? 'completed' : ($r['status'] === 'overdue' ? 'overdue' : ($started ? 'in_progress' : 'assigned'));
            $upd = array('progress_percentage' => $done === count($courses) ? 100 : $pct, 'status' => $status, 'updated_at' => $now);
            if ($started && !$r['started_at']) {
                $upd['started_at'] = $now;
            }
            if ($status === 'completed' && !$r['completed_at']) {
                $upd['completed_at'] = $now;
            }
            $this->CI->db->where('id', $r['id'])->update('ha_training_recipient', $upd);
        }
        $this->CI->db->query("UPDATE ha_training_assignment a SET recipients_completed =
            (SELECT COUNT(*) FROM ha_training_recipient r WHERE r.assignment_id = a.id AND r.status = 'completed')
            WHERE a.id IN (SELECT assignment_id FROM ha_training_recipient WHERE user_id = ?)", array((int) $user_id));
    }

    public function exempt($recipient_id, $reason, $actor_id) {
        $r = $this->CI->db->get_where('ha_training_recipient', array('id' => (int) $recipient_id))->row_array();
        if (!$r || !$this->CI->ha_auth->can_user($r['user_id']) || !$this->CI->ha_auth->has('training_assignments.update')) {
            throw new RuntimeException('You cannot exempt this person.');
        }
        if (trim((string) $reason) === '') {
            throw new InvalidArgumentException('Record the reason for the exemption.');
        }
        $this->CI->db->where('id', (int) $recipient_id)->update('ha_training_recipient', array('status' => 'waived',
            'exemption_reason' => mb_substr($reason, 0, 255), 'updated_at' => date('Y-m-d H:i:s')));
        $this->CI->ha_audit->log('update', 'training_recipient', (int) $recipient_id, array('description' => 'Exempted: ' . $reason));
    }

    /**
     * Builds (or tops up) the role plan: one assignment per person holding a
     * role with requirements, sourced from ha_role_requirement.
     */
    public function sync_role_plan($user_id) {
        $p = $this->CI->db->get_where('ha_profile', array('user_id' => (int) $user_id))->row_array();
        if (!$p || !$p['job_role_id']) {
            return null;
        }
        $req = $this->CI->db->where('job_role_id', (int) $p['job_role_id'])->where_in('property_key', array(0, (int) $p['property_id']))
            ->where_in('item_type', array('track', 'course'))->order_by('sort_order')->get('ha_role_requirement')->result_array();
        if (!$req) {
            return null;
        }
        $role = $this->CI->db->get_where('ha_job_role', array('id' => $p['job_role_id']))->row_array();
        $existing = $this->CI->db->select('ta.id')->from('ha_training_assignment ta')->join('ha_training_recipient tr', 'tr.assignment_id = ta.id')
            ->where(array('tr.user_id' => (int) $user_id, 'ta.source' => 'role_requirement', 'ta.status' => 'active'))
            ->like('ta.title_en', $role['title_en'], 'after')->get()->row_array();
        $due_days = max(1, (int) $req[0]['due_days']);
        if ($existing) {
            foreach ($req as $r) {
                $this->CI->db->query('INSERT IGNORE INTO ha_training_item (assignment_id, item_type, item_id, sort_order) VALUES (?, ?, ?, ?)',
                    array($existing['id'], $r['item_type'], $r['item_id'], $r['sort_order']));
            }
            $this->dispatch($existing['id'], false);
            return (int) $existing['id'];
        }
        $items = array();
        foreach ($req as $r) {
            $items[] = array($r['item_type'], $r['item_id']);
        }
        $res = $this->assign(array('title_en' => $role['title_en'] . ' — role plan', 'title_ar' => $role['title_ar'] . ' — خطة الدور',
            'due_at' => date('Y-m-d', strtotime('+' . $due_days . ' days')), 'is_mandatory' => 1, 'property_id' => $p['property_id'],
            'items' => $items, 'targets' => array(array('user', (int) $user_id))), null, 'role_requirement');
        return $res['id'];
    }

    // ------------------------------------------------------------- the plan

    /**
     * What the learner has to do: every assigned module with its state
     * (assigned, started, in progress, completed, overdue, exempted) and due date.
     */
    public function plan($user_id) {
        $loc = hkp_locale();
        $rows = $this->CI->db->select('e.*, c.code, c.duration_minutes, c.level, COALESCE(NULLIF(t.title, \'\'), te.title) AS title, tr.status AS assignment_status, ta.is_mandatory, ta.title_en AS assignment_en, ta.title_ar AS assignment_ar', false)
            ->from('ha_enrollment e')->join('ha_course c', 'c.id = e.course_id')
            ->join('ha_course_translation t', 't.course_id = c.id AND t.locale = ' . $this->CI->db->escape($loc), 'left')
            ->join('ha_course_translation te', "te.course_id = c.id AND te.locale = 'en'", 'left')
            ->join('ha_training_assignment ta', 'ta.id = e.training_assignment_id', 'left')
            ->join('ha_training_recipient tr', 'tr.assignment_id = ta.id AND tr.user_id = e.user_id', 'left')
            ->where('e.user_id', (int) $user_id)->where('e.status !=', 'cancelled')
            ->order_by("FIELD(e.status,'active','completed','expired')", '', false)->order_by('e.due_at IS NULL', '', false)->order_by('e.due_at')->get()->result_array();
        $now = time();
        foreach ($rows as &$r) {
            if ($r['assignment_status'] === 'waived') {
                $r['state'] = 'exempted';
            } elseif ($r['status'] === 'completed') {
                $r['state'] = 'completed';
            } elseif ($r['due_at'] && strtotime($r['due_at']) < $now) {
                $r['state'] = 'overdue';
            } elseif ((float) $r['progress_percentage'] > 0) {
                $r['state'] = 'in_progress';
            } elseif ($r['started_at']) {
                $r['state'] = 'started';
            } else {
                $r['state'] = 'assigned';
            }
            $r['next_lesson_id'] = $this->next_lesson($r['id'], $r['course_id']);
        }
        return $rows;
    }

    public function next_lesson($enrollment_id, $course_id) {
        $row = $this->CI->db->query("SELECT l.id FROM ha_lesson l
            LEFT JOIN ha_lesson_progress lp ON lp.lesson_id = l.id AND lp.enrollment_id = ? AND lp.status = 'completed'
            WHERE l.course_id = ? AND l.status = 'published' AND lp.id IS NULL ORDER BY l.sort_order LIMIT 1", array((int) $enrollment_id, (int) $course_id))->row_array();
        return $row ? (int) $row['id'] : null;
    }

    /** Due-soon reminders and overdue marking. Returns counts. */
    public function sweep() {
        $now = date('Y-m-d H:i:s');
        $overdue = $this->CI->db->select('tr.*, ta.title_en')->from('ha_training_recipient tr')->join('ha_training_assignment ta', 'ta.id = tr.assignment_id')
            ->where_in('tr.status', array('assigned', 'in_progress'))->where('tr.due_at <', $now)->where('ta.status', 'active')->get()->result_array();
        foreach ($overdue as $r) {
            $this->CI->db->where('id', $r['id'])->update('ha_training_recipient', array('status' => 'overdue', 'escalated_at' => $now, 'updated_at' => $now));
            $this->CI->ha_notify->send($r['user_id'], 'assignment.overdue', array('course_name' => $r['title_en'], 'due_date' => substr($r['due_at'], 0, 10),
                'url' => hkp_url('learn'), 'related_type' => 'assignment', 'related_id' => (int) $r['assignment_id']));
        }
        $days = (int) $this->CI->ha_tenant->get('training.reminder_days_before');
        $soon = $this->CI->db->select('tr.*, ta.title_en')->from('ha_training_recipient tr')->join('ha_training_assignment ta', 'ta.id = tr.assignment_id')
            ->where_in('tr.status', array('assigned', 'in_progress'))->where('tr.due_at >=', $now)
            ->where('tr.due_at <=', date('Y-m-d H:i:s', strtotime('+' . $days . ' days')))->where('tr.last_reminded_at IS NULL', null, false)->get()->result_array();
        foreach ($soon as $r) {
            $this->CI->db->where('id', $r['id'])->update('ha_training_recipient', array('last_reminded_at' => $now));
            $this->CI->ha_notify->send($r['user_id'], 'assignment.due_soon', array('course_name' => $r['title_en'], 'due_date' => substr($r['due_at'], 0, 10),
                'url' => hkp_url('learn'), '_no_manager' => 1));
        }
        return array('overdue' => count($overdue), 'reminded' => count($soon));
    }
}
