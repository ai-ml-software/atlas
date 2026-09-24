<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/**
 * AI Studio worker.
 *
 *   php index.php ha_ai_cli work           process queued jobs until the queue is empty
 *   php index.php ha_ai_cli work 50        process at most 50 jobs, then exit
 *   php index.php ha_ai_cli daemon         keep polling (for a supervisor / Laragon service)
 *   php index.php ha_ai_cli status         queue, heartbeat, renderer readiness
 *   php index.php ha_ai_cli sync_models    refresh the model list of every enabled provider
 *
 * Cron (every minute is fine: a second worker exits when the lock is held):
 *   * * * * *  cd /home/<user>/public_html && php index.php ha_ai_cli work > /dev/null 2>&1
 *
 * Generation runs here and not in the web request because a full course or a
 * rendered video takes minutes, longer than any sane PHP-FPM or proxy timeout.
 */
class Ha_ai_cli extends CI_Controller {

    const HEARTBEAT = 'cache/ha_ai_worker.json';
    const LOCK = 'cache/ha_ai_worker.lock';

    public function __construct() {
        parent::__construct();
        if (!is_cli()) {
            show_404();
        }
        @set_time_limit(0);
        ini_set('memory_limit', '512M');
        $this->load->database();
        $this->load->library('ha_ai_studio');
    }

    private function out($line = '') {
        fwrite(STDOUT, '[' . date('H:i:s') . '] ' . $line . PHP_EOL);
    }

    public function index() {
        $this->status();
    }

    public function work($max = 0) {
        $lock = fopen(APPPATH . self::LOCK, 'c');
        if (!$lock || !flock($lock, LOCK_EX | LOCK_NB)) {
            $this->out('Another worker is running. Exiting.');
            return;
        }
        $done = 0;
        $max = (int) $max;
        $idle_rounds = 0;
        while (true) {
            $this->heartbeat('working', $done);
            $id = $this->ha_ai_studio->work_one();
            if ($id === null) {
                // Vendor renders sit in the queue with a short back-off; wait
                // for them rather than exit while a video is still baking.
                $waiting = $this->db->where('status', 'queued')->count_all_results('ha_ai_job');
                if ($waiting && $idle_rounds++ < 90) {
                    sleep(10);
                    continue;
                }
                break;
            }
            $idle_rounds = 0;
            $job = $this->ha_ai_studio->job($id);
            $this->out('#' . $id . ' ' . $job['type'] . ' -> ' . $job['status']
                . ($job['error'] ? ' (' . mb_substr($job['error'], 0, 160) . ')' : ''));
            $done++;
            if ($max && $done >= $max) {
                break;
            }
        }
        $this->heartbeat('idle', $done);
        flock($lock, LOCK_UN);
        fclose($lock);
        $this->out('Processed ' . $done . ' job(s).');
    }

    public function daemon() {
        $this->out('AI worker daemon started. Ctrl+C to stop.');
        while (true) {
            $this->work();
            sleep(15);
        }
    }

    public function status() {
        $counts = $this->ha_ai_studio->counts();
        foreach ($counts as $k => $v) {
            $this->out(str_pad($k, 12) . $v);
        }
        $beat = self::read_heartbeat();
        $this->out('heartbeat   ' . ($beat ? $beat['state'] . ' at ' . $beat['at'] : 'never'));
        $this->load->library('ha_video_renderer');
        $s = $this->ha_video_renderer->status();
        $this->out('ffmpeg      ' . ($s['ffmpeg'] ?: 'NOT FOUND (winget install Gyan.FFmpeg / apt install ffmpeg)'));
        $this->out('fonts       latin=' . ($s['font_latin'] ?: 'none') . ' arabic=' . ($s['font_arabic'] ?: 'none'));
    }

    public function sync_models() {
        $this->load->library('ha_ai_gateway');
        foreach ($this->ha_ai_gateway->providers() as $slug => $p) {
            if (!$p['enabled'] || !in_array('chat', $p['capabilities'], true)) {
                continue;
            }
            try {
                $n = count($this->ha_ai_gateway->sync_models($slug));
                $this->out(str_pad($slug, 16) . $n . ' models');
            } catch (Exception $e) {
                $this->out(str_pad($slug, 16) . 'ERROR ' . $e->getMessage());
            }
        }
    }

    private function heartbeat($state, $done) {
        @file_put_contents(APPPATH . self::HEARTBEAT, json_encode(array(
            'state' => $state, 'at' => date('Y-m-d H:i:s'), 'pid' => getmypid(), 'processed' => $done,
        )));
    }

    public static function read_heartbeat() {
        $f = APPPATH . self::HEARTBEAT;
        return is_file($f) ? json_decode((string) file_get_contents($f), true) : null;
    }
}
