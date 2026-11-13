<?php
// Isolated directory only; this test never writes to the site's real galleries.
define('_JEXEC',1);
$source=$argv[1]??'/var/www/html/administrator/components/com_tz_media_transfer/src/Service';
require $source.'/Adapters.php';require $source.'/Files.php';
use Umatstar\Component\TzMediaTransfer\Administrator\Service\Files;
function check($ok,$label){if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
function rejected($call,$label){$blocked=false;try{$call();}catch(RuntimeException $e){$blocked=true;}check($blocked,$label);}
function cleanup($p){if(is_link($p)||is_file($p)){unlink($p);return;}foreach(new DirectoryIterator($p) as $f){if(!$f->isDot())cleanup($f->getPathname());}rmdir($p);}
$root=sys_get_temp_dir().'/tzmt-security-'.bin2hex(random_bytes(8));mkdir($root,0700);$f=new Files($root);
$dir='images/com_tz_portfolio/flipbook_gallery/12';$target='images/com_tz_portfolio/gallery/12';mkdir($root.'/'.$dir,0755,true);mkdir($root.'/'.$target,0755,true);
try{
 foreach(['shell.php','shell.php.jpg','shell.php;.jpg','shell.phtml%00.jpg','shell.php .jpg','shell.pHp8.JPEG','shell.phtml.png','shell.phar.gif','payload.svg','payload.html','.htaccess','file.jpg/../x.php',"bad\0.jpg",'file.jpg:payload.php',str_repeat('a',250).'.jpg'] as $name)rejected(fn()=>Files::filename($name),'unsafe filename '.str_replace("\0",'[NUL]',$name));
 foreach(['configuration.php','images/../../configuration.php','/etc/passwd','php://filter/resource=configuration.php','https://example.test/a.jpg','administrator/components/com_tz_media_transfer/storage/evil.php'] as $path)rejected(fn()=>$f->path($path),'arbitrary path rejected');
 $im=imagecreatetruecolor(20,12);imagejpeg($im,$root.'/'.$dir.'/good.jpg');imagedestroy($im);$good=file_get_contents($root.'/'.$dir.'/good.jpg');
 $f->assertImage($dir.'/good.jpg');$f->atomicCopy($dir.'/good.jpg',$target.'/good.jpg',hash('sha256',$good));check(file_get_contents($root.'/'.$target.'/good.jpg')===$good,'legitimate JPEG copied byte-for-byte');
 file_put_contents($root.'/'.$dir.'/fake.jpg','<?php echo "TZMT_SHELL_CANARY"; ?>');rejected(fn()=>$f->assertImage($dir.'/fake.jpg'),'PHP text disguised as JPEG rejected');
 file_put_contents($root.'/'.$dir.'/polyglot.jpg',$good.'<?php echo "TZMT_SHELL_CANARY"; ?>');rejected(fn()=>$f->assertImage($dir.'/polyglot.jpg'),'valid JPEG with PHP trailer rejected');
 file_put_contents($root.'/'.$dir.'/echo.jpg',$good.'<?= "TZMT_SHELL_CANARY" ?>');rejected(fn()=>$f->assertImage($dir.'/echo.jpg'),'PHP echo tag rejected');
 file_put_contents($root.'/'.$dir.'/boundary.jpg',$good.str_repeat('A',65534-strlen($good)).'<?php echo "TZMT_SHELL_CANARY"; ?>');rejected(fn()=>$f->assertImage($dir.'/boundary.jpg'),'PHP marker crossing read-buffer boundary rejected');
 file_put_contents($root.'/'.$dir.'/xmp.jpg',$good.'<?xpacket begin="test"?>');$f->assertImage($dir.'/xmp.jpg');check(true,'ordinary XMP metadata retained');
 $before=file_get_contents($root.'/'.$target.'/good.jpg');rejected(fn()=>$f->atomicCopy($dir.'/fake.jpg',$target.'/good.jpg',$f->hash($dir.'/fake.jpg')),'unsafe bytes cannot replace existing public image');check(file_get_contents($root.'/'.$target.'/good.jpg')===$before,'destination unchanged on validation failure');
 mkdir($root.'/private');file_put_contents($root.'/private/secret.jpg',$good);symlink($root.'/private',$root.'/images/com_tz_portfolio/flipbook_gallery/13');rejected(fn()=>$f->resolve('flipbook_gallery',13,'secret.jpg'),'intermediate symlink inside site rejected');
 symlink(sys_get_temp_dir(),$root.'/images/com_tz_portfolio/flipbook_gallery/14');rejected(fn()=>$f->path('images/com_tz_portfolio/flipbook_gallery/14/file.jpg'),'external intermediate symlink rejected');
 symlink($root.'/private/secret.jpg',$root.'/'.$dir.'/linked.jpg');rejected(fn()=>$f->resolve('flipbook_gallery',12,'linked.jpg'),'final image symlink rejected');
 $backup=$f->backup($dir.'/fake.jpg');check((fileperms($f->path($backup))&0777)===0600,'protected backup permissions 0600');
 check(str_starts_with(file_get_contents($f->path($backup)),'<?php http_response_code(404); exit; __halt_compiler();'),'backup halts execution before stored PHP payload');
 rejected(fn()=>$f->atomicCopy($backup,$target.'/restored.jpg',$f->hash($backup)),'unsafe legacy backup cannot restore executable PHP payload into public image');
 check(!file_exists($root.'/'.$target.'/restored.jpg'),'rejected restore leaves no public output');
}finally{cleanup($root);}
