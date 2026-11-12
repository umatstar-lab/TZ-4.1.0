<?php
// Local test data only. Never run on a production site.
require '/var/www/html/configuration.php';$c=new JConfig();
if($c->dbprefix!=='migrationj54_')throw new RuntimeException('Isolated fixture database required.');
$db=new PDO('mysql:host='.$c->host.';dbname='.$c->db.';charset=utf8mb4',$c->user,$c->password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db->exec("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");
function imageFile(string $path,int $color): void {@mkdir(dirname($path),0755,true);$im=imagecreatetruecolor(200,120);$rgb=imagecolorallocate($im,($color*61)%255,($color*83)%255,($color*113)%255);imagefill($im,0,0,$rgb);imagejpeg($im,$path,90);imagedestroy($im);chmod($path,0644);}
$base=$db->query('SELECT * FROM '.$c->dbprefix.'tz_portfolio_plus_content WHERE id=6')->fetch(PDO::FETCH_ASSOC);
$db->exec("UPDATE {$c->dbprefix}tz_portfolio_plus_extensions SET published=1 WHERE folder='content' AND element='gallery'");
$ext=(int)$db->query("SELECT id FROM {$c->dbprefix}tz_portfolio_plus_extensions WHERE element='gallery' AND folder='content'")->fetchColumn();
$cases=[
 91001=>['title'=>'TZMT test — копирование','state'=>1,'type'=>'flipbook_gallery','flip'=>[['a.jpg','Новий підпис A',1],['b.jpg','',2],['фото з підписом.jpg','Підпис <script>window.tzmtXss=1</script> «тест» & "',3],['samehash.jpg','Інша підпис того ж фото',1]],'gallery'=>[['a.jpg','Старий підпис A',1],['b.jpg','',4]]],
 91002=>['title'=>'TZMT test — перемещение','state'=>1,'type'=>'flipbook_gallery','flip'=>[['move.jpg','Перенести разом із фото',1],['keep.jpg','Не втратити джерело',3]],'gallery'=>[['keep.jpg','Старе фото',4]]],
 91003=>['title'=>'TZMT test — обратное направление','state'=>1,'type'=>'none','flip'=>[['reverse.jpg','Наявна підпис, не стерти',1]],'gallery'=>[['reverse.jpg','',1],['q.jpg','З Gallery у фліпбук',2]]],
 91004=>['title'=>'TZMT test — КОРЗИНА','state'=>-2,'type'=>'flipbook_gallery','flip'=>[['trash.jpg','Не чіпати',1]],'gallery'=>[]],
 91005=>['title'=>'TZMT test — переименовать','state'=>1,'type'=>'flipbook_gallery','flip'=>[['orphan.jpg','Нова підпис',2]],'gallery'=>[]],
 91006=>['title'=>'TZMT test — небезопасный путь','state'=>1,'type'=>'flipbook_gallery','flip'=>[['../escape.jpg','Не читати',1]],'gallery'=>[]],
 91008=>['title'=>'TZMT test — безопасный диалог','state'=>1,'type'=>'flipbook_gallery','flip'=>[['xss.jpg','<img src=x onerror="window.tzmtXss=1"> «Підпис»',2]],'gallery'=>[['xss.jpg','Інша підпис',2]]],
 91007=>['title'=>'TZMT test — нет файла','state'=>1,'type'=>'flipbook_gallery','flip'=>[['missing.jpg','Немає файлу',1]],'gallery'=>[]],
];
foreach($cases as $id=>$case){
 $a=$base;$a['id']=$id;$a['asset_id']=0;$a['title']=$case['title'];$a['alias']='tzmt-test-'.$id;$a['state']=$case['state'];$a['language']='*';$a['type']=$case['type'];$a['attribs']='{}';$a['introtext']='';$a['fulltext']='';
 $a['media']=json_encode(['flipbook_gallery'=>['data'=>array_column($case['flip'],0),'title'=>array_column($case['flip'],1),'featured'=>$case['flip'][0][0]??'']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
 $db->exec('DELETE FROM '.$c->dbprefix.'tz_portfolio_plus_content WHERE id='.$id);$s=$db->prepare('INSERT INTO '.$c->dbprefix.'tz_portfolio_plus_content (`'.implode('`,`',array_keys($a)).'`) VALUES ('.implode(',',array_fill(0,count($a),'?')).')');$s->execute(array_values($a));
 $db->exec('DELETE FROM '.$c->dbprefix.'tz_portfolio_plus_addon_data WHERE content_id='.$id);
 if($case['gallery']){$value=json_encode(['title'=>'Збережений заголовок','custom_class'=>'keep-this','gallery_content'=>['gallery_image'=>array_column($case['gallery'],0),'gallery_image_title'=>array_column($case['gallery'],1),'image_featured'=>$case['gallery'][0][0]]],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$s=$db->prepare('INSERT INTO '.$c->dbprefix.'tz_portfolio_plus_addon_data (extension_id,element,content_id,value,published) VALUES (?,\'gallery\',?,?,1)');$s->execute([$ext,$id,$value]);}
 foreach(['flip'=>'images/tz_portfolio/flipbook_gallery/','gallery'=>'images/tz_portfolio_plus/gallery/'] as $key=>$dir){
  foreach($case[$key] as [$name,$caption,$color]){if(str_contains($name,'..')||$name==='missing.jpg')continue;imageFile('/var/www/html/'.$dir.$id.'/'.$name,$color);if(in_array($name,['a.jpg','b.jpg','move.jpg']))imageFile('/var/www/html/'.$dir.$id.'/resize/'.pathinfo($name,PATHINFO_FILENAME).'_xs.jpg',$color);}
 }
}
imageFile('/var/www/html/images/tz_portfolio_plus/gallery/91005/orphan.jpg',4);
echo 'Created 8 isolated test materials, including a trashed row and invalid/missing sources.',PHP_EOL;
