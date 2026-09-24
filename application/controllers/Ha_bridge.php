<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Publishes the Hospitality Academy catalogue into the original Academy LMS
 * tables, so the shipped theme, the student area, enrolment, the instructor
 * panel and the admin course manager all work against the same content.
 *
 *   php index.php ha_bridge sync     mirror ha_* into the legacy tables
 *   php index.php ha_bridge status   what is mirrored and what has drifted
 *   php index.php ha_bridge enrol    give the demo learners some enrolments
 *   php index.php ha_bridge clear    remove the mirrored rows
 *
 * The academy tables stay the single source of truth. This writes one way and
 * is idempotent: a course already mirrored is updated in place, matched on its
 * academy code, so running it twice does not duplicate the catalogue.
 */
class Ha_bridge extends CI_Controller {

    /** Legacy rows created by this bridge carry the academy code here. */
    const MARKER = 'ha:';

    /**
     * The courses the legacy home page promotes. One per department, chosen
     * because they are the courses a hotel actually starts a new joiner on.
     */
    public static function featured() {
        return array(
            'fo-fundamentals', 'fo-check-in', 'hk-fundamentals', 'hk-room-cleaning',
            'fb-restaurant-service', 'kit-food-safety', 'eng-fire-safety',
            'mgt-leadership', 'dig-pms', 'mgt-guest-experience',
            'fo-complaints', 'kit-temperature',
        );
    }

    private $now;

    public function __construct() {
        parent::__construct();
        if (!is_cli()) {
            show_404();
        }
        @set_time_limit(0);
        ini_set('memory_limit', '512M');
        $this->load->database();
        $this->load->helper('text');
        $this->now = time();
    }

    private function out($line = '') {
        fwrite(STDOUT, $line . PHP_EOL);
    }

    const COURSE_DIR = 'uploads/thumbnails/course_thumbnails/';
    const CATEGORY_DIR = 'uploads/thumbnails/category_thumbnails/';

