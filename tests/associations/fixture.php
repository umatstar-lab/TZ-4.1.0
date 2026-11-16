<?php
if(PHP_SAPI!=='cli')exit(1);
require '/var/www/html/configuration.php';$c=new JConfig();if($c->dbprefix!=='joom_'||!is_file('/tmp/tzmt-j62-fixtures-authorized'))throw new RuntimeException('Isolated Joomla 6 fixture required');
$db=new PDO('mysql:host='.$c->host.';dbname='.$c->db.';charset=utf8mb4',$c->user,$c->password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db->exec("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");$p=$c->dbprefix;$id=91040;$backup='/tmp/tz-associations-test.json';
function insert($db,$table,$r){$db->prepare('INSERT INTO '.$table.' (`'.implode('`,`',array_keys($r)).'`) VALUES ('.implode(',',array_fill(0,count($r),'?')).')')->execute(array_values($r));return (int)$db->lastInsertId();}
$action=$argv[1]??'';
if($action==='prepare'){
 if(file_exists($backup))throw new RuntimeException('Fixture occupied');
 $ext=$db->query("SELECT extension_id,enabled,params FROM {$p}extensions WHERE element='languagefilter' AND folder='system'")->fetch(PDO::FETCH_ASSOC);file_put_contents($backup,json_encode($ext));chmod($backup,0600);
 $params=json_decode($ext['params'],true)?:[];$params['item_associations']=1;$db->prepare("UPDATE {$p}extensions SET enabled=1,params=? WHERE extension_id=?")->execute([json_encode($params),$ext['extension_id']]);
 $base=$db->query("SELECT * FROM {$p}tz_portfolio_plus_content WHERE state=1 AND id<90000 LIMIT 1")->fetch(PDO::FETCH_ASSOC);$baseId=$base['id'];
 foreach([91040=>'en-GB',91041=>'uk-UA'] as $aid=>$language){
 $r=$base;$r['id']=$aid;$r['title']='Associations fixture '.$aid.' <img src=x onerror=alert(1)>';$r['alias']='association-test-'.$aid;$r['language']=$language;$r['featured']=1;$r['checked_out']=0;insert($db,$p.'tz_portfolio_plus_content',$r);
 foreach($db->query("SELECT * FROM {$p}tz_portfolio_plus_content_category_map WHERE contentid=".(int)$baseId)->fetchAll(PDO::FETCH_ASSOC) as $map){unset($map['id']);$map['contentid']=$aid;insert($db,$p.'tz_portfolio_plus_content_category_map',$map);}
 insert($db,$p.'tz_portfolio_plus_content_featured_map',['content_id'=>$aid,'ordering'=>$aid]);
 insert($db,$p.'associations',['id'=>$aid,'context'=>'com_tz_portfolio.article.item','key'=>md5('isolated-association-test')]);
 }
 echo 'prepared';
}elseif($action==='cleanup'){
 foreach(['tz_portfolio_plus_content_category_map'=>'contentid','tz_portfolio_plus_content_featured_map'=>'content_id','associations'=>'id','tz_portfolio_plus_content'=>'id'] as $table=>$col)$db->exec("DELETE FROM {$p}{$table} WHERE {$col} IN (91040,91041)");
 if(file_exists($backup)){$s=json_decode(file_get_contents($backup),true);$db->prepare("UPDATE {$p}extensions SET enabled=?,params=? WHERE extension_id=?")->execute([$s['enabled'],$s['params'],$s['extension_id']]);unlink($backup);}echo 'cleaned';
}else throw new RuntimeException('Unknown action');
