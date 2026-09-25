<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * altus Hospitality Knowledge & Performance: the evidence chain end to end
 * (ppt-features 185 "Final QA scenario"), tenant isolation for knowledge,
 * search and AI (151), role escalation (152), knowledge governance and
 * versioning (11, 12, 33), and the product rules of section 87.
 */
class Test_hkp extends Ha_testcase {

    public function setUp() {
        $this->CI->load->helper(array('url', 'hkp'));
        $this->CI->load->library(array('ha_auth', 'ha_tenant', 'ha_competency', 'ha_practical', 'ha_action_plans', 'ha_readiness',
            'ha_certification', 'ha_knowledge', 'ha_governed_ai', 'ha_learning', 'ha_theory', 'ha_kpi', 'ha_advisory', 'ha_reports'));
    }

    private function uid($email) {
        $r = $this->db->get_where('users', array('email' => $email))->row_array();
        return $r ? (int) $r['id'] : null;
    }

    private function as_user($email) {
        $id = $this->uid($email);
        $this->CI->ha_auth->assume($id);
        return $id;
    }

    private function skill($code) {
        return (int) $this->db->get_where('ha_skill', array('code' => $code))->row()->id;
    }

    /** Completes every lesson of a module and passes its assessment with correct answers. */
    private function finish_module($user_id, $course_code) {
        $c = $this->db->get_where('ha_course', array('code' => $course_code))->row_array();
        foreach ($this->db->order_by('sort_order')->get_where('ha_lesson', array('course_id' => $c['id'], 'status' => 'published'))->result_array() as $l) {
            $this->CI->ha_learning->open_lesson($user_id, $l['id']);
            if ($l['completion_rule'] === 'watch_percentage') {
                for ($s = 0; $s < (int) $l['duration_seconds'] + 600; $s += 600) {
                    $this->CI->ha_learning->track($user_id, $l['id'], 600, $s);
                }
            }
            $this->CI->ha_learning->complete_lesson($user_id, $l['id']);
        }
        $a = $this->db->get_where('ha_assessment', array('course_id' => $c['id'], 'status' => 'published'))->row_array();
        if ($a) {
            $att = $this->CI->ha_theory->start($a['id'], $user_id);
            $paper = $this->CI->ha_theory->paper($att, $user_id);
            $answers = array();
            foreach ($paper['questions'] as $q) {
                $right = $this->db->get_where('ha_question_option', array('question_id' => $q['id'], 'is_correct' => 1))->row_array();
                $answers[$q['id']] = (int) $right['id'];
            }
            $res = $this->CI->ha_theory->submit($att, $answers, $user_id);
            $this->assertEquals(1, (int) $res['passed'], 'Theory for ' . $course_code . ' should pass with correct answers');
        }
        $e = $this->db->get_where('ha_enrollment', array('user_id' => $user_id, 'course_id' => $c['id']))->row_array();
        $this->assertEquals('completed', $e['status'], 'Module ' . $course_code . ' completed');
    }

    private function rate_all($practical_id, $rating, array $overrides = array()) {
        $pa = $this->CI->ha_practical->get($practical_id);
        $scores = array();
        foreach ($pa['rubric']['criteria'] as $c) {
            $scores[$c['id']] = array('rating' => isset($overrides[$c['label_en']]) ? $overrides[$c['label_en']] : $rating, 'comment' => 'Observed');
        }
        $this->CI->ha_practical->save($practical_id, $scores, 'Observed at the desk');
    }

    // ------------------------------------------------------------------ data

    public function test_ten_core_domains_and_structured_content_model() {
        $core = $this->db->where('is_core', 1)->order_by('sort_order')->get('ha_domain')->result_array();
        $this->assertCount(10, $core, 'Exactly ten core professional domains');
        $this->assertEquals(array('Hotel Fundamentals', 'Sales & Marketing', 'Front Office', 'Revenue & Reservations', 'Housekeeping',
            'Guest Experience', 'Food & Beverage', 'Quality & Audit', 'Kitchen', 'Security & Safety'), array_column($core, 'name_en'));
        foreach ($core as $d) {
            $this->assertNotEmpty($d['name_ar'], 'Arabic name for ' . $d['code']);
        }
        $this->assertGreaterThan(0, $this->db->count_all('ha_track_module'), 'Tracks contain modules');
        $this->assertGreaterThan(50, $this->db->where('domain_id IS NOT NULL', null, false)->count_all_results('ha_course'), 'Modules belong to domains');
        $this->assertGreaterThan(0, $this->db->count_all('ha_assessment'), 'Modules carry assessments');
        $this->assertDatabaseHas('ha_certification_program', array('code' => 'cert-front-office-agent', 'status' => 'published'));
    }