    /**
     * Writes an academy WebP out as the JPEG the legacy theme expects.
     * Returns false when the source is missing, so a course simply falls back
     * to the theme placeholder rather than showing a broken image.
     */
    private function publish_jpeg($source_path, $target_path, $max_width = 900) {
        if (!$source_path || !file_exists(FCPATH . $source_path)) {
            return false;
        }
        $dir = dirname(FCPATH . $target_path);
        if (!is_dir($dir)) {
            mkdir($dir, 0775, true);
        }
        if (file_exists(FCPATH . $target_path)) {
            return true;
        }

        $bytes = file_get_contents(FCPATH . $source_path);
        $image = @imagecreatefromstring($bytes);
        if (!$image) {
            return false;
        }

        $w = imagesx($image);
        $h = imagesy($image);
        $scale = min(1, $max_width / max(1, $w));
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));

        $canvas = imagecreatetruecolor($nw, $nh);
        imagecopyresampled($canvas, $image, 0, 0, 0, 0, $nw, $nh, $w, $h);
        $ok = imagejpeg($canvas, FCPATH . $target_path, 86);
        imagedestroy($canvas);
        imagedestroy($image);
        return $ok;
    }

    private function theme() {
        $row = $this->db->get_where('frontend_settings', array('key' => 'theme'))->row_array();
        return $row ? $row['value'] : 'default-new';
    }

    /** The legacy schema has no slug on course; the theme derives it from the title. */
    private function slugify($text) {
        $text = trim(strtolower((string) $text));
        $text = preg_replace('/[^a-z0-9\x{0600}-\x{06FF}]+/u', '-', $text);
        return trim($text, '-');
    }

    // ------------------------------------------------------------------- sync

    public function sync() {
        $this->out('Publishing the academy catalogue into the Academy LMS tables');
        $this->out(str_repeat('-', 72));

        $categories = $this->sync_categories();
        $this->out(str_pad('categories', 18) . $categories);

        $courses = $this->sync_courses();
        $this->out(str_pad('courses', 18) . $courses['courses']);
        $this->out(str_pad('sections', 18) . $courses['sections']);
        $this->out(str_pad('lessons', 18) . $courses['lessons']);

        // Republishing rewrites every legacy lesson row, which used to take
        // the end-of-course assessments with it: they existed until the next
        // routine sync and then quietly did not. Rebuilding them here is what
        // makes a sync safe to run at any time.
        $this->load->library('ha_assessment');
        $assessments = $this->ha_assessment->build();
        $this->out(str_pad('assessments', 18) . $assessments['courses']
            . ' (' . $assessments['questions'] . ' questions)');

        $this->out(str_repeat('-', 72));
        $this->out('Done. The academy tables remain the source of truth.');
    }

    /**
     * Academy categories become legacy top level categories. The academy code
     * is stored in category.code, which is what makes this re-runnable.
     */
    private function sync_categories() {
        $written = 0;
        $rows = $this->db
            ->select('c.id, c.code, c.slug_en, c.sort_order, c.icon')
            ->select('t.name', false)
            ->from('ha_category c')
            ->join('ha_category_translation t', "t.category_id = c.id AND t.locale = 'en'", 'left')
            ->where('c.status', 'active')
            ->order_by('c.sort_order', 'ASC')
            ->get()->result_array();

        foreach ($rows as $r) {
            $written += $this->sync_category_pair($r);
        }
        $written += $this->prune_categories($rows);
        return $written;
    }

    /**
     * Removes mirrored categories whose academy counterpart is gone.
     *
     * sync writes one way and upserts, which keeps it idempotent but blind to
     * deletion: retiring a category in the academy tables left its legacy pair
     * behind, still listed in the theme's course filter and still drawn as a
     * home-page tile, now empty. Only rows this bridge created are considered
     * -- the marker prefix is what distinguishes them from categories an
     * administrator added by hand, which are none of our business.
     *
     * A category still holding courses is left alone and reported. That means
     * a mirror that has drifted stays visible instead of taking its courses
     * out of the legacy catalogue with it.
     */
    private function prune_categories(array $rows) {
        $keep = array();
        foreach ($rows as $r) {
            $keep[] = self::MARKER . $r['code'];
            $keep[] = self::MARKER . 'sub-' . $r['code'];
        }

        $mirrored = $this->db->select('id, code')->from('category')
            ->like('code', self::MARKER, 'after')->get()->result_array();

        $removed = 0;
        foreach ($mirrored as $row) {
            if (in_array($row['code'], $keep, true)) {
                continue;
            }
            $held = $this->db->where('category_id', $row['id'])->count_all_results('course');
            if ($held > 0) {
                $this->out(str_pad('kept, has courses', 18) . $row['code'] . ' (' . $held . ')');
                continue;
            }
            $this->db->where('id', $row['id'])->delete('category');
            $removed++;
        }
        if ($removed) {
            $this->out(str_pad('retired', 18) . $removed . ' stale categories');
        }
        return $removed;
    }

    /**
     * Writes the parent category and its sub category, and returns how many
     * rows were touched. A course is attached to the sub category, because
     * that is what the theme filters and the home page tiles read.
     */
    private function sync_category_pair(array $r) {
        $written = 0;
        foreach (array('parent', 'child') as $tier) {
            $is_child = ($tier === 'child');
            $code = self::MARKER . ($is_child ? 'sub-' : '') . $r['code'];

            // category.thumbnail is a bare filename; the theme prefixes the
            // directory itself. Storing a path here is what produced the
            // broken uploads/thumbnails/category_thumbnails/uploads/... URLs.
            $filename = '';
            if ($r['icon']) {
                $candidate = 'ha-' . $r['code'] . '.jpg';
                if ($this->publish_jpeg($r['icon'], self::CATEGORY_DIR . $candidate, 600)) {
                    $filename = $candidate;
                }
            }

            $parent_id = 0;
            if ($is_child) {
                $parent = $this->db->select('id')
                    ->get_where('category', array('code' => self::MARKER . $r['code']))->row_array();
                if (!$parent) {
                    continue;
                }
                $parent_id = (int) $parent['id'];
            }

            $payload = array(
                'code'                   => $code,
                'name'                   => $r['name'],
                'parent'                 => $parent_id,
                'slug'                   => $is_child ? $r['slug_en'] . '-courses' : $r['slug_en'],
                'font_awesome_class'     => 'fas fa-graduation-cap',
                'thumbnail'              => $filename,
                'sub_category_thumbnail' => $filename,
                'last_modified'          => $this->now,
            );

            $existing = $this->db->get_where('category', array('code' => $code))->row_array();
            if ($existing) {
                $this->db->where('id', $existing['id'])->update('category', $payload);
            } else {
                $payload['date_added'] = $this->now;
                $this->db->insert('category', $payload);
            }
            $written++;
        }
        return $written;
    }

    private function sync_courses() {
        $counts = array('courses' => 0, 'sections' => 0, 'lessons' => 0);

        // Legacy category ids, keyed by academy category code. Sub categories
        // are stored under the same key so a course can reference both tiers.
        $category_ids = array();
        $sub_category_ids = array();
        foreach ($this->db->get('category')->result_array() as $c) {
            if (strpos($c['code'], self::MARKER . 'sub-') === 0) {
                $sub_category_ids[substr($c['code'], strlen(self::MARKER . 'sub-'))] = (int) $c['id'];
            } elseif (strpos($c['code'], self::MARKER) === 0) {
                $category_ids[substr($c['code'], strlen(self::MARKER))] = (int) $c['id'];
            }
        }

        $courses = $this->db
            ->select('c.*, cat.code AS category_code')
            ->select('t.title, t.short_description, t.description, t.requirements', false)
            ->from('ha_course c')
            ->join('ha_course_translation t', "t.course_id = c.id AND t.locale = 'en'", 'left')
            ->join('ha_category cat', 'cat.id = c.category_id', 'left')
            ->where('c.status', 'published')
            ->order_by('c.id', 'ASC')
            ->get()->result_array();

        foreach ($courses as $course) {
            $legacy_id = $this->sync_course($course, $category_ids, $sub_category_ids);
            if (!$legacy_id) {
                continue;
            }
            $counts['courses']++;
            $result = $this->sync_curriculum($course, $legacy_id);
            $counts['sections'] += $result['sections'];
            $counts['lessons'] += $result['lessons'];
        }
        return $counts;
    }

    private function sync_course(array $course, array $category_ids, array $sub_category_ids) {
        // meta_keywords is a free text column on the legacy table and nothing
        // else writes it, so it carries the academy code and keeps this
        // mirror re-runnable without altering the shipped schema.
        $marker = self::MARKER . $course['code'];

        $outcomes = array_column($this->db->select('body')
            ->where(array('course_id' => $course['id'], 'locale' => 'en'))
            ->order_by('sort_order', 'ASC')
            ->get('ha_course_outcome')->result_array(), 'body');

        $faqs = array();
        foreach ($this->db->select('question, answer')
                     ->where(array('course_id' => $course['id'], 'locale' => 'en'))
                     ->order_by('sort_order', 'ASC')
                     ->get('ha_course_faq')->result_array() as $f) {
            $faqs[$f['question']] = $f['answer'];
        }

        $requirements = array();
        if (!empty($course['requirements'])) {
            $requirements[] = $course['requirements'];
        }

        // The legacy course owner must be a real user, or the instructor
        // panel and the course page byline break.
        $instructor = $course['instructor_user_id'];
        if (!$instructor) {
            $admin = $this->db->get_where('users', array('role_id' => 1))->row_array();
            $instructor = $admin ? $admin['id'] : 1;
        }

        $payload = array(
            'title'             => $course['title'],
            'short_description' => $course['short_description'],
            'description'       => $course['description'],
            'outcomes'          => json_encode(array_values($outcomes), JSON_UNESCAPED_UNICODE),
            'faqs'              => json_encode($faqs, JSON_UNESCAPED_UNICODE),
            'requirements'      => json_encode(array_values($requirements), JSON_UNESCAPED_UNICODE),
            'language'          => 'English',
            'category_id'       => isset($category_ids[$course['category_code']])
                                    ? $category_ids[$course['category_code']] : 0,
            'sub_category_id'   => isset($sub_category_ids[$course['category_code']])
                                    ? $sub_category_ids[$course['category_code']] : 0,
            'price'             => (float) $course['price'],
            'discount_flag'     => 0,
            'discounted_price'  => 0,
            'level'             => $this->legacy_level($course['level']),
            'user_id'           => (string) $instructor,
            'creator'           => (int) $instructor,
            'thumbnail'         => $course['thumbnail'] ?: '',
            'video_url'         => $course['preview_video'] ?: '',
            'course_type'       => 'general',
            'is_top_course'     => in_array($course['code'], self::featured(), true) ? 1 : 0,
            'is_admin'          => 1,
            'status'            => 'active',
            'is_free_course'    => ((int) $course['is_free'] === 1) ? 1 : null,
            'multi_instructor'  => 0,
            'enable_drip_content' => 0,
            'expiry_period'     => 0,
            'meta_keywords'     => $marker,
            'meta_description'  => $course['short_description'],
            'last_modified'     => $this->now,
            'publish_date'      => !empty($course['published_at'])
                                    ? strtotime($course['published_at']) : $this->now,
        );

        $existing = $this->db->get_where('course', array('meta_keywords' => $marker))->row_array();
        $previous_stamp = null;
        if ($existing) {
            $previous_stamp = $existing['last_modified'];
            $this->db->where('id', $existing['id'])->update('course', $payload);
            $legacy_id = (int) $existing['id'];
        } else {
            $payload['date_added'] = $this->now;
            $this->db->insert('course', $payload);
            $legacy_id = (int) $this->db->insert_id();
        }

        $this->publish_course_thumbnail($legacy_id, $course['thumbnail'],
            $payload['last_modified'], $previous_stamp);
        return $legacy_id;
    }

    /**
     * The theme builds the thumbnail URL from the course id and its
     * last_modified stamp, so the file has to be written under exactly that
     * name. Older stamps are cleared out or the directory grows on every sync.
     */
    private function publish_course_thumbnail($course_id, $source, $last_modified, $previous_stamp = null) {
        $theme = $this->theme();
        $prefix = 'course_thumbnail_' . $theme . '_' . $course_id;

        // The id and the timestamp are concatenated with no delimiter, so a
        // glob on the id alone is ambiguous: course 11's prefix also matches
        // course 1's file. Only the exact previous name is safe to remove.
        if ($previous_stamp !== null && $previous_stamp != $last_modified) {
            @unlink(FCPATH . self::COURSE_DIR . $prefix . $previous_stamp . '.jpg');
            @unlink(FCPATH . self::COURSE_DIR . 'optimized/' . $prefix . $previous_stamp . '.jpg');
        }

        $this->publish_jpeg($source, self::COURSE_DIR . $prefix . $last_modified . '.jpg', 900);
    }

    /** The legacy theme expects one of these exact strings. */
    private function legacy_level($level) {
        $map = array(
            'foundation'   => 'beginner',
            'intermediate' => 'intermediate',
            'advanced'     => 'advanced',
            'leadership'   => 'advanced',
        );
        return isset($map[$level]) ? $map[$level] : 'beginner';
    }

    /**
     * Sections and lessons are rebuilt rather than diffed. They are wholly
     * owned by the mirror, so replacing them is simpler than reconciling and
     * cannot leave an orphan lesson behind.
     */
    private function sync_curriculum(array $course, $legacy_course_id) {
        $old_sections = $this->db->select('id')
            ->get_where('section', array('course_id' => $legacy_course_id))->result_array();
        $this->db->where('course_id', $legacy_course_id)->delete('lesson');
        $this->db->where('course_id', $legacy_course_id)->delete('section');

        $sections = $this->db
            ->select('id, title_en AS title, sort_order', false)
            ->where('course_id', $course['id'])
            ->order_by('sort_order', 'ASC')
            ->get('ha_course_section')->result_array();

        $section_ids = array();
        $count = array('sections' => 0, 'lessons' => 0);

        foreach ($sections as $s) {
            $this->db->insert('section', array(
                'title'         => $s['title'],
                'course_id'     => $legacy_course_id,
                'order'         => (int) $s['sort_order'] + 1,
                'restricted_by' => '',
            ));
            $legacy_section_id = (int) $this->db->insert_id();
            $section_ids[] = $legacy_section_id;
            $count['sections']++;

            $lessons = $this->db
                ->select('l.id, l.lesson_type, l.duration_seconds, l.is_preview, l.sort_order')
                ->select('lt.title, lt.body', false)
                ->select('v.video_id, v.embed_url, v.author_name, v.author_url, v.status AS video_status', false)
                ->from('ha_lesson l')
                ->join('ha_lesson_translation lt', "lt.lesson_id = l.id AND lt.locale = 'en'", 'left')
                ->join('ha_lesson_video_source v', "v.lesson_id = l.id AND v.status = 'live'", 'left')
                ->where('l.section_id', $s['id'])
                ->where('l.status', 'published')
                ->order_by('l.sort_order', 'ASC')
                ->get()->result_array();

            foreach ($lessons as $l) {
                $type = $this->legacy_lesson_type($l['lesson_type']);

                // A video lesson with no verified source is published as text
                // rather than as a video player with nothing in it. The
                // written content is real; an empty player is a dead end.
                $video_type = '';
                $video_url  = '';
                $summary    = $l['body'];
                if ($type === 'video' && !empty($l['embed_url'])) {
                    $video_type = 'youtube';
                    $video_url  = $l['embed_url'];
                    $summary   .= $this->video_credit($l);
                } elseif ($type === 'video') {
                    $type = 'text';
                }

                $this->db->insert('lesson', array(
                    'title'           => $l['title'],
                    'duration'        => gmdate('H:i:s', (int) $l['duration_seconds']),
                    'course_id'       => $legacy_course_id,
                    'section_id'      => $legacy_section_id,
                    'lesson_type'     => $type,
                    'video_type'      => $video_type,
                    'video_url'       => $video_url,
                    'attachment'      => '',
                    'attachment_type' => '',
                    'summary'         => $summary,
                    'is_free'         => ((int) $l['is_preview'] === 1) ? 1 : 0,
                    'order'           => (int) $l['sort_order'] + 1,
                    'date_added'      => $this->now,
                    'last_modified'   => $this->now,
                ));
                $count['lessons']++;
            }
        }

        // course.section holds the ordered list of its section ids.
        $this->db->where('id', $legacy_course_id)->update('course',
            array('section' => json_encode($section_ids)));

        return $count;
    }

    /**
     * The credit line that travels with a borrowed video.
     *
     * These videos belong to their channels, not to the academy. Showing the
     * channel and linking to the original is the minimum owed to the person
     * who made it, and it is also what tells a learner that this particular
     * lesson is not the academy's own production.
     */
    private function video_credit(array $lesson) {
        if (empty($lesson['author_name'])) {
            return '';
        }
        $name = html_escape($lesson['author_name']);
        $link = !empty($lesson['author_url'])
            ? '<a href="' . html_escape($lesson['author_url']) . '" rel="noopener nofollow" target="_blank">' . $name . '</a>'
            : $name;
        return '<p class="lesson-video-credit"><small>Video by ' . $link
            . ', used under the standard YouTube embed terms. It is not produced by the academy.</small></p>';
    }

    /** The legacy player switches on these values. */
    private function legacy_lesson_type($type) {
        $map = array(
            'video'        => 'video',
            'audio'        => 'audio',
            'text'         => 'text',
            'pdf'          => 'document_type',
            'presentation' => 'document_type',
            'external'     => 'iframe',
            'checklist'    => 'text',
            'sop'          => 'text',
            'interactive'  => 'text',
        );
        return isset($map[$type]) ? $map[$type] : 'text';
    }

    // ------------------------------------------------------------------ enrol

    /**
     * Gives the seeded learners a set of enrolments, so the student area has
     * something real to show instead of an empty shelf.
     */
    public function enrol() {
        $learners = $this->db
            ->select('u.id, u.email, p.department_id')
            ->from('users u')
            ->join('ha_profile p', 'p.user_id = u.id')
            ->join('ha_user_role ur', 'ur.user_id = u.id')
            ->join('ha_role r', "r.id = ur.role_id AND r.code = 'learner'")
            ->get()->result_array();

        if (!$learners) {
            fwrite(STDERR, 'ERROR: no seeded learners found. Run "ha_cli seed" first.' . PHP_EOL);
            exit(1);
        }

        $this->out('Enrolling seeded learners');
        $this->out(str_repeat('-', 72));

        $written = 0;
        foreach ($learners as $learner) {
            // A learner is enrolled on the courses for their own department,
            // which is what the training assignment engine would do.
            $department = $this->db->select('code')
                ->get_where('ha_department', array('id' => $learner['department_id']))->row_array();
            $code = $department ? $department['code'] : null;

            $academy_courses = $this->db->select('code')
                ->from('ha_course')
                ->where('status', 'published')
                ->where($code ? array('department_code' => $code) : array())
                ->order_by('id', 'ASC')
                ->limit(6)
                ->get()->result_array();

            // Some departments have no course of their own yet. Everyone still
            // gets the mandatory safety set, so no learner sees an empty shelf.
            if (!$academy_courses) {
                $academy_courses = $this->db->select('code')
                    ->from('ha_course')
                    ->where('status', 'published')
                    ->where_in('code', array('eng-fire-safety', 'eng-emergency',
                        'mgt-crisis', 'fo-guest-privacy', 'hk-chemical-safety'))
                    ->get()->result_array();
            }

            $enrolled = 0;
            foreach ($academy_courses as $ac) {
                $legacy = $this->db->select('id')
                    ->get_where('course', array('meta_keywords' => self::MARKER . $ac['code']))
                    ->row_array();
                if (!$legacy) {
                    continue;
                }
                $already = $this->db->where(array(
                    'user_id'   => $learner['id'],
                    'course_id' => $legacy['id'],
                ))->count_all_results('enrol');
                if ($already) {
                    continue;
                }
                $this->db->insert('enrol', array(
                    'user_id'       => $learner['id'],
                    'course_id'     => $legacy['id'],
                    'date_added'    => $this->now,
                    'last_modified' => $this->now,
                ));
                $enrolled++;
                $written++;
            }
            $this->out(str_pad($learner['email'], 38) . $enrolled . ' course(s)');
        }

        $this->out(str_repeat('-', 72));
        $this->out('enrolments created: ' . $written);
    }

    // ----------------------------------------------------------------- status

    public function status() {
        $mirrored = $this->db->like('meta_keywords', self::MARKER, 'after')->count_all_results('course');
        $academy = $this->db->where('status', 'published')->count_all_results('ha_course');

        $this->out('Academy catalogue -> Academy LMS');
        $this->out(str_repeat('-', 72));
        $this->out(str_pad('academy courses', 24) . $academy);
        $this->out(str_pad('mirrored courses', 24) . $mirrored);
        $this->out(str_pad('legacy categories', 24) . $this->db->count_all_results('category'));
        $this->out(str_pad('legacy sections', 24) . $this->db->count_all_results('section'));
        $this->out(str_pad('legacy lessons', 24) . $this->db->count_all_results('lesson'));
        $this->out(str_pad('enrolments', 24) . $this->db->count_all_results('enrol'));

        if ($mirrored !== $academy) {
            $this->out('');
            $this->out('Out of date. Run "php index.php ha_bridge sync".');
        }
    }

    public function clear() {
        $courses = $this->db->like('meta_keywords', self::MARKER, 'after')->get('course')->result_array();
        foreach ($courses as $c) {
            $this->db->where('course_id', $c['id'])->delete('lesson');
            $this->db->where('course_id', $c['id'])->delete('section');
            $this->db->where('course_id', $c['id'])->delete('enrol');
            $this->db->where('id', $c['id'])->delete('course');
        }
        $this->db->like('code', self::MARKER, 'after')->delete('category');
        $this->out('Removed ' . count($courses) . ' mirrored course(s).');
    }
}
