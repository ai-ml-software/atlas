<?php
defined('BASEPATH') OR exit('No direct script access allowed');

/** Additive native mobile contract. No web session or posted role grants access.
 * Use the same personal keys and current tenant permissions as /api/v1.
 * Writes reuse existing domain services and require the corresponding live permission.
 * Configure HA_MOBILE_WEB_ORIGINS as comma-separated HTTPS origins for a hosted web preview.
 */
class Mobile_api extends CI_Controller {
    private $auth_result;
    private $locale = 'en';
    private $uid;

    public function __construct() {
        parent::__construct();
        $this->load->database();
        $this->load->helper(array('ha_security', 'hkp', 'url'));
        $this->load->library(array('ha_api_keys', 'ha_auth'));
        $this->output->set_content_type('application/json', 'utf-8');
        header('Cache-Control: no-store');
        header('X-Content-Type-Options: nosniff');
        $origin = isset($_SERVER['HTTP_ORIGIN']) ? $_SERVER['HTTP_ORIGIN'] : '';
        $this->config->load('ha_mobile', true);
        $origins = (array) $this->config->item('web_origins', 'ha_mobile');
        if ($origin && in_array($origin, $origins, true)) {
            header('Access-Control-Allow-Origin: ' . $origin);
            header('Vary: Origin');
            header('Access-Control-Allow-Headers: Authorization, Content-Type, Accept');
            header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
        }
    }

