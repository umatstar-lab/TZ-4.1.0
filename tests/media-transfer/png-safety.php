<?php
define('_JEXEC',1);require '/var/www/html/administrator/components/com_tz_media_transfer/src/Service/Adapters.php';require '/var/www/html/administrator/components/com_tz_media_transfer/src/Service/Files.php';
use Umatstar\Component\TzMediaTransfer\Administrator\Service\Files;
function check($ok,$what){if(!$ok)throw new RuntimeException($what);echo 'PASS '.$what.PHP_EOL;}
function reject($call,$what){$blocked=false;try{$call();}catch(RuntimeException $e){$blocked=true;}check($blocked,$what);}
function chunk($type,$data){return pack('N',strlen($data)).$type.$data.pack('H*',hash('crc32b',$type.$data));}
function png($pixels,$text=''){return "\x89PNG\r\n\x1a\n".chunk('IHDR',pack('NNCCCCC',16,1,8,2,0,0,0)).($text!==''?chunk('tEXt',"Comment\0".$text):'').chunk('IDAT',gzcompress("\0".$pixels,0)).chunk('IEND','');}
function clean($p){if(is_file($p)){unlink($p);return;}foreach(new DirectoryIterator($p) as $f)if(!$f->isDot())clean($f->getPathname());rmdir($p);}
$root=sys_get_temp_dir().'/tzmt-png-'.bin2hex(random_bytes(8));mkdir($root,0700);$folder='images/com_tz_portfolio/grid_gallery/26';mkdir($root.'/'.$folder,0755,true);$f=new Files($root);
try{
 $pixels=str_pad('<?=t)'."\x13\x00\xFF",48,'A');$valid=png($pixels);$path=$folder.'/008.png';file_put_contents($root.'/'.$path,$valid);check(str_contains($valid,'<?='),'deterministic valid PNG contains accidental marker inside stored IDAT');
 $f->assertImage($path);$f->atomicCopy($path,'images/com_tz_portfolio/gallery/26/008.png',$f->hash($path));check(hash_file('sha256',$root.'/'.$path)===hash_file('sha256',$root.'/images/com_tz_portfolio/gallery/26/008.png'),'binary IDAT coincidence accepted and copied without changing image bytes');
 file_put_contents($root.'/'.$path,png(str_repeat('A',48),'<?=t) not valid PHP'));$f->assertImage($path);check(true,'non-code marker in textual metadata accepted');
 file_put_contents($root.'/'.$path,$valid.'<?php echo "test"; ?>');reject(fn()=>$f->assertImage($path),'syntactically valid PHP trailer rejected');
 file_put_contents($root.'/'.$path,png(str_repeat('A',48),'<?= "test" ?>'));reject(fn()=>$f->assertImage($path),'syntactically valid PHP in metadata rejected without execution');
 $bad=$valid;$bad[20]=chr(ord($bad[20])^1);file_put_contents($root.'/'.$path,$bad);reject(fn()=>$f->assertImage($path),'bad PNG CRC rejected');
 file_put_contents($root.'/'.$path,$valid.'trailing junk');reject(fn()=>$f->assertImage($path),'extra bytes after IEND rejected');
 $bad="\x89PNG\r\n\x1a\n".chunk('IHDR',pack('NNCCCCC',16,1,8,2,0,0,0)).chunk('IDAT',gzcompress("\0".str_repeat('A',47),0)).chunk('IEND','');file_put_contents($root.'/'.$path,$bad);reject(fn()=>$f->assertImage($path),'wrong decompressed scanline size rejected');
 if(isset($argv[1])){if(!copy($argv[1],$root.'/'.$path))throw new RuntimeException('Cannot read original test PNG');$f->assertImage($path);$f->atomicCopy($path,'images/com_tz_portfolio/gallery/26/original.png',$f->hash($path));check(hash_file('sha256',$root.'/'.$path)===hash_file('sha256',$root.'/images/com_tz_portfolio/gallery/26/original.png'),'original user PNG accepted unchanged, including Adam7 interlace');}
}finally{clean($root);}