    // --------------------------------------------------- the section 185 flow

    public function test_final_qa_scenario_end_to_end() {
        $learner = $this->as_user('demo.learner@altusdemo.sa');
        $checkin = $this->skill('guest-check-in');
        $complaint = $this->skill('complaint-handling');

        foreach (array('fo-fundamentals', 'fo-check-in', 'fo-guest-privacy', 'fo-complaints') as $code) {
            $this->finish_module($learner, $code);
        }
        // Rule 1: training completion is not competency.
        $this->assertEquals(2, $this->CI->ha_competency->current_level($learner, $checkin), 'Theory lifts check-in only to Developing');
        $r = $this->CI->ha_readiness->calculate($learner);
        $this->assertEquals('not_ready', $r['status'], 'Learning complete but no practical evidence: not ready');

        // Practical check-in: competent.
        $sup = $this->as_user('demo.supervisor@altusdemo.sa');
        $rub = $this->db->get_where('ha_rubric', array('code' => 'rub-fo-check-in'))->row_array();
        $pid = $this->CI->ha_practical->start($rub['id'], $learner, $sup);
        $this->assertEquals($pid, $this->CI->ha_practical->start($rub['id'], $learner, $sup), 'Resuming returns the same draft');
        $this->rate_all($pid, 'competent');
        $x = $this->CI->ha_practical->submit($pid);
        $this->assertEquals('competent', $x['outcome']);
        $this->assertEquals(3, $this->CI->ha_competency->current_level($learner, $checkin));
        $this->assertThrows(function () use ($pid) { $this->CI->ha_practical->save($pid, array()); }, 'A submitted practical cannot be edited');

        // Practical complaint handling: developing -> gap.
        $rub2 = $this->db->get_where('ha_rubric', array('code' => 'rub-complaint-handling'))->row_array();
        $p2 = $this->CI->ha_practical->start($rub2['id'], $learner, $sup);
        $this->rate_all($p2, 'developing', array('Listens without interrupting' => 'competent', 'Acknowledges and apologises sincerely' => 'competent',
            'Offers a solution within authority' => 'competent'));
        $x2 = $this->CI->ha_practical->submit($p2);
        $this->assertEquals('developing', $x2['outcome'], 'Complaint handling fails at Developing');
        $gap = $this->db->get_where('ha_competency_gap', array('user_id' => $learner, 'skill_id' => $complaint, 'status' => 'open'))->row_array();
        $this->assertNotEmpty($gap, 'A gap record is opened');
        $this->assertEquals('minor', $gap['severity'], 'One level short of a non-critical competency is minor');
        $r = $this->CI->ha_readiness->evaluate($learner);
        $this->assertEquals('conditional', $r['status'], 'Only a non-critical gap: conditional');
        $reasons = implode(' ', array_column(array_filter($r['checks'], function ($c) { return !$c['passed']; }), 'detail'));
        $this->assertContains('Complaint Handling', $reasons, 'The reason names the competency');

        // Corrective action by the property manager, evidence by the learner, review by the supervisor.
        $gm = $this->as_user('demo.gm@altusdemo.sa');
        $ap = $this->CI->ha_action_plans->create(array('gap_id' => $gap['id'], 'title' => 'Service Recovery Action Plan', 'action_type' => 'coaching',
            'required_action' => 'Shadow the duty manager for three complaint cases.'), $gm);
        $this->assertDatabaseHas('ha_competency_gap', array('id' => $gap['id'], 'status' => 'in_action'));
        $this->as_user('demo.learner@altusdemo.sa');
        $this->assertThrows(function () use ($ap, $learner) { $this->CI->ha_action_plans->transition($ap, 'completed', $learner); }, 'Learner cannot complete own plan');
        $this->CI->ha_action_plans->add_evidence($ap, array('description' => 'Handled three complaints with the duty manager.'), $learner);
        $this->CI->ha_action_plans->transition($ap, 'submitted', $learner, 'Done');
        $this->as_user('demo.supervisor@altusdemo.sa');
        $plan = $this->CI->ha_action_plans->transition($ap, 'completed', $sup, 'Evidence reviewed');
        $this->assertEquals('completed', $plan['status']);
        $this->assertDatabaseHas('ha_competency_gap', array('id' => $gap['id'], 'status' => 'in_action'), 'Completing the plan does not close the gap');
        $re = $this->db->get_where('ha_reassessment', array('action_plan_id' => $ap, 'status' => 'approved'))->row_array();
        $this->assertNotEmpty($re, 'An approved reassessment is opened');

        // Reassessment: competent. History is kept.
        $p3 = $this->CI->ha_practical->start($rub2['id'], $learner, $sup, $re['id']);
        $this->rate_all($p3, 'competent');
        $this->CI->ha_practical->submit($p3);
        $this->assertEquals(3, $this->CI->ha_competency->current_level($learner, $complaint));
        $this->assertDatabaseHas('ha_competency_gap', array('id' => $gap['id'], 'status' => 'closed'));
        $this->assertDatabaseHas('ha_reassessment', array('id' => $re['id'], 'status' => 'completed'));
        $sources = array_column($this->CI->ha_competency->history($learner, $complaint), 'source');
        sort($sources);
        $this->assertEquals(array('practical', 'reassessment', 'theory'), $sources, 'Theory, practical and reassessment results all remain on record');
        $this->assertCount(2, $this->CI->ha_competency->history($learner, $checkin), 'Theory and practical check-in results are both kept');

        // Readiness -> certification -> readiness.
        $cert = $this->db->get_where('ha_certificate', array('user_id' => $learner, 'status' => 'issued'))->row_array();
        $this->assertNotEmpty($cert, 'Front Office Certification issued automatically once every rule is met');
        $this->assertMatches('/^ALTUS-FOA-\d{4}-\d{6}$/', $cert['certificate_no']);
        $this->assertNotEmpty($cert['evidence_json'], 'The evidence used is frozen on the certificate');
        $cur = $this->CI->ha_readiness->current($learner);
        $this->assertEquals('ready', $cur['status'], 'Readiness = Ready');

        // Public verification shows no private data.
        $v = $this->CI->ha_certification->verify($cert['certificate_no']);
        $this->assertEquals('valid', $v['result']);
        $this->assertFalse(isset($v['email']) || isset($v['employee_no']) || isset($v['evidence_json']), 'No private fields in verification');
        $this->assertEquals('not_found', $this->CI->ha_certification->verify('ALTUS-NOPE-0000-000000')['result']);
        $pdf = $this->CI->ha_certification->pdf($cert);
        $this->assertEquals('%PDF-1.4', substr($pdf, 0, 8), 'Certificate renders as PDF');

        // Governed AI answers from the property's own SOP.
        $this->as_user('demo.learner@altusdemo.sa');
        $ans = $this->CI->ha_governed_ai->ask('What is our approved check-in procedure?');
        $this->assertContains($ans['coverage'], array('answered', 'no_model'));
        $codes = array();
        foreach ($ans['sources'] as $s) {
            if ($s['source_type'] === 'knowledge') {
                $codes[] = $this->db->get_where('ha_sop_document', array('id' => $s['source_id']))->row()->code;
            }
        }
        $this->assertContains('adh-sop-fo-check-in', $codes, 'The authorised property SOP is the source');
        $this->assertDatabaseHas('ha_ai_query', array('id' => $ans['query_id']));
    }