    public function _remap($route, $params = array()) {
        if (strtoupper($this->input->method()) === 'OPTIONS') {
            return $this->output->set_status_header(204)->set_output('');
        }
        try {
            // Email + password sign-in (no key yet). Issues a personal mobile session key.
            if (in_array($route, array('login', 'login_2fa'), true)) {
                if (strtoupper($this->input->method()) !== 'POST') { return $this->respond(null, 405, 'Use POST.'); }
                if (ENVIRONMENT === 'production' && !is_https()) { return $this->respond(null, 403, 'Sign-in requires HTTPS.'); }
                $bucket='mobile_login_request:'.ha_client_ip();
                $recent=$this->db->where('bucket',$bucket)->where('created_at >=',date('Y-m-d H:i:s',time()-60))->count_all_results('ha_auth_attempt');
                if($recent>=30){header('Retry-After: 60');return $this->respond(null,429,'Too many sign-in requests. Please wait a minute.');}
                $this->ha_api_keys->attempt($bucket,true,ha_client_ip());
                $body = json_decode((string) file_get_contents('php://input'), true);
                if (!is_array($body)) { $body = (array) $this->input->post(); }
                $r = $this->sign_in($route, $body, ha_client_ip());
                return $this->respond($r['data'], $r['status'], $r['message']);
            }
            $this->auth_result = $this->ha_api_keys->authenticate(Ha_api_keys::from_request(), ha_client_ip());
            if (!$this->auth_result['ok']) { return $this->respond(null, $this->auth_result['status'], $this->auth_result['error']); }
            $bucket = 'mobile_key:' . $this->auth_result['key']['id'];
            $recent = $this->db->where('bucket', $bucket)->where('created_at >=', date('Y-m-d H:i:s', time() - 60))->count_all_results('ha_auth_attempt');
            if ($recent >= 120) { header('Retry-After: 60'); return $this->respond(null, 429, 'Too many requests. Please wait a minute.'); }
            $this->ha_api_keys->attempt($bucket, true, ha_client_ip());
            $this->ha_auth->from_api_key($this->auth_result);
            if (!$this->ha_auth->check()) { return $this->respond(null, 401, 'The account is not active.'); }
            $this->uid = (int) $this->ha_auth->id();
            $loc = (string) $this->input->get('locale');
            $this->locale = in_array($loc, array('en', 'ar'), true) ? $loc : 'en';
            hkp_locale($this->locale);
            $method = strtoupper($this->input->method());
            if ($method === 'POST' && $route === 'logout') {
                $this->load->library('ha_mobile_session');
                $this->ha_mobile_session->logout($this->auth_result['key']);
                return $this->respond(null, 200, 'Signed out.');
            }
            if ($method === 'POST') { $this->need('mobile:write'); }
            $id = (int) $this->input->get('id');
            $body = json_decode((string) file_get_contents('php://input'), true) ?: array();
            if (!is_array($body)) { throw new InvalidArgumentException('Use a JSON object.'); }
            switch ($method . ' ' . $route) {
                case 'GET me':
                    $this->need('profile:read');
                    $u = $this->ha_auth->user(); $p = $this->ha_auth->profile();
                    $profile = $p ? array_intersect_key($p, array_flip(array('organization_id','property_id','department_id','employee_no','locale','job_role_id'))) : null;
                    if ($profile && $profile['property_id']) { $profile['property_name'] = $this->db->select('name_en')->get_where('ha_property', array('id'=>(int)$profile['property_id']))->row('name_en'); }
                    return $this->respond(array('id'=>$this->uid,'name'=>$this->ha_auth->display_name(),'email'=>$u['email'],'roles'=>$this->ha_auth->role_codes(),'permissions'=>$this->ha_auth->permissions(),'profile'=>$profile));
                case 'GET courses':
                case 'GET plan':
                    $this->need($route === 'plan' ? 'enrollments:read' : 'courses:read');
                    $this->load->library('ha_learning');
                    if ($route === 'plan') {
                        $raw = $this->ha_learning->plan($this->uid);
                        $raw = array_values(array_filter($raw,function($r){return $this->ha_learning->course_visible($r['course_id'],$this->uid);}));
                        return $this->respond(array_map(function($r){ return $this->course_shape($r['course_id'], (float)$r['progress_percentage'], !empty($r['is_mandatory'])); }, $raw));
                    }
                    $page = max(1,(int)$this->input->get('page')); $per = max(1,min(50,(int)$this->input->get('per_page') ?: 25));
                    $out = array();
                    // Visibility is checked before pagination, so unauthorized titles never leak.
                    $profile=$this->ha_auth->profile();
                    foreach ($this->db->select('id,organization_id,property_id')->where('status','published')->order_by('id')->get('ha_course')->result_array() as $r) {
                        $global=!$r['organization_id']&&!$r['property_id'];
                        $own=$profile&&($r['property_id']?(int)$profile['property_id']===(int)$r['property_id']:(int)$profile['organization_id']===(int)$r['organization_id']);
                        if ($global || $this->ha_auth->is_system_scoped() || $own) { $out[] = (int)$r['id']; }
                    }
                    return $this->respond(array_map(function($cid){return $this->course_shape($cid);},array_slice($out,($page-1)*$per,$per)));
                case 'GET course':
                    $this->need('courses:read'); $this->load->library('ha_learning');
                    if (!$id || !$this->ha_learning->course_visible($id,$this->uid)) { return $this->respond(null,404,'Course unavailable.'); }
                    $c = $this->ha_learning->course($id,$this->locale);
                    $public = $this->course_shape($id);
                    $public['objectives']=array_values(array_unique(array_filter(array_map(function($lesson){return trim(strip_tags((string)($lesson['objective'] ?? '')));},$c['lessons']))));
                    $public['owner']='';if($c['instructor_user_id']){$owner=$this->db->select('first_name,last_name')->get_where('users',array('id'=>(int)$c['instructor_user_id']))->row_array();if($owner){$public['owner']=trim($owner['first_name'].' '.$owner['last_name']);}}
                    $public['items'] = array_map(function($l){return array('id'=>(int)$l['id'],'title'=>$l['title'] ?: $l['title_en'],'type'=>$l['lesson_type'],'duration'=>(int)$l['duration_seconds']);},$c['lessons']);
                    $public['assessments'] = array_map(function($a){return array('id'=>(int)$a['id'],'title'=>$a['title_'.$this->locale] ?: $a['title_en']);},$c['assessments']);
                    return $this->respond($public);
                case 'GET lesson':
                    $this->need('courses:read'); $this->load->library('ha_learning');
                    $l = $this->ha_learning->lesson($id,$this->locale);
                    if (!$l || !$this->ha_learning->course_visible($l['course_id'],$this->uid)) { return $this->respond(null,404,'Lesson unavailable.'); }
                    // open_lesson enforces enrollment, sequencing, prerequisites and release dates.
                    $access = $this->ha_learning->open_lesson($this->uid,$id);
                    $media=array();
                    if(!empty($l['media_path'])&&$this->media_url($l['media_path'])){$media[]=array('type'=>$l['media_type'],'url'=>$this->media_url($l['media_path']),'title'=>$l['title']);}
                    foreach($l['blocks'] as $block){if(in_array($block['block_type'],array('image','video','audio','pdf'),true)&&$this->media_url($block['media_path'])){$media[]=array('type'=>$block['block_type'],'url'=>$this->media_url($block['media_path']),'title'=>$block['title']);}}
                    foreach($l['attachments'] as $attachment){if(!$attachment['is_private']&&$this->media_url($attachment['file_path'])){$media[]=array('type'=>'pdf','url'=>$this->media_url($attachment['file_path']),'title'=>$attachment['title_'.$this->locale] ?: $attachment['title_en']);}}
                    return $this->respond(array('id'=>$id,'title'=>$l['title'] ?: $l['title_en'],'body'=>strip_tags($l['body'] ?: $l['body_en']),'transcript'=>strip_tags($l['transcript']),'type'=>$l['lesson_type'],'assessment_id'=>(int)$l['assessment_id'],'media'=>$media,'progress'=>$access['progress']));
                case 'POST lesson_track':
                    $this->need('enrollments:read');$this->load->library('ha_learning');$lid=(int)($body['id'] ?? 0);$l=$this->ha_learning->lesson($lid,$this->locale);
                    if(!$l||!$this->ha_learning->course_visible($l['course_id'],$this->uid)){return $this->respond(null,404,'Lesson unavailable.');}
                    return $this->respond($this->ha_learning->track($this->uid,$lid,max(0,min(15,(int)($body['seconds'] ?? 0))),max(0,(int)($body['position'] ?? 0))));
                case 'POST lesson_complete':
                    $this->need('enrollments:read'); $this->load->library('ha_learning');
                    $lid=(int)($body['id'] ?? 0); $l=$this->ha_learning->lesson($lid,$this->locale);
                    if (!$l || !$this->ha_learning->course_visible($l['course_id'],$this->uid)) { return $this->respond(null,404,'Lesson unavailable.'); }
                    $this->ha_learning->open_lesson($this->uid,$lid);
                    return $this->respond($this->ha_learning->complete_lesson($this->uid,$lid));
                case 'GET knowledge':
                    $this->need('knowledge:read','knowledge.view'); $this->load->library('ha_knowledge');
                    return $this->respond($this->ha_knowledge->visible_items($this->uid,array('limit'=>100,'q'=>mb_substr((string)$this->input->get('q'),0,200))));
                case 'GET sop':
                    $this->need('knowledge:read','knowledge.view'); $this->load->library('ha_knowledge');
                    if (!$this->ha_knowledge->can_view($id,$this->uid)) { return $this->respond(null,404,'Document unavailable.'); }
                    $doc=$this->ha_knowledge->get($id);
                    // Never return draft/archived versions or reviewer personal data to employees.
                    $text=$doc['text'][$this->locale] ?? ($doc['text']['en'] ?? array());
                    $fields=array('purpose','scope','responsibilities','required_tools','procedure','checklist','safety_notes','quality_standard','escalation','related_documents');
                    $sections=array();foreach($fields as $field){if(!empty($text[$field])){$sections[$field]=strip_tags($text[$field]);}}
                    return $this->respond(array('id'=>$id,'title'=>$text['title'] ?? $doc['code'],'code'=>$doc['code'],'version'=>$doc['version']['version_label'],'effective_date'=>$doc['version']['effective_date'],'owner'=>trim($doc['owner_first'].' '.$doc['owner_last']),'sections'=>$sections,'acknowledged'=>$this->ha_knowledge->acknowledgement($id,$this->uid)));
                case 'POST acknowledge':
                    $this->need('knowledge:read','knowledge.view'); $this->load->library('ha_knowledge');
                    $id=(int)($body['id'] ?? 0); if (!$this->ha_knowledge->can_view($id,$this->uid)) { return $this->respond(null,404,'Document unavailable.'); }
                    return $this->respond($this->ha_knowledge->acknowledge($id,$this->uid));
                case 'POST assessment_start':
                    $this->need('enrollments:read','assessments.view');$this->load->library('ha_theory');$aid=(int)($body['id'] ?? 0);
                    $assessment=$this->ha_theory->assessment($aid);$this->load->library('ha_learning');
                    if(!$assessment||!$this->ha_learning->course_visible($assessment['course_id'],$this->uid)){return $this->respond(null,404,'Assessment unavailable.');}
                    return $this->respond(array('id'=>$this->ha_theory->start($aid,$this->uid)));
                case 'GET assessment':
                    $this->need('enrollments:read','assessments.view');$this->load->library('ha_theory');
                    $attempt=$this->db->get_where('ha_assessment_attempt',array('id'=>$id,'user_id'=>$this->uid))->row_array();
                    if(!$attempt){return $this->respond(null,404,'Assessment attempt unavailable.');}
                    $assessment=$this->ha_theory->assessment($attempt['assessment_id']);$this->load->library('ha_learning');
                    if(!$assessment||!$this->ha_learning->course_visible($assessment['course_id'],$this->uid)){return $this->respond(null,404,'Assessment unavailable.');}
                    $paper=$this->ha_theory->paper($id,$this->uid);
                    $questions=$paper['questions'];
                    foreach($questions as &$question){
                        // Text answers and matching pairs must not reveal the grading key.
                        if(in_array($question['type'],array('short_answer','essay'),true)){$question['options']=array();}
                        if($question['type']==='matching'){
                            $question['matches']=array_values(array_unique(array_column($question['options'],'match')));
                            sort($question['matches'],SORT_NATURAL|SORT_FLAG_CASE);
                            foreach($question['options'] as &$option){unset($option['match']);}unset($option);
                        }
                    }unset($question);
                    return $this->respond(array('id'=>$id,'status'=>$paper['attempt']['status'],'score'=>$paper['attempt']['percentage']!==null?(float)$paper['attempt']['percentage']:null,'passed'=>$paper['attempt']['passed']!==null?(int)$paper['attempt']['passed']:null,'expires_at'=>$paper['attempt']['expires_at'],'pass_score'=>(int)$paper['assessment']['pass_percentage'],'questions'=>$questions));
                case 'POST assessment_submit':
                    $this->need('enrollments:read','assessments.view');$this->load->library('ha_theory');
                    $attempt=$this->db->get_where('ha_assessment_attempt',array('id'=>(int)($body['id'] ?? 0),'user_id'=>$this->uid))->row_array();
                    if(!$attempt){return $this->respond(null,403,'Assessment attempt unavailable.');}
                    $assessment=$this->ha_theory->assessment($attempt['assessment_id']);$this->load->library('ha_learning');
                    if(!$assessment||!$this->ha_learning->course_visible($assessment['course_id'],$this->uid)){return $this->respond(null,404,'Assessment unavailable.');}
                    return $this->respond($this->ha_theory->submit((int)($body['id'] ?? 0),(array)($body['answers'] ?? array()),$this->uid));
                case 'GET search':
                    $this->need('knowledge:read','knowledge.view'); $this->load->library('ha_api_hkp');
                    return $this->respond($this->ha_api_hkp->knowledge_search(mb_substr((string)$this->input->get('q'),0,200),$this->locale));
                case 'POST assistant':
                    $this->need('knowledge:read','ai.use'); $this->load->library('ha_governed_ai');
                    $question=trim((string)($body['question'] ?? '')); if(!$question||mb_strlen($question)>1000){throw new InvalidArgumentException('Ask a question of 1 to 1000 characters.');}
                    return $this->respond($this->ha_governed_ai->ask($question,$this->locale));
                case 'GET people':
                case 'GET gaps':
                    $this->need('team:read',$route==='people'?'learners.view':'gaps.view');$this->load->library('ha_api_hkp');
                    return $this->respond($this->ha_api_hkp->{$route}());
                case 'GET competencies':
                case 'GET readiness':
                case 'GET actions':
                case 'GET certificates':
                    $permissions=array('competencies'=>'competencies.view','readiness'=>'readiness.view','actions'=>'action_plans.view','certificates'=>'certificates.view');
                    $this->need('performance:read',$permissions[$route]);$this->load->library('ha_api_hkp');
                    return $this->respond(in_array($route,array('competencies','readiness'),true)?$this->ha_api_hkp->{$route}((int)$this->input->get('user_id')):$this->ha_api_hkp->{$route}());
                case 'GET kpis':
                    $this->need('kpis:read','kpis.view');$this->load->library('ha_api_hkp');return $this->respond($this->ha_api_hkp->kpis((int)$this->input->get('property_id')));
                case 'GET notifications':
                    $this->need('profile:read');$this->load->library('ha_notify');return $this->respond($this->ha_notify->inbox($this->uid,100));
                case 'POST notifications_read':
                    $this->need('profile:read');$this->load->library('ha_notify');$this->ha_notify->mark_read($this->uid,isset($body['id'])?(int)$body['id']:null);return $this->respond(array('read'=>true));
                case 'GET organizations':
                case 'GET properties':
                    $this->need('profile:read');$table=$route==='properties'?'ha_property':'ha_organization';$out=array();
                    foreach($this->db->select('id,name_en,name_ar')->get($table)->result_array() as $r){$allowed=$route==='properties'?$this->ha_auth->can_property($r['id']):$this->ha_auth->can_organization($r['id']);$profile=$this->ha_auth->profile();$own=$profile&&((int)$profile[$route==='properties'?'property_id':'organization_id']===(int)$r['id']);if($allowed||$own){$out[]=$r;}}
                    return $this->respond($out);
                case 'POST assign':
                    $this->need('team:read','training_assignments.assign');$this->load->library('ha_learning');
                    $uid=(int)($body['user_id'] ?? 0);$cid=(int)($body['course_id'] ?? 0);
                    if(!$uid||!$this->ha_auth->can_user($uid)||!$this->ha_learning->course_visible($cid,$uid)){return $this->respond(null,403,'Assignment outside your scope.');}
                    $profile=$this->db->get_where('ha_profile',array('user_id'=>$uid))->row_array();
                    if(!$profile){throw new InvalidArgumentException('The employee has no active profile.');}
                    if(!empty($body['due_at'])&&(!strtotime($body['due_at'])||strtotime($body['due_at'])<time())){throw new InvalidArgumentException('Choose a future deadline.');}
                    return $this->respond($this->ha_learning->assign(array('items'=>array(array('course',$cid)),'targets'=>array(array('user',$uid)),'property_id'=>$profile['property_id'],'due_at'=>$body['due_at'] ?? null,'title_en'=>mb_substr((string)($body['title'] ?? 'Mobile learning assignment'),0,190)), $this->uid));
            }
            return $this->respond(null,404,'This mobile endpoint is not available.');
        } catch (InvalidArgumentException $e) { return $this->respond(null,422,$e->getMessage());
        } catch (RuntimeException $e) { return $this->respond(null,403,$e->getMessage());
        } catch (Throwable $e) { log_message('error','Mobile API request failed: '.get_class($e)); return $this->respond(null,500,'The request could not be completed.'); }
    }

