<?php
namespace Umatstar\Component\TzMediaTransfer\Administrator\Service;
defined('_JEXEC') or die;
use Joomla\Database\DatabaseInterface;
use Joomla\CMS\User\User;
final class TransferService {
 private Files $files;
 private array $available=[];
 private string $lock;
 public function __construct(private DatabaseInterface $db,private User $user,private string $root) {
  $this->files=new Files($root);$this->lock='tzmt-'.substr(hash('sha256',$root.$db->getPrefix()),0,40);
  $manifest=$root.'/administrator/components/com_tz_portfolio/tz_portfolio.xml';
  if(!is_file($manifest))throw new \RuntimeException('Установите TZ Portfolio 4.1 или новее.');
  $xml=simplexml_load_file($manifest);if(!$xml || version_compare((string)$xml->version,'4.1.0','<'))throw new \RuntimeException('Нужен TZ Portfolio версии 4.1 или новее.');
  foreach(Adapters::DEFINITIONS as $key=>$d){
   $rows=$this->rows('SELECT id,published FROM #__tz_portfolio_plus_extensions WHERE folder='.$this->q($d['folder']).' AND element='.$this->q($key));
   $provider=$root.'/components/com_tz_portfolio/add-ons/'.$d['folder'].'/'.$key.'/services/provider.php';
   if(count($rows)===1&&is_file($provider))$this->available[$key]=$d+['id'=>(int)$rows[0]['id'],'enabled'=>(bool)$rows[0]['published'],'writable'=>true];
   elseif($key==='grid_gallery')$this->available[$key]=$d+['id'=>0,'enabled'=>false,'writable'=>false];
  }
 }
 private function q($v): string {return $v===null?'NULL':$this->db->quote((string)$v);}
 private function rows(string $sql): array {$this->db->setQuery($sql);return $this->db->loadAssocList();}
 private function execute(string $sql): void {$this->db->setQuery($sql)->execute();}
 private function now(): string {return gmdate('Y-m-d H:i:s');}
 public function dispatch(string $action,array $p): array {
  if(!$this->user->id||!$this->user->authorise('core.manage','com_tz_media_transfer'))throw new \RuntimeException('Недостаточно прав доступа.',403);
  foreach(['source','target','mode','job','conflict','choice','file_error'] as $key)if(isset($p[$key])&&!is_string($p[$key]))throw new \RuntimeException('Неверный формат параметров.');
  foreach(['activate','all'] as $key)if(isset($p[$key])&&!is_bool($p[$key]))throw new \RuntimeException('Неверный формат флажка.');
  if($action==='inventory')return $this->inventory(true);
  if($action==='catalog')return $this->inventory(false);
  if($action==='history')return $this->history();
  if(!in_array($action,['start','step','resolve','skip','status','cancel','undo','undoStep','purge'],true))throw new \RuntimeException('Неизвестное действие.');
  $this->db->setQuery('SELECT GET_LOCK('.$this->q($this->lock).',0)');
  if((int)$this->db->loadResult()!==1)throw new \RuntimeException('Сейчас выполняется другой запрос переноса. Повторите через несколько секунд.');
  try {
   if($action==='start')return $this->start($p);
   $job=$this->job((string)($p['job']??''));
   if($action==='cancel')return $this->cancel($job);
   if($action==='purge')return $this->purge($job);
   foreach($job['state']['plans'] as $plan)if(!$this->canEdit((int)$plan['id'],$job['state']['mode']==='move'||in_array($action,['undo','undoStep'],true)))throw new \RuntimeException('Права доступа к материалам операции изменились.',403);
   $this->recoverPrepared($job['id']);$this->cleanup($job['id']);$job=$this->job($job['id']);
   switch($action){
    case 'step':return $this->step($job);
    case 'resolve':return $this->resolve($job,$p);
    case 'skip':return $this->skipFile($job,$p);
    case 'status':return $this->publicJob($job);
    case 'undo':$job['status']='rolling_back';unset($job['state']['error']);$job['state']['undo_total']=($job['state']['undo_count']??0)+(int)$this->db->setQuery('SELECT COUNT(*) FROM #__tz_media_transfer_changes WHERE job_id='.$this->q($job['id'])." AND status IN ('done','undoing')")->loadResult();$job['state']['conflict']=null;$job['state']['file_error']=null;$this->saveJob($job);return $this->publicJob($job);
    case 'undoStep':return $this->undoStep($job);
   }
  }finally{$this->db->setQuery('SELECT RELEASE_LOCK('.$this->q($this->lock).')')->loadResult();}
  throw new \RuntimeException('Неизвестное действие.');
 }
 private function cancel(array $job): array {
  if($job['status']==='rolling_back')throw new \RuntimeException('Дождитесь окончания отката.');
  if(in_array($job['status'],['running','conflict','file_error'],true)){$job['status']='cancelled';$job['state']['conflict']=null;$job['state']['file_error']=null;$this->saveJob($job);}
  return $this->publicJob($job);
 }
 private function canEdit(int $id,bool $move=false): bool {
  return $this->user->authorise('core.edit','com_tz_portfolio.article.'.$id)&&(!$move||$this->user->authorise('core.delete','com_tz_portfolio.article.'.$id));
 }
 private function pair(string $source,string $target): void {
  if($source===$target || !isset($this->available[$source],$this->available[$target]) || !$this->available[$target]['writable'])throw new \RuntimeException('Выберите два разных установленных дополнения Portfolio.');
 }
 private function bundle(int $id,bool $locked=false): array {
  $rows=$this->rows('SELECT id,title,state,media,attribs,type FROM #__tz_portfolio_plus_content WHERE id='.$id.($locked?' FOR UPDATE':''));
  if(count($rows)!==1)throw new \RuntimeException('Материал больше не существует.');
  $g=$this->rows("SELECT * FROM #__tz_portfolio_plus_addon_data WHERE element='gallery' AND content_id=".$id.' ORDER BY id'.($locked?' FOR UPDATE':''));
  if(count($g)>1)throw new \RuntimeException('У материала '.$id.' несколько записей Gallery. Сначала устраните дубликаты записей.');
  return ['article'=>$rows[0],'gallery'=>$g[0]??null];
 }
 private function fingerprint(array $bundle): string {unset($bundle['article']['title']);return hash('sha256',Adapters::encode($bundle));}
 private function update(string $table,array $values,string $where): void {
  $sets=[];foreach($values as $key=>$v){if(!preg_match('/^[a-z_]+$/D',$key))throw new \RuntimeException('Неверное поле записи.');$sets[]='`'.$key.'`='.$this->q($v);}
  if($sets)$this->execute('UPDATE '.$table.' SET '.implode(',',$sets).' WHERE '.$where);
 }
 private function insert(string $table,array $values): int {
  $keys=array_keys($values);foreach($keys as $k)if(!preg_match('/^[a-z_]+$/D',$k))throw new \RuntimeException('Неверное поле записи.');
  $this->execute('INSERT INTO '.$table.' (`'.implode('`,`',$keys).'`) VALUES ('.implode(',',array_map(fn($v)=>$this->q($v),array_values($values))).')');return (int)$this->db->insertid();
 }
 private function writeBundle(array $desired,array $current): void {
  $id=(int)$current['article']['id'];$updates=[];
  foreach(['media','attribs','type'] as $key)if($desired['article'][$key]!==$current['article'][$key])$updates[$key]=$desired['article'][$key];
  if($updates)$this->update('#__tz_portfolio_plus_content',$updates,'id='.$id);
  $old=$current['gallery'];$new=$desired['gallery'];
  if($old && !$new)$this->execute('DELETE FROM #__tz_portfolio_plus_addon_data WHERE id='.(int)$old['id']);
  elseif($new && !$old){
   unset($new['id']);
   // Legacy TZ tables use zero dates. Only relax zero-date checks for this connection.
   $mode=(string)$this->db->setQuery('SELECT @@SESSION.sql_mode')->loadResult();
   $modes=array_diff(explode(',',$mode),['NO_ZERO_DATE','NO_ZERO_IN_DATE']);$this->execute('SET SESSION sql_mode='.$this->q(implode(',',$modes)));
   try{$this->insert('#__tz_portfolio_plus_addon_data',$new);}finally{$this->execute('SET SESSION sql_mode='.$this->q($mode));}
  }elseif($new && $old && $new!==$old){unset($new['id']);$changed=[];foreach($new as $key=>$value)if(!array_key_exists($key,$old)||$value!==$old[$key])$changed[$key]=$value;if($changed)$this->update('#__tz_portfolio_plus_addon_data',$changed,'id='.(int)$old['id']);}
 }
 private function inventory(bool $scan): array {
  $out=[];
  foreach($this->available as $key=>$d)$out[$key]=['key'=>$key,'label'=>$d['label'],'enabled'=>$d['enabled'],'writable'=>$d['writable'],'images'=>$scan?0:null,'captions'=>$scan?0:null,'articles'=>[]];
  $materials=[];
  foreach($this->rows('SELECT id,title,state FROM #__tz_portfolio_plus_content WHERE state<>-2 ORDER BY title,id') as $a){
   $id=(int)$a['id'];if(!$this->canEdit($id))continue;
   $materials[]=['id'=>$id,'title'=>$a['title'],'state'=>(int)$a['state']];
   if(!$scan)continue;
   foreach($out as $key=>&$entry){
    $row=['id'=>$id,'title'=>$a['title'],'state'=>(int)$a['state'],'images'=>0,'captions'=>0];
    try{
     $dataset=Adapters::read($key,$this->bundle($id));
     foreach($dataset['entries'] as $item){
      if(!$this->files->resolve($key,$id,$item['name']))continue;
      $row['images']++;if(trim($item['caption'])!=='')$row['captions']++;
     }
     $entry['images']+=$row['images'];$entry['captions']+=$row['captions'];
    }catch(\RuntimeException $e){$row['error']=$e->getMessage();}
    $entry['articles'][]=$row;
   }unset($entry);
  }
  $trash=(int)$this->db->setQuery('SELECT COUNT(*) FROM #__tz_portfolio_plus_content WHERE state=-2')->loadResult();
  return ['adapters'=>array_values($out),'materials'=>$materials,'scanned'=>$scan,'trash_skipped'=>$trash];
 }
 private function start(array $p): array {
  foreach(['tz_portfolio_plus_content','tz_portfolio_plus_addon_data','tz_media_transfer_jobs','tz_media_transfer_changes'] as $table){$rows=$this->rows('SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME='.$this->q($this->db->getPrefix().$table));if(strtoupper($rows[0]['ENGINE']??'')!=='INNODB')throw new \RuntimeException('Для безопасного переноса требуются таблицы InnoDB.');}
  $source=(string)($p['source']??'');$target=(string)($p['target']??'');$this->pair($source,$target);
  if(!empty($p['activate'])&&!$this->available[$target]['enabled'])throw new \RuntimeException('Дополнение назначения выключено. Включите его в Portfolio или снимите флажок переключения показа.');
  $mode=(string)($p['mode']??'copy');if(!in_array($mode,['copy','move'],true))throw new \RuntimeException('Неверный режим.');
  $rawIds=$p['articles']??[];if(!is_array($rawIds)||!array_is_list($rawIds)||count($rawIds)>10000)throw new \RuntimeException('Неверный список материалов.');
  foreach($rawIds as $id)if(!is_int($id)||$id<1)throw new \RuntimeException('Неверный ID материала.');
  $ids=array_values(array_unique($rawIds));if(!$ids || count($ids)>10000)throw new \RuntimeException('Выберите материалы для обработки.');
  $plans=[];$total=0;
  foreach($ids as $id){
   if(!$this->canEdit($id,$mode==='move'))throw new \RuntimeException('Нет прав на изменение выбранного материала.');
   $b=$this->bundle($id);if((int)$b['article']['state']===-2)throw new \RuntimeException('Материалы в корзине исключены из переноса.');
   $data=Adapters::read($source,$b);Adapters::read($target,$b);
   $paths=[];$entries=[];foreach($data['entries'] as $entry){$path=$this->files->resolve($source,$id,$entry['name']);if($path!==null){$entries[]=$entry;$paths[$entry['name']]=$path;}}
   $data['entries']=$entries;if(!$entries)continue;
   $plans[]=['id'=>$id,'title'=>$b['article']['title'],'entries'=>$data['entries'],'paths'=>$paths,'expected'=>$this->fingerprint($b)];$total+=count($data['entries']);
  }
  if(!$total)throw new \RuntimeException('В выбранных материалах нет изображений источника.');
  $job=['id'=>bin2hex(random_bytes(16)),'user_id'=>(int)$this->user->id,'status'=>'running','created'=>$this->now(),'updated'=>$this->now(),
   'state'=>['source'=>$source,'target'=>$target,'mode'=>$mode,'activate'=>!empty($p['activate']),'plans'=>$plans,'article'=>0,'item'=>0,'total'=>$total,'processed'=>0,'policies'=>[],'choices'=>[],'conflict'=>null,'file_error'=>null,'skip_file_errors'=>false,'skipped_files'=>[],'warnings'=>[],
    'stats'=>['copied'=>0,'moved'=>0,'skipped'=>0,'replaced'=>0,'renamed'=>0,'caption_copies'=>0],'results'=>[]]];
  $this->execute('INSERT INTO #__tz_media_transfer_jobs (id,user_id,status,state,created,updated) VALUES ('.$this->q($job['id']).','.$job['user_id'].','.$this->q($job['status']).','.$this->q(Adapters::encode($job['state'])).','.$this->q($job['created']).','.$this->q($job['updated']).')');
  return $this->publicJob($job);
 }
 private function job(string $id): array {
  if(!preg_match('/^[a-f0-9]{32}$/D',$id))throw new \RuntimeException('Неверный номер операции.');
  $rows=$this->rows('SELECT * FROM #__tz_media_transfer_jobs WHERE id='.$this->q($id).' AND user_id='.(int)$this->user->id);
  if(count($rows)!==1)throw new \RuntimeException('Операция не найдена или принадлежит другому пользователю.');
  $job=$rows[0];$job['state']=Adapters::json($job['state']);return $job;
 }
 private function saveJob(array $job): void {$this->update('#__tz_media_transfer_jobs',['status'=>$job['status'],'state'=>Adapters::encode($job['state']),'updated'=>$this->now()],'id='.$this->q($job['id']));}
 private function publicJob(array $job): array {
  $s=$job['state'];return ['id'=>$job['id'],'status'=>$job['status'],'source'=>$s['source'],'target'=>$s['target'],'mode'=>$s['mode'],'activate'=>$s['activate'],'total'=>$s['total'],'processed'=>$s['processed'],'stats'=>$s['stats'],'warnings'=>$s['warnings'],'error'=>$s['error']??null,'conflict'=>$s['conflict'],'file_error'=>$s['file_error']??null,'skipped_files'=>$s['skipped_files']??[],'results'=>$s['results'],'created'=>$job['created'],'undo_processed'=>$s['undo_count']??0,'undo_total'=>$s['undo_total']??0];
 }
 private function history(): array {
  $rows=$this->rows('SELECT * FROM #__tz_media_transfer_jobs WHERE user_id='.(int)$this->user->id.' ORDER BY created DESC LIMIT 50');$out=[];
  foreach($rows as $j){$j['state']=Adapters::json($j['state']);$v=$this->publicJob($j);unset($v['results'],$v['conflict'],$v['file_error'],$v['skipped_files']);$out[]=$v;}
  return ['jobs'=>$out];
 }
 private function resolve(array $job,array $p): array {
  $s=&$job['state'];$c=$s['conflict'];
  if($job['status']!=='conflict'||!$c||!hash_equals($c['id'],(string)($p['conflict']??'')))throw new \RuntimeException('Это окно конфликта уже не актуально. Обновите состояние операции.');
  $allowed=$c['kind']==='image'?['replace','keep','rename']:['replace','keep','duplicate'];$choice=(string)($p['choice']??'');
  if(!in_array($choice,$allowed,true))throw new \RuntimeException('Выберите решение конфликта.');
  $s['choices'][$c['kind']]=$choice;if(!empty($p['all']))$s['policies'][$c['kind']]=$choice;
  $s['conflict']=null;$job['status']='running';$this->saveJob($job);return $this->publicJob($job);
 }
 private function skipFile(array $job,array $p): array {
  $error=$job['state']['file_error']??null;
  if($job['status']!=='file_error'||!$error||!hash_equals($error['id'],(string)($p['file_error']??'')))throw new \RuntimeException('Это сообщение об ошибке уже не актуально. Обновите состояние операции.');
  if(!empty($p['all']))$job['state']['skip_file_errors']=true;
  $this->recordSkip($job,$error['path'],$error['message'],'file_error');return $this->publicJob($job);
 }
 private function recordSkip(array &$job,string $path,string $reason,string $kind): void {
  $s=&$job['state'];$plan=$s['plans'][$s['article']];$item=$plan['entries'][$s['item']];
  $s['skipped_files'][]=['article'=>$plan['id'],'title'=>$plan['title'],'name'=>$item['name'],'path'=>$path,'reason'=>$reason,'kind'=>$kind];
  $s['stats']['skipped']++;$s['file_error']=null;$job['status']='running';
  $this->advance($job,'Пропущено: '.$reason,$item['name']);if($job['status']==='done')$this->clearCache($job);$this->saveJob($job);
 }
 private function choose(array &$job,string $kind,array $details): ?string {
  $s=&$job['state'];$choice=$s['choices'][$kind]??$s['policies'][$kind]??null;
  if($choice!==null)return $choice;
  $s['conflict']=['id'=>bin2hex(random_bytes(12)),'kind'=>$kind]+$details;$job['status']='conflict';$this->saveJob($job);return null;
 }
 private function advance(array &$job,string $result,string $name): void {
  $s=&$job['state'];$plan=$s['plans'][$s['article']];
  $s['results'][]=['article'=>$plan['id'],'title'=>$plan['title'],'name'=>$name,'result'=>$result];
  $s['processed']++;$s['item']++;$s['choices']=[];$s['conflict']=null;$s['file_error']=null;
  if($s['item']>=count($plan['entries'])){$s['article']++;$s['item']=0;}
  if($s['article']>=count($s['plans']))$job['status']='done';
 }
 private function step(array $job): array {
  if($job['status']!=='running')return $this->publicJob($job);
  $src=null;$cursor=$job['state']['processed'];
  try{
   $s=&$job['state'];$this->pair($s['source'],$s['target']);if($s['activate']&&!$this->available[$s['target']]['enabled'])throw new \RuntimeException('Дополнение назначения было выключено. Включите его перед продолжением.');$plan=$s['plans'][$s['article']];$id=(int)$plan['id'];
   if(!$this->canEdit($id,$s['mode']==='move'))throw new \RuntimeException('Права доступа к материалу изменились.');
   $before=$this->bundle($id);
   if((int)$before['article']['state']===-2 || !hash_equals($plan['expected'],$this->fingerprint($before)))throw new \RuntimeException('Материал '.$id.' изменён после начала операции. Остановитесь, проверьте изменения и создайте новую операцию.');
   $item=$plan['entries'][$s['item']];$src=$this->files->resolve($s['source'],$id,$item['name']);
   if(!$src)throw new FileProblem('Исходный файл больше не существует: '.$item['name']);$this->files->assertImage($src);$srcHash=$this->files->hash($src);
   $source=Adapters::read($s['source'],$before);$dest=Adapters::read($s['target'],$before);
   $index=null;$dst=null;$same=false;
   // Filename collision wins; otherwise compare bytes, including differently named files.
   foreach($dest['entries'] as $i=>$entry)if($entry['name']===$item['name']){$index=$i;$dst=$this->files->resolve($s['target'],$id,$entry['name']);break;}
   if($index===null){$existing=$this->files->resolve($s['target'],$id,$item['name']);if($existing)$dst=$existing;}
   if($dst)$same=$this->files->hash($dst)===$srcHash;
   if($index===null && !$dst){
    foreach($dest['entries'] as $i=>$entry){$candidate=$this->files->resolve($s['target'],$id,$entry['name']);if($candidate&&$this->files->hash($candidate)===$srcHash){$index=$i;$dst=$candidate;$same=true;break;}}
   }
   $collision=$index!==null||$dst!==null;$imageChoice=null;
   $targetCaption=$index!==null?$dest['entries'][$index]['caption']:'';
   $targetName=$index!==null?$dest['entries'][$index]['name']:($dst?basename($dst):$item['name']);
   if($collision){
    $details=['article'=>$id,'title'=>$plan['title'],'same_content'=>$same,'source'=>$this->files->info($src,$item['caption']),'target'=>$this->files->info($dst,$targetCaption)];$details['target']['name']=$targetName;
    $imageChoice=$this->choose($job,'image',$details);if($imageChoice===null)return $this->publicJob($job);
    if($imageChoice==='keep'&&!$same){$this->recordSkip($job,$this->files->displayPath($src),'Оставлен существующий файл назначения','collision');return $this->publicJob($job);}
   }
   $append=!$collision||$imageChoice==='rename';$name=$append?($collision?$this->files->unique($s['target'],$id,$item['name'],$dest['entries']):$item['name']):$targetName;
   $caption=$item['caption'];$captionChoice=null;
   if(!$append && $index!==null){
    if($caption!=='' && $targetCaption!=='' && $caption!==$targetCaption){
     $captionChoice=$this->choose($job,'caption',['article'=>$id,'title'=>$plan['title'],'source'=>$this->files->info($src,$caption),'target'=>$this->files->info($dst,$targetCaption),'same_content'=>$same]);
     if($captionChoice===null)return $this->publicJob($job);
     if($captionChoice==='keep')$caption=$targetCaption;
     if($captionChoice==='duplicate'){$append=true;$name=$this->files->unique($s['target'],$id,$item['name'],$dest['entries']);}
    }elseif($caption==='')$caption=$targetCaption;
   }
   $destination=$append||!$dst?$this->files->destination($s['target'],$id,$name):$dst;
   $after=$before;
   if($append||$index===null)$dest['entries'][]=['name'=>$name,'caption'=>$caption];
   else $dest['entries'][$index]=['name'=>$name,'caption'=>$caption];
   Adapters::write($s['target'],$after,$dest,$this->available[$s['target']]['id']);
   $removeSource=false;
   if($s['mode']==='move'){
    $found=false;foreach($source['entries'] as $i=>$entry)if($entry===$item){array_splice($source['entries'],$i,1);$found=true;break;}
    if(!$found)throw new \RuntimeException('Исходная запись была изменена.');
    $removeSource=!in_array($item['name'],array_column($source['entries'],'name'),true);
    Adapters::write($s['source'],$after,$source,$this->available[$s['source']]['id']);
   }
   if($s['activate'])Adapters::activate($s['target'],$after);
   $files=[];
   $copyOriginal=!$this->files->exists($destination)||$this->files->hash($destination)!==$srcHash;
   if($copyOriginal)$files[]=$this->filePlan($src,$destination,$srcHash);
   foreach($this->files->derivatives($src) as $small){
     $suffix=substr(pathinfo($small,PATHINFO_FILENAME),strlen(pathinfo($src,PATHINFO_FILENAME)));
     $newSmall=dirname($destination).'/resize/'.pathinfo($name,PATHINFO_FILENAME).$suffix.'.'.pathinfo($small,PATHINFO_EXTENSION);
     $hash=$this->files->hash($small);
     if(!$this->files->exists($newSmall)||($copyOriginal&&$this->files->hash($newSmall)!==$hash))$files[]=$this->filePlan($small,$newSmall,$hash);
   }
   if($removeSource){
    $files[]=$this->removePlan($src,$srcHash);
    foreach($this->files->derivatives($src) as $small)$files[]=$this->removePlan($small,$this->files->hash($small));
   }
   $s['stats'][$s['mode']==='move'?'moved':'copied']++;
   if($collision&&$imageChoice==='replace'&&!$append)$s['stats']['replaced']++;
   if($collision&&$append)$s['stats']['renamed']++;
   if($captionChoice==='duplicate')$s['stats']['caption_copies']++;
   $result=$s['mode']==='move'?'Перемещено':'Скопировано';if($name!==$item['name'])$result.=' как '.$name;
   $articleCursor=$s['article'];$this->advance($job,$result,$item['name']);
   $operation=['article'=>$id,'before'=>$before,'after'=>$after,'files'=>$files,'cleanup'=>false];
   $opId=$this->insert('#__tz_media_transfer_changes',['job_id'=>$job['id'],'status'=>'prepared','data'=>Adapters::encode($operation)]);
   $this->files->claimBackups();
   try{
    foreach($files as $file)if($file['after_hash']!==null)$this->files->atomicCopy($file['source'],$file['path'],$file['after_hash']);
    if($this->files->hash($src)!==$srcHash)throw new FileProblem('Исходный файл изменился во время копирования.');
    $this->db->transactionStart();
    try{
     $current=$this->bundle($id,true);if($this->fingerprint($current)!==$this->fingerprint($before))throw new \RuntimeException('Материал изменён во время копирования. Копирование отменено.');
     $this->writeBundle($after,$current);$operation['after']=$this->bundle($id);
     $s['plans'][$articleCursor]['expected']=$this->fingerprint($operation['after']);
     $this->update('#__tz_media_transfer_changes',['status'=>'done','data'=>Adapters::encode($operation)],'id='.$opId);
     $this->saveJob($job);$this->db->transactionCommit();
    }catch(\Throwable $e){$this->db->transactionRollback();throw $e;}
   }catch(\Throwable $e){$this->recoverPrepared($job['id']);throw $e;}
   $this->cleanup($job['id']);$finished=$this->job($job['id']);if($finished['status']==='done'){$this->clearCache($finished);$this->saveJob($finished);}return $this->publicJob($finished);
  }catch(FileProblem $e){
   $this->files->discardUnclaimed();
   try{$this->recoverPrepared($job['id']);}catch(\Throwable $recovery){return $this->fatalJob($job['id'],'Не удалось восстановить прерванную запись. Требуется проверка: '.$recovery->getMessage());}
   $stored=$this->job($job['id']);
   if($stored['state']['processed']!==$cursor)return $this->fatalJob($job['id'],$e->getMessage());
   $s=&$stored['state'];$plan=$s['plans'][$s['article']];$item=$plan['entries'][$s['item']];
   $relative=$src??($plan['paths'][$item['name']]??$this->files->destination($s['source'],(int)$plan['id'],$item['name']));
   $path=$this->files->displayPath($relative);
   $s['file_error']=['id'=>bin2hex(random_bytes(12)),'article'=>$plan['id'],'title'=>$plan['title'],'name'=>$item['name'],'path'=>$path,'message'=>$e->getMessage()];
   $s['conflict']=null;$s['choices']=[];$stored['status']='file_error';
   if(!empty($s['skip_file_errors']))$this->recordSkip($stored,$path,$e->getMessage(),'file_error');else $this->saveJob($stored);
   return $this->publicJob($stored);
  }catch(\Throwable $e){
   $this->files->discardUnclaimed();
   $stored=$this->job($job['id']);$stored['status']='error';$stored['state']['error']=get_class($e)===\RuntimeException::class?$e->getMessage():'Не удалось сохранить изменения. Исходные данные и резервные копии сохранены.';$stored['state']['conflict']=null;$this->saveJob($stored);
   if(get_class($e)!==\RuntimeException::class)\Joomla\CMS\Log\Log::add((string)$e,\Joomla\CMS\Log\Log::ERROR,'com_tz_media_transfer');
   return $this->publicJob($stored);
  }
 }
 private function fatalJob(string $id,string $message): array {
  $job=$this->job($id);$job['status']='error';$job['state']['error']=$message;$job['state']['conflict']=null;$job['state']['file_error']=null;$this->saveJob($job);return $this->publicJob($job);
 }
 private function filePlan(string $source,string $target,string $hash): array {
  $exists=$this->files->exists($target);$before=$exists?$this->files->hash($target):null;return ['source'=>$source,'path'=>$target,'before_hash'=>$before,'backup'=>$exists?$this->files->backup($target,$before):null,'after_hash'=>$hash];
 }
 private function removePlan(string $source,string $hash): array {return ['source'=>$source,'path'=>$source,'before_hash'=>$hash,'backup'=>$this->files->backup($source,$hash),'after_hash'=>null];}
 private function recoverPrepared(string $job): void {
  foreach($this->rows('SELECT * FROM #__tz_media_transfer_changes WHERE job_id='.$this->q($job)." AND status='prepared' ORDER BY id DESC") as $row){
   $op=Adapters::json($row['data']);
   foreach(array_reverse($op['files']) as $file){
    // Sources are deleted only after the DB commit; a prepared operation never deletes them.
    if($file['after_hash']===null)continue;
    $current=$this->files->exists($file['path'])?$this->files->hash($file['path']):null;
    if($current===$file['before_hash'])continue;
    if($current!==$file['after_hash'])throw new \RuntimeException('Файл изменился после прерванного запроса. Требуется ручная проверка: '.basename($file['path']));
    if($file['before_hash']===null)$this->files->remove($file['path'],$file['after_hash']);
    else $this->files->atomicCopy($file['backup'],$file['path'],$file['before_hash']);
   }
   $this->update('#__tz_media_transfer_changes',['status'=>'failed'],'id='.(int)$row['id']);
  }
 }
 private function cleanup(string $jobId): void {
  foreach($this->rows('SELECT * FROM #__tz_media_transfer_changes WHERE job_id='.$this->q($jobId)." AND status='done' AND cleaned=0 ORDER BY id") as $row){
   $op=Adapters::json($row['data']);if($op['cleanup'])continue;$warnings=[];
   foreach($op['files'] as $file)if($file['after_hash']===null){try{$this->files->remove($file['path'],$file['before_hash']);}catch(\RuntimeException $e){$warnings[]=$e->getMessage();}}
   $op['cleanup']=true;$this->update('#__tz_media_transfer_changes',['data'=>Adapters::encode($op),'cleaned'=>1],'id='.(int)$row['id']);
   if($warnings){$job=$this->job($jobId);$job['state']['warnings']=array_merge($job['state']['warnings'],$warnings);$this->saveJob($job);}
  }
 }
 private function undoStep(array $job): array {
  if($job['status']!=='rolling_back')throw new \RuntimeException('Откат не запущен.');
  $rows=$this->rows('SELECT * FROM #__tz_media_transfer_changes WHERE job_id='.$this->q($job['id'])." AND status IN ('done','undoing') ORDER BY id DESC LIMIT 1");
  if(!$rows){$job['status']='undone';$this->clearCache($job);$this->saveJob($job);return $this->publicJob($job);}
  $row=$rows[0];$op=Adapters::json($row['data']);$id=(int)$op['article'];
  try{
   if(!$this->canEdit($id,true))throw new \RuntimeException('Нет прав на откат материала.');
   $current=$this->bundle($id);
   if($this->fingerprint($current)!==$this->fingerprint($op['after']))throw new \RuntimeException('Материал '.$id.' изменён после переноса. Откат остановлен, чтобы не перезаписать новые правки.');
   foreach($op['files'] as $file){
    $hash=$this->files->exists($file['path'])?$this->files->hash($file['path']):null;
    $allowed=$row['status']==='undoing'?[$file['before_hash'],$file['after_hash']]:[$file['after_hash']];
    // Failed deletion after a committed move may have left the original source intact.
    if($file['after_hash']===null)$allowed[]=$file['before_hash'];
    if(!in_array($hash,$allowed,true))throw new \RuntimeException('Файл изменён после операции: '.basename($file['path']));
    if($file['before_hash']!==null && $this->files->hash($file['backup'])!==$file['before_hash'])throw new \RuntimeException('Резервная копия повреждена.');
   }
   $this->update('#__tz_media_transfer_changes',['status'=>'undoing'],'id='.(int)$row['id']);
   foreach(array_reverse($op['files']) as $file){
    $hash=$this->files->exists($file['path'])?$this->files->hash($file['path']):null;
    if($hash===$file['before_hash'])continue;
    if($file['before_hash']===null)$this->files->remove($file['path'],$file['after_hash']);
    else $this->files->atomicCopy($file['backup'],$file['path'],$file['before_hash']);
   }
   $this->db->transactionStart();
   try{
    $current=$this->bundle($id,true);if($this->fingerprint($current)!==$this->fingerprint($op['after']))throw new \RuntimeException('Материал изменился во время отката.');
    $this->writeBundle($op['before'],$current);$this->update('#__tz_media_transfer_changes',['status'=>'undone'],'id='.(int)$row['id']);
    $job['state']['undo_count']=($job['state']['undo_count']??0)+1;$this->saveJob($job);$this->db->transactionCommit();
   }catch(\Throwable $e){$this->db->transactionRollback();throw $e;}
   return $this->publicJob($job);
  }catch(\RuntimeException $e){$job['status']='error';$job['state']['error']=$e->getMessage();$this->saveJob($job);return $this->publicJob($job);}
 }
 private function clearCache(array &$job): void {
  try{
   $factory=\Joomla\CMS\Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class);
   foreach(['com_tz_portfolio','page'] as $group)$factory->createCacheController('callback',['defaultgroup'=>$group])->clean();
  }catch(\Throwable $e){$job['state']['warnings'][]='Данные сохранены. Очистите кеш Joomla вручную, если на сайте ещё видны старые изображения.';}
 }
 private function purge(array $job): array {
  if(in_array($job['status'],['running','conflict','file_error','rolling_back'],true))throw new \RuntimeException('Сначала остановите или завершите операцию.');
  foreach($this->rows('SELECT data,status FROM #__tz_media_transfer_changes WHERE job_id='.$this->q($job['id'])) as $row){
   if(in_array($row['status'],['prepared','undoing'],true))throw new \RuntimeException('Сначала завершите восстановление прерванной операции или откат.');
   foreach(Adapters::json($row['data'])['files'] as $f)if($f['backup']&&$this->files->exists($f['backup'])){
    if(!str_starts_with($f['backup'],'administrator/components/com_tz_media_transfer/storage/'))throw new \RuntimeException('Неверный путь резервной копии.');
    $this->files->remove($f['backup'],$f['before_hash']);
   }
  }
  $this->execute('DELETE FROM #__tz_media_transfer_changes WHERE job_id='.$this->q($job['id']));$this->execute('DELETE FROM #__tz_media_transfer_jobs WHERE id='.$this->q($job['id']));return ['deleted'=>true];
 }
}
