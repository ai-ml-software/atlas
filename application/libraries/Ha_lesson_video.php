<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Shared video selection for learner playback and read-only editor previews. */
class Ha_lesson_video {
    private $CI;
    public function __construct() { $this->CI =& get_instance(); }

    public function for_lesson(array $l, $user_id, $locale) {
        $src = null;
        $country = null;
        if ($this->CI->db->table_exists('ha_language_inventory')) {
            $this->CI->load->library('ha_library_video');
            $profile = $this->CI->db->get_where('ha_profile', array('user_id' => $user_id))->row_array();
            $country = $profile && isset($profile['country']) ? $profile['country'] : null;
            $src = $this->CI->ha_library_video->for_lesson($l['id'], $locale, $country);
        } elseif ($this->CI->db->table_exists('ha_lesson_video_source')) {
            $src = $this->CI->db->where(array('lesson_id' => $l['id'], 'status' => 'live'))->limit(1)->get('ha_lesson_video_source')->row_array();
        }
        if (!$src && $l['lesson_type'] !== 'video') { return null; }
        // Preserve grandfathered URLs, while a known-unavailable source never
        // becomes playable again merely by falling back to the same URL.
        if (!$src && $this->CI->db->table_exists('ha_lesson_video_source')) {
            foreach ($this->CI->db->get_where('ha_lesson_video_source', array('lesson_id' => $l['id']))->result_array() as $candidate) {
                if ($candidate['watch_url'] !== $l['video_url'] && $candidate['embed_url'] !== $l['video_url']) { continue; }
                if ($candidate['status'] === 'unavailable' || (isset($candidate['region_restrictions']) && !Ha_library_video::country_allowed($candidate['region_restrictions'], $country))) { return null; }
            }
        }
        $url = $src ? ($src['provider'] === 'academy' || $src['provider'] === 'upload' ? $src['watch_url'] : ($src['embed_url'] ?: $src['watch_url'])) : $l['video_url'];
        if (!$url) {
            return null;
        }
        $meta = array('locale' => $src && isset($src['locale']) ? $src['locale'] : 'en', 'credit' => $src ? $src['author_name'] : '',
            'captions_url' => $src && !empty($src['captions_authorized']) ? $src['captions_path'] : $l['captions_url'],
            'watch_url' => $src && preg_match('~^https://~', $src['watch_url']) ? $src['watch_url'] : null);
        if (preg_match('~(?:youtube(?:-nocookie)?\.com/(?:watch\?v=|embed/)|youtu\.be/)([A-Za-z0-9_-]{11})~', $url, $m)) {
            return array('type' => 'embed', 'src' => 'https://www.youtube-nocookie.com/embed/' . $m[1]) + $meta;
        }
        if (preg_match('~vimeo\.com/(\d+)~', $url, $m)) {
            return array('type' => 'embed', 'src' => 'https://player.vimeo.com/video/' . $m[1]) + $meta;
        }
        if (preg_match('~dailymotion\.com/(?:embed/video/|video/)([A-Za-z0-9]+)~', $url, $m)) { return array('type' => 'embed', 'src' => 'https://www.dailymotion.com/embed/video/' . $m[1]) + $meta; }
        return array('type' => 'file', 'src' => preg_match('~^https?://~', $url) ? $url : base_url(ltrim($url, '/'))) + $meta;
    }
}
