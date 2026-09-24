<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Builds end-of-course assessments into the published Academy LMS tables.
 *
 * This lives in a library rather than in the Ha_quiz controller because two
 * commands need it and only one of them is about quizzes. Ha_bridge sync
 * republishes the catalogue by deleting and rewriting the legacy lesson rows,
 * which silently took every quiz with it: the assessments existed until the
 * next routine sync and then quietly did not. Rebuilding them as the last
 * step of a sync is what makes that safe.
 *
 * Where the questions ultimately belong is the academy tables, ha_assessment
 * and ha_question, so they would be the source of truth like everything else
 * and the bridge would publish them. That move is blocked on Arabic: those
 * tables require body_ar on every question, and 296 machine-translated exam
 * questions about food safety and fire response is not something to ship. So
 * for now the bank is the source and this publishes it, which is honest about
 * where the gap is rather than filling body_ar with English.
 */
class Ha_assessment {

    /** A learner gets three attempts and needs 70 per cent, matching ha_course.pass_percentage. */
    const PASS_PERCENTAGE = 70;
    const ATTEMPTS        = 3;
    const MINUTES         = 10;

    private $CI;

    public function __construct() {
        $this->CI = &get_instance();
        $this->CI->load->database();
        $this->CI->load->library('ha_quizbank');
    }

    /**
     * Create or replace the assessment on every published course.
     *
     * @return array counts and the courses that had no questions
     */
    public function build() {
        $bank = $this->CI->ha_quizbank->questions();
        $db   = $this->CI->db;

        $courses = $db->select('id, title, meta_keywords, section')
            ->like('meta_keywords', 'ha:')->get('course')->result_array();

        $result = array('courses' => 0, 'questions' => 0, 'orphans' => 0, 'skipped' => array());

        // A republish deletes the legacy lesson rows, which leaves the
        // questions behind pointing at a quiz lesson that no longer exists.
        // They are invisible to the learner and they accumulate on every
        // sync, so clear them before writing the new set.
        $live = $db->select('id')->where('lesson_type', 'quiz')->get('lesson')->result_array();
        $live_ids = array();
        foreach ($live as $l) {
            $live_ids[] = (int) $l['id'];
        }
        if ($live_ids) {
            $db->where_not_in('quiz_id', $live_ids);
        }
        $result['orphans'] = $db->count_all_results('question', false);
        if ($live_ids) {
            $db->where_not_in('quiz_id', $live_ids);
        }
        $db->delete('question');

        foreach ($courses as $course) {
            $code = $this->academy_code($course['meta_keywords']);
            if ($code === '' || !isset($bank[$code])) {
                $result['skipped'][] = ($code !== '' ? $code : $course['title']);
                continue;
            }

            $sections = json_decode((string) $course['section'], true);
            if (!is_array($sections) || !$sections) {
                $result['skipped'][] = $code . ' (no sections)';
                continue;
            }
            $section_id = (int) end($sections);
            $items = $bank[$code];
            $marks = count($items);

            // Replace rather than duplicate, so this is safe to re-run.
            $existing = $db->where('course_id', $course['id'])
                ->where('lesson_type', 'quiz')->get('lesson')->result_array();
            foreach ($existing as $old) {
                $db->where('quiz_id', $old['id'])->delete('question');
                $db->where('id', $old['id'])->delete('lesson');
            }

            $order = (int) $db->select_max('order')->where('section_id', $section_id)
                ->get('lesson')->row('order');

            $db->insert('lesson', array(
                'title'           => 'Assessment: ' . $course['title'],
                'course_id'       => $course['id'],
                'section_id'      => $section_id,
                'lesson_type'     => 'quiz',
                'duration'        => gmdate('H:i:s', self::MINUTES * 60),
                'attachment_type' => 'json',
                'attachment'      => json_encode(array(
                    'total_marks'                   => $marks,
                    'pass_mark'                     => (int) ceil($marks * self::PASS_PERCENTAGE / 100),
                    'drip_content_for_passing_rule' => '',
                )),
                'summary'         => 'Answer every question. You need '
                    . self::PASS_PERCENTAGE . ' per cent to pass, and you have '
                    . self::ATTEMPTS . ' attempts.',
                'quiz_attempt'    => self::ATTEMPTS,
                'order'           => $order + 1,
                'date_added'      => strtotime(date('D, d-M-Y')),
                'last_modified'   => strtotime(date('D, d-M-Y')),
            ));
            $quiz_id = (int) $db->insert_id();

            foreach ($items as $i => $item) {
                list($title, $options, $correct) = $item;
                $db->insert('question', array(
                    'quiz_id'           => $quiz_id,
                    'title'             => $title,
                    // single_choice renders radio buttons. multiple_choice
                    // renders checkboxes and belongs to questions with more
                    // than one right answer; every question here has one.
                    'type'              => 'single_choice',
                    'number_of_options' => count($options),
                    'options'           => json_encode($options, JSON_UNESCAPED_UNICODE),
                    // 1-based position as a string: that is the value the
                    // radio posts and what the grader compares with in_array.
                    'correct_answers'   => json_encode(array((string) $correct)),
                    'order'             => $i + 1,
                ));
                $result['questions']++;
            }
            $result['courses']++;
        }
        return $result;
    }

    /** The bridge stamps the academy course code into meta_keywords as "ha:<code>". */
    private function academy_code($meta) {
        if (preg_match('/ha:([a-z0-9\-]+)/i', (string) $meta, $m)) {
            return strtolower($m[1]);
        }
        return '';
    }
}
