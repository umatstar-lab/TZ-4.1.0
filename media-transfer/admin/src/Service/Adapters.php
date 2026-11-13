<?php
namespace Umatstar\Component\TzMediaTransfer\Administrator\Service;
defined('_JEXEC') or die;
/** Explicit schemas: never guess how an unknown add-on stores or deletes data. */
final class Adapters {
 public const DEFINITIONS=[
  'flipbook_gallery'=>['label'=>'Флипбук — Flipbook Gallery','folder'=>'mediatype','storage'=>'media','field'=>'flipbook_gallery','directory'=>'flipbook_gallery','roots'=>['tz_portfolio','tz_portfolio_plus'],'write'=>'tz_portfolio'],
  'gallery'=>['label'=>'Галерея — Content Gallery','folder'=>'content','storage'=>'addon','field'=>'gallery_content','directory'=>'gallery','roots'=>['tz_portfolio_plus','com_tz_portfolio','tz_portfolio'],'write'=>'tz_portfolio_plus'],
  'grid_gallery'=>['label'=>'Сетка — Grid Gallery','folder'=>'mediatype','storage'=>'media','field'=>'grid_gallery','directory'=>'grid_gallery','roots'=>['tz_portfolio','tz_portfolio_plus'],'write'=>'tz_portfolio'],
 ];
 public static function json(?string $value): array {
  if ($value===null || $value==='') return [];
  try {$v=json_decode($value,true,512,JSON_THROW_ON_ERROR);} catch (\Throwable $e) {throw new \RuntimeException('Повреждён JSON дополнения. Операция не выполнена.');}
  if (!is_array($v)) throw new \RuntimeException('Неподдерживаемый формат данных дополнения.');return $v;
 }
 public static function encode(array $v): string {return json_encode($v,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);}
 public static function definition(string $key): array {
  if (!isset(self::DEFINITIONS[$key])) throw new \RuntimeException('Нет адаптера для выбранного дополнения.');return self::DEFINITIONS[$key];
 }
 public static function read(string $key,array $bundle): array {
  $d=self::definition($key);$outer=$d['storage']==='media'?self::json($bundle['article']['media']):self::json($bundle['gallery']['value']??null);
  $data=$outer[$d['field']]??[];
  if (is_string($data)) $data=self::json($data);
  if (!is_array($data)) throw new \RuntimeException('Неподдерживаемая структура галереи.');
  $names=$data[$d['storage']==='media'?'data':'gallery_image']??[];
  $captions=$d['storage']==='media'?($data['title']??[]):($data['gallery_image_title']??$data['caption']??$outer['caption']??[]);
  if (!is_array($names) || !is_array($captions)) throw new \RuntimeException('Списки изображений и подписей имеют неверный формат.');
  $entries=[];
  foreach ($names as $i=>$name) {
   if (!is_string($name)) throw new \RuntimeException('Неверное имя файла.');Files::filename($name);
   $caption=$captions[$i]??'';if (!is_string($caption)) throw new \RuntimeException('Неверный формат подписи.');
   $entries[]=['name'=>$name,'caption'=>$caption];
  }
  return ['entries'=>$entries,'featured'=>(string)($data[$d['storage']==='media'?'featured':'image_featured']??'')];
 }
 public static function write(string $key,array &$bundle,array $dataset,int $extensionId): void {
  $d=self::definition($key);$outer=$d['storage']==='media'?self::json($bundle['article']['media']):self::json($bundle['gallery']['value']??null);
  $data=$outer[$d['field']]??[];if(is_string($data))$data=self::json($data);if(!is_array($data))$data=[];
  $names=array_column($dataset['entries'],'name');$captions=array_column($dataset['entries'],'caption');
  $featured=in_array($dataset['featured'],$names,true)?$dataset['featured']:($names[0]??'');
  if($d['storage']==='media'){$data['data']=$names;$data['title']=$captions;$data['featured']=$featured;}
  else{$data['gallery_image']=$names;$data['gallery_image_title']=$captions;$data['image_featured']=$featured;unset($data['gallery_folder'],$data['gallery_source']);}
  $outer[$d['field']]=$data;
  if($d['storage']==='media')$bundle['article']['media']=self::encode($outer);
  else{
   if(!$bundle['gallery'])$bundle['gallery']=['id'=>null,'extension_id'=>$extensionId,'element'=>'gallery','content_id'=>(int)$bundle['article']['id'],'published'=>1,'ordering'=>0];
   $bundle['gallery']['value']=self::encode($outer);
  }
 }
 public static function activate(string $key,array &$bundle): void {
  $d=self::definition($key);$params=self::json($bundle['article']['attribs']);
  $bundle['article']['type']=$d['storage']==='media'?$key:'content_gallery';
  $params[$d['storage']==='media'?'mt_'.$key.'_show':'mt_gallery_show']='1';
  $bundle['article']['attribs']=self::encode($params);
  if($d['storage']==='addon')$bundle['gallery']['published']=1;
 }
}
