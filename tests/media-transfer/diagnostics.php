<?php
define('_JEXEC',1);
require '/var/www/html/administrator/components/com_tz_media_transfer/src/Service/Adapters.php';
require '/var/www/html/administrator/components/com_tz_media_transfer/src/Service/Files.php';
use Umatstar\Component\TzMediaTransfer\Administrator\Service\Files;
function check($ok,$label){if(!$ok)throw new RuntimeException($label);echo 'PASS '.$label.PHP_EOL;}
function error($call){try{$call();}catch(RuntimeException $e){return $e->getMessage();}throw new RuntimeException('Expected rejection');}
function clean($p){if(!is_dir($p)){unlink($p);return;}foreach(new DirectoryIterator($p) as $f)if(!$f->isDot())clean($f->getPathname());rmdir($p);}
$root=sys_get_temp_dir().'/tzmt-diagnostics-'.bin2hex(random_bytes(8));mkdir($root,0700);$dir='images/com_tz_portfolio/grid_gallery/26';mkdir($root.'/'.$dir,0755,true);$files=new Files($root);
try{
 $image=imagecreatetruecolor(12,10);ob_start();imagepng($image);$png=ob_get_clean();imagedestroy($image);
 $path=$root.'/'.$dir.'/008.png';$php='<?php echo "diagnostic value"; ?>';file_put_contents($path,$png.$php);
 $message=error(fn()=>$files->assertImage($dir.'/008.png'));
 check(str_contains($message,'Файл: '.$path),'absolute original path included');
 check(str_contains($message,'Маркер: <?php')&&str_contains($message,'3C 3F 70 68 70'),'actual marker and hex bytes included');
 check(str_contains($message,'Смещение в данных изображения: '.strlen($png).' байт'),'byte offset is zero-based and exact');
 check(str_contains($message,'Значение / фрагмент вставки: '.$php),'entire short insertion included without interpreting its value');
 $offset=strlen($png)+strpos($php,'?>');check(str_contains($message,'?> найден на смещении '.$offset),'closing marker offset included');
 file_put_contents($path,$png.'<?='."\x00\xFF\x1B".'<script>window.tzmtDiagnosticXss=1</script>' );
 $message=error(fn()=>$files->assertImage($dir.'/008.png'));
 check(str_contains($message,'Маркер: <?=')&&str_contains($message,'3C 3F 3D'),'short echo marker identified');
 check(str_contains($message,'\\x00\\xFF\\x1B'),'binary and control bytes escaped exactly');
 check(str_contains($message,'не подтверждает наличие вредоносного кода'),'binary coincidence distinguished from confirmed malicious code');
 json_encode(['message'=>$message],JSON_THROW_ON_ERROR);check(true,'invalid UTF-8 bytes do not break JSON response');
 file_put_contents($path,$png.str_repeat('A',65534-strlen($png)).'<?php echo "boundary"; ?>');
 $message=error(fn()=>$files->assertImage($dir.'/008.png'));check(str_contains($message,'Смещение в данных изображения: 65534 байт'),'marker spanning read-buffer boundary has correct absolute offset');
 file_put_contents($path,$png.'<?php '.str_repeat('B',2000).' ?>');$message=error(fn()=>$files->assertImage($dir.'/008.png'));
 check(str_contains($message,'сокращён до 512 байт')&&strlen($message)<4000,'oversized insertion preview bounded and truncation stated');
 $destination='images/com_tz_portfolio/gallery/26/copied.png';
 $message=error(fn()=>$files->atomicCopy($dir.'/008.png',$destination,$files->hash($dir.'/008.png')));
 check(str_contains($message,'Файл: '.$path)&&!str_contains($message,'.tz-transfer-'),'copy validation reports source path rather than removed temporary path');
 check(!is_file($root.'/'.$destination),'rejected copy leaves no public output');
}finally{clean($root);}
