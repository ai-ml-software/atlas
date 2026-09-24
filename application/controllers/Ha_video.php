<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Third-party lesson video pipeline.
 *
 *   php index.php ha_video discover     pull candidates from source playlists
 *   php index.php ha_video assign       match verified videos to video lessons
 *   php index.php ha_video recheck      re-verify every assigned video
 *   php index.php ha_video report       what is assigned, what is dead
 *   php index.php ha_video clear        remove every assignment
 *
 * The rule this file exists to enforce: no video reaches a lesson without
 * first having been fetched and confirmed to exist. A video ID is a string
 * that looks valid whether or not it points at anything, and a catalogue of
 * plausible-looking dead embeds is worse than no video at all, because the
 * failure is invisible until a learner hits it.
 *
 * Verification uses YouTube's oEmbed endpoint, which needs no API key and no
 * quota, returns 200 with the title and channel for a playable video, and
 * 401 or 404 for one that is private, deleted or region blocked. The channel
 * name it returns is also the attribution the lesson has to display, so one
 * request satisfies both obligations.
 *
 * These videos belong to their channels. The academy does not own them, and
 * this pipeline records that fact in every row (is_owned defaults to 0) so
 * nothing downstream can mistake a borrowed video for an asset.
 */
class Ha_video extends CI_Controller {

    /** Hospitality training playlists, recorded with what each one covers. */
    private $playlists = array(
        'PLmkDNT2QC8j3kmg0chVXWBmBztYEpQzQ9' => 'front-office',
        'PLmkDNT2QC8j0LHbzhHrUeBonOmZh4Csjq' => 'housekeeping',
        'PLmkDNT2QC8j3lSYwazffOQRK1kuv_pTV7' => 'food-beverage',
        'PLmkDNT2QC8j2-Z3uRsTw7md6ELkIu7mzk' => 'kitchen',
        'PLmkDNT2QC8j2p2LzpAxCrwNNhLM44uFAS' => 'general',
        'PLmkDNT2QC8j3eynI5vWcytax5EKWY1vG6' => 'general',
        'PLmkDNT2QC8j0MfEY3S47YblRdL5BJSB15' => 'general',
    );

    /**
     * Which video teaches which course, decided by watching what each video
     * is actually about rather than by scoring its title.
     *
     * A scorer was tried first and is the reason this map exists. With 39
     * videos covering 74 courses there is no threshold that works: set it
     * loose and a guest-complaint video lands on a HACCP course and a bellboy
     * video on a property-management course, both in the right department and
     * about the wrong thing; set it strict and it still cannot tell "turndown
     * service" from "room turnaround". Title text does not carry enough
     * signal, and a wrong video is worse than none, because it teaches the
     * wrong procedure with the academy's name on it.
     *
     * Courses absent from this map keep their written lesson and are reported
     * by "php index.php ha_audit content" as still needing a video. That gap
     * is the honest state, not an oversight.
     *
     * Every ID here was confirmed playable through oEmbed by discover().
     */
    private $curated = array(
        // Front office
        'fo-fundamentals'         => '8rqcn4RGRys', // Front office dialogue, part 1
        'fo-telephone'            => 'KsDqa3eqvuw', // Answering the telephone, dos and don'ts
        'fo-night-audit'          => 'TjtLVfkKtgY', // Night auditor duties and the night audit process
        'fo-reception-operations' => 'U97mpc-FEfU', // Taking a room reservation by phone
        'fo-complaints'           => 'DhQA7BpptMI', // Handling a guest complaint step by step
        'fo-check-out'            => 'proxfBOA0pY', // Payment methods and bill settlement
        'fo-guest-registration'   => 'FlHoASvqXOY', // The guest folio
        'fo-check-in'             => 'SRP4w3YYNxA', // Pre-registered guest check-in procedure
        'fo-concierge'            => 'trccLlafOjo', // Bell desk and uniformed services
        'fo-room-assignment'      => 'lIw5Xz4Arms', // Room types and how they are classified

        // Housekeeping
        'hk-fundamentals'         => '7fX6H_NxX5s', // Housekeeping control desk
        'hk-room-cleaning'        => 'VC07QgAGOSg', // Room cleaning procedure end to end
        'hk-chemical-safety'      => 'CuocKHxstx8', // Cleaning agents and their types
        'hk-deep-cleaning'        => 'f-dQek2Nzjo', // Cleaning equipment
        'hk-laundry'              => '9r2efmm4KEE', // Linen room and linen keeper
        'hk-turnaround'           => 'DJHRvVn2ZFs', // Entering a guest room correctly
        'hk-productivity'         => 'ckeeJn8D7-0', // Housekeeping do's and don'ts
        'hk-bathroom'             => 'gt8lf_rhusA', // Cleaning and inspecting a guest bathroom
        'hk-public-areas'         => 'IqUBTZ21HNs', // Public areas in a hotel

        // Food and beverage
        'fb-restaurant-service'   => 'xq4LRjw7j8U', // Serving food at table
        'fb-table-service'        => 'NM9f972N4ds', // American service, setting and procedure
        'fb-banquet'              => 'h2EnimgdzP0', // Banquet service styles
        'fb-guest-greeting'       => 'vb5ZTFC3R34', // Greeting, welcoming and seating a guest
        'fb-hygiene'              => 'dWVHVrNT80c', // Personal hygiene in hotels and restaurants
        'fb-upselling'            => '7HJ2WyYoX5o', // Taking an order from the guest
        // Tea and coffee rather than the wine or beer videos in the same
        // playlist: the academy trains hotel teams in Saudi Arabia.
        'fb-beverage'             => 'GC4Mq7-Ta-s', // Serving tea and coffee at table
        'fb-pos'                  => '-8f_WkDvCiQ', // Settling the guest bill and payment methods

        // Kitchen, engineering and management
        'kit-knife-safety'        => 'PJ2_OKPutzA', // Preventing hand and finger cuts in the kitchen
        'eng-emergency'           => 'v54YxHTa7Ys', // Handling emergency situations
        'mgt-guest-experience'    => 'j6onuyGn1kc', // Assisting guests with special needs
    );

