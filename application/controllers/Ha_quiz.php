<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Builds the end-of-course assessment for every course.
 *
 *   php index.php ha_quiz build     create or refresh a quiz on every course
 *   php index.php ha_quiz report    coverage, and which courses have none
 *   php index.php ha_quiz clear     remove every generated quiz
 *
 * Until this existed a learner could finish any course in the catalogue and
 * be issued a certificate without answering a single question, which makes
 * the certificate a record of attendance rather than of competence. For a
 * hotel buying training to satisfy an auditor, that difference is the whole
 * product.
 *
 * Questions come from Ha_quizbank, written per course rather than generated
 * from a template, because a question that could belong to any course tests
 * nothing. They are stored in the Academy LMS `question` table, whose
 * encoding is not obvious and is worth stating:
 *
 *   type             single_choice, which renders radio buttons. The
 *                    multiple_choice type renders checkboxes and belongs to
 *                    questions with more than one right answer.
 *   options          JSON array of the option text, in display order
 *   correct_answers  JSON array of the correct options' 1-BASED positions,
 *                    as strings, because the radio input's value is the
 *                    index and the grader compares with in_array()
 *
 * The quiz itself is a lesson of type `quiz` whose `attachment` holds the
 * marks as JSON, and questions join to it on question.quiz_id = lesson.id.
 */
class Ha_quiz extends CI_Controller {

    public function __construct() {
        parent::__construct();
        if (!is_cli()) {
            show_404();
        }
        @set_time_limit(0);
        $this->load->database();
        $this->load->library('ha_quizbank');
    }

    private function out($line = '') {
        fwrite(STDOUT, $line . PHP_EOL);
    }

    public function build() {
        $this->load->library('ha_assessment');
        $this->out('Building end-of-course assessments');
        $this->out(str_repeat('-', 72));

        $r = $this->ha_assessment->build();

        $this->out('courses with an assessment: ' . $r['courses']);
        $this->out('questions written:          ' . $r['questions']);
        $this->out('orphan questions cleared:   ' . $r['orphans']);
        $this->out('courses skipped:            ' . count($r['skipped']));
        foreach ($r['skipped'] as $x) {
            $this->out('  ' . $x);
        }
    }

    public function report() {
        $courses = $this->db->like('meta_keywords', 'ha:')->count_all_results('course');
        $with    = $this->db->where('lesson_type', 'quiz')->distinct()
            ->select('course_id')->get('lesson')->num_rows();
        $qs      = $this->db->count_all('question');
        $this->out('courses:              ' . $courses);
        $this->out('with an assessment:   ' . $with);
        $this->out('without:              ' . ($courses - $with));
        $this->out('questions in total:   ' . $qs);
        $this->out('bank entries:         ' . count($this->ha_quizbank->questions()));
    }

    public function clear() {
        $quizzes = $this->db->where('lesson_type', 'quiz')->get('lesson')->result_array();
        foreach ($quizzes as $q) {
            $this->db->where('quiz_id', $q['id'])->delete('question');
        }
        $this->db->where('lesson_type', 'quiz')->delete('lesson');
        $this->out('Removed ' . count($quizzes) . ' assessments and their questions.');
    }

}
