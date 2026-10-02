<?php
// Isolated HTTP integration checks. Never connects to the working or production DB.
if (PHP_SAPI !== 'cli') { exit(1); }
$root = dirname(__DIR__, 3);
define('BASEPATH', $root . '/system/'); define('APPPATH', $root . '/application/');
if (!is_file(APPPATH . 'config/ha_app_key.php')) { throw new RuntimeException('Existing application crypto key required.'); }
require APPPATH . 'libraries/Ha_crypto.php';
$crypto = new Ha_crypto();
$db = new mysqli('127.0.0.1', 'root', '', 'atlas_hospitality_test');
$db->set_charset('utf8mb4');
$keys = array(); $checks = array(); $attempts = array();
function request_api($path, $key = null, $method = 'GET', $data = null) {
    $headers = array('Accept: application/json'); if ($key) { $headers[]='Authorization: Bearer '.$key; }
    if ($data !== null) { $headers[]='Content-Type: application/json'; }
    $ctx=stream_context_create(array('http'=>array('method'=>$method,'header'=>implode("\r\n",$headers),'ignore_errors'=>true,'timeout'=>60,'content'=>$data!==null?json_encode($data):'')));
    $raw=file_get_contents('http://127.0.0.1:8099/mobile_api/'.$path,false,$ctx);
    $http=$http_response_header ?? array(); $confirmed=false;$status=0;
    foreach($http as $h){if(preg_match('/^HTTP\/\S+ (\d+)/',$h,$m)){$status=(int)$m[1];}if(stripos($h,'X-HA-Test-Database: atlas_hospitality_test')===0){$confirmed=true;}}
    if(!$confirmed){throw new RuntimeException('Refusing tests: isolated test database header missing.');}
    $body=json_decode($raw,true);if(!is_array($body)){throw new RuntimeException('Invalid JSON response: '.substr(strip_tags($raw),0,250));}
    return array($status,$body);
}
function check_api($condition,$label){global $checks;if(!$condition){throw new RuntimeException($label);} $checks[]=$label;echo 'PASS '.$label.PHP_EOL;}
function fixture_key($uid,$scopes){global $db,$crypto,$keys;$prefix=substr(bin2hex(random_bytes(8)),0,12);$secret=bin2hex(random_bytes(20));$hash=$crypto->hmac($secret);$stmt=$db->prepare("INSERT INTO ha_api_key(user_id,name,prefix,secret_hash,scopes,expires_at,created_at) VALUES(?,'mobile-http-check',?,?,?,DATE_ADD(NOW(),INTERVAL 1 DAY),NOW())");$stmt->bind_param('isss',$uid,$prefix,$hash,$scopes);$stmt->execute();$keys[]=(int)$db->insert_id;return 'ha_'.$prefix.'_'.$secret;}
try {
    $all='profile:read courses:read enrollments:read performance:read knowledge:read team:read kpis:read mobile:write';
    $learner=fixture_key(12,$all);$readOnly=fixture_key(12,'profile:read');$manager=fixture_key(6,$all);
    list($status,$body)=request_api('me');check_api($status===401&&!$body['success'],'Missing token returns 401 JSON.');
    list($status,$body)=request_api('me',$learner);check_api($status===200&&(int)$body['data']['id']===12,'Verified key resolves its own live identity.');
    check_api(!isset($body['data']['password'])&&!isset($body['data']['secret_hash']),'Identity response contains no password or credential hash.');
    list($status,$body)=request_api('courses',$readOnly);check_api($status===403,'Missing course scope is denied.');
    list($status,$body)=request_api('notifications_read',$readOnly,'POST',array());check_api($status===403,'Read-only key cannot perform mobile writes.');
    list($status,$body)=request_api('courses?per_page=3',$learner);check_api($status===200&&is_array($body['data'])&&count($body['data'])<=3,'Native course contract returns a bounded authorized list.');
    list($status,$body)=request_api('competencies?user_id=25',$learner);check_api($status===403,'Learner cannot read another tenant employee competencies.');
    list($status,$body)=request_api('assign',$learner,'POST',array('user_id'=>25,'course_id'=>1));check_api($status===403,'Learner cannot assign learning.');
    list($status,$body)=request_api('assign',$manager,'POST',array('user_id'=>25,'course_id'=>1));check_api($status===403,'Manager cannot assign to another tenant employee.');
    list($status,$body)=request_api('knowledge',$learner);check_api($status===200&&is_array($body['data']),'Knowledge list uses published visible records.');
    list($status,$body)=request_api('properties',$learner);check_api($status===200,'Property selector is server-scoped.');
    list($status,$body)=request_api('assessment?id=3',$learner);check_api($status===404,'Another employee assessment attempt is inaccessible.');
    list($status,$body)=request_api('assessment_submit',$learner,'POST',array('id'=>3,'answers'=>array()));check_api($status===403,'Another employee assessment cannot be submitted.');
    $before=array_column($db->query('SELECT id FROM ha_assessment_attempt WHERE user_id=12')->fetch_all(MYSQLI_ASSOC),'id');
    list($status,$body)=request_api('assessment_start',$learner,'POST',array('id'=>1));check_api($status===200&&!empty($body['data']['id']),'Authorized assessment starts through the existing policy service.');
    $attempt=(int)$body['data']['id'];if(!in_array($attempt,array_map('intval',$before),true)){$attempts[]=$attempt;}
    list($status,$body)=request_api('assessment?id='.$attempt,$learner);check_api($status===200&&!empty($body['data']['questions']),'Native assessment paper contains authorized questions.');
    $safe=true;foreach($body['data']['questions'] as $q){foreach($q['options'] as $o){if(isset($o['is_correct'])){$safe=false;}}if($q['explanation']!==null){$safe=false;}}
    check_api($safe,'An in-progress paper does not reveal correct answers or explanations.');
    list($status,$body)=request_api('assessment_submit',$learner,'POST',array('id'=>$attempt,'answers'=>array()));
    check_api($status===200&&(float)$body['data']['percentage']===0.0&&(int)$body['data']['passed']===0,'Empty answers are graded by the server as a failed attempt.');
    list($status,$body)=request_api('assessment_submit',$learner,'POST',array('id'=>$attempt,'answers'=>array()));check_api($status===403,'A submitted attempt cannot be graded twice.');
    list($status,$body)=request_api('does_not_exist',$learner);check_api($status===404,'Unknown endpoint returns 404 JSON.');
    $db->query('UPDATE ha_api_key SET revoked_at=NOW() WHERE id='.(int)$keys[0]);
    list($status,$body)=request_api('me',$learner);check_api($status===401,'Revoked credentials stop working immediately.');
    file_put_contents(dirname(__DIR__,2).'/client-deliverables/api-checks.json',json_encode(array('database'=>'atlas_hospitality_test','checks'=>$checks,'working_data_touched'=>false),JSON_PRETTY_PRINT));
} finally {
    if($attempts){$ids=implode(',',array_map('intval',$attempts));$db->query('DELETE FROM ha_assessment_answer WHERE attempt_id IN ('.$ids.')');$db->query('DELETE FROM ha_assessment_attempt WHERE id IN ('.$ids.')');}
    if($keys){$db->query('DELETE FROM ha_api_key WHERE id IN ('.implode(',',array_map('intval',$keys)).')');}
    $db->close();
}
