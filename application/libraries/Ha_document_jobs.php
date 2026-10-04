<?php
defined('BASEPATH') OR exit('No direct script access allowed');
/**
 * Retained private sources with a bounded extraction worker and progress.
 *
 * Failure policy: transient failures (time limits, expired leases, unexpected errors) are retried
 * automatically up to max_attempts with exponential backoff; input/dependency errors fail at once.
 * People may retry a failed job until attempt_limit. Retained source files are deleted after
 * source_retention_days once the job is finished (see cleanup()).
 */
class Ha_document_jobs {
    const PERMANENT = 4220;
    private $CI;
    public function __construct() { $this->CI =& get_instance(); $this->CI->load->library('ha_document_publisher'); }
    private function cfg($key) { return $this->CI->ha_document_publisher->cfg($key); }
    private function now($offset = 0) { return gmdate('Y-m-d H:i:s', time() + $offset); }
    private function folder() { return APPPATH . 'storage/private/publisher/'; }
    public function enqueue($path,$name,$target,$locale) {
        if (!is_file($path) || filesize($path)>15*1048576 || filesize($path)<1) { throw new InvalidArgumentException('Upload a non-empty document up to 15 MB.'); }
        $ext=strtolower(pathinfo($name,PATHINFO_EXTENSION));
        if (!in_array($ext,array('pdf','docx','pptx','txt','md','json'),true)) { throw new InvalidArgumentException('Unsupported source document.'); }
        if ($ext==='pdf' && (new finfo(FILEINFO_MIME_TYPE))->file($path)!=='application/pdf') { throw new InvalidArgumentException('The file content is not PDF.'); }
        $stored=null; $this->CI->db->trans_begin(); try {
        $id=$this->CI->ha_document_publisher->source(str_repeat('Source extraction pending. ',2),$name,$target,$locale);
        $folder=$this->folder(); if (!is_dir($folder) && !mkdir($folder,0700,true)) { throw new RuntimeException('Private source storage is unavailable.'); }
        $stored=$folder.bin2hex(random_bytes(16)).'.'.$ext;
        if (!copy($path,$stored)) { throw new RuntimeException('Source could not be retained.'); } chmod($stored,0600);
        $now=$this->now(); $this->CI->db->where('id',$id)->update('ha_publisher_draft',array('source_text'=>'','source_hash'=>hash_file('sha256',$stored),'status'=>'extracting','updated_at'=>$now));
        $this->CI->db->insert('ha_document_job',array('draft_id'=>$id,'source_path'=>$stored,'created_at'=>$now,'updated_at'=>$now)); if (!$this->CI->db->trans_status()) throw new RuntimeException('Upload queue could not be saved.'); $this->CI->db->trans_commit(); return $id;
        } catch (Throwable $e) { $this->CI->db->trans_rollback(); if ($stored && is_file($stored)) unlink($stored); throw $e; }
    }
    public function status($draft) {
        $this->CI->ha_document_publisher->find($draft);
        $j=$this->CI->db->select('id,draft_id,status,progress,cancel_requested,error,attempts,pages_json,next_attempt_at,updated_at')->order_by('id','DESC')->get_where('ha_document_job',array('draft_id'=>(int)$draft))->row_array();
        if ($j) { $j['max_attempts']=(int)$this->cfg('max_attempts'); $j['attempt_limit']=(int)$this->cfg('attempt_limit'); $j['pages']=json_decode((string)$j['pages_json'],true) ?: array(); unset($j['pages_json']); }
        return $j;
    }
    public function control($draft,$action) {
        $d=$this->CI->ha_document_publisher->find($draft); if ($d['entity_id']) { throw new InvalidArgumentException('Source was already imported.'); }
        $j=$this->CI->db->order_by('id','DESC')->get_where('ha_document_job',array('draft_id'=>(int)$draft))->row_array(); if (!$j) { throw new InvalidArgumentException('No extraction job.'); }
        if ($action==='cancel' && in_array($j['status'],array('queued','processing'),true)) { $this->CI->db->where('id',$j['id'])->update('ha_document_job',array('cancel_requested'=>1)); return; }
        if ($action==='retry' && in_array($j['status'],array('failed','cancelled'),true)) {
            if ((int)$j['attempts']>=(int)$this->cfg('attempt_limit')) { throw new InvalidArgumentException('Retry limit reached after '.(int)$j['attempts'].' attempts. Fix the setup issue shown in the health panel, then upload the document again.'); }
            if ($j['source_path']==='' || !is_file($j['source_path'])) { throw new InvalidArgumentException('The retained source was removed by the retention policy. Upload the document again.'); }
            $this->CI->db->where('id',$j['id'])->update('ha_document_job',array('status'=>'queued','progress'=>0,'cancel_requested'=>0,'error'=>null,'next_attempt_at'=>null,'updated_at'=>$this->now()));
            $this->CI->db->where('id',$draft)->update('ha_publisher_draft',array('status'=>'extracting','updated_at'=>$this->now())); return;
        }
        throw new InvalidArgumentException('This job cannot be '.($action==='cancel'?'cancelled':'retried').' in its current state.');
    }
    /** Seconds to wait before automatic attempt N+1. */
    public function backoff($attempts) { return (int)$this->cfg('backoff_seconds') * (2 ** max(0, (int)$attempts - 1)); }
    private function fail($job, $token, Throwable $e) {
        $db=$this->CI->db; $attempts=(int)$job['attempts']+1; $msg=mb_substr($e->getMessage(),0,3500);
        if ($e instanceof DomainException) { $row=array('status'=>'cancelled','error'=>$msg); }
        elseif ($e->getCode()!==self::PERMANENT && !($e instanceof InvalidArgumentException) && $attempts<(int)$this->cfg('max_attempts')) {
            $wait=$this->backoff($attempts); $row=array('status'=>'queued','progress'=>0,'next_attempt_at'=>$this->now($wait),'error'=>$msg.' Automatic retry '.($attempts+1).' of '.(int)$this->cfg('max_attempts').' in '.$wait.' seconds.');
        } else { $row=array('status'=>'failed','error'=>$msg.($e->getCode()!==self::PERMANENT && !($e instanceof InvalidArgumentException)?' Failed after '.$attempts.' attempts.':'')); }
        $db->where(array('id'=>$job['id'],'worker_token'=>$token))->update('ha_document_job',$row+array('updated_at'=>$this->now()));
    }
    public function work() {
        if (!is_cli()) { throw new RuntimeException('Workers run on the command line.'); }
        $db=$this->CI->db;
        // Expired leases: a crashed worker. Requeue within the retry budget.
        foreach ($db->where('status','processing')->where('updated_at <',$this->now(-900))->get('ha_document_job')->result_array() as $stale) {
            $retry=(int)$stale['attempts']<(int)$this->cfg('max_attempts');
            $db->where(array('id'=>$stale['id'],'status'=>'processing'))->update('ha_document_job',$retry?array('status'=>'queued','next_attempt_at'=>$this->now($this->backoff($stale['attempts'])),'error'=>'Worker lease expired. Retrying automatically.','updated_at'=>$this->now()):array('status'=>'failed','error'=>'Worker lease expired after '.(int)$stale['attempts'].' attempts. Retry extraction.','updated_at'=>$this->now()));
        }
        $db->trans_begin(); $j=$db->query("SELECT * FROM ha_document_job WHERE status='queued' AND (next_attempt_at IS NULL OR next_attempt_at <= ? OR cancel_requested=1) ORDER BY id LIMIT 1 FOR UPDATE",array($this->now()))->row_array();
        if (!$j) { $db->trans_commit(); return false; }
        $token=bin2hex(random_bytes(16)); $db->where('id',$j['id'])->update('ha_document_job',array('status'=>'processing','worker_token'=>$token,'attempts'=>$j['attempts']+1,'updated_at'=>$this->now())); $db->trans_commit();
        $proc=null; $pipes=array(); $open=false;
        try {
            if ($j['cancel_requested']) { throw new DomainException('Extraction cancelled.'); }
            $d=$db->get_where('ha_publisher_draft',array('id'=>$j['draft_id']))->row_array();
            if (!$d) { throw new RuntimeException('The draft for this source was deleted.', self::PERMANENT); }
            if ($j['source_path']==='' || !is_file($j['source_path'])) { throw new RuntimeException('The retained source file is missing. Upload the document again.', self::PERMANENT); }
            if (strtolower(pathinfo($j['source_path'],PATHINFO_EXTENSION))!=='pdf') { $res=array('ok'=>true,'text'=>$this->CI->ha_document_publisher->extract($j['source_path'],$d['source_name']),'provenance'=>array()); }
            else {
                $P=$this->CI->ha_document_publisher;
                $proc=@proc_open($P->python_command(array($j['source_path'],'--progress')),array(0=>array('pipe','r'),1=>array('pipe','w'),2=>array('pipe','w')),$pipes,null,$P->python_env());
                if (!is_resource($proc)) { throw new RuntimeException('Python could not start. Set python in config/ha_publisher.php (ALTUS_PUBLISHER_PYTHON) and install tools/publisher-requirements.txt.', self::PERMANENT); }
                fclose($pipes[0]); stream_set_blocking($pipes[1],false); stream_set_blocking($pipes[2],false); $buf=''; $err=''; $start=microtime(true); $res=null;
                do {
                    if ($db->get_where('ha_document_job',array('id'=>$j['id']))->row('cancel_requested')) { throw new DomainException('Extraction cancelled.'); }
                    if (microtime(true)-$start>840) { throw new RuntimeException('Extraction time limit reached. Split the source.'); }
                    $buf.=stream_get_contents($pipes[1]); $err.=stream_get_contents($pipes[2]); if (strlen($buf)>2000000) { throw new RuntimeException('Extraction output exceeded its limit.', self::PERMANENT); }
                    while (($pos=strpos($buf,"\n"))!==false) { $line=substr($buf,0,$pos); $buf=substr($buf,$pos+1); $r=json_decode($line,true); if (!$r) continue; if (isset($r['progress'])) { $db->where(array('id'=>$j['id'],'worker_token'=>$token))->update('ha_document_job',array('progress'=>(int)$r['progress'],'updated_at'=>$this->now())); } else { $res=$r; } }
                    $running=proc_get_status($proc)['running']; if ($running) usleep(100000);
                } while ($running);
                $buf.=stream_get_contents($pipes[1]); $err.=stream_get_contents($pipes[2]); if (trim($buf)!=='') { $res=json_decode(trim($buf),true) ?: $res; }
                if (!$res) { throw new RuntimeException('Python did not return a result. Check the configured interpreter and dependencies. '.mb_substr(trim($err),0,300), self::PERMANENT); }
                if (empty($res['ok'])) { throw new RuntimeException($res['error']??'Extraction failed. Check Python dependencies.', self::PERMANENT); }
            }
            if ($db->get_where('ha_document_job',array('id'=>$j['id']))->row('cancel_requested')) { throw new DomainException('Extraction cancelled.'); }
            $db->trans_begin(); $open=true; $db->where('id',$j['draft_id'])->update('ha_publisher_draft',array('source_text'=>$res['text'],'source_hash'=>hash('sha256',$res['text']),'status'=>'source','updated_at'=>$this->now()));
            $db->where(array('id'=>$j['id'],'worker_token'=>$token))->update('ha_document_job',array('status'=>'completed','progress'=>100,'error'=>null,'next_attempt_at'=>null,'pages_json'=>json_encode($res['provenance']??array()),'updated_at'=>$this->now())); $db->trans_commit(); $open=false;
        } catch (Throwable $e) { if ($open) { $db->trans_rollback(); } $this->fail($j,$token,$e); }
        finally { if (is_resource($proc)) { if (proc_get_status($proc)['running']) proc_terminate($proc); foreach ($pipes as $p) if (is_resource($p)) fclose($p); proc_close($proc); } }
        return true;
    }

