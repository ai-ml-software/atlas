<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Availability evidence is distinct from editorial relevance and popularity. */
class Ha_library_video {
    private $CI;
    private $db;
    public function __construct() { $this->CI =& get_instance(); $this->CI->load->database(); $this->db = $this->CI->db; $this->CI->load->library('ha_library_review'); }
    public static function urls($provider, $id) {
        if ($provider === 'youtube' && preg_match('/^[A-Za-z0-9_-]{11}$/D', $id)) { return array('https://www.youtube.com/watch?v=' . $id, 'https://www.youtube-nocookie.com/embed/' . $id); }
        if ($provider === 'vimeo' && ctype_digit((string) $id)) { return array('https://vimeo.com/' . $id, 'https://player.vimeo.com/video/' . $id); }
        if ($provider === 'dailymotion' && preg_match('/^[A-Za-z0-9]+$/D', $id)) { return array('https://www.dailymotion.com/video/' . $id, 'https://www.dailymotion.com/embed/video/' . $id); }
        if ($provider === 'upload' && preg_match('~^uploads/academy/library/(?:signed|videos)/[A-Za-z0-9_./-]+\.(mp4|webm)$~D', $id) && strpos($id, '..') === false) { return array($id, $id); }
        throw new InvalidArgumentException('Invalid video provider or identifier.');
    }
    public static function country_allowed($json, $country) {
        $r = json_decode((string) $json, true);
        if (!$country || !is_array($r)) { return true; }
        $country = strtoupper($country);
        if (isset($r['allowed']) && !in_array($country, $r['allowed'], true)) { return false; }
        return !isset($r['blocked']) || !in_array($country, $r['blocked'], true);
    }
    public static function media_unchanged(array $source) {
        if ($source['provider'] !== 'upload') { return true; }
        $evidence=json_decode((string)$source['verification_json'],true);
        self::urls($source['provider'],$source['video_id']);
        $path=FCPATH.$source['video_id'];
        return is_file($path) && !empty($evidence['sha256']) && hash_equals($evidence['sha256'],hash_file('sha256',$path));
    }
    public function import(array $package) {
        if (!isset($package['version']) || $package['version'] !== 1 || !isset($package['videos']) || !is_array($package['videos'])) { throw new InvalidArgumentException('Invalid video package.'); }
        $validated = array();
        foreach ($package['videos'] as $v) {
            if (empty($v['course_code']) || empty($v['lesson_key']) || empty($v['provider']) || !isset($v['video_id'])) { throw new InvalidArgumentException('Course, lesson and provider identifiers are required.'); }
            $c = $this->CI->ha_library_review->course($v['course_code']); $keys = $this->CI->ha_library_review->keys($c);
            if (!isset($keys['lessons'][$v['lesson_key']])) { throw new InvalidArgumentException('Unknown lesson key.'); }
            $id = $this->CI->ha_library_review->identity('lesson', $v['lesson_key'], $v['course_code']);
            if (!$id) { throw new InvalidArgumentException('Import the course identities before videos.'); }
            $locale = isset($v['locale']) ? $v['locale'] : 'en'; $this->CI->ha_library_review->language($locale);
            list($watch, $embed) = self::urls($v['provider'], $v['video_id']);
            $score = isset($v['editorial_score']) ? (int) $v['editorial_score'] : null;
            if ($score !== null && ($score < 0 || $score > 100)) { throw new InvalidArgumentException('Editorial score must be 0–100.'); }
            $captions = !empty($v['captions_path']) ? $v['captions_path'] : null;
            if ($captions && (empty($v['captions_authorized']) || !preg_match('~^uploads/academy/library/captions/[A-Za-z0-9_./-]+\.vtt$~D', $captions) || strpos($captions, '..') !== false || !is_file(FCPATH . $captions))) { throw new InvalidArgumentException('Captions require authorization and an existing local VTT.'); }
            $validated[] = array('lesson_id' => $id, 'provider' => $v['provider'], 'video_id' => $v['video_id'], 'locale' => $locale,
                'watch_url' => $watch, 'embed_url' => $embed, 'title' => isset($v['title']) ? $v['title'] : null,
                'author_name' => isset($v['author_name']) ? $v['author_name'] : null, 'author_url' => isset($v['author_url']) ? $v['author_url'] : null,
                'duration_seconds' => isset($v['duration_seconds']) ? max(0, (int) $v['duration_seconds']) : 0,
                'relevance_reason' => isset($v['relevance_reason']) ? $v['relevance_reason'] : null,
                'editorial_score' => $score, 'reviewer' => !empty($v['reviewer']) ? $v['reviewer'] : null,
                'sort_order' => isset($v['sort_order']) ? (int) $v['sort_order'] : 0, 'is_owned' => $v['provider'] === 'upload' ? 1 : 0,
                'captions_authorized' => $captions ? 1 : 0, 'captions_path' => $captions, 'status' => 'unchecked',
                'embeddable' => null, 'verification_json' => null, 'last_checked_at' => null, 'last_error' => null, 'updated_at' => date('Y-m-d H:i:s'));
        }
        $this->db->trans_begin();
        try {
            foreach ($validated as $v) {
                $match = array_intersect_key($v, array_flip(array('lesson_id','provider','video_id','locale')));
                $old = $this->db->get_where('ha_lesson_video_source', $match)->row_array();
                if ($old) {
                    // Editorial edits do not invalidate evidence for the same source.
                    foreach (array('status','embeddable','verification_json','last_checked_at','last_error') as $field) { unset($v[$field]); }
                    foreach (array('duration_seconds','title','author_name','author_url') as $field) { if (!$v[$field] && $old[$field]) { $v[$field] = $old[$field]; } }
                    $this->db->where($match)->update('ha_lesson_video_source', $v);
                } else { $this->db->insert('ha_lesson_video_source', $v + array('created_at' => date('Y-m-d H:i:s'))); }
            }
            if (!$this->db->trans_status()) { throw new RuntimeException('Video import failed.'); }
            $this->db->trans_commit();
        } catch (Throwable $e) { $this->db->trans_rollback(); throw $e; }
        return count($validated);
    }
    private function get_json($url, array $headers = array()) {
        $ch = curl_init($url);
        curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 20,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_PROTOCOLS => CURLPROTO_HTTPS, CURLOPT_HTTPHEADER => $headers, CURLOPT_USERAGENT => 'AtlasCourseVideoVerification/1.0'));
        $body = curl_exec($ch); $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        if ($status !== 200 || $body === false) { throw new RuntimeException('Provider verification HTTP ' . $status); }
        $data = json_decode($body, true);
        if (!is_array($data)) { throw new RuntimeException('Provider returned invalid verification data.'); }
        return $data;
    }
    public static function youtube_details(array $item) {
        if (!isset($item['status']['embeddable'], $item['status']['privacyStatus'], $item['contentDetails']['duration'], $item['snippet']['title'])) { throw new RuntimeException('YouTube response lacks embedding, privacy or duration evidence.'); }
        $interval = new DateInterval($item['contentDetails']['duration']);
        $duration = $interval->h*3600 + $interval->i*60 + $interval->s + $interval->d*86400;
        $playable = $item['status']['embeddable'] && $item['status']['privacyStatus'] === 'public' && empty($item['contentDetails']['contentRating']['ytRating']);
        return array('status' => $playable ? 'live' : 'unavailable', 'embeddable' => (int) $item['status']['embeddable'],
            'duration_seconds' => $duration, 'title' => $item['snippet']['title'], 'author_name' => $item['snippet']['channelTitle'],
            'author_url' => 'https://www.youtube.com/channel/' . $item['snippet']['channelId'],
            'youtube_license' => isset($item['status']['license']) ? $item['status']['license'] : null,
            'region_restrictions' => json_encode(isset($item['contentDetails']['regionRestriction']) ? $item['contentDetails']['regionRestriction'] : new stdClass()),
            'verification_json' => json_encode(array('method' => 'youtube-data-api', 'item' => $item)), 'last_error' => $playable ? null : 'Video is private, age restricted, or embedding disabled');
    }
    public function verify(array $v) {
        self::urls($v['provider'], $v['video_id']);
        if ($v['provider'] === 'youtube') {
            $key = getenv('HA_YOUTUBE_API_KEY');
            if (!$key) {
                // oEmbed confirms attribution only; it does not establish global playback.
                $o = $this->get_json('https://www.youtube.com/oembed?format=json&url=' . rawurlencode($v['watch_url']));
                return array('status' => 'unchecked', 'title' => $o['title'], 'author_name' => $o['author_name'], 'author_url' => $o['author_url'],
                    'thumbnail_url' => isset($o['thumbnail_url']) ? $o['thumbnail_url'] : null, 'embeddable' => null,
                    'last_error' => 'Attribution verified; HA_YOUTUBE_API_KEY needed for embedding and region checks',
                    'verification_json' => json_encode(array('method' => 'youtube-oembed-attribution-only')));
            }
            $data = $this->get_json('https://www.googleapis.com/youtube/v3/videos?part=snippet,status,contentDetails&id=' . rawurlencode($v['video_id']) . '&key=' . rawurlencode($key));
            if (empty($data['items'])) { return array('status' => 'unavailable', 'embeddable' => 0, 'last_error' => 'Video absent from YouTube Data API'); }
            return self::youtube_details($data['items'][0]);
        }
        if ($v['provider'] === 'dailymotion') {
            $data = $this->get_json('https://api.dailymotion.com/video/' . rawurlencode($v['video_id']) . '?fields=id,title,duration,owner.screenname,owner.url,allow_embed,geoblocking,private,published');
            if (!isset($data['allow_embed'], $data['geoblocking'], $data['private'], $data['published'])) { throw new RuntimeException('Dailymotion embedding or region evidence missing.'); }
            $regions = $data['geoblocking']; $rules = new stdClass();
            if (is_array($regions) && $regions) { $mode = array_shift($regions); $rules = array($mode === 'deny' ? 'blocked' : 'allowed' => array_map('strtoupper', $regions)); }
            $playable = $data['allow_embed'] && !$data['private'] && $data['published'];
            return array('status' => $playable ? 'live' : 'unavailable', 'embeddable' => (int) $data['allow_embed'], 'title' => $data['title'],
                'duration_seconds' => (int) $data['duration'], 'author_name' => $data['owner.screenname'], 'author_url' => $data['owner.url'],
                'region_restrictions' => json_encode($rules), 'verification_json' => json_encode(array('method' => 'dailymotion-api', 'item' => $data)), 'last_error' => $playable ? null : 'Video is private, unpublished or embedding disabled');
        }
        if ($v['provider'] === 'vimeo') {
            $data = $this->get_json('https://vimeo.com/api/oembed.json?url=' . rawurlencode($v['watch_url']));
            return array('status' => 'unchecked', 'embeddable' => null, 'title' => $data['title'], 'author_name' => $data['author_name'],
                'duration_seconds' => isset($data['duration']) ? (int) $data['duration'] : 0,
                'last_error' => 'Attribution verified; embedding/privacy restrictions need provider review', 'verification_json' => json_encode(array('method' => 'vimeo-oembed-attribution-only')));
        }
        if ($v['provider'] === 'upload') {
            $path = FCPATH . $v['video_id'];
            if (!is_file($path) || !(int) $v['duration_seconds'] || !$v['reviewer']) { throw new RuntimeException('Owned video needs an existing file, measured duration and reviewer.'); }
            return array('status' => 'live', 'embeddable' => 1, 'region_restrictions' => '{}', 'last_error' => null,
                'verification_json' => json_encode(array('method' => 'local-file-reviewed', 'sha256' => hash_file('sha256', $path))));
        }
        throw new RuntimeException('Provider verification is not supported.');
    }
    public function verify_all($code = null) {
        $files = $this->CI->ha_library_review->course_files();
        if ($code && !isset($files[$code])) { throw new InvalidArgumentException('Course is outside the PDF manifest.'); }
        $query = $this->db->select('v.*')->from('ha_lesson_video_source v')->join('ha_lesson l', 'l.id=v.lesson_id')->join('ha_course c','c.id=l.course_id')->where_in('c.code', $code ? array($code) : array_keys($files));
        $out = array();
        foreach ($query->get()->result_array() as $v) {
            try { $data = $this->verify($v); }
            catch (Throwable $e) { $data = array('status' => 'unchecked', 'embeddable' => null, 'last_error' => mb_substr($e->getMessage(), 0, 250)); }
            $data['last_checked_at'] = date('Y-m-d H:i:s'); $data['updated_at'] = $data['last_checked_at'];
            $this->db->where('id', $v['id'])->update('ha_lesson_video_source', $data);
            $out[] = array('id' => $v['id'], 'status' => $data['status'], 'problem' => isset($data['last_error']) ? $data['last_error'] : null);
        }
        return $out;
    }
    public function report($code = null) {
        $out = array();
        foreach ($this->CI->ha_library_review->course_files() as $c => $file) {
            if ($code && $c !== $code) { continue; }
            foreach ($this->CI->ha_library_review->keys(Ha_library_review::json_file($file))['lessons'] as $key => $l) {
                $id = $this->CI->ha_library_review->identity('lesson', $key, $c);
                $videos = $id ? $this->db->get_where('ha_lesson_video_source', array('lesson_id' => $id))->result_array() : array();
                $out[] = array('course' => $c, 'lesson_key' => $key, 'title' => $l['title'], 'sources' => $videos, 'missing' => !$videos);
            }
        }
        return $out;
    }
    public function for_lesson($id, $locale, $country = null) {
        $rows = $this->db->where(array('lesson_id' => (int) $id, 'status' => 'live'))->order_by('editorial_score', 'DESC')->order_by('sort_order')->get('ha_lesson_video_source')->result_array();
        usort($rows, function ($a, $b) use ($locale) { return ($a['locale'] === $locale ? 0 : 1) <=> ($b['locale'] === $locale ? 0 : 1); });
        foreach ($rows as $r) { if (self::country_allowed($r['region_restrictions'], $country) && self::media_unchanged($r)) { return $r; } }
        return null;
    }
}