    // ---------------------------------------------------------- isolation

    public function test_tenant_isolation_for_knowledge_search_and_ai() {
        $sop = $this->db->get_where('ha_sop_document', array('code' => 'adh-sop-fo-check-in'))->row_array();
        $omar = $this->as_user('omar.learner@dyafagroup.sa');
        $this->assertFalse($this->CI->ha_knowledge->can_view($sop['id'], $omar), 'Tenant B learner cannot open Tenant A SOP');
        foreach ($this->CI->ha_knowledge->search($omar, 'ALTUS Demo Hotel check-in') as $hit) {
            $this->assertNotEquals((int) $sop['id'], $hit['type'] === 'knowledge' ? $hit['id'] : 0, 'Search never returns another tenant\'s SOP');
        }
        $ans = $this->CI->ha_governed_ai->ask('What is the check-in procedure at ALTUS Demo Hotel Riyadh with Shomoos?');
        foreach ($ans['sources'] as $s) {
            $this->assertFalse($s['source_type'] === 'knowledge' && (int) $s['source_id'] === (int) $sop['id'], 'AI never retrieves another tenant\'s knowledge');
        }
        $ahmed = $this->as_user('demo.learner@altusdemo.sa');
        $this->assertTrue($this->CI->ha_knowledge->can_view($sop['id'], $ahmed));
        $gm = $this->as_user('demo.gm@altusdemo.sa');
        $this->assertFalse($this->CI->ha_auth->can_user($omar), 'Property A manager cannot see Property B employee');
        $this->assertFalse($this->CI->ha_auth->can_property($this->db->get_where('ha_property', array('slug' => 'riyadh-business-tower'))->row()->id));
        $this->as_user('omar.learner@dyafagroup.sa');
        $this->assertFalse($this->CI->ha_auth->can_user($ahmed), 'A learner cannot view another employee');
    }

