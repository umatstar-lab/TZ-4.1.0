<?php
if(PHP_SAPI!=='cli')exit(1);
require '/var/www/html/configuration.php';$c=new JConfig();if($c->dbprefix!=='migrationj54_')throw new RuntimeException('Fixture only.');
$db=new PDO('mysql:host='.$c->host.';dbname='.$c->db.';charset=utf8mb4',$c->user,$c->password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$action=$argv[1]??'';
if($action==='account'){
 $exists=$db->query("SELECT id FROM {$c->dbprefix}users WHERE username='tzmt_test_other'")->fetchColumn();
 if(!$exists){$u=$db->query("SELECT * FROM {$c->dbprefix}users WHERE username='developer'")->fetch(PDO::FETCH_ASSOC);unset($u['id']);$u['name']='TZMT other local test admin';$u['username']='tzmt_test_other';$u['email']='tzmt-other@example.test';$s=$db->prepare('INSERT INTO '.$c->dbprefix.'users (`'.implode('`,`',array_keys($u)).'`) VALUES ('.implode(',',array_fill(0,count($u),'?')).')');$s->execute(array_values($u));$id=$db->lastInsertId();$db->exec('INSERT INTO '.$c->dbprefix.'user_usergroup_map (user_id,group_id) VALUES ('.(int)$id.',8)');}
 echo 'Local second-account fixture ready.';exit;
}
if($action==='edit'){
 $s=$db->query("SELECT id,value FROM {$c->dbprefix}tz_portfolio_plus_addon_data WHERE content_id=91005 AND element='gallery'");$r=$s->fetch(PDO::FETCH_ASSOC);$v=json_decode($r['value'],true);$v['title']='Ручная правка после копирования';$q=$db->prepare('UPDATE '.$c->dbprefix.'tz_portfolio_plus_addon_data SET value=? WHERE id=?');$q->execute([json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),$r['id']]);exit;
}
if($action==='restore'){$value=base64_decode($argv[2],true);json_decode($value,flags:JSON_THROW_ON_ERROR);$q=$db->prepare("UPDATE {$c->dbprefix}tz_portfolio_plus_addon_data SET value=? WHERE content_id=91005 AND element='gallery'");$q->execute([$value]);exit;}
if($action==='prepared'){
 $job=$argv[2];if(!preg_match('/^[a-f0-9]{32}$/D',$job))throw new RuntimeException('Bad job.');
 define('_JEXEC',1);require '/var/www/html/administrator/components/com_tz_media_transfer/src/Service/Adapters.php';require '/var/www/html/administrator/components/com_tz_media_transfer/src/Service/Files.php';
 $files=new Umatstar\Component\TzMediaTransfer\Administrator\Service\Files('/var/www/html');
 $src='images/tz_portfolio/flipbook_gallery/91005/orphan.jpg';$dst='images/tz_portfolio_plus/gallery/91005/orphan.jpg';$old=$files->hash($dst);$hash=$files->hash($src);$backup=$files->backup($dst,$old);
 $op=['article'=>91005,'files'=>[['source'=>$src,'path'=>$dst,'before_hash'=>$old,'backup'=>$backup,'after_hash'=>$hash]],'cleanup'=>false];
 $s=$db->prepare('INSERT INTO '.$c->dbprefix.'tz_media_transfer_changes (job_id,status,data) VALUES (?,\'prepared\',?)');$s->execute([$job,json_encode($op,JSON_UNESCAPED_SLASHES)]);$files->claimBackups();$files->atomicCopy($src,$dst,$hash);echo $backup;exit;
}
throw new RuntimeException('Unknown fixture action.');
