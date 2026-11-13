<?php
if(PHP_SAPI!=='cli')exit(1);
require '/var/www/html/configuration.php';$c=new JConfig();if($c->dbprefix!=='migrationj54_')throw new RuntimeException('Local fixture only');
$db=new PDO('mysql:host='.$c->host.';dbname='.$c->db.';charset=utf8mb4',$c->user,$c->password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$id=91010;$root='/var/www/html/';$source='images/com_tz_portfolio/grid_gallery/91010';$target='images/com_tz_portfolio/gallery/91010';
function clearFolder($p){if(!is_dir($p))return;foreach(new DirectoryIterator($p) as $f)if(!$f->isDot())$f->isDir()?clearFolder($f->getPathname()):unlink($f->getPathname());rmdir($p);}
function pngChunk($type,$data){return pack('N',strlen($data)).$type.$data.pack('H*',hash('crc32b',$type.$data));}
$action=$argv[1]??'';
if($action==='prepare'){
 if($db->query("SELECT id FROM {$c->dbprefix}tz_portfolio_plus_content WHERE id=$id")->fetchColumn()||is_dir($root.$source)||is_dir($root.$target))throw new RuntimeException('Fixture occupied');
 $a=$db->query("SELECT * FROM {$c->dbprefix}tz_portfolio_plus_content WHERE id=91001")->fetch(PDO::FETCH_ASSOC);$a['id']=$id;$a['title']='TZMT file error actions';$a['alias']='tzmt-file-errors';$a['type']='grid_gallery';$a['attribs']='{}';
 $a['media']=json_encode(['grid_gallery'=>['data'=>['008.png','bad.jpg','good.jpg','bad.png','last.jpg'],'title'=>['Коректна світлина','Підозрілий файл','Зберегти підпис','Пошкоджений PNG','Остання світлина'],'featured'=>'008.png'],'image'=>['url'=>'unrelated.jpg']],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
 $db->prepare('INSERT INTO '.$c->dbprefix.'tz_portfolio_plus_content (`'.implode('`,`',array_keys($a)).'`) VALUES ('.implode(',',array_fill(0,count($a),'?')).')')->execute(array_values($a));
 mkdir($root.$source,0755,true);$pixels=str_pad('<?=t)'."\x13\x00\xFF",48,'A');$png="\x89PNG\r\n\x1a\n".pngChunk('IHDR',pack('NNCCCCC',16,1,8,2,0,0,0)).pngChunk('IDAT',gzcompress("\0".$pixels,0)).pngChunk('IEND','');file_put_contents($root.$source.'/008.png',$png);
 $im=imagecreatetruecolor(30,20);foreach(['bad.jpg','good.jpg','last.jpg'] as $index=>$name){imagefill($im,0,0,imagecolorallocate($im,40+$index*70,30,90));imagejpeg($im,$root.$source.'/'.$name,90);}imagedestroy($im);
 file_put_contents($root.$source.'/bad.jpg','<?php echo "<script>window.tzmtErrorXss=1</script>"; ?>',FILE_APPEND);$png[29]=chr(ord($png[29])^1);file_put_contents($root.$source.'/bad.png',$png);
 echo 'Prepared valid binary-marker PNG, PHP-bearing JPEG, good images and bad CRC PNG.',PHP_EOL;
}elseif($action==='thumbnail'){
 mkdir($root.$source.'/resize',0755,true);copy($root.$source.'/bad.png',$root.$source.'/resize/008_xl.png');
}elseif($action==='thumbnail-remove'){
 unlink($root.$source.'/resize/008_xl.png');rmdir($root.$source.'/resize');
}elseif($action==='snapshot'){
 $a=$db->query("SELECT id,media,type,attribs FROM {$c->dbprefix}tz_portfolio_plus_content WHERE id=$id")->fetch(PDO::FETCH_ASSOC);$g=$db->query("SELECT * FROM {$c->dbprefix}tz_portfolio_plus_addon_data WHERE content_id=$id AND element='gallery'")->fetch(PDO::FETCH_ASSOC);$files=[];
 foreach([$source,$target] as $dir)if(is_dir($root.$dir))foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root.$dir,FilesystemIterator::SKIP_DOTS)) as $f)if($f->isFile())$files[substr($f->getPathname(),strlen($root))]=hash_file('sha256',$f->getPathname());ksort($files);echo json_encode(['article'=>$a,'gallery'=>$g,'files'=>$files],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
}elseif($action==='cleanup'){$db->exec("DELETE FROM {$c->dbprefix}tz_portfolio_plus_addon_data WHERE content_id=$id");$db->exec("DELETE FROM {$c->dbprefix}tz_portfolio_plus_content WHERE id=$id");clearFolder($root.$source);clearFolder($root.$target);}else throw new RuntimeException('Unknown fixture action');