    public function test_role_escalation_is_refused_server_side() {
        $this->as_user('omar.learner@dyafagroup.sa');
        $this->assertFalse($this->CI->ha_auth->has('knowledge.publish'));
        $this->assertFalse($this->CI->ha_auth->has('system.configure'));
        $this->assertThrows(function () { $this->CI->ha_knowledge->create(array('title_en' => 'x'), 1); }, 'Learner cannot create knowledge');
        $this->as_user('demo.supervisor@altusdemo.sa');
        $this->assertFalse($this->CI->ha_auth->has('organizations.update'), 'Supervisor cannot reach Altus administration');
        $this->assertTrue($this->CI->ha_auth->has('practicals.assess'));
        $gm = $this->as_user('demo.gm@altusdemo.sa');
        $global = $this->db->where('organization_id IS NULL', null, false)->get('ha_sop_document')->row_array();
        $dyafa = $this->db->get_where('ha_sop_document', array('code' => 'sop-fo-checkin'))->row_array();
        $this->assertFalse($this->CI->ha_knowledge->can_view($dyafa['id'], $gm), 'Another client\'s SOP is invisible');
        if ($global) {
            $this->assertFalse($this->CI->ha_knowledge->can_edit($global, $gm), 'A property manager cannot change global content');
        }
    }

    // ------------------------------------------------------ knowledge rules