    /** Deletes retained source files of finished jobs older than the retention window. */
    public function cleanup($days = null) {
        $days = max(1, (int) ($days === null ? $this->cfg('source_retention_days') : $days)); $n = 0;
        $rows = $this->CI->db->where_in('status', array('completed', 'failed', 'cancelled'))->where('source_path !=', '')->where('updated_at <', $this->now(-$days * 86400))->get('ha_document_job')->result_array();
        foreach ($rows as $j) {
            if (is_file($j['source_path']) && strpos(str_replace('\\', '/', realpath($j['source_path'])), str_replace('\\', '/', realpath($this->folder()) ?: $this->folder())) === 0) { @unlink($j['source_path']); }
            $this->CI->db->where('id', $j['id'])->update('ha_document_job', array('source_path' => ''));
            $n++;
        }
        return $n;
    }

    // -------------------------------------------------------- worker status

    private function heartbeat_file() { return $this->folder() . 'worker-heartbeat-' . preg_replace('/[^a-z0-9_]/i', '', (string) $this->CI->db->database) . '.json'; }
    public function heartbeat($mode, $processed) {
        if (!is_dir($this->folder())) { @mkdir($this->folder(), 0700, true); }
        @file_put_contents($this->heartbeat_file(), json_encode(array('ts' => time(), 'at' => $this->now(), 'mode' => $mode, 'pid' => getmypid(), 'host' => php_uname('n'), 'processed' => (int) $processed)), LOCK_EX);
    }
    public function worker_status() {
        $f = $this->heartbeat_file(); $hb = is_file($f) ? json_decode((string) @file_get_contents($f), true) : null;
        $age = $hb ? time() - (int) $hb['ts'] : null;
        $queued = (int) $this->CI->db->where_in('status', array('queued', 'processing'))->count_all_results('ha_document_job');
        return array('last_heartbeat' => $hb ? $hb['at'] . ' UTC' : null, 'age_seconds' => $age, 'alive' => $hb && $age <= (int) $this->cfg('heartbeat_stale_seconds'),
            'mode' => $hb['mode'] ?? null, 'queued' => $queued,
            'command' => 'php index.php publisher_cli daemon', 'batch_command' => 'php index.php publisher_cli work 25');
    }