    // Deliberately left unmapped, with the reason, so nobody re-adds them:
    //   fo-vip        the only candidate was generic polite phrases, not VIP handling
    //   hk-inspection par stock of linen is inventory, not room inspection
    //   hk-lost-found the only candidate was general housekeeping dialogue
    //   fb-haccp, fb-food-safety, kit-* except knife safety: the source
    //                 channel covers service, not kitchen production
    //   eng-*, fin-*, rev-*, dig-* marketing and SEO: no hospitality training
    //                 video in these sources covers them at all, and a
    //                 general business video would teach the wrong context

    public function __construct() {
        parent::__construct();
        if (!is_cli()) {
            show_404();
        }
        @set_time_limit(0);
        $this->load->database();
    }

    private function out($line = '') {
        fwrite(STDOUT, $line . PHP_EOL);
    }

    // ------------------------------------------------------------- transport

    private function get($url) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 30,
            CURLOPT_USERAGENT      => 'HospitalityAcademy/1.0 (course video verification)',
            CURLOPT_SSL_VERIFYPEER => false,
        ));
        $body   = curl_exec($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error  = curl_error($ch);
        curl_close($ch);
        return array('status' => $status, 'body' => (string) $body, 'error' => $error);
    }

    /**
     * Confirm a video exists and collect the credit it obliges.
     * Returns null when the video is not playable, which is the whole point.
     */
    private function verify($video_id) {
        $watch = 'https://www.youtube.com/watch?v=' . $video_id;
        $res = $this->get('https://www.youtube.com/oembed?url=' . rawurlencode($watch) . '&format=json');

        if ($res['status'] !== 200) {
            return array('ok' => false, 'error' => 'oembed HTTP ' . $res['status'] . ($res['error'] ? ' ' . $res['error'] : ''));
        }
        $data = json_decode($res['body'], true);
        if (!is_array($data) || empty($data['title'])) {
            return array('ok' => false, 'error' => 'oembed returned no title');
        }
        return array(
            'ok'            => true,
            'title'         => $data['title'],
            'author_name'   => isset($data['author_name']) ? $data['author_name'] : null,
            'author_url'    => isset($data['author_url']) ? $data['author_url'] : null,
            'thumbnail_url' => isset($data['thumbnail_url']) ? $data['thumbnail_url'] : null,
        );
    }

    // -------------------------------------------------------------- discover

    /**
     * Read each source playlist and verify every video in it. The RSS feed is
     * used rather than the Data API because it needs no key and no quota;
     * the tradeoff is that it returns the most recent 15 entries per
     * playlist, which is recorded honestly in the output rather than hidden.
     */
    public function discover() {
        $this->out('Discovering lesson videos from source playlists');
        $this->out(str_repeat('-', 72));

        $found = array();
        foreach ($this->playlists as $playlist_id => $department) {
            // The RSS feed returns only the 15 most recent entries per
            // playlist, which left most of each playlist invisible. The
            // playlist page carries every video id in its embedded data, so
            // it is read instead and the ids are verified afterwards anyway.
            $res = $this->get('https://www.youtube.com/playlist?list=' . $playlist_id);
            if ($res['status'] !== 200) {
                $this->out('  ' . $department . ': playlist unreachable (HTTP ' . $res['status'] . ')');
                continue;
            }
            preg_match_all('#"videoId":"([A-Za-z0-9_-]{11})"#', $res['body'], $ids);

            $count = 0;
            foreach (array_unique($ids[1]) as $video_id) {
                // A video already claimed by a more specific playlist keeps
                // that department: "general" is the weakest signal here.
                if (isset($found[$video_id]) && $department === 'general') {
                    continue;
                }
                $found[$video_id] = array('department' => $department, 'title' => '');
                $count++;
            }
            $this->out('  ' . str_pad($department, 16) . $count . ' candidates');
        }

        $this->out(str_repeat('-', 72));
        $this->out('Verifying ' . count($found) . ' videos against oEmbed');

        $live = 0;
        $dead = 0;
        $now  = date('Y-m-d H:i:s');
        foreach ($found as $video_id => $meta) {
            $check = $this->verify($video_id);
            if (!$check['ok']) {
                $dead++;
                $this->out('  dead  ' . $video_id . '  ' . $check['error']);
                continue;
            }
            $live++;
            $row = array(
                'provider'        => 'youtube',
                'video_id'        => $video_id,
                'watch_url'       => 'https://www.youtube.com/watch?v=' . $video_id,
                'embed_url'       => 'https://www.youtube-nocookie.com/embed/' . $video_id,
                'title'           => $check['title'],
                'author_name'     => $check['author_name'],
                'author_url'      => $check['author_url'],
                'thumbnail_url'   => $check['thumbnail_url'],
                'is_owned'        => 0,
                'status'          => 'live',
                'last_checked_at' => $now,
                'last_error'      => null,
                'updated_at'      => $now,
            );
            // Parked against lesson_id 0 until assign() places it. The unique
            // key is on lesson_id, so the staging rows live in a side table.
            $this->stage($video_id, $meta['department'], $row);
        }

        $this->out(str_repeat('-', 72));
        $this->out('verified live: ' . $live . '   unplayable: ' . $dead);
        $this->out('Run "php index.php ha_video assign" to place them on lessons.');
    }

    /** Staging lives in a temp table so discover() can run without touching lessons. */
    private function stage($video_id, $department, array $row) {
        $this->db->query("
            CREATE TABLE IF NOT EXISTS ha_lesson_video_candidate (
                video_id      VARCHAR(64) NOT NULL,
                department    VARCHAR(40) NOT NULL,
                payload       TEXT NOT NULL,
                created_at    DATETIME NOT NULL,
                PRIMARY KEY (video_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
        $this->db->replace('ha_lesson_video_candidate', array(
            'video_id'   => $video_id,
            'department' => $department,
            'payload'    => json_encode($row, JSON_UNESCAPED_UNICODE),
            'created_at' => date('Y-m-d H:i:s'),
        ));
    }

    // ---------------------------------------------------------------- assign

    /**
     * Place verified videos on the lessons that declare a video and carry
     * none. A lesson is matched on its course subject, and anything that does
     * not match stays empty: a housekeeping video on a kitchen course is
     * worse than a lesson that is honestly still written-only.
     */
    public function assign() {
        if (!$this->db->table_exists('ha_lesson_video_candidate')) {
            $this->out('No candidates. Run "php index.php ha_video discover" first.');
            return;
        }
        $candidates = $this->db->get('ha_lesson_video_candidate')->result_array();
        if (!$candidates) {
            $this->out('No candidates. Run "php index.php ha_video discover" first.');
            return;
        }

        $lessons = $this->db
            ->select('l.id, l.course_id, c.code AS course_code, ct.title AS course_title')
            ->from('ha_lesson l')
            ->join('ha_course c', 'c.id = l.course_id')
            ->join('ha_course_translation ct', "ct.course_id = c.id AND ct.locale = 'en'", 'left')
            ->where('l.lesson_type', 'video')
            ->order_by('l.course_id')
            ->get()->result_array();

        $this->out('video lessons: ' . count($lessons) . '   verified videos: ' . count($candidates));
        $this->out(str_repeat('-', 72));

        $now = date('Y-m-d H:i:s');
        $placed = 0;
        $skipped = 0;

        foreach ($lessons as $lesson) {
            $best = $this->curated_match($lesson, $candidates);
            if ($best === null) {
                $skipped++;
                continue;
            }
            $row = json_decode($best['payload'], true);
            $row['lesson_id']  = $lesson['id'];
            $row['created_at'] = $now;
            $row['updated_at'] = $now;

            $exists = $this->db->where('lesson_id', $lesson['id'])
                ->count_all_results('ha_lesson_video_source');
            if ($exists) {
                $this->db->where('lesson_id', $lesson['id'])->update('ha_lesson_video_source', $row);
            } else {
                $this->db->insert('ha_lesson_video_source', $row);
            }

            // Keep ha_lesson's own columns in step so anything reading the
            // lesson directly still finds the video.
            $this->db->where('id', $lesson['id'])->update('ha_lesson', array(
                'video_source' => 'youtube',
                'video_url'    => $row['watch_url'],
                'updated_at'   => $now,
            ));

            $placed++;
        }

        $this->out('placed: ' . $placed . '   left written-only: ' . $skipped);
        if ($skipped) {
            $this->out('');
            $this->out('The ' . $skipped . ' unmatched lessons keep their written content. They are');
            $this->out('reported by "php index.php ha_audit content" and need a video of their own.');
        }
    }

    /** Look the lesson's course up in the curated map. Nothing else places a video. */
    private function curated_match(array $lesson, array $candidates) {
        $code = (string) $lesson['course_code'];
        if (!isset($this->curated[$code])) {
            return null;
        }
        $wanted = $this->curated[$code];
        foreach ($candidates as $candidate) {
            if ($candidate['video_id'] === $wanted) {
                return $candidate;
            }
        }
        // Curated but the video did not survive verification. Say so rather
        // than quietly leaving the course looking merely uncurated.
        $this->out('  ' . $code . ': curated video ' . $wanted . ' is not in the verified set');
        return null;
    }

    // --------------------------------------------------------------- recheck

    /**
     * Re-verify every assigned video. Third-party videos are taken down,
     * made private and age-gated without notice, so this is meant to run on
     * a schedule and not once at launch.
     */
    public function recheck() {
        $rows = $this->db->get('ha_lesson_video_source')->result_array();
        $this->out('Re-verifying ' . count($rows) . ' assigned videos');
        $this->out(str_repeat('-', 72));

        $now = date('Y-m-d H:i:s');
        $live = 0;
        $gone = array();
        foreach ($rows as $row) {
            $check = $this->verify($row['video_id']);
            $update = array('last_checked_at' => $now, 'updated_at' => $now);
            if ($check['ok']) {
                $live++;
                $update['status']      = 'live';
                $update['last_error']  = null;
                $update['title']       = $check['title'];
                $update['author_name'] = $check['author_name'];
            } else {
                $update['status']     = 'unavailable';
                $update['last_error'] = $check['error'];
                $gone[] = $row['video_id'] . ' on lesson ' . $row['lesson_id'] . ': ' . $check['error'];
            }
            $this->db->where('id', $row['id'])->update('ha_lesson_video_source', $update);
        }

        $this->out('live: ' . $live . '   unavailable: ' . count($gone));
        foreach ($gone as $g) {
            $this->out('  ' . $g);
        }
        if ($gone) {
            $this->out('');
            $this->out('Unavailable videos still have their lesson text. Replace or remove them.');
            exit(1);
        }
        $this->out('OK');
    }

    // ---------------------------------------------------------------- report

    public function report() {
        $total = $this->db->count_all('ha_lesson_video_source');
        $live  = $this->db->where('status', 'live')->count_all_results('ha_lesson_video_source');
        $dead  = $this->db->where('status', 'unavailable')->count_all_results('ha_lesson_video_source');
        $lessons = $this->db->where('lesson_type', 'video')->count_all_results('ha_lesson');

        $this->out('video lessons:        ' . $lessons);
        $this->out('with a video:         ' . $total);
        $this->out('  playable:           ' . $live);
        $this->out('  unavailable:        ' . $dead);
        $this->out('still written-only:   ' . ($lessons - $total));
        $this->out('');

        $channels = $this->db->select('author_name, COUNT(*) AS n', false)
            ->group_by('author_name')->order_by('n', 'DESC')
            ->get('ha_lesson_video_source')->result_array();
        if ($channels) {
            $this->out('Channels used (every one of these is credited on the lesson):');
            foreach ($channels as $c) {
                $this->out('  ' . str_pad((string) $c['n'], 4, ' ', STR_PAD_LEFT) . '  ' . $c['author_name']);
            }
        }
    }

    public function clear() {
        $this->db->empty_table('ha_lesson_video_source');
        $this->db->where('video_source', 'youtube')->update('ha_lesson', array(
            'video_source' => null, 'video_url' => null,
        ));
        $this->out('Cleared every video assignment.');
    }
}
