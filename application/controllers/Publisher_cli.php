<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Document extraction worker.
 *   php index.php publisher_cli work [limit]            process up to [limit] jobs, then exit (Task Scheduler / cron)
 *   php index.php publisher_cli daemon [sleep] [minutes] keep polling; optional maximum run time in minutes
 *   php index.php publisher_cli cleanup [days]          delete retained sources older than the retention window
 *   php index.php publisher_cli health                  print setup checks
 */
class Publisher_cli extends CI_Controller {
    public function __construct() {
        parent::__construct();
        if (!is_cli()) { show_404(); }
        $this->load->database();
        if (getenv('ALTUS_PUBLISHER_TEST_DB')==='atlas_hospitality_test') {
            if (!in_array($this->db->hostname,array('127.0.0.1','localhost'),true)) throw new RuntimeException('Test workers require a loopback database.');
            $this->db->db_select('atlas_hospitality_test'); $this->db->database='atlas_hospitality_test';
        }
        $this->load->library('ha_document_jobs'); @set_time_limit(0);
    }
    private function batch($limit) { $n=0; for ($i=0;$i<max(1,min(100,(int)$limit));$i++) { if (!$this->ha_document_jobs->work()) break; $n++; } return $n; }
    public function work($limit=10) {
        $n=$this->batch($limit); $this->ha_document_jobs->heartbeat('batch',$n); $removed=$this->ha_document_jobs->cleanup();
        echo 'Extraction queue processed: '.$n.' job(s)'.($removed?', '.$removed.' expired source(s) removed':'').'.'.PHP_EOL;
    }
    public function daemon($sleep=0,$minutes=0) {
        $sleep=max(1,min(300,(int)$sleep ?: (int)$this->config->item('worker_sleep_seconds','ha_publisher') ?: 5)); $until=$minutes>0?time()+60*(int)$minutes:0; $last_cleanup=0; $total=0;
        echo 'Publisher worker started (poll '.$sleep.'s). Press Ctrl+C to stop.'.PHP_EOL;
        while (!$until || time()<$until) {
            try {
                $n=$this->batch(25); $total+=$n; $this->ha_document_jobs->heartbeat('daemon',$total);
                if (time()-$last_cleanup>3600) { $this->ha_document_jobs->cleanup(); $last_cleanup=time(); }
                if ($n) { echo gmdate('c').' processed '.$n.PHP_EOL; }
            } catch (Throwable $e) {
                echo gmdate('c').' worker error: '.$e->getMessage().PHP_EOL;
                $this->db->reconnect();
            }
            $this->db->data_cache=array();
            sleep($sleep);
        }
        echo 'Publisher worker stopped after '.$total.' job(s).'.PHP_EOL;
    }
    public function cleanup($days=0) { echo 'Removed '.$this->ha_document_jobs->cleanup($days?:null).' retained source file(s).'.PHP_EOL; }
    public function health() {
        $h=$this->ha_document_jobs->health(true); $bad=0;
        foreach ($h['checks'] as $c) { if (!$c['ok']) $bad++; echo ($c['ok']?'[ok]   ':'[FAIL] ').$c['label'].': '.$c['detail'].PHP_EOL.($c['ok']?'':'       -> '.$c['fix'].PHP_EOL); }
        exit($bad?1:0);
    }
}
