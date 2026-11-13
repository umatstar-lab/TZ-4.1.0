<?php
if(PHP_SAPI!=='cli')exit(1);
require '/var/www/html/configuration.php';$c=new JConfig();if($c->dbprefix!=='migrationj54_')throw new RuntimeException('Local fixture only');
$db=new PDO('mysql:host='.$c->host.';dbname='.$c->db.';charset=utf8mb4',$c->user,$c->password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db->exec("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");$p=$c->dbprefix;$id=91020;$backup='/tmp/tzmt-gallery-columns.json';$folder='/var/www/html/images/com_tz_portfolio/gallery/'.$id;
$ext=$db->query("SELECT * FROM {$p}tz_portfolio_plus_extensions WHERE element='gallery' AND folder='content'")->fetch(PDO::FETCH_ASSOC);if(!$ext)throw new RuntimeException('Gallery missing');
function insertRow($db,$table,$r){unset($r['id']);$db->prepare('INSERT INTO '.$table.' (`'.implode('`,`',array_keys($r)).'`) VALUES ('.implode(',',array_fill(0,count($r),'?')).')')->execute(array_values($r));}
function cleanFolder($path){foreach(new DirectoryIterator($path) as $file){if($file->isDot())continue;if($file->isDir()&&!$file->isLink())cleanFolder($file->getPathname());else unlink($file->getPathname());}rmdir($path);}
$a=$argv[1]??'';
if($a==='prepare'){
 if(file_exists($backup)||is_dir($folder)||$db->query("SELECT id FROM {$p}tz_portfolio_plus_content WHERE id=$id")->fetchColumn())throw new RuntimeException('Fixture occupied');
 file_put_contents($backup,json_encode(['id'=>$ext['id'],'params'=>$ext['params'],'published'=>$ext['published']]));chmod($backup,0600);
 $base=$db->query("SELECT * FROM {$p}tz_portfolio_plus_content WHERE state=1 AND id<90000 ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);$baseId=$base['id'];$base['id']=$id;$base['title']='TZMT Responsive Gallery';$base['alias']='tzmt-responsive-gallery';$base['type']='content_gallery';$base['attribs']='{}';$base['media']='{}';$base['language']='*';$base['access']=1;$base['checked_out']=0;$base['checked_out_time']='0000-00-00 00:00:00';
 $db->prepare('INSERT INTO '.$p.'tz_portfolio_plus_content (`'.implode('`,`',array_keys($base)).'`) VALUES ('.implode(',',array_fill(0,count($base),'?')).')')->execute(array_values($base));
 foreach($db->query("SELECT * FROM {$p}tz_portfolio_plus_content_category_map WHERE contentid=".(int)$baseId)->fetchAll(PDO::FETCH_ASSOC) as $map){$map['contentid']=$id;insertRow($db,$p.'tz_portfolio_plus_content_category_map',$map);}
 mkdir($folder,0755,true);$names=[];$titles=[];for($i=1;$i<=20;$i++){$name='column-'.$i.'.jpg';$names[]=$name;$titles[]='Підпис '.$i;$im=imagecreatetruecolor(100,70+($i%3)*15);imagefill($im,0,0,imagecolorallocate($im,($i*33)%250,110,170));imagejpeg($im,$folder.'/'.$name,85);imagedestroy($im);}
 insertRow($db,$p.'tz_portfolio_plus_addon_data',['extension_id'=>$ext['id'],'element'=>'gallery','content_id'=>$id,'value'=>json_encode(['gallery_content'=>['gallery_image'=>$names,'gallery_image_title'=>$titles]]),'published'=>1]);
 $params=json_decode($ext['params'],true)?:[];$params=array_merge($params,['mt_gallery_show'=>1,'mt_gallery_type'=>'masonry','mt_gallery_size'=>'o','mt_gallery_columns_mobile'=>2,'mt_gallery_columns_tablet'=>4,'mt_gallery_columns_desktop'=>5,'mt_gallery_carousel_center_mode'=>0,'mt_gallery_carousel_autoplay'=>0]);$db->prepare("UPDATE {$p}tz_portfolio_plus_extensions SET params=?,published=1 WHERE id=?")->execute([json_encode($params),$ext['id']]);echo json_encode(['addon'=>(int)$ext['id'],'article'=>$id]);
}elseif($a==='settings'){
 $attr=json_decode($argv[2],true,16,JSON_THROW_ON_ERROR);if(isset($attr['mt_gallery_type'])){$params=json_decode($ext['params'],true);$params['mt_gallery_type']=$attr['mt_gallery_type'];$db->prepare("UPDATE {$p}tz_portfolio_plus_extensions SET params=? WHERE id=?")->execute([json_encode($params),$ext['id']]);}$db->prepare("UPDATE {$p}tz_portfolio_plus_content SET attribs=? WHERE id=$id")->execute([json_encode($attr)]);
}elseif($a==='read'){
 echo json_encode(['params'=>json_decode($db->query("SELECT params FROM {$p}tz_portfolio_plus_extensions WHERE id=".(int)$ext['id'])->fetchColumn(),true),'attribs'=>json_decode($db->query("SELECT attribs FROM {$p}tz_portfolio_plus_content WHERE id=$id")->fetchColumn(),true)]);
}elseif($a==='cleanup'){
 if(file_exists($backup)){$b=json_decode(file_get_contents($backup),true);$db->prepare("UPDATE {$p}tz_portfolio_plus_extensions SET params=?,published=? WHERE id=?")->execute([$b['params'],$b['published'],$b['id']]);unlink($backup);}
 $db->exec("DELETE FROM {$p}tz_portfolio_plus_addon_data WHERE content_id=$id");$db->exec("DELETE FROM {$p}tz_portfolio_plus_content_category_map WHERE contentid=$id");$db->exec("DELETE FROM {$p}tz_portfolio_plus_content WHERE id=$id");if(is_dir($folder))cleanFolder($folder);
}else throw new RuntimeException('Unknown action');