    /** @return array('status','message','data') — public so tests can drive it without HTTP. */
    public function sign_in($route, array $body, $ip) {
        $this->load->library('ha_mobile_session');
        foreach(array('email','password','device','challenge','code') as $field){
            if(isset($body[$field])&&!is_string($body[$field])){return array('status'=>422,'message'=>'Use text values for sign-in fields.','data'=>null);}
        }
        $device = isset($body['device']) ? (string) $body['device'] : '';
        if ($route === 'login_2fa') {
            return $this->ha_mobile_session->verify_two_factor($body['challenge'] ?? '', $body['code'] ?? '', $ip, $device);
        }
        return $this->ha_mobile_session->login($body['email'] ?? '', $body['password'] ?? '', $ip, $device);
    }

    private function need($scope,$permission=null) {
        if(!Ha_api_keys::has_scope($this->auth_result['key'],$scope)){throw new RuntimeException('This personal key is missing the required scope.');}
        // Scope checks alone cannot preserve access after a role is changed.
        $grants=array('courses:read'=>'courses.view','enrollments:read'=>'courses.view');
        if(isset($grants[$scope])&&!$this->ha_auth->has($grants[$scope])){throw new RuntimeException('Your account does not have this permission.');}
        if($permission&&!$this->ha_auth->has($permission)){throw new RuntimeException('Your account does not have this permission.');}
    }
    private function media_url($path) {
        $path=trim((string)$path);if(!$path||strpos($path,'..')!==false||preg_match('~uploads/private~i',$path)){return null;}
        if(preg_match('~^https://~i',$path)){return $path;}
        // Same public locations the website's lesson view links to; native clients require HTTPS.
        if(preg_match('~^(uploads/academy/|uploads/hkp/|assets/)~',ltrim($path,'/'))){return preg_replace('~^http://~i','https://',base_url(ltrim($path,'/')));}
        return null;
    }
    private function course_shape($id,$progress=null,$mandatory=false) {
        $this->load->library('ha_learning');$c=$this->ha_learning->course($id,$this->locale);
        if(!$c){return null;}
        if($progress===null){$e=$this->db->get_where('ha_enrollment',array('user_id'=>$this->uid,'course_id'=>$id))->row_array();$progress=$e?(float)$e['progress_percentage']:0;}
        $title=$c['title'];$description=strip_tags($c['short_description'] ?: $c['description']);
        return array('id'=>(string)$id,'title'=>array('en'=>$title,'ar'=>$title),'category'=>array('en'=>$c['code'],'ar'=>$c['code']),'description'=>array('en'=>$description,'ar'=>$description),'minutes'=>(int)$c['duration_minutes'],'lessons'=>count($c['lessons']),'progress'=>$progress,'mandatory'=>$mandatory,'image'=>'lobby','image_url'=>$this->media_url($c['thumbnail'] ?? ''));
    }
    private function respond($data,$status=200,$message='Operation completed successfully') {
        return $this->output->set_status_header($status)->set_output(json_encode(array('success'=>$status<400,'data'=>$data,'message'=>$message),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
    }
}
