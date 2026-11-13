<?php
namespace Umatstar\Component\TzMediaTransfer\Administrator\Service;
defined('_JEXEC') or die;
final class Files {
 private const BACKUP_PREFIX='<?php http_response_code(404); exit; __halt_compiler();' . "\n";
 private array $unclaimed=[];
 private function isBackup(string $path): bool {return preg_match('~^administrator/components/com_tz_media_transfer/storage/[a-f0-9]{48}\.php$~D',$path)===1;}
 private function payload(string $path,bool $backup) {
  $stream=@fopen($path,'rb');if(!$stream)throw new \RuntimeException('Не удалось прочитать файл.');
  if($backup&&fread($stream,strlen(self::BACKUP_PREFIX))!==self::BACKUP_PREFIX){fclose($stream);throw new \RuntimeException('Повреждён формат резервной копии.');}return $stream;
 }
 private function payloadHash(string $path,bool $backup): string {$stream=$this->payload($path,$backup);try{$h=hash_init('sha256');hash_update_stream($h,$stream);return hash_final($h);}finally{fclose($stream);}}

 public function __construct(private string $root) {$this->root=rtrim((string)realpath($root),DIRECTORY_SEPARATOR);if(!$this->root)throw new \RuntimeException('Не найден каталог сайта.');}
 public static function filename(string $name): void {
  if($name==='' || $name==='.' || $name==='..' || strpbrk($name,"/\\\0")!==false || preg_match('/[\x00-\x1f\x7f:]/',$name) || strlen($name)>240 || str_starts_with($name,'.') || preg_match('/\.(?:php[0-9]*|phtml|pht|phar|cgi|pl|py|sh|shtml|asp|aspx|jsp)(?:[^a-z0-9]|$)/i',$name) || !in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),['jpg','jpeg','png','gif','webp','avif','bmp','tif','tiff'],true))throw new \RuntimeException('Небезопасное или неподдерживаемое имя изображения.');
 }
 /** Only known image trees and protected journal backups belong to this service. */
 public function path(string $relative): string {
  $image='~^images/(?:com_tz_portfolio|tz_portfolio|tz_portfolio_plus)/(?:gallery|flipbook_gallery|grid_gallery)/[1-9][0-9]*(?:/resize)?(?:/[^/]+)?$~D';
  $backup='~^administrator/components/com_tz_media_transfer/storage(?:/(?:[a-f0-9]{48}\.php|\.htaccess|index\.html))?$~D';
  if(str_contains($relative,"\0") || str_contains($relative,'\\') || preg_match('/[\x00-\x1f\x7f]/',$relative)
   || in_array('..',explode('/',$relative),true) || (!preg_match($image,$relative)&&!preg_match($backup,$relative)))throw new \RuntimeException('Недопустимый путь файла.');
  $path=$this->root;
  foreach(explode('/',$relative) as $part){
   $path.='/'.$part;clearstatcache(true,$path);
   if(is_link($path))throw new \RuntimeException('Символические ссылки не поддерживаются.');
   if(file_exists($path)){
    $real=realpath($path);
    if(!$real || !str_starts_with($real,$this->root.DIRECTORY_SEPARATOR))throw new \RuntimeException('Файл находится за пределами сайта.');
   }
  }
  return $path;
 }
 private function validateImageFile(string $path,string $name): void {
  self::filename($name);
  $types=['jpg'=>IMAGETYPE_JPEG,'jpeg'=>IMAGETYPE_JPEG,'png'=>IMAGETYPE_PNG,'gif'=>IMAGETYPE_GIF,'webp'=>IMAGETYPE_WEBP,'avif'=>IMAGETYPE_AVIF,'bmp'=>IMAGETYPE_BMP,'tif'=>IMAGETYPE_TIFF_II,'tiff'=>IMAGETYPE_TIFF_II];
  $info=@getimagesize($path);$extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));
  if(!$info || ($info[2]!==$types[$extension] && !(in_array($extension,['tif','tiff'],true)&&$info[2]===IMAGETYPE_TIFF_MM)))throw new \RuntimeException('Содержимое файла не соответствует изображению: '.$name);
  $stream=@fopen($path,'rb');if(!$stream)throw new \RuntimeException('Не удалось проверить изображение.');
  try{
   $tail='';while(!feof($stream)){
    $chunk=fread($stream,65536);if($chunk===false)throw new \RuntimeException('Не удалось прочитать изображение.');
    $data=$tail.$chunk;
    if(preg_match('/<\?(?:php(?:\s|\/|$)|=)/i',$data))throw new \RuntimeException('В изображении обнаружена PHP-вставка: '.$name);
    $tail=substr($data,-16);
   }
  }finally{fclose($stream);}
 }
 public function assertImage(string $relative): void {
  if(!str_starts_with($relative,'images/'))throw new \RuntimeException('Ожидается оригинал изображения.');
  $this->validateImageFile($this->path($relative),basename($relative));
 }
 public function folder(string $key,int $id,?string $root=null): string {
  $d=Adapters::definition($key);if($root!==null&&!in_array($root,$d['roots'],true))throw new \RuntimeException('Недопустимый каталог изображения.');if($id<1)throw new \RuntimeException('Неверный материал.');
  return 'images/'.($root??$d['write']).'/'.$d['directory'].'/'.$id;
 }
 public function resolve(string $key,int $id,string $name): ?string {
  self::filename($name);$d=Adapters::definition($key);
  foreach($d['roots'] as $root){$p=$this->folder($key,$id,$root).'/'.$name;$absolute=$this->path($p);clearstatcache(true,$absolute);if(is_file($absolute)&&is_readable($absolute))return $p;}return null;
 }
 public function destination(string $key,int $id,string $name): string {self::filename($name);return $this->folder($key,$id).'/'.$name;}
 public function hash(string $relative): string {
  $p=$this->path($relative);if(!is_file($p)||!is_readable($p))throw new \RuntimeException('Изображение отсутствует или недоступно: '.basename($relative));
  $h=$this->payloadHash($p,$this->isBackup($relative));if(!$h)throw new \RuntimeException('Не удалось проверить изображение.');return $h;
 }
 public function exists(string $relative): bool {return is_file($this->path($relative));}
 public function unique(string $key,int $id,string $name,array $entries): string {
  self::filename($name);$stem=pathinfo($name,PATHINFO_FILENAME);$ext=pathinfo($name,PATHINFO_EXTENSION);
  $stem=function_exists('mb_strcut')?mb_strcut($stem,0,170,'UTF-8'):substr($stem,0,170);
  $taken=array_column($entries,'name');
  for($i=1;$i<=10000;$i++){$n=$stem.'__copy_'.$i.'.'.$ext;if(!in_array($n,$taken,true)&&!$this->resolve($key,$id,$n))return $n;}
  throw new \RuntimeException('Не удалось подобрать свободное имя изображения.');
 }
 public function derivatives(string $relative): array {
  $folder=dirname($relative).'/resize';$out=[];$path=$this->path($folder);if(!is_dir($path))return [];
  $stem=pathinfo($relative,PATHINFO_FILENAME);$extension=strtolower(pathinfo($relative,PATHINFO_EXTENSION));
  foreach(new \DirectoryIterator($path) as $entry){
   if(!$entry->isFile()||$entry->isLink())continue;$name=$entry->getFilename();
   if(str_starts_with($name,$stem.'_')&&strtolower($entry->getExtension())===$extension){self::filename($name);$out[]=$folder.'/'.$name;}
  }
  return $out;
 }
 public function backup(string $relative,?string $expected=null): string {
  $base='administrator/components/com_tz_media_transfer/storage';$folder=$this->path($base);
  if(!is_dir($folder)&&!mkdir($folder,0700,true)&&!is_dir($folder))throw new \RuntimeException('Не удалось создать каталог резервных копий.');
  foreach(['.htaccess'=>"Require all denied\nDeny from all\n",'index.html'=>''] as $n=>$body){$guard=$this->path($base.'/'.$n);if(!file_exists($guard)&&file_put_contents($guard,$body)===false)throw new \RuntimeException('Не удалось защитить каталог резервных копий.');}
  $hash=$expected??$this->hash($relative);$name=$base.'/'.bin2hex(random_bytes(24)).'.php';$this->atomicCopy($relative,$name,$hash);@chmod($this->path($name),0600);$this->unclaimed[$name]=$hash;return $name;
 }
 public function claimBackups(): void {$this->unclaimed=[];}
 public function discardUnclaimed(): void {foreach($this->unclaimed as $name=>$hash){try{$this->remove($name,$hash);}catch(\Throwable $e){/* Preserve unexpected files for inspection. */}}$this->unclaimed=[];}
 public function atomicCopy(string $source,string $target,string $hash): void {
  $src=$this->path($source);$dst=$this->path($target);$folder=dirname($dst);
  if(!is_dir($folder)&&!mkdir($folder,0755,true)&&!is_dir($folder))throw new \RuntimeException('Не удалось создать папку назначения.');
  $dst=$this->path($target);$temp=$dst.'.tz-transfer-'.bin2hex(random_bytes(10)).'.tmp';$out=null;$in=null;
  try{
   $mask=umask(0077);try{$out=@fopen($temp,'xb');}finally{umask($mask);}if(!$out)throw new \RuntimeException('Нет прав записи в папку назначения.');if(!chmod($temp,0600))throw new \RuntimeException('Не удалось защитить временный файл.');$in=$this->payload($src,$this->isBackup($source));if($this->isBackup($target)&&fwrite($out,self::BACKUP_PREFIX)!==strlen(self::BACKUP_PREFIX))throw new \RuntimeException('Не удалось записать резервную копию.');
   if(stream_copy_to_stream($in,$out)===false)throw new \RuntimeException('Ошибка копирования файла.');
   fclose($in);$in=null;fclose($out);$out=null;
   if($this->payloadHash($temp,$this->isBackup($target))!==$hash)throw new \RuntimeException('Контрольная сумма копии не совпала. Исходник сохранён.');
   if(!$this->isBackup($target))$this->validateImageFile($temp,basename($target));
   $dst=$this->path($target);if(!chmod($temp,$this->isBackup($target)?0600:0644))throw new \RuntimeException('Не удалось установить права файла.');if(!rename($temp,$dst))throw new \RuntimeException('Не удалось записать готовую копию.');
  }finally{if(is_resource($in))fclose($in);if(is_resource($out))fclose($out);if(is_file($temp))@unlink($temp);}
 }
 public function remove(string $path,string $expected): void {
  if(!$this->exists($path))return;
  if($this->hash($path)!==$expected)throw new \RuntimeException('Файл изменился после копирования: '.basename($path));
  if(!unlink($this->path($path)))throw new \RuntimeException('Не удалось удалить файл: '.basename($path));
 }
 public function info(?string $relative,string $caption=''): array {
  if(!$relative)return ['name'=>'','caption'=>$caption,'bytes'=>0,'url'=>'','modified'=>''];
  $p=$this->path($relative);
  return ['name'=>basename($relative),'caption'=>$caption,'bytes'=>is_file($p)?filesize($p):0,'modified'=>is_file($p)?date('d.m.Y H:i',filemtime($p)):'','url'=>is_file($p)?\Joomla\CMS\Uri\Uri::root().implode('/',array_map('rawurlencode',explode('/',$relative))):''];
 }
}