    public function test_knowledge_governance_versions_and_ai_index() {
        $author = $this->as_user('demo.gm@altusdemo.sa');
        $org = $this->db->get_where('ha_organization', array('slug' => 'altus-demo-client'))->row()->id;
        $id = $this->CI->ha_knowledge->create(array('title_en' => 'Lost property handover', 'title_ar' => 'تسليم المفقودات', 'item_type' => 'procedure',
            'organization_id' => $org, 'sections' => array('en' => array('procedure' => "Log the item.\nStore it in the locked cabinet."), 'ar' => array('procedure' => 'سجل الغرض.'))), $author);
        $this->assertEquals(0, $this->db->where(array('source_type' => 'knowledge', 'source_id' => $id))->count_all_results('ha_ai_chunk'), 'Drafts are never indexed for AI');
        $this->CI->ha_knowledge->act($id, 'submit', $author);
        $this->assertThrows(function () use ($id, $author) { $this->CI->ha_knowledge->act($id, 'approve_internal', $author); }, 'The author cannot review their own version');
        $this->as_user('admin@hospitalityacademy.sa');
        $admin = $this->uid('admin@hospitalityacademy.sa');
        $this->CI->ha_knowledge->act($id, 'approve_internal', $admin);
        $this->CI->ha_knowledge->act($id, 'approve_quality', $admin);
        $doc = $this->CI->ha_knowledge->act($id, 'publish', $admin);
        $this->assertEquals('published', $doc['status']);
        $this->assertGreaterThan(0, $this->db->where(array('source_type' => 'knowledge', 'source_id' => $id))->count_all_results('ha_ai_chunk'), 'Publishing indexes the version');
        $v1 = $doc['current_version_id'];

        $this->as_user('demo.gm@altusdemo.sa');
        $v2 = $this->CI->ha_knowledge->new_version($id, $author, false, 'Add photo step');
        $this->CI->ha_knowledge->update_draft($id, array('title_en' => 'Lost property handover', 'title_ar' => 'تسليم المفقودات',
            'sections' => array('en' => array('procedure' => "Log the item.\nPhotograph it.\nStore it in the locked cabinet."), 'ar' => array('procedure' => 'سجل الغرض.'))), $author);
        $diff = $this->CI->ha_knowledge->compare($v1, $v2);
        $this->assertNotEmpty($diff, 'Versions can be compared');
        $this->assertDatabaseHas('ha_sop_version', array('id' => $v1, 'status' => 'published'), 'Opening a new version never touches the published one');
        $chunks = $this->db->where(array('source_type' => 'knowledge', 'source_id' => $id))->get('ha_ai_chunk')->result_array();
        foreach ($chunks as $c) {
            $this->assertEquals($v1, (int) $c['version_id'], 'Only the current published version is retrievable');
        }
        $this->as_user('admin@hospitalityacademy.sa');
        foreach (array('submit', 'approve_internal', 'approve_quality', 'publish') as $step) {
            if ($step === 'submit') {
                $this->as_user('demo.gm@altusdemo.sa');
                $this->CI->ha_knowledge->act($id, $step, $author);
                $this->as_user('admin@hospitalityacademy.sa');
            } else {
                $this->CI->ha_knowledge->act($id, $step, $admin);
            }
        }
        $this->assertDatabaseHas('ha_sop_version', array('id' => $v1, 'status' => 'superseded'), 'The old version is superseded, not deleted');
        $this->assertEquals(0, $this->db->where(array('source_type' => 'knowledge', 'source_id' => $id, 'version_id' => $v1))->count_all_results('ha_ai_chunk'), 'Old version retired from retrieval');
        $this->CI->ha_knowledge->act($id, 'archive', $admin);
        $this->assertEquals(0, $this->db->where(array('source_type' => 'knowledge', 'source_id' => $id))->count_all_results('ha_ai_chunk'), 'Archived content leaves the index');
    }

    public function test_ai_states_insufficient_knowledge_instead_of_inventing() {
        $this->as_user('demo.learner@altusdemo.sa');
        $ans = $this->CI->ha_governed_ai->ask('What is the corporate policy on yacht chartering commissions in Monaco?');
        $this->assertEquals('insufficient', $ans['coverage']);
        $this->assertEquals(Ha_governed_ai::INSUFFICIENT_EN, $ans['answer']);
        $ar = $this->CI->ha_governed_ai->ask('ما هي سياسة تأجير اليخوت في موناكو؟');
        $this->assertEquals(Ha_governed_ai::INSUFFICIENT_AR, $ar['answer'], 'Arabic question, Arabic refusal');
    }

    public function test_bilingual_retrieval_finds_arabic_source_for_english_question() {
        $concepts = $this->CI->ha_governed_ai->concepts('check-in procedure');
        $flat = array();
        foreach ($concepts as $c) {
            $flat = array_merge($flat, $c);
        }
        $this->assertContains(Ha_governed_ai::normalize('تسجيل الوصول'), $flat, 'English term expands to Arabic');
    }

    // ---------------------------------------------------------- calculations

    public function test_practical_critical_criterion_caps_outcome() {
        $learner = $this->uid('demo.learner2@altusdemo.sa');
        $sup = $this->as_user('demo.supervisor@altusdemo.sa');
        $rub = $this->db->get_where('ha_rubric', array('code' => 'rub-fo-check-in'))->row_array();
        $pid = $this->CI->ha_practical->start($rub['id'], $learner, $sup);
        $this->rate_all($pid, 'exceeds', array('Identity verification' => 'not_demonstrated'));
        $x = $this->CI->ha_practical->explain($this->CI->ha_practical->get($pid));
        $this->assertGreaterThanOrEqual(75, $x['percentage'], 'Weighted score alone would pass');
        $this->assertTrue($x['capped'], 'The critical criterion caps the outcome');
        $this->assertEquals('developing', $x['outcome']);
        $this->assertThrows(function () use ($learner) {
            $this->as_user('demo.learner2@altusdemo.sa');
            $this->CI->ha_practical->start(1, $learner, $learner);
        }, 'Nobody assesses their own practical');
    }

