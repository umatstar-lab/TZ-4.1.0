<?php
// Isolated filesystem checks: no live images or database writes.
define('_JEXEC',1);
$service='/var/www/html/administrator/components/com_tz_media_transfer/src/Service/';
require $service.'Files.php';require $service.'Adapters.php';
use Umatstar\Component\TzMediaTransfer\Administrator\Service\Files;
$root=sys_get_temp_dir().'/tzmt-paths-'.bin2hex(random_bytes(6));mkdir($root);
function check($ok,$message){if(!$ok)throw new RuntimeException($message);echo 'PASS '.$message.PHP_EOL;}
function cleanup($p){foreach(new DirectoryIterator($p) as $f){if($f->isDot())continue;$f->isDir()?cleanup($f->getPathname()):unlink($f->getPathname());}rmdir($p);}
try {
 $files=new Files($root);
 foreach(['flipbook_gallery','gallery'] as $key){
  $dir=$key==='gallery'?'gallery':'flipbook_gallery';
  foreach(['tz_portfolio_plus','tz_portfolio','com_tz_portfolio'] as $directory){
   $relative='images/'.$directory.'/'.$dir.'/12/photo.jpg';mkdir(dirname($root.'/'.$relative),0755,true);file_put_contents($root.'/'.$relative,$directory);
   check($files->resolve($key,12,'photo.jpg')===$relative,$key.' reads '.$directory.' with per-file precedence');
  }
  check($files->destination($key,12,'new.jpg')==='images/com_tz_portfolio/'.$dir.'/12/new.jpg',$key.' writes outside removed Portfolio+ storage');
  unlink($root.'/images/com_tz_portfolio/'.$dir.'/12/photo.jpg');
  check($files->resolve($key,12,'photo.jpg')==='images/tz_portfolio/'.$dir.'/12/photo.jpg','empty current folder does not mask intermediate original');
 }
}finally{cleanup($root);}
