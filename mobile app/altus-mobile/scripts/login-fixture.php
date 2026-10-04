<?php
// CLI only, loopback test database only. Secrets travel through a child-process pipe.
if (PHP_SAPI!=='cli') { exit(1); }
$db=new mysqli('127.0.0.1','root','','atlas_hospitality_test'); $db->set_charset('utf8mb4');
if (($argv[1] ?? '')==='setup') {
    $root=dirname(__DIR__,3);
    $source=file_get_contents($root.'/application/migrations/20260101000034_mobile_session_security.php');
    preg_match_all('~query\("(.*?)"\s*\.\s*\$this->engine\)~s',$source,$matches);
    if (count($matches[1])!==2) { throw new RuntimeException('Migration schema could not be read.'); }
    foreach ($matches[1] as $sql) { $db->query($sql.' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci'); }
    $users=$db->query('SELECT id,email,password,status,sessions FROM users WHERE id IN (1,3,6,12)')->fetch_all(MYSQLI_ASSOC);
    if (count($users)!==4) { throw new RuntimeException('Required isolated fixtures missing.'); }
    if ((int)$db->query('SELECT COUNT(*) n FROM ha_user_2fa WHERE user_id IN (1,3,6,12) AND confirmed_at IS NOT NULL')->fetch_assoc()['n']) {
        throw new RuntimeException('These HTTP fixtures must have no enabled second factor; MFA is checked by the isolated service suite.');
    }
    $settings=$db->query("SELECT `key`,value FROM settings WHERE `key`='allowed_device_number_of_loging'")->fetch_all(MYSQLI_ASSOC);
    $password='Mobile#'.bin2hex(random_bytes(12)); $hash=sha1($password);
    $start=(int)$db->query('SELECT COALESCE(MAX(id),0) n FROM ha_api_key')->fetch_assoc()['n'];
    $db->query("UPDATE settings SET value='99' WHERE `key`='allowed_device_number_of_loging'");
    $stmt=$db->prepare("UPDATE users SET password=?,status=1,sessions='[]' WHERE id IN (1,3,6,12)"); $stmt->bind_param('s',$hash); $stmt->execute();
    $roles=$db->query('SELECT * FROM ha_user_role WHERE user_id=12')->fetch_all(MYSQLI_ASSOC);
    echo json_encode(array('users'=>$users,'settings'=>$settings,'roles'=>$roles,'password'=>$password,'start'=>$start));
} elseif (($argv[1] ?? '')==='revoke-role') {
    $db->query('DELETE FROM ha_user_role WHERE user_id=12');
    echo 'Isolated learner role revoked.';
} elseif (($argv[1] ?? '')==='cleanup') {
    $data=json_decode(file_get_contents('php://stdin'),true);
    foreach ($data['users'] as $u) {
        if (!in_array((int)$u['id'],array(1,3,6,12),true)) { throw new RuntimeException('Unexpected fixture id.'); }
        $s=$db->prepare('UPDATE users SET password=?,status=?,sessions=? WHERE id=?');
        $s->bind_param('sisi',$u['password'],$u['status'],$u['sessions'],$u['id']); $s->execute();
    }
    foreach ($data['settings'] as $row) { $s=$db->prepare('UPDATE settings SET value=? WHERE `key`=?'); $s->bind_param('ss',$row['value'],$row['key']); $s->execute(); }
    if (isset($data['roles'])) {
        $db->query('DELETE FROM ha_user_role WHERE user_id=12');
        foreach ($data['roles'] as $row) {
            if ((int)$row['user_id']!==12) { throw new RuntimeException('Unexpected role fixture.'); }
            $fields=array_keys($row);
            foreach ($fields as $field) { if (!preg_match('/^[a-z_]+$/D',$field)) { throw new RuntimeException('Unexpected role column.'); } }
            $s=$db->prepare('INSERT INTO ha_user_role (`'.implode('`,`',$fields).'`) VALUES ('.implode(',',array_fill(0,count($fields),'?')).')');
            $values=array_values($row); $s->bind_param(str_repeat('s',count($values)),...$values); $s->execute();
        }
    }
    $start=(int)$data['start'];
    $db->query("DELETE s FROM ha_mobile_session s JOIN ha_api_key k ON k.id=s.key_id WHERE k.id>$start AND k.user_id IN (1,3,6,12)");
    $db->query("DELETE FROM ha_api_key WHERE id>$start AND user_id IN (1,3,6,12)");
    echo 'Isolated login fixtures restored.';
} else { exit(1); }