    public function test_gap_severity_thresholds_are_configurable() {
        $c = $this->CI->ha_competency;
        $this->assertEquals('minor', $c->severity(1, false));
        $this->assertEquals('moderate', $c->severity(2, false));
        $this->assertEquals('critical', $c->severity(3, false));
        $this->assertEquals('critical', $c->severity(1, true), 'A critical competency escalates');
        $this->CI->ha_tenant->set('gap.moderate_max', 3);
        $this->assertEquals('moderate', $this->CI->ha_competency->severity(3, false), 'Changing the setting changes the rule');
        $this->CI->ha_tenant->reset('gap.moderate_max', 'global', 0);
    }

    public function test_kpi_status_respects_direction_and_thresholds() {
        $k = array('direction' => 'higher_better', 'target' => 400, 'threshold_warning' => 350, 'threshold_critical' => 300);
        $this->assertEquals('on_target', $this->CI->ha_kpi->status($k, 420));
        $this->assertEquals('warning', $this->CI->ha_kpi->status($k, 340));
        $this->assertEquals('critical', $this->CI->ha_kpi->status($k, 280));
        $this->assertEquals('no_data', $this->CI->ha_kpi->status($k, null), 'No value is shown as no data, never invented');
        $low = array('direction' => 'lower_better', 'target' => 20, 'threshold_warning' => 25, 'threshold_critical' => 30);
        $this->assertEquals('critical', $this->CI->ha_kpi->status($low, 31));
        $this->assertEquals(0, $this->db->count_all('ha_kpi_value'), 'No KPI values are seeded');
    }

    public function test_performance_matrix_places_asset_by_configured_threshold() {
        $this->as_user('admin@hospitalityacademy.sa');
        $admin = $this->uid('admin@hospitalityacademy.sa');
        $f = $this->CI->ha_advisory->framework('performance_matrix');
        $org = $this->db->get_where('ha_organization', array('slug' => 'altus-demo-client'))->row()->id;
        $id = $this->CI->ha_advisory->start_assessment($f['id'], array('organization_id' => $org, 'title' => 'Baseline'), $admin);
        $answers = array();
        foreach ($f['dimensions'] as $d) {
            foreach ($d['questions'] as $q) {
                $answers[$q['id']] = array('score' => $d['axis'] === 'operational' ? 4 : 2);
            }
        }
        $a = $this->CI->ha_advisory->save_answers($id, $answers, true);
        $this->assertEquals('legacy_operator', $a['result_label'], 'Strong operations, weak digital: Legacy Operator');
    }

    public function test_reports_are_scoped_and_export_to_real_xlsx() {
        $this->as_user('demo.gm@altusdemo.sa');
        $data = $this->CI->ha_reports->dataset('readiness', array());
        $names = array_column($data['rows'], 0);
        $this->assertNotContains('Omar Al Suwailem', $names, 'Another tenant\'s staff never appear');
        $bin = $this->CI->ha_reports->xlsx($data, $this->CI->ha_reports->context_lines(array()));
        $this->assertEquals("PK", substr($bin, 0, 2), 'XLSX is a zip package');
        $csv = $this->CI->ha_reports->csv(array('title' => 't', 'header' => array('a'), 'rows' => array(array('=cmd()'))), array());
        $this->assertContains("'=cmd()", $csv, 'CSV neutralises formula injection');
    }

    public function test_qr_encoder_and_pdf_writer() {
        $this->CI->load->library(array('ha_qr', 'ha_pdf'));
        $m = $this->CI->ha_qr->matrix('https://example.com/verify/ALTUS-FOA-2026-000152');
        $this->assertGreaterThanOrEqual(21, count($m));
        $this->assertEquals("\x89PNG", substr($this->CI->ha_qr->png('ALTUS'), 0, 4));
    }
}
