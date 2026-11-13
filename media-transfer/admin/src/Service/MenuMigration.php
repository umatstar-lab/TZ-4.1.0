<?php
namespace Umatstar\Component\TzMediaTransfer\Administrator\Service;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\Database\DatabaseInterface;
/** Migrate only verified native Portfolio+ menu routes, without loading legacy PHP. */
final class MenuMigration {
 private const OLD='com_tz_portfolio_plus';
 private const NEW='com_tz_portfolio';
 private const TYPES=['article:default'=>'Отдельный материал','categories:default'=>'Список категорий','categories:grid'=>'Категории — сетка','portfolio:default'=>'Портфолио','date:default'=>'Материалы по дате','search:default'=>'Поиск','myarticles:default'=>'Мои материалы','form:edit'=>'Создать материал'];
 public function __construct(private DatabaseInterface $db,private User $user,private string $root) {}
 private function q($v): string {return $this->db->quote((string)$v);}
 private function rows(string $sql): array {return $this->db->setQuery($sql)->loadAssocList();}
 private function run(string $sql): void {$this->db->setQuery($sql)->execute();}
 private function json($v): string {return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
 private function signature(array $row,array $proposal): string {return hash_hmac('sha256',$this->json([$row,$proposal]),(string)Factory::getApplication()->get('secret'));}
 private function target(): int {
  $rows=$this->rows("SELECT extension_id,enabled FROM #__extensions WHERE type='component' AND element=".$this->q(self::NEW));
  $manifest=$this->root.'/administrator/components/'.self::NEW.'/tz_portfolio.xml';
  if(count($rows)!==1||!(int)$rows[0]['enabled']||!is_file($manifest))throw new \RuntimeException('Установите и включите TZ Portfolio 4.1 или новее.');
  $xml=simplexml_load_file($manifest);if(!$xml||version_compare((string)$xml->version,'4.1.0','<'))throw new \RuntimeException('Нужен TZ Portfolio 4.1 или новее.');
  return (int)$rows[0]['extension_id'];
 }
 private function menuRows(string $where='',bool $locked=false): array {
  return $this->rows('SELECT m.*,t.id AS menu_type_id,t.title AS menu_title FROM #__menu m LEFT JOIN #__menu_types t ON t.menutype=m.menutype AND t.client_id=m.client_id'.($where?' WHERE '.$where:'').' ORDER BY m.menutype,m.lft,m.id'.($locked?' FOR UPDATE':''));
 }
 private function canEdit(array $r): bool {return $this->user->authorise('core.edit','com_menus')&&(!empty($r['menu_type_id'])&&$this->user->authorise('core.edit','com_menus.menu.'.(int)$r['menu_type_id']));}
 private function linked(array $r): bool {
  $u=parse_url(html_entity_decode((string)$r['link'],ENT_QUOTES|ENT_HTML5,'UTF-8'));$p=[];if(is_array($u))parse_str($u['query']??'',$p);
  if(($p['option']??null)===self::OLD)return true;
  $old=$this->rows("SELECT extension_id FROM #__extensions WHERE type='component' AND element=".$this->q(self::OLD));
  return $old&&(int)$r['component_id']===(int)$old[0]['extension_id'];
 }
 private function objectTitle(string $kind,$raw): string {
  if(!is_scalar($raw)||!preg_match('/^([1-9][0-9]*)(?::[^\x00-\x1f]*)?$/D',(string)$raw,$m))throw new \RuntimeException('Неверный ID назначения.');
  $id=(int)$m[1];$table=$kind==='article'?'content':'categories';$state=$kind==='article'?'state':'published';
  if($kind==='category'&&$id===1)return 'Все категории (корень, ID 1)';
  $rows=$this->rows('SELECT title,'.$state.' AS state FROM #__tz_portfolio_plus_'.$table.' WHERE id='.$id);
  if(count($rows)!==1||(int)$rows[0]['state']===-2)throw new \RuntimeException(($kind==='article'?'Материал':'Категория').' ID '.$id.' не найден или находится в корзине.');
  return $rows[0]['title'].' (ID '.$id.')'.((int)$rows[0]['state']===0?' — не опубликован':'');
 }
 private function categoryList($raw): string {
  if($raw===null||$raw===''||$raw===[]||$raw===0||$raw==='0')return 'Все категории';
  $ids=is_array($raw)?$raw:explode(',',(string)$raw);if(count($ids)>500)throw new \RuntimeException('Слишком много категорий.');
  return implode('; ',array_map(fn($id)=>$this->objectTitle('category',$id),$ids));
 }
 private function proposal(array $r,int $target): array {
  if((int)$r['client_id']!==0||$r['type']!=='component')throw new \RuntimeException('Не является штатным пунктом компонента на сайте.');
  if((int)$r['published']===-2)throw new \RuntimeException('Пункт меню находится в корзине.');
  if(!empty($r['checked_out'])&&(int)$r['checked_out']!==(int)$this->user->id)throw new \RuntimeException('Пункт редактируется другим пользователем.');
  $link=html_entity_decode($r['link'],ENT_QUOTES|ENT_HTML5,'UTF-8');$url=parse_url($link);
  if(!$url||isset($url['scheme'])||isset($url['host'])||($url['path']??'')!=='index.php')throw new \RuntimeException('Нестандартная ссылка: требуется ручная проверка.');
  parse_str($url['query']??'',$p);
  if(($p['option']??null)!==self::OLD||!is_string($p['view']??null))throw new \RuntimeException('Не найдена штатная ссылка Portfolio+.');
  $view=$p['view'];$layout=$p['layout']??($view==='form'?'edit':'default');if(!is_string($layout)||!isset(self::TYPES[$view.':'.$layout])||isset($p['task']))throw new \RuntimeException('Тип или макет не поддерживается автоматически.');
  if(!is_file($this->root.'/components/'.self::NEW.'/tmpl/'.$view.'/'.$layout.'.xml'))throw new \RuntimeException('В новом Portfolio нет соответствующего типа меню.');
  $params=json_decode($r['params']?:'{}',true,32,JSON_THROW_ON_ERROR);if(!is_array($params))throw new \RuntimeException('Неверные параметры пункта меню.');
  $name=self::TYPES[$view.':'.$layout];
  if($view==='article')$name=$this->objectTitle('article',$p['id']??null);
  elseif($view==='categories')$name=$this->objectTitle('category',$p['id']??null);
  elseif($view==='portfolio'||$view==='date')$name=$this->categoryList($params[$view==='date'?'tz_catid':'catid']??null);
  elseif($view==='form'&&!empty($params['enable_category']))$name=$this->objectTitle('category',$params['catid']??null);
  if($view==='search'&&!empty($params['search_parent_cat']))$name.=' — '.$this->objectTitle('category',$params['search_parent_cat']);
  // Preserve every other query byte, fragment, custom parameter and ID; require a single option.
  $count=0;$query=preg_replace_callback('/(^|&)option=com_tz_portfolio_plus(?=&|$)/',static function($m)use(&$count){$count++;return $m[1].'option=com_tz_portfolio';},$url['query']);
  if($count!==1||preg_match_all('/(?:^|&)option(?:=|%5B|\[)/i',$url['query'])!==1)throw new \RuntimeException('Неоднозначный параметр компонента в ссылке.');
  return ['link'=>'index.php?'.$query.(isset($url['fragment'])?'#'.$url['fragment']:''),'component_id'=>$target,'type'=>self::TYPES[$view.':'.$layout],'name'=>$name];
 }
 public function dispatch(string $action,array $p): array {
  if(!$this->user->id||!$this->user->authorise('core.manage','com_tz_media_transfer')||!$this->user->authorise('core.manage','com_menus'))throw new \RuntimeException('Недостаточно прав управления меню.',403);
  if($action==='menuScan')return $this->scan();
  if($action==='menuHistory')return ['jobs'=>$this->rows('SELECT id,created,status FROM #__tz_media_transfer_menus WHERE user_id='.(int)$this->user->id.' ORDER BY created DESC,id DESC LIMIT 100')];
  if(!in_array($action,['menuApply','menuUndo'],true))throw new \RuntimeException('Неизвестное действие меню.');
  $target=$this->target();$lock='tzmt-menus-'.substr(hash('sha256',$this->root.$this->db->getPrefix()),0,35);
  if((int)$this->db->setQuery('SELECT GET_LOCK('.$this->q($lock).',0)')->loadResult()!==1)throw new \RuntimeException('Меню уже обновляется. Повторите запрос.');
  try{return $action==='menuApply'?$this->apply($p,$target):$this->undo($p);}finally{$this->db->setQuery('SELECT RELEASE_LOCK('.$this->q($lock).')')->loadResult();}
 }
 private function scan(): array {
  $target=$this->target();$out=[];
  $old=$this->rows("SELECT extension_id FROM #__extensions WHERE type='component' AND element=".$this->q(self::OLD));
  $where='m.client_id=0 AND (m.link LIKE '.$this->q('%com_tz_portfolio_plus%').($old?' OR m.component_id='.(int)$old[0]['extension_id']:'').')';
  foreach($this->menuRows($where) as $r){if(!$this->linked($r))continue;
   $v=['id'=>(int)$r['id'],'menu'=>$r['menu_title']?:$r['menutype'],'menutype'=>$r['menutype'],'title'=>$r['title'],'published'=>(int)$r['published'],'type'=>'Неизвестный тип','match'=>'','link'=>$r['link'],'new_link'=>'','token'=>null,'error'=>''];
   $query=[];$uri=parse_url(html_entity_decode($r['link'],ENT_QUOTES|ENT_HTML5,'UTF-8'));if(is_array($uri))parse_str($uri['query']??'',$query);$view=$query['view']??'';$layout=$query['layout']??($view==='form'?'edit':'default');if(is_string($view)&&is_string($layout))$v['type']=self::TYPES[$view.':'.$layout]??'Неизвестный тип';
   try{$pro=$this->proposal($r,$target);$v['type']=$pro['type'];$v['match']=$pro['name'];$v['new_link']=$pro['link'];if(!$this->canEdit($r))throw new \RuntimeException('Нет права изменения этого меню.');$v['token']=$this->signature($r,$pro);}
   catch(\RuntimeException $e){$v['error']=$e->getMessage();}catch(\JsonException $e){$v['error']='Повреждены параметры меню.';}
   $out[]=$v;
  }
  return ['items'=>$out,'found'=>count($out)];
 }
 private function apply(array $p,int $target): array {
  $items=$p['items']??null;if(!is_array($items)||!array_is_list($items)||!count($items)||count($items)>500)throw new \RuntimeException('Выберите от 1 до 500 пунктов меню.');
  $seen=[];$changes=[];$id=bin2hex(random_bytes(16));$this->db->transactionStart();
  try{
   foreach($items as $item){
    if(!is_array($item)||!is_int($item['id']??null)||$item['id']<1||!is_string($item['token']??null)||strlen($item['token'])!==64||isset($seen[$item['id']]))throw new \RuntimeException('Неверный список пунктов меню.');$seen[$item['id']]=true;
    $rows=$this->menuRows('m.id='.$item['id'],true);if(count($rows)!==1)throw new \RuntimeException('Пункт меню больше не существует. Повторите поиск.');$r=$rows[0];
    if(!$this->canEdit($r))throw new \RuntimeException('Нет права изменения выбранного меню.',403);
    $pro=$this->proposal($r,$target);if(!hash_equals($this->signature($r,$pro),$item['token']))throw new \RuntimeException('Меню или назначение изменилось после поиска. Повторите поиск.');
    $this->run('UPDATE #__menu SET link='.$this->q($pro['link']).',component_id='.$target.' WHERE id='.(int)$r['id']);
    $actual=$this->menuRows('m.id='.(int)$r['id'])[0];$changes[]=['before'=>$r,'after'=>$actual];
   }
   $this->run('INSERT INTO #__tz_media_transfer_menus (id,user_id,created,status,data) VALUES ('.$this->q($id).','.(int)$this->user->id.','.$this->q(gmdate('Y-m-d H:i:s')).",'done',".$this->q($this->json($changes)).')');
   $this->db->transactionCommit();
  }catch(\Throwable $e){$this->db->transactionRollback();throw $e;}
  $this->clearCache();return ['id'=>$id,'changed'=>count($changes)];
 }
 private function undo(array $p): array {
  $id=$p['job']??null;if(!is_string($id)||!preg_match('/^[a-f0-9]{32}$/D',$id))throw new \RuntimeException('Неверный журнал меню.');$this->db->transactionStart();
  try{
   $jobs=$this->rows('SELECT * FROM #__tz_media_transfer_menus WHERE id='.$this->q($id).' AND user_id='.(int)$this->user->id.' FOR UPDATE');
   if(count($jobs)!==1||$jobs[0]['status']!=='done')throw new \RuntimeException('Журнал недоступен или уже отменён.');
   $changes=json_decode($jobs[0]['data'],true,64,JSON_THROW_ON_ERROR);
   foreach($changes as $change){$before=$change['before'];$rows=$this->menuRows('m.id='.(int)$before['id'],true);
    if(count($rows)!==1||!$this->canEdit($rows[0]))throw new \RuntimeException('Нет права изменения меню или пункт удалён.',403);
    if(!empty($rows[0]['checked_out']))throw new \RuntimeException('Закройте редактор пункта меню перед откатом.');
    $current=$rows[0];$expected=$change['after'];foreach(['checked_out','checked_out_time'] as $key){unset($current[$key],$expected[$key]);}
    if($current!==$expected)throw new \RuntimeException('Пункт меню изменён после переноса. Откат остановлен, чтобы сохранить новые правки.');
    $this->run('UPDATE #__menu SET link='.$this->q($before['link']).',component_id='.(int)$before['component_id'].' WHERE id='.(int)$before['id']);
   }
   $this->run("UPDATE #__tz_media_transfer_menus SET status='undone' WHERE id=".$this->q($id));$this->db->transactionCommit();
  }catch(\Throwable $e){$this->db->transactionRollback();throw $e;}
  $this->clearCache();return ['restored'=>count($changes)];
 }
 private function clearCache(): void {foreach(['com_menus','com_tz_portfolio','com_tz_portfolio_plus','com_modules','mod_menu','page','_system'] as $group)Factory::getContainer()->get(\Joomla\CMS\Cache\CacheControllerFactoryInterface::class)->createCacheController('callback',['defaultgroup'=>$group])->clean();}
}
