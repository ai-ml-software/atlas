<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Puts the photography the site already owns onto the records that had none.
 *
 *   php index.php ha_artwork assign   give every programme and path a photo
 *   php index.php ha_artwork report   what carries artwork and what does not
 *
 * Courses and topics arrived with a picture each. Programmes and learning
 * paths did not, so those two listings were walls of white rectangles, and no
 * amount of typography fixes a card that has nothing to look at. The pictures
 * come from ha_media, which means they are the same Wikimedia Commons files
 * the rest of the site uses, under the same licences, already credited on the
 * credits page. Nothing new is downloaded and nothing new has to be cleared.
 *
 * The pairing is written out rather than guessed from the title: "Hotel Safety
 * Essentials" matched nothing sensible on words alone, and a front office
 * photograph on a fire safety programme is worse than no photograph.
 */
class Ha_artwork extends CI_Controller {

    /** Programme code to the ha_media subject that belongs on it. */
    private $programs = array(
        'front-office-professional' => 'front-office',
        'housekeeping-professional' => 'housekeeping',
        'food-safety-certified'     => 'kitchen',
        'hospitality-supervisor'    => 'management',
        'revenue-and-distribution'  => 'digital-hospitality',
        'hotel-safety-essentials'   => 'safety-compliance',
    );

    /** Learning path code to the ha_media subject that belongs on it. */
    private $paths = array(
        'front-office-career'  => 'guest-experience',
        'housekeeping-career'  => 'housekeeping-2',
        'food-beverage-career' => 'food-and-beverage',
    );

    public function __construct() {
        parent::__construct();
        if (!is_cli()) {
            show_404();
        }
        $this->load->database();
    }

    private function out($line = '') {
        fwrite(STDOUT, $line . PHP_EOL);
    }

    private function path_for($subject) {
        $row = $this->db->select('file_path')->where('subject', $subject)
            ->get('ha_media')->row_array();
        return $row ? $row['file_path'] : null;
    }

    public function assign() {
        $this->out('Assigning artwork from the existing media library');
        $this->out(str_repeat('-', 72));

        $done = 0;
        $missing = array();

        foreach (array('ha_program' => $this->programs, 'ha_learning_path' => $this->paths) as $table => $map) {
            foreach ($map as $code => $subject) {
                $file = $this->path_for($subject);
                if (!$file) {
                    $missing[] = $code . ': no media with subject "' . $subject . '"';
                    continue;
                }
                $this->db->where('code', $code)->update($table, array('thumbnail' => $file));
                if ($this->db->affected_rows() >= 0) {
                    $this->out('  ' . str_pad($code, 30) . $file);
                    $done++;
                }
            }
        }

        $this->out(str_repeat('-', 72));
        $this->out('records given artwork: ' . $done);
        foreach ($missing as $m) {
            $this->out('  ' . $m);
        }
    }

    public function report() {
        $rows = array(
            'courses'   => array('ha_course', 'thumbnail'),
            'programs'  => array('ha_program', 'thumbnail'),
            'paths'     => array('ha_learning_path', 'thumbnail'),
            'topics'    => array('ha_topic', 'hero_image'),
        );
        foreach ($rows as $label => $spec) {
            list($table, $column) = $spec;
            $total = $this->db->count_all($table);
            $with  = $this->db->where($column . ' IS NOT NULL')->where($column . ' !=', '')
                ->count_all_results($table);
            $this->out(str_pad($label, 12) . $with . ' of ' . $total . ' carry artwork');
        }
    }
}
