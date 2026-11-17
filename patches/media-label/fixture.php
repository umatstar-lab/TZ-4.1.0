<?php
if(PHP_SAPI!=='cli')exit(1);
require '/var/www/html/configuration.php';$c=new JConfig();if($c->dbprefix!=='joom_'||!is_file('/tmp/tzmt-j62-fixtures-authorized'))throw new RuntimeException('Isolated Joomla 6 fixture required');
$db=new PDO('mysql:host='.$c->host.';dbname='.$c->db.';charset=utf8mb4',$c->user,$c->password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db->exec("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");$p=$c->dbprefix;$id=91040;$backup='/tmp/tz-associations-test.json';
function insert($db,$table,$r){$db->prepare('INSERT INTO '.$table.' (`'.implode('`,`',array_keys($r)).'`) VALUES ('.implode(',',array_fill(0,count($r),'?')).')')->execute(array_values($r));return (int)$db->lastInsertId();}
$action=$argv[1]??'';
if($action==='prepare'){
 $base=$db->query("SELECT * FROM {$p}tz_portfolio_plus_content WHERE state=1 AND id<90000 LIMIT 1")->fetch(PDO::FETCH_ASSOC);$baseId=$base['id'];
 foreach([91060=>'content_gallery',91061=>'none',91062=>'image',91063=>'flipbook_gallery'] as $aid=>$type){
 $r=$base;$r['id']=$aid;$r['title']='Media label fixture '.$type;$r['alias']='media-label-fixture-'.$aid;$r['type']=$type;$r['featured']=1;$r['checked_out']=0;insert($db,$p.'tz_portfolio_plus_content',$r);
 foreach($db->query("SELECT * FROM {$p}tz_portfolio_plus_content_category_map WHERE contentid=".(int)$baseId)->fetchAll(PDO::FETCH_ASSOC) as $map){unset($map['id']);$map['contentid']=$aid;insert($db,$p.'tz_portfolio_plus_content_category_map',$map);}
 insert($db,$p.'tz_portfolio_plus_content_featured_map',['content_id'=>$aid,'ordering'=>$aid]);
 }
 echo 'prepared';
}elseif($action==='snapshot')echo json_encode($db->query("SELECT id,type FROM {$p}tz_portfolio_plus_content WHERE id BETWEEN 91060 AND 91063 ORDER BY id")->fetchAll(PDO::FETCH_ASSOC));
elseif($action==='cleanup'){
 foreach(['tz_portfolio_plus_content_category_map'=>'contentid','tz_portfolio_plus_content_featured_map'=>'content_id','tz_portfolio_plus_content'=>'id'] as $table=>$col)$db->exec("DELETE FROM {$p}{$table} WHERE {$col} BETWEEN 91060 AND 91063");echo 'cleaned';
}else throw new RuntimeException('Unknown action');
