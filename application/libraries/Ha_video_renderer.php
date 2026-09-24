<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * Narrated slide video: the academy's own lesson video, produced from an
 * approved lesson script.
 *
 *   1. every slide is drawn as a 1280x720 PNG with GD (Arabic shaped and
 *      laid out right to left through Ha_arabic);
 *   2. each slide's narration is synthesised through the "narration" task
 *      route; with no route the video is silent and timed to reading speed;
 *   3. ffmpeg encodes one segment per slide and concatenates them (H.264 +
 *      AAC, yuv420p, faststart, so every browser plays it progressively);
 *   4. a WebVTT caption file is written from the same narration and timing.
 *
 * Output lives under uploads/ai_videos/. Nothing here publishes: the job goes
 * back to "draft" and a person approves the finished video.
 */
class Ha_video_renderer {

    private $CI;
    private $cfg;
    private $progress;

    public function __construct() {
        $this->CI =& get_instance();
        $this->CI->config->load('ha_ai', true);
        $this->cfg = (array) $this->CI->config->item('ha_ai_video', 'ha_ai');
        require_once APPPATH . 'libraries/Ha_arabic.php';
    }

    /** Optional callback(int percent, string note) for job progress. */
    public function on_progress(callable $fn) {
        $this->progress = $fn;
    }

    private function progress($pct, $note) {
        if ($this->progress) {
            call_user_func($this->progress, $pct, $note);
        }
    }

    // ------------------------------------------------------------- ffmpeg

    public function ffmpeg() {
        $configured = trim((string) $this->cfg['ffmpeg_path']);
        $candidates = $configured !== '' ? array($configured) : array('ffmpeg');
        if (DIRECTORY_SEPARATOR === '\\') {
            $candidates[] = 'C:/ffmpeg/bin/ffmpeg.exe';
            $candidates[] = 'C:/laragon/bin/ffmpeg/bin/ffmpeg.exe';
            foreach (glob(getenv('LOCALAPPDATA') . '/Microsoft/WinGet/Packages/Gyan.FFmpeg*/ffmpeg-*/bin/ffmpeg.exe') ?: array() as $g) {
                $candidates[] = $g;
            }
        } else {
            $candidates[] = '/usr/bin/ffmpeg';
            $candidates[] = '/usr/local/bin/ffmpeg';
            $candidates[] = FCPATH . 'bin/ffmpeg';
        }
        foreach ($candidates as $bin) {
            $out = $this->run(escapeshellarg($bin) . ' -hide_banner -version');
            if ($out['code'] === 0 && stripos($out['output'], 'ffmpeg version') !== false) {
                return $bin;
            }
        }
        return null;
    }

    public function status() {
        $bin = $this->ffmpeg();
        return array(
            'ffmpeg' => $bin,
            'exec' => function_exists('proc_open'),
            'gd' => function_exists('imagettftext'),
            'font_latin' => $this->font(false),
            'font_arabic' => $this->font(true),
        );
    }