    // ---------------------------------------------------------------- health

    /** Setup checks with actionable guidance. Cached briefly because it starts Python. */
    public function health($refresh = false) {
        $cache = $this->folder() . 'health-' . preg_replace('/[^a-z0-9_]/i', '', (string) $this->CI->db->database) . '.json';
        $report = null;
        if (!$refresh && is_file($cache) && filemtime($cache) > time() - 120) { $report = json_decode((string) @file_get_contents($cache), true); }
        if (!$report) {
            $report = array('ok' => false, 'error' => 'PHP proc_open is disabled.');
            if (function_exists('proc_open')) {
                $P = $this->CI->ha_document_publisher;
                $proc = @proc_open($P->python_command(array('--health')), array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w')), $pipes, null, $P->python_env());
                if (is_resource($proc)) {
                    fclose($pipes[0]); stream_set_blocking($pipes[1], false); $out = ''; $start = microtime(true);
                    while (proc_get_status($proc)['running'] && microtime(true) - $start < 20) { $out .= stream_get_contents($pipes[1]); usleep(50000); }
                    $out .= stream_get_contents($pipes[1]); if (proc_get_status($proc)['running']) { proc_terminate($proc); }
                    fclose($pipes[1]); fclose($pipes[2]); proc_close($proc);
                    $lines = array_values(array_filter(array_map('trim', explode("\n", $out)))); $report = ($lines ? json_decode(end($lines), true) : null) ?: array('ok' => false, 'error' => 'Python did not run (' . $P->cfg('python') . ').');
                } else { $report = array('ok' => false, 'error' => 'Python could not start (' . $P->cfg('python') . ').'); }
            }
            if (!is_dir($this->folder())) { @mkdir($this->folder(), 0700, true); }
            @file_put_contents($cache, json_encode($report));
        }
        $py = (string) $this->cfg('python'); $tessdata = (string) $this->cfg('tessdata'); $langs = (array) ($report['languages'] ?? array()); $report += array('tesseract' => null, 'tesseract_version' => null, 'tessdata' => null, 'python' => null, 'executable' => null);
        $checks = array();
        $add = function ($key, $label, $ok, $detail, $fix) use (&$checks) { $checks[] = array('key' => $key, 'label' => $label, 'ok' => (bool) $ok, 'detail' => (string) $detail, 'fix' => $ok ? '' : $fix); };
        $add('python', 'Python', !empty($report['ok']), !empty($report['ok']) ? 'Python ' . $report['python'] . ' (' . $report['executable'] . ')' : ($report['error'] ?? 'Unavailable'),
            'Install Python 3.10+ and set python in application/config/ha_publisher.php or the ALTUS_PUBLISHER_PYTHON environment variable to its absolute path (currently "' . $py . '").');
        $add('pypdf', 'pypdf', !empty($report['pypdf']), $report['pypdf'] ?? 'Not installed', 'Run: "' . $py . '" -m pip install -r tools/publisher-requirements.txt');
        $add('pymupdf', 'PyMuPDF (page rendering for OCR)', !empty($report['pymupdf']), $report['pymupdf'] ?? 'Not installed', 'Run: "' . $py . '" -m pip install -r tools/publisher-requirements.txt');
        $add('tesseract', 'Tesseract OCR binary', !empty($report['tesseract_version']), $report['tesseract_version'] ? $report['tesseract_version'] . ' (' . $report['tesseract'] . ')' : ($report['tesseract'] ? 'Not runnable: ' . $report['tesseract'] : 'Not found'),
            'Install Tesseract 5 (Windows: UB Mannheim build) and set tesseract in config/ha_publisher.php or ALTUS_TESSERACT.');
        foreach (array('eng' => 'English', 'ara' => 'Arabic') as $code => $name) {
            $add('tessdata_' . $code, $name . ' OCR data (' . $code . ')', in_array($code, $langs, true), in_array($code, $langs, true) ? 'Available' . ($report['tessdata'] ? ' in ' . $report['tessdata'] : '') : 'Missing',
                'Download ' . $code . '.traineddata from github.com/tesseract-ocr/tessdata_fast into ' . $tessdata . ' (or set tessdata / ALTUS_TESSDATA).');
        }
        $this->CI->load->library('ha_ai_assist');
        $models = array(); try { $models = $this->CI->ha_ai_assist->catalogue(); } catch (Throwable $e) {}
        $add('ai', 'AI provider', (bool) $models, $models ? count($models) . ' enabled chat provider(s)' : 'None enabled', 'Open AI Studio → Providers, enable an approved chat provider, add its key and sync models.');
        $w = $this->worker_status();
        $add('worker', 'Extraction worker', $w['alive'], $w['last_heartbeat'] ? 'Last heartbeat ' . $w['last_heartbeat'] . ' (' . $w['age_seconds'] . 's ago)' : 'No heartbeat recorded',
            'Run "' . $w['command'] . '" as a service, or schedule "' . $w['batch_command'] . '" every minute (tools/publisher-worker-task.ps1 on Windows, cron on Linux).');
        return array('checks' => $checks, 'worker' => $w);
    }
}
