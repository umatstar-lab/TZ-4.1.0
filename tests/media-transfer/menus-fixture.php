<?php
if(PHP_SAPI!=='cli')exit(1);
require '/var/www/html/configuration.php';$c=new JConfig();if($c->dbprefix!=='migrationj54_')throw new RuntimeException('Local fixture only');
$db=new PDO('mysql:host='.$c->host.';dbname='.$c->db.';charset=utf8mb4',$c->user,$c->password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db->exec("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");$p=$c->dbprefix;
function put($db,$table,$row){$db->prepare('INSERT INTO '.$table.' (`'.implode('`,`',array_keys($row)).'`) VALUES ('.implode(',',array_fill(0,count($row),'?')).')')->execute(array_values($row));}
$action=$argv[1]??'';
if($action==='prepare'){
 if($db->query("SELECT id FROM {$p}menu WHERE id BETWEEN 93001 AND 93016")->fetchColumn())throw new RuntimeException('Fixture occupied');
 foreach([93001,93002] as $id){$m=$db->query("SELECT * FROM {$p}menu_types WHERE client_id=0 LIMIT 1")->fetch(PDO::FETCH_ASSOC);$m['id']=$id;$m['menutype']='tzmt-menu-'.$id;$m['title']='TZMT меню '.$id;put($db,$p.'menu_types',$m);}
 $base=$db->query("SELECT * FROM {$p}menu WHERE client_id=0 AND type='component' AND id>1 LIMIT 1")->fetch(PDO::FETCH_ASSOC);$root=$db->query("SELECT rgt FROM {$p}menu WHERE id=1")->fetchColumn();
 $article=(int)$db->query("SELECT id FROM {$p}tz_portfolio_plus_content WHERE state=1 AND id<90000 ORDER BY id LIMIT 1")->fetchColumn();
 $links=['article&id=91001','categories&id=2','categories&layout=grid&id=2','portfolio','date','search','myarticles','form&layout=edit','article&id=91001','article&id=999999999','mystery','article&id=91001','article&id=91001','article&id=91001','article&id=91001','article&id=91001'];
 foreach($links as $i=>$view){$r=$base;$r['id']=93001+$i;$r['menutype']='tzmt-menu-'.($i%2?93002:93001);$r['title']='TZMT menu '.$i.' <script>window.tzmtMenuXss=1</script>';$r['alias']='tzmt-menu-'.$i;$r['path']=$r['alias'];$r['parent_id']=1;$r['level']=1;$r['lft']=$root+$i*2;$r['rgt']=$root+$i*2+1;$r['component_id']=999999;$r['link']='index.php?option=com_tz_portfolio_plus&view='.$view;$r['published']=$i===8?0:($i===11?-2:1);$r['home']=0;$r['language']='*';$r['checked_out']=0;$r['checked_out_time']=null;$r['params']=json_encode(['catid'=>[2],'tz_catid'=>[2],'keep'=>'unchanged']);
 if($i===12){$r['type']='alias';$r['component_id']=0;$r['link']='index.php?Itemid=93001';$r['params']='{"aliasoptions":93001}';}
 if($i===13){$r['type']='url';$r['component_id']=0;$r['link']='https://example.invalid/index.php?option=com_tz_portfolio_plus&view=article&id=91001';}
 if($i===14){$r['component_id']=0;$r['link']='index.php?option=com_tz_portfolio_plus&view=article&id=91001&Itemid=93015&custom=a%26b#keep';}
 if($i===15){$r['component_id']=0;$r['link']='index.php?option=com_content&view=article&id=91001';}
 if($i===0)$r['rgt']=$root+3;if($i===1){$r['menutype']='tzmt-menu-93001';$r['parent_id']=93001;$r['level']=2;$r['lft']=$root+1;$r['rgt']=$root+2;$r['path']='tzmt-menu-0/'.$r['alias'];}
 $r['link']=str_replace('id=91001','id='.$article,$r['link']);put($db,$p.'menu',$r);
 }
 $db->exec("UPDATE {$p}menu SET rgt=rgt+32 WHERE id=1");echo 'prepared';
}elseif($action==='snapshot')echo json_encode($db->query("SELECT * FROM {$p}menu WHERE id BETWEEN 93001 AND 93016 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC),JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
elseif($action==='edit')$db->exec("UPDATE {$p}menu SET note='manual edit' WHERE id=93001");
elseif($action==='restore-edit')$db->exec("UPDATE {$p}menu SET note=".$db->quote($argv[2]??'')." WHERE id=93001");
elseif($action==='cleanup'){foreach($db->query("SELECT id,data FROM {$p}tz_media_transfer_menus")->fetchAll(PDO::FETCH_ASSOC) as $job){$changes=json_decode($job['data'],true);if(isset($changes[0]['before']['id'])&&(int)$changes[0]['before']['id']>=93001&&(int)$changes[0]['before']['id']<=93016)$db->prepare("DELETE FROM {$p}tz_media_transfer_menus WHERE id=?")->execute([$job['id']]);}$n=$db->query("SELECT COUNT(*) FROM {$p}menu WHERE id BETWEEN 93001 AND 93016")->fetchColumn();$db->exec("DELETE FROM {$p}menu WHERE id BETWEEN 93001 AND 93016");$db->exec("DELETE FROM {$p}menu_types WHERE id IN (93001,93002)");if($n)$db->exec("UPDATE {$p}menu SET rgt=rgt-".(int)$n*2 ." WHERE id=1");}
else throw new RuntimeException('Unknown action');