    private function run($cmd) {
        if (!function_exists('proc_open')) {
            return array('code' => -1, 'output' => 'proc_open is disabled on this server');
        }
        $proc = proc_open($cmd, array(1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes);
        if (!is_resource($proc)) {
            return array('code' => -1, 'output' => 'could not start process');
        }
        $out = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
        fclose($pipes[1]);
        fclose($pipes[2]);
        return array('code' => proc_close($proc), 'output' => $out);
    }

    // --------------------------------------------------------------- render

    /**
     * @param array  $script  array('slides' => array(array('title', 'bullets' => [], 'narration')))
     * @param string $locale  en | ar
     * @param string $name    file stem, e.g. "lesson-123-en"
     * @param array  $meta    array('course' => title, 'lesson' => title)
     * @return array('video' => rel path, 'poster' => rel path, 'captions' => rel path, 'seconds' => float, 'narrated' => bool)
     */
    public function render(array $script, $locale, $name, array $meta = array()) {
        $slides = isset($script['slides']) ? array_values($script['slides']) : array();
        if (!$slides) {
            throw new RuntimeException('The script has no slides to render.');
        }
        $ffmpeg = $this->ffmpeg();
        if (!$ffmpeg) {
            throw new RuntimeException('ffmpeg was not found. Install it (Windows: winget install Gyan.FFmpeg; Linux: apt install ffmpeg) or set HA_FFMPEG to its path.');
        }
        if (!function_exists('imagettftext')) {
            throw new RuntimeException('PHP GD with FreeType is required to draw slides.');
        }
        $rtl = $locale === 'ar';
        if (!$this->font($rtl)) {
            throw new RuntimeException('No usable ' . ($rtl ? 'Arabic' : 'Latin') . ' font found. Put a TTF under assets/academy/fonts/.');
        }

        $out_dir = rtrim($this->cfg['output_dir'], '/') . '/';
        $work = FCPATH . $out_dir . '_work/' . $name . '/';
        if (!is_dir($work)) {
            mkdir($work, 0755, true);
        }

        $this->CI->load->library('ha_ai_media');
        $narrate = $this->CI->ha_ai_gateway->route('narration') !== null;
        $segments = array();
        $cues = array();
        $clock = 0.0;
        $count = count($slides);

        foreach ($slides as $i => $slide) {
            $n = $i + 1;
            $this->progress(5 + (int) (80 * $i / $count), 'Slide ' . $n . ' of ' . $count);
            $png = $work . sprintf('slide-%02d.png', $n);
            $this->draw_slide($slide, $n, $count, $locale, $meta, $png);

            $text = trim(isset($slide['narration']) ? (string) $slide['narration'] : '');
            $audio = null;
            if ($narrate && $text !== '') {
                $speech = $this->CI->ha_ai_media->speak($text, $locale);
                $audio = $work . sprintf('slide-%02d.%s', $n, $speech['ext']);
                file_put_contents($audio, $speech['bytes']);
                $seconds = $this->duration($ffmpeg, $audio);
                if ($seconds <= 0) {
                    throw new RuntimeException('Could not read the length of the narration audio for slide ' . $n . '.');
                }
            } else {
                // Silent: long enough to read the slide and its caption.
                $words = max(1, count(preg_split('/\s+/u', $text . ' ' . implode(' ', (array) (isset($slide['bullets']) ? $slide['bullets'] : array())), -1, PREG_SPLIT_NO_EMPTY)));
                $seconds = max(5.0, min(40.0, $words / 2.6));
            }
            $hold = $seconds + 0.8;   // a breath between slides

            $seg = $work . sprintf('seg-%02d.mp4', $n);
            $cmd = escapeshellarg($ffmpeg) . ' -y -hide_banner -loglevel error -loop 1 -framerate ' . (int) $this->cfg['fps']
                 . ' -i ' . escapeshellarg($png);
            if ($audio) {
                $cmd .= ' -i ' . escapeshellarg($audio) . ' -filter_complex "[1:a]apad=pad_dur=0.8,aresample=44100[a]" -map 0:v -map "[a]"';
            } else {
                $cmd .= ' -f lavfi -i anullsrc=channel_layout=stereo:sample_rate=44100 -map 0:v -map 1:a';
            }
            $cmd .= ' -t ' . number_format($hold, 3, '.', '')
                  . ' -c:v libx264 -preset veryfast -tune stillimage -pix_fmt yuv420p -r ' . (int) $this->cfg['fps']
                  . ' -c:a aac -b:a 128k -ac 2 ' . escapeshellarg($seg);
            $r = $this->run($cmd);
            if ($r['code'] !== 0 || !is_file($seg)) {
                throw new RuntimeException('ffmpeg failed on slide ' . $n . ': ' . substr(trim($r['output']), 0, 400));
            }
            $segments[] = $seg;

            if ($text !== '') {
                foreach ($this->caption_chunks($text) as $chunk) {
                    $share = $seconds * (mb_strlen($chunk) / max(1, mb_strlen($text)));
                    $cues[] = array($clock, $clock + $share, $chunk);
                    $clock += $share;
                }
                $clock += $hold - $seconds;
            } else {
                $clock += $hold;
            }
        }

        $this->progress(88, 'Joining segments');
        $list = $work . 'segments.txt';
        $lines = '';
        foreach ($segments as $s) {
            $lines .= "file '" . str_replace("'", "'\\''", str_replace('\\', '/', $s)) . "'\n";
        }
        file_put_contents($list, $lines);

        $video_rel = $out_dir . $name . '.mp4';
        $r = $this->run(escapeshellarg($ffmpeg) . ' -y -hide_banner -loglevel error -f concat -safe 0 -i ' . escapeshellarg($list)
            . ' -c copy -movflags +faststart ' . escapeshellarg(FCPATH . $video_rel));
        if ($r['code'] !== 0 || !is_file(FCPATH . $video_rel)) {
            throw new RuntimeException('ffmpeg could not join the slide segments: ' . substr(trim($r['output']), 0, 400));
        }

        $poster_rel = $out_dir . $name . '.jpg';
        $first = imagecreatefrompng($work . 'slide-01.png');
        imagejpeg($first, FCPATH . $poster_rel, 88);
        imagedestroy($first);

        $captions_rel = 'uploads/captions/' . $name . '.vtt';
        if (!is_dir(FCPATH . 'uploads/captions')) {
            mkdir(FCPATH . 'uploads/captions', 0755, true);
        }
        file_put_contents(FCPATH . $captions_rel, $this->vtt($cues));

        $this->progress(96, 'Cleaning up');
        $this->rmdir($work);

        return array(
            'video' => $video_rel,
            'poster' => $poster_rel,
            'captions' => $captions_rel,
            'seconds' => round($clock, 1),
            'narrated' => $narrate,
            'bytes' => filesize(FCPATH . $video_rel),
        );
    }

    private function duration($ffmpeg, $file) {
        $r = $this->run(escapeshellarg($ffmpeg) . ' -hide_banner -i ' . escapeshellarg($file));
        if (preg_match('/Duration:\s*(\d+):(\d+):(\d+(?:\.\d+)?)/', $r['output'], $m)) {
            return $m[1] * 3600 + $m[2] * 60 + (float) $m[3];
        }
        return 0.0;
    }

    // ---------------------------------------------------------------- slides

    public function draw_slide(array $slide, $n, $count, $locale, array $meta, $file) {
        $w = (int) $this->cfg['width'];
        $h = (int) $this->cfg['height'];
        $rtl = $locale === 'ar';
        $font = $this->font($rtl);
        $c = $this->cfg['colors'];

        $im = imagecreatetruecolor($w, $h);
        imagesavealpha($im, true);
        $bg = $this->color($im, $c['bg']);
        $accent = $this->color($im, $c['accent']);
        $fg = $this->color($im, $c['text']);
        $muted = $this->color($im, $c['muted']);
        imagefilledrectangle($im, 0, 0, $w, $h, $bg);

        // Accent rail on the reading-start edge, and a soft corner shape.
        $rail_x = $rtl ? $w - 18 : 0;
        imagefilledrectangle($im, $rail_x, 0, $rail_x + 18, $h, $accent);
        $glow = imagecolorallocatealpha($im, 139, 92, 246, 110);
        imagefilledellipse($im, $rtl ? 80 : $w - 80, $h - 60, 520, 520, $glow);

        $pad = 90;
        $max_w = $w - 2 * $pad;

        // Header: course and lesson.
        $kicker = trim((isset($meta['course']) ? $meta['course'] : '') . (isset($meta['lesson']) ? '  ·  ' . $meta['lesson'] : ''));
        if ($kicker !== '') {
            $this->text_block($im, $kicker, $font, 17, $muted, $pad, 70, $max_w, $rtl, 1);
        }

        $title = isset($slide['title']) ? (string) $slide['title'] : '';
        $y = $this->text_block($im, $title, $font, 40, $fg, $pad, 150, $max_w, $rtl, 2, 1.25);
        imagefilledrectangle($im, $rtl ? $w - $pad - 120 : $pad, (int) $y + 18, $rtl ? $w - $pad : $pad + 120, (int) $y + 24, $accent);
        $y += 70;

        foreach (array_slice((array) (isset($slide['bullets']) ? $slide['bullets'] : array()), 0, 5) as $bullet) {
            $bullet = trim((string) $bullet);
            if ($bullet === '') {
                continue;
            }
            $dot_x = $rtl ? $w - $pad - 8 : $pad + 8;
            imagefilledellipse($im, $dot_x, (int) $y - 9, 12, 12, $accent);
            $y = $this->text_block($im, $bullet, $font, 26, $fg, $pad + 34, $y, $max_w - 34, $rtl, 2, 1.35) + 26;
            if ($y > $h - 110) {
                break;
            }
        }

        // Footer: brand and slide counter.
        $brand = $rtl ? 'أكاديمية الضيافة' : 'Hospitality Academy';
        $counter = $n . ' / ' . $count;
        $this->text_block($im, $brand, $font, 16, $muted, $pad, $h - 48, $max_w, $rtl, 1);
        $this->text_block($im, $counter, $this->font(false), 16, $muted, $pad, $h - 48, $max_w, !$rtl, 1);

        imagepng($im, $file, 6);
        imagedestroy($im);
    }

    /**
     * Wrap and draw text; returns the baseline y after the last line.
     */
    private function text_block($im, $text, $font, $size, $color, $x, $y, $max_w, $rtl, $max_lines = 3, $leading = 1.3) {
        $has_ar = Ha_arabic::has_arabic($text);
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $lines = array();
        $line = '';
        foreach ($words as $word) {
            $try = $line === '' ? $word : $line . ' ' . $word;
            if ($this->width($has_ar ? Ha_arabic::shape($try) : $try, $font, $size) > $max_w && $line !== '') {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $try;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
        if (count($lines) > $max_lines) {
            $lines = array_slice($lines, 0, $max_lines);
            $lines[$max_lines - 1] = rtrim($lines[$max_lines - 1], '.,،;: ') . '…';
        }

        // $x is the inset from the reading-start edge: the left edge for LTR,
        // the right edge for RTL, so a block lines up with its bullet either way.
        $w_img = imagesx($im);
        foreach ($lines as $l) {
            $draw = $has_ar ? Ha_arabic::visual(Ha_arabic::shape($l)) : $l;
            $lw = $this->width($draw, $font, $size);
            $dx = $rtl ? ($w_img - $x - $lw) : $x;
            imagettftext($im, $size, 0, (int) $dx, (int) $y, $color, $font, $draw);
            $y += (int) round($size * $leading * 1.33);
        }
        return $y - (int) round($size * $leading * 1.33) + 8;
    }

    private function width($text, $font, $size) {
        $box = imagettfbbox($size, 0, $font, $text);
        return abs($box[2] - $box[0]);
    }

    private function color($im, $hex) {
        $hex = ltrim($hex, '#');
        return imagecolorallocate($im, hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2)));
    }

    public function font($arabic) {
        $explicit = $arabic ? $this->cfg['font_arabic'] : $this->cfg['font_latin'];
        $list = $explicit ? array($explicit) : array();
        $list = array_merge($list, (array) $this->cfg[$arabic ? 'font_candidates_arabic' : 'font_candidates_latin']);
        foreach ($list as $f) {
            if ($f && is_readable($f)) {
                return $f;
            }
        }
        return null;
    }

    // -------------------------------------------------------------- captions

    private function caption_chunks($text) {
        $sentences = preg_split('/(?<=[\.\!\?؟。])\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY);
        $out = array();
        foreach ($sentences as $s) {
            // Long sentences split near 90 characters on a word boundary.
            while (mb_strlen($s) > 90) {
                $cut = mb_strrpos(mb_substr($s, 0, 90), ' ');
                $cut = $cut ?: 90;
                $out[] = trim(mb_substr($s, 0, $cut));
                $s = trim(mb_substr($s, $cut));
            }
            if ($s !== '') {
                $out[] = $s;
            }
        }
        return $out;
    }

    private function vtt(array $cues) {
        $fmt = function ($t) {
            $ms = (int) round(($t - floor($t)) * 1000);
            $t = (int) floor($t);
            return sprintf('%02d:%02d:%02d.%03d', floor($t / 3600), floor(($t % 3600) / 60), $t % 60, $ms);
        };
        $out = "WEBVTT\n\n";
        foreach ($cues as $i => $c) {
            $out .= ($i + 1) . "\n" . $fmt($c[0]) . ' --> ' . $fmt($c[1]) . "\n" . $c[2] . "\n\n";
        }
        return $out;
    }

    private function rmdir($dir) {
        foreach (glob(rtrim($dir, '/') . '/*') ?: array() as $f) {
            is_dir($f) ? $this->rmdir($f) : @unlink($f);
        }
        @rmdir($dir);
    }
}
