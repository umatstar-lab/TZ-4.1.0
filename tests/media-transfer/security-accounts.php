<?php
if(PHP_SAPI!=='cli')exit(1);
require '/var/www/html/configuration.php';$c=new JConfig();if($c->dbprefix!=='migrationj54_')throw new RuntimeException('Local fixture only');
$db=new PDO('mysql:host='.$c->host.';dbname='.$c->db.';charset=utf8mb4',$c->user,$c->password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$path='/tmp/tzmt-security-account.json';$action=$argv[1]??'';
function rules($db,$c,$name,$group,$values){$q=$db->prepare('SELECT rules FROM '.$c->dbprefix.'assets WHERE name=?');$q->execute([$name]);$r=json_decode($q->fetchColumn(),true)?:[];foreach($values as $key=>$value)$r[$key][(string)$group]=$value;$db->prepare('UPDATE '.$c->dbprefix.'assets SET rules=? WHERE name=?')->execute([json_encode($r),$name]);}
if($action==='setup'){
 if(file_exists($path)||$db->query("SELECT id FROM {$c->dbprefix}users WHERE username='tzmt_security_limited'")->fetchColumn())throw new RuntimeException('Security fixture already active');
 $names=['root.1','com_tz_media_transfer'];$state=['assets'=>[]];foreach($names as $name){$q=$db->prepare('SELECT name,rules FROM '.$c->dbprefix.'assets WHERE name=?');$q->execute([$name]);$r=$q->fetch(PDO::FETCH_ASSOC);if(!$r)throw new RuntimeException('Missing component asset');$state['assets'][]=$r;}
 $db->prepare('INSERT INTO '.$c->dbprefix.'usergroups (parent_id,lft,rgt,title) VALUES (1,100000,100001,?)')->execute(['TZMT security fixture']);$state['group']=(int)$db->lastInsertId();
 $u=$db->query("SELECT * FROM {$c->dbprefix}users WHERE username='developer'")->fetch(PDO::FETCH_ASSOC);unset($u['id']);$u['username']='tzmt_security_limited';$u['name']='TZMT security fixture';$u['email']='tzmt-security@example.test';$u['sendEmail']=0;
 $db->prepare('INSERT INTO '.$c->dbprefix.'users (`'.implode('`,`',array_keys($u)).'`) VALUES ('.implode(',',array_fill(0,count($u),'?')).')')->execute(array_values($u));$state['user']=(int)$db->lastInsertId();
 $db->prepare('INSERT INTO '.$c->dbprefix.'user_usergroup_map (user_id,group_id) VALUES (?,?)')->execute([$state['user'],$state['group']]);file_put_contents($path,json_encode($state));chmod($path,0600);
 rules($db,$c,'root.1',$state['group'],['core.login.admin'=>true,'core.edit'=>true,'core.delete'=>true]);rules($db,$c,'com_tz_media_transfer',$state['group'],['core.manage'=>true]);echo 'Limited local security account created.',PHP_EOL;
}elseif($action==='revoke'||$action==='grant'||$action==='deny-manage'){
 $state=json_decode(file_get_contents($path),true);if($action==='deny-manage')rules($db,$c,'com_tz_media_transfer',$state['group'],['core.manage'=>false]);else rules($db,$c,'root.1',$state['group'],['core.edit'=>$action==='grant','core.delete'=>$action==='grant']);
}elseif($action==='restore'&&file_exists($path)){
 $state=json_decode(file_get_contents($path),true);foreach($state['assets'] as $r)$db->prepare('UPDATE '.$c->dbprefix.'assets SET rules=? WHERE name=?')->execute([$r['rules'],$r['name']]);
 $db->prepare('DELETE FROM '.$c->dbprefix.'user_usergroup_map WHERE user_id=?')->execute([$state['user']]);$db->prepare('DELETE FROM '.$c->dbprefix.'users WHERE id=?')->execute([$state['user']]);$db->prepare('DELETE FROM '.$c->dbprefix.'usergroups WHERE id=?')->execute([$state['group']]);unlink($path);echo 'Security account and ACL fixtures restored.',PHP_EOL;
}else throw new RuntimeException('Unknown security fixture action');
