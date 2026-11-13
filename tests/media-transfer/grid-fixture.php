<?php
if(PHP_SAPI!=='cli')exit(1);
require '/var/www/html/configuration.php';$c=new JConfig();if($c->dbprefix!=='migrationj54_')throw new RuntimeException('Local fixture only');
$db=new PDO('mysql:host='.$c->host.';dbname='.$c->db.';charset=utf8mb4',$c->user,$c->password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$id=91009;$action=$argv[1]??'prepare';$root='/var/www/html/';
$paths=['images/tz_portfolio_plus/grid_gallery/91009','images/tz_portfolio/grid_gallery/91009','images/com_tz_portfolio/grid_gallery/91009','images/com_tz_portfolio/gallery/91009','images/com_tz_portfolio/flipbook_gallery/91009'];
function clean($p){if(!is_dir($p))return;foreach(new DirectoryIterator($p) as $f){if($f->isDot())continue;$f->isDir()?clean($f->getPathname()):unlink($f->getPathname());}rmdir($p);}
function image($p,$color){if(!is_dir(dirname($p))&&!mkdir(dirname($p),0755,true))throw new RuntimeException('Cannot create Grid fixture directory');$i=imagecreatetruecolor(120,80);imagefill($i,0,0,imagecolorallocate($i,$color,40,70));imagejpeg($i,$p,90);imagedestroy($i);chmod($p,0644);}
if($action==='prepare'){
 if($db->query("SELECT id FROM {$c->dbprefix}tz_portfolio_plus_content WHERE id=$id")->fetchColumn())throw new RuntimeException('Grid fixture already active');
 foreach($paths as $path)if(is_dir($root.$path))throw new RuntimeException('Grid fixture directory occupied');
 $a=$db->query("SELECT * FROM {$c->dbprefix}tz_portfolio_plus_content WHERE id=91001")->fetch(PDO::FETCH_ASSOC);if(!$a)throw new RuntimeException('Prepare standard fixtures first');
 $a['id']=$id;$a['title']='TZMT Grid Gallery 1.0.13 migration';$a['alias']='tzmt-grid-legacy';$a['type']='grid_gallery';$a['attribs']='{}';
 $a['media']=json_encode(['grid_gallery'=>['data'=>['legacy.jpg','current.jpg','intermediate.jpg','gone.jpg'],'title'=>['Вистава «Сковорода» — старий каталог','Poster "Nabucco" & Україна','Третя світлина','Відсутня світлина'],'featured'=>'legacy.jpg'],'flipbook_gallery'=>['data'=>['legacy.jpg'],'title'=>['Стара підпис фліпбука'],'featured'=>'legacy.jpg'],'image'=>['url'=>'unrelated.jpg']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
 $db->prepare('INSERT INTO '.$c->dbprefix.'tz_portfolio_plus_content (`'.implode('`,`',array_keys($a)).'`) VALUES ('.implode(',',array_fill(0,count($a),'?')).')')->execute(array_values($a));
 foreach([['legacy.jpg',$paths[0],90],['current.jpg',$paths[2],160],['intermediate.jpg',$paths[1],210]] as [$name,$path,$color]){image($root.$path.'/'.$name,$color);image($root.$path.'/resize/'.pathinfo($name,PATHINFO_FILENAME).'_xs.jpg',$color);}
 image($root.$paths[4].'/legacy.jpg',90);echo 'Prepared Grid 1.0.13 media schema and files in three migration roots.',PHP_EOL;
}elseif($action==='snapshot'){
 $a=$db->query("SELECT id,media,type,attribs FROM {$c->dbprefix}tz_portfolio_plus_content WHERE id=$id")->fetch(PDO::FETCH_ASSOC);
 $g=$db->query("SELECT * FROM {$c->dbprefix}tz_portfolio_plus_addon_data WHERE content_id=$id AND element='gallery'")->fetch(PDO::FETCH_ASSOC);$files=[];
 foreach($paths as $path)if(is_dir($root.$path))foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.$path,FilesystemIterator::SKIP_DOTS)) as $f)if($f->isFile())$files[substr($f->getPathname(),strlen($root))]=hash_file('sha256',$f->getPathname());ksort($files);echo json_encode(['article'=>$a,'gallery'=>$g,'files'=>$files],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}elseif($action==='cleanup'){
 $db->exec("DELETE FROM {$c->dbprefix}tz_portfolio_plus_addon_data WHERE content_id=$id");$db->exec("DELETE FROM {$c->dbprefix}tz_portfolio_plus_content WHERE id=$id");foreach($paths as $path)clean($root.$path);echo 'Grid fixture removed.',PHP_EOL;
}else throw new RuntimeException('Unknown fixture action');
