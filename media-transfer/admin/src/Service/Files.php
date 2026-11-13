<?php
namespace Umatstar\Component\TzMediaTransfer\Administrator\Service;
defined('_JEXEC') or die;
final class FileProblem extends \RuntimeException {}
final class Files {
 private const BACKUP_PREFIX='<?php http_response_code(404); exit; __halt_compiler();' . "\n";
 private array $unclaimed=[];
 private function isBackup(string $path): bool {return preg_match('~^administrator/components/com_tz_media_transfer/storage/[a-f0-9]{48}\.php$~D',$path)===1;}
 private function payload(string $path,bool $backup) {
  $stream=@fopen($path,'rb');if(!$stream)throw new FileProblem('Не удалось прочитать файл.');
  if($backup&&fread($stream,strlen(self::BACKUP_PREFIX))!==self::BACKUP_PREFIX){fclose($stream);throw new FileProblem('Повреждён формат резервной копии.');}return $stream;
 }
 private function payloadHash(string $path,bool $backup): string {$stream=$this->payload($path,$backup);try{$h=hash_init('sha256');hash_update_stream($h,$stream);return hash_final($h);}finally{fclose($stream);}}

 public function __construct(private string $root) {$this->root=rtrim((string)realpath($root),DIRECTORY_SEPARATOR);if(!$this->root)throw new FileProblem('Не найден каталог сайта.');}
 public static function filename(string $name): void {
  if($name==='' || $name==='.' || $name==='..' || strpbrk($name,"/\\\0")!==false || preg_match('/[\x00-\x1f\x7f:]/',$name) || strlen($name)>240 || str_starts_with($name,'.') || preg_match('/\.(?:php[0-9]*|phtml|pht|phar|cgi|pl|py|sh|shtml|asp|aspx|jsp)(?:[^a-z0-9]|$)/i',$name) || !in_array(strtolower(pathinfo($name,PATHINFO_EXTENSION)),['jpg','jpeg','png','gif','webp','avif','bmp','tif','tiff'],true))throw new FileProblem('Небезопасное или неподдерживаемое имя изображения.');
 }
 /** Only known image trees and protected journal backups belong to this service. */
 public function path(string $relative): string {
  $image='~^images/(?:com_tz_portfolio|tz_portfolio|tz_portfolio_plus)/(?:gallery|flipbook_gallery|grid_gallery)/[1-9][0-9]*(?:/resize)?(?:/[^/]+)?$~D';
  $backup='~^administrator/components/com_tz_media_transfer/storage(?:/(?:[a-f0-9]{48}\.php|\.htaccess|index\.html))?$~D';
  if(str_contains($relative,"\0") || str_contains($relative,'\\') || preg_match('/[\x00-\x1f\x7f]/',$relative)
   || in_array('..',explode('/',$relative),true) || (!preg_match($image,$relative)&&!preg_match($backup,$relative)))throw new FileProblem('Недопустимый путь файла.');
  $path=$this->root;
  foreach(explode('/',$relative) as $part){
   $path.='/'.$part;clearstatcache(true,$path);
   if(is_link($path))throw new FileProblem('Символические ссылки не поддерживаются.');
   if(file_exists($path)){
    $real=realpath($path);
    if(!$real || !str_starts_with($real,$this->root.DIRECTORY_SEPARATOR))throw new FileProblem('Файл находится за пределами сайта.');
   }
  }
  return $path;
 }
 /** Represent binary fragments without invalid UTF-8 or terminal control characters. */
 private function diagnosticBytes(string $value): string {
  $out='';foreach(str_split($value) as $byte){$n=ord($byte);$out.=$byte==='\\'?'\\\\':($n>=32&&$n<=126?$byte:sprintf('\\x%02X',$n));}return $out;
 }
 private function markerDiagnostic($stream,string $path,int $offset,string $match): string {
  $marker=str_starts_with($match,'<?=')?'<?=':substr($match,0,5);
  $fragment='';$before='';$limit=512;
  if(fseek($stream,max(0,$offset-32))===0){$length=min(32,$offset);if($length>0)$before=(string)fread($stream,$length);$fragment=(string)fread($stream,$limit+1);}
  $close=strpos($fragment,'?>');$truncated=false;
  if($close!==false&&$close+2<=$limit)$fragment=substr($fragment,0,$close+2);
  else{$truncated=strlen($fragment)>$limit;$fragment=substr($fragment,0,$limit);}
  return "Обнаружен маркер, похожий на PHP-вставку.\nФайл: ".$path
   ."\nМаркер: ".$this->diagnosticBytes($marker)
   ."\nБайты маркера (HEX): ".strtoupper(implode(' ',str_split(bin2hex($marker),2)))
   ."\nСмещение в данных изображения: ".$offset." байт (отсчёт с 0)."
   ."\nБайты перед маркером: ".$this->diagnosticBytes($before)
   ."\nЗначение / фрагмент вставки: ".$this->diagnosticBytes($fragment)
   .($truncated?"\nФрагмент сокращён до ".$limit." байт.":'')
   .(($close!==false&&$close+2<=$limit)?"\nЗакрывающий маркер ?> найден на смещении ".($offset+$close).".":"\nЗакрывающий маркер ?> в показанном фрагменте не найден.")
   ."\nСовпадение маркера может быть случайным в бинарных данных и само по себе не подтверждает наличие вредоносного кода.";
 }
 /** Tokenize candidates without evaluating PHP; random compressed bytes are not code. */
 private function isPhpCode($stream,int $offset): bool {
  if(!function_exists('token_get_all'))throw new FileProblem('Для проверки подозрительного фрагмента требуется расширение PHP Tokenizer.');
  $resume=ftell($stream);
  try{
   if(fseek($stream,$offset)!==0)throw new FileProblem('Не удалось прочитать подозрительный фрагмент.');
   $candidate=(string)fread($stream,65536);$tokens=token_get_all($candidate);
   if(!$tokens||!is_array($tokens[0])||!in_array($tokens[0][0],[T_OPEN_TAG,T_OPEN_TAG_WITH_ECHO],true))return false;
   $length=0;$closed=false;
   foreach($tokens as $token){$length+=strlen(is_array($token)?$token[1]:$token);if(is_array($token)&&$token[0]===T_CLOSE_TAG){$closed=true;break;}}
   $code=$closed?substr($candidate,0,$length):$candidate;
   try{token_get_all($code,TOKEN_PARSE);return true;}
   catch(\ParseError $error){return !$closed&&strlen($candidate)===65536&&preg_match('/^[\x09\x0a\x0d\x20-\x7e]+$/D',$candidate)===1;}
  }finally{fseek($stream,$resume);}
 }
 /** Validate the PNG container and the complete bounded zlib pixel stream. */
 private function validatePng($stream,string $path): void {
  $fail=static function(string $why)use($path){throw new FileProblem('Некорректный PNG: '.$why."\nФайл: ".$path);};
  rewind($stream);if(fread($stream,8)!=="\x89PNG\r\n\x1a\n")$fail('сигнатура');
  $header=false;$seenData=false;$dataClosed=false;$palette=false;$inflater=null;$decoded=0;$compressed=0;$expected=0;$rows=[];$row=0;$nextFilter=0;$pass=0;
  while(!feof($stream)){
   $raw=fread($stream,8);if(strlen($raw)!==8)$fail('оборван заголовок блока');
   $length=unpack('N',substr($raw,0,4))[1];$type=substr($raw,4,4);
   if(!preg_match('/^[A-Za-z]{4}$/D',$type)||$length>0x7fffffff)$fail('тип или длина блока');
   if(!$header&&($type!=='IHDR'||$length!==13))$fail('нет начального IHDR');
   if($header&&$type==='IHDR')$fail('повторный IHDR');
   if(ctype_upper($type[0])&&!in_array($type,['IHDR','PLTE','IDAT','IEND'],true))$fail('неизвестный критический блок');
   $hash=hash_init('crc32b');hash_update($hash,$type);$remaining=$length;$payload='';
   if($type==='IDAT'){
    if($dataClosed||(!$palette&&($color??0)===3))$fail('порядок IDAT/PLTE');$seenData=true;
    if($inflater===null)$inflater=inflate_init(ZLIB_ENCODING_DEFLATE);
   }elseif($seenData)$dataClosed=true;
   while($remaining>0){
    $part=fread($stream,min(8192,$remaining));if($part===false||$part==='')$fail('оборваны данные блока');
    $remaining-=strlen($part);hash_update($hash,$part);
    if($type==='IHDR')$payload.=$part;
    if($type==='IDAT'){
     if(inflate_get_status($inflater)===ZLIB_STREAM_END)$fail('лишние данные после zlib-потока');
     $out=@inflate_add($inflater,$part,ZLIB_SYNC_FLUSH);if($out===false)$fail('сжатый поток IDAT повреждён');
     $compressed+=strlen($part);$end=$decoded+strlen($out);if($end>$expected)$fail('размер распакованных данных');
     while($pass<count($rows)&&$nextFilter<$end){
      if($nextFilter>=$decoded&&ord($out[$nextFilter-$decoded])>4)$fail('фильтр строки');
      $nextFilter+=$rows[$pass][0];$row++;if($row===$rows[$pass][1]){$pass++;$row=0;}
     }
     $decoded=$end;
    }
   }
   $crc=fread($stream,4);if(strlen($crc)!==4||!hash_equals(hash_final($hash),bin2hex($crc)))$fail('контрольная сумма блока '.$type);
   if($type==='IHDR'){
    $h=unpack('Nwidth/Nheight/Cdepth/Ccolor/Ccompression/Cfilter/Cinterlace',$payload);extract($h,EXTR_SKIP);
    $depths=[0=>[1,2,4,8,16],2=>[8,16],3=>[1,2,4,8],4=>[8,16],6=>[8,16]];
    if(!$width||!$height||!isset($depths[$color])||!in_array($depth,$depths[$color],true)||$compression!==0||$filter!==0||!in_array($interlace,[0,1],true))$fail('параметры IHDR');
    $channels=[0=>1,2=>3,3=>1,4=>2,6=>4][$color];
    $passes=$interlace?[[0,0,8,8],[4,0,8,8],[0,4,4,8],[2,0,4,4],[0,2,2,4],[1,0,2,2],[0,1,1,2]]:[[0,0,1,1]];
    foreach($passes as [$x,$y,$dx,$dy]){
     $pw=max(0,intdiv($width-$x+$dx-1,$dx));$ph=max(0,intdiv($height-$y+$dy-1,$dy));if(!$pw||!$ph)continue;
     $stride=intdiv($pw*$channels*$depth+7,8)+1;$rows[]=[$stride,$ph];$expected+=$stride*$ph;
    }
    if($expected>536870912)$fail('размер изображения превышает предел проверки 512 МиБ');$header=true;
   }
   if($type==='PLTE'){if($seenData||$palette||$length===0||$length>768||$length%3!==0)$fail('палитра');$palette=true;}
   if($type==='IEND'){
    if($length!==0||!$seenData||$decoded!==$expected||$pass!==count($rows)||inflate_get_status($inflater)!==ZLIB_STREAM_END||inflate_get_read_len($inflater)!==$compressed)$fail('незавершённые данные изображения');
    if(fread($stream,1)!=='')$fail('данные после IEND');return;
   }
  }
  $fail('не найден IEND');
 }
 private function validateImageFile(string $path,string $name,?string $diagnosticPath=null): void {
  $diagnosticPath??=$path;
  self::filename($name);
  $types=['jpg'=>IMAGETYPE_JPEG,'jpeg'=>IMAGETYPE_JPEG,'png'=>IMAGETYPE_PNG,'gif'=>IMAGETYPE_GIF,'webp'=>IMAGETYPE_WEBP,'avif'=>IMAGETYPE_AVIF,'bmp'=>IMAGETYPE_BMP,'tif'=>IMAGETYPE_TIFF_II,'tiff'=>IMAGETYPE_TIFF_II];
  $info=@getimagesize($path);$extension=strtolower(pathinfo($name,PATHINFO_EXTENSION));
  if(!$info || ($info[2]!==$types[$extension] && !(in_array($extension,['tif','tiff'],true)&&$info[2]===IMAGETYPE_TIFF_MM)))throw new FileProblem('Содержимое файла не соответствует изображению. Файл: '.$diagnosticPath);
  $stream=@fopen($path,'rb');if(!$stream)throw new FileProblem('Не удалось проверить изображение.');
  try{
   $tail='';$position=0;while(!feof($stream)){
    $chunk=fread($stream,65536);if($chunk===false)throw new FileProblem('Не удалось прочитать изображение.');
    $data=$tail.$chunk;$base=$position-strlen($tail);$position+=strlen($chunk);
    if(preg_match_all('/<\?(?:php(?:\s|\/|$)|=)/i',$data,$matches,PREG_OFFSET_CAPTURE))foreach($matches[0] as [$match,$index]){
     $offset=$base+$index;if($this->isPhpCode($stream,$offset))throw new FileProblem($this->markerDiagnostic($stream,$diagnosticPath,$offset,$match));
    }
    $tail=substr($data,-16);
   }
   if($extension==='png')$this->validatePng($stream,$diagnosticPath);
  }finally{fclose($stream);}
 }
 public function assertImage(string $relative): void {
  if(!str_starts_with($relative,'images/'))throw new FileProblem('Ожидается оригинал изображения.');
  $this->validateImageFile($this->path($relative),basename($relative));
 }
 public function folder(string $key,int $id,?string $root=null): string {
  $d=Adapters::definition($key);if($root!==null&&!in_array($root,$d['roots'],true))throw new FileProblem('Недопустимый каталог изображения.');if($id<1)throw new FileProblem('Неверный материал.');
  return 'images/'.($root??$d['write']).'/'.$d['directory'].'/'.$id;
 }
 public function resolve(string $key,int $id,string $name): ?string {
  self::filename($name);$d=Adapters::definition($key);
  foreach($d['roots'] as $root){$p=$this->folder($key,$id,$root).'/'.$name;$absolute=$this->path($p);clearstatcache(true,$absolute);if(is_file($absolute)&&is_readable($absolute))return $p;}return null;
 }
 public function displayPath(string $relative): string {return $this->root.'/'.$relative;}
 public function destination(string $key,int $id,string $name): string {self::filename($name);return $this->folder($key,$id).'/'.$name;}
 public function hash(string $relative): string {
  $p=$this->path($relative);if(!is_file($p)||!is_readable($p))throw new FileProblem('Изображение отсутствует или недоступно: '.basename($relative));
  $h=$this->payloadHash($p,$this->isBackup($relative));if(!$h)throw new FileProblem('Не удалось проверить изображение.');return $h;
 }
 public function exists(string $relative): bool {return is_file($this->path($relative));}
 public function unique(string $key,int $id,string $name,array $entries): string {
  self::filename($name);$stem=pathinfo($name,PATHINFO_FILENAME);$ext=pathinfo($name,PATHINFO_EXTENSION);
  $stem=function_exists('mb_strcut')?mb_strcut($stem,0,170,'UTF-8'):substr($stem,0,170);
  $taken=array_column($entries,'name');
  for($i=1;$i<=10000;$i++){$n=$stem.'__copy_'.$i.'.'.$ext;if(!in_array($n,$taken,true)&&!$this->resolve($key,$id,$n))return $n;}
  throw new FileProblem('Не удалось подобрать свободное имя изображения.');
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
  if(!is_dir($folder)&&!@mkdir($folder,0700,true)&&!is_dir($folder))throw new FileProblem('Не удалось создать каталог резервных копий.');
  foreach(['.htaccess'=>"Require all denied\nDeny from all\n",'index.html'=>''] as $n=>$body){$guard=$this->path($base.'/'.$n);if(!file_exists($guard)&&@file_put_contents($guard,$body)===false)throw new FileProblem('Не удалось защитить каталог резервных копий.');}
  $hash=$expected??$this->hash($relative);$name=$base.'/'.bin2hex(random_bytes(24)).'.php';$this->atomicCopy($relative,$name,$hash);@chmod($this->path($name),0600);$this->unclaimed[$name]=$hash;return $name;
 }
 public function claimBackups(): void {$this->unclaimed=[];}
 public function discardUnclaimed(): void {foreach($this->unclaimed as $name=>$hash){try{$this->remove($name,$hash);}catch(\Throwable $e){/* Preserve unexpected files for inspection. */}}$this->unclaimed=[];}
 public function atomicCopy(string $source,string $target,string $hash): void {
  $src=$this->path($source);$dst=$this->path($target);$folder=dirname($dst);
  if(!is_dir($folder)&&!@mkdir($folder,0755,true)&&!is_dir($folder))throw new FileProblem('Не удалось создать папку назначения.');
  $dst=$this->path($target);$temp=$dst.'.tz-transfer-'.bin2hex(random_bytes(10)).'.tmp';$out=null;$in=null;
  try{
   $mask=umask(0077);try{$out=@fopen($temp,'xb');}finally{umask($mask);}if(!$out)throw new FileProblem('Нет прав записи в папку назначения.');if(!@chmod($temp,0600))throw new FileProblem('Не удалось защитить временный файл.');$in=$this->payload($src,$this->isBackup($source));if($this->isBackup($target)&&fwrite($out,self::BACKUP_PREFIX)!==strlen(self::BACKUP_PREFIX))throw new FileProblem('Не удалось записать резервную копию.');
   if(@stream_copy_to_stream($in,$out)===false)throw new FileProblem('Ошибка копирования файла.');
   fclose($in);$in=null;fclose($out);$out=null;
   if($this->payloadHash($temp,$this->isBackup($target))!==$hash)throw new FileProblem('Контрольная сумма копии не совпала. Исходник сохранён.');
   if(!$this->isBackup($target))$this->validateImageFile($temp,basename($target),$src);
   $dst=$this->path($target);if(!@chmod($temp,$this->isBackup($target)?0600:0644))throw new FileProblem('Не удалось установить права файла.');if(!@rename($temp,$dst))throw new FileProblem('Не удалось записать готовую копию.');
  }finally{if(is_resource($in))fclose($in);if(is_resource($out))fclose($out);if(is_file($temp))@unlink($temp);}
 }
 public function remove(string $path,string $expected): void {
  if(!$this->exists($path))return;
  if($this->hash($path)!==$expected)throw new FileProblem('Файл изменился после копирования: '.basename($path));
  if(!@unlink($this->path($path)))throw new FileProblem('Не удалось удалить файл: '.basename($path));
 }
 public function info(?string $relative,string $caption=''): array {
  if(!$relative)return ['name'=>'','caption'=>$caption,'bytes'=>0,'url'=>'','modified'=>''];
  $p=$this->path($relative);
  return ['name'=>basename($relative),'caption'=>$caption,'bytes'=>is_file($p)?filesize($p):0,'modified'=>is_file($p)?date('d.m.Y H:i',filemtime($p)):'','url'=>is_file($p)?\Joomla\CMS\Uri\Uri::root().implode('/',array_map('rawurlencode',explode('/',$relative))):''];
 }
}
