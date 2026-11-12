<?php
// Contract checks for the explicit data adapters. No database or site writes.
define('_JEXEC',1);
require '/var/www/html/administrator/components/com_tz_media_transfer/src/Service/Files.php';
require '/var/www/html/administrator/components/com_tz_media_transfer/src/Service/Adapters.php';
use Umatstar\Component\TzMediaTransfer\Administrator\Service\Adapters;
use Umatstar\Component\TzMediaTransfer\Administrator\Service\Files;
function check(bool $ok,string $what): void {if(!$ok)throw new RuntimeException($what);echo 'PASS '.$what.PHP_EOL;}
$b=['article'=>['id'=>1,'media'=>Adapters::encode(['image'=>['url'=>'keep.jpg'],'flipbook_gallery'=>['data'=>['a.jpg','b.jpg'],'title'=>['Підпис А','Підпис Б'],'featured'=>'b.jpg'],'grid_gallery'=>['data'=>['old.jpg'],'title'=>['Стара підпис'],'featured'=>'old.jpg']]),'type'=>'flipbook_gallery','attribs'=>'{}'],'gallery'=>null];
$s=Adapters::read('flipbook_gallery',$b);$g=Adapters::read('grid_gallery',$b);$g['entries']=array_merge($g['entries'],$s['entries']);Adapters::write('grid_gallery',$b,$g,12);$s['entries']=[];Adapters::write('flipbook_gallery',$b,$s,11);$m=Adapters::json($b['article']['media']);
check($m['grid_gallery']['data']===['old.jpg','a.jpg','b.jpg'],'Grid Gallery data/title contract and append order');
check($m['grid_gallery']['title']===['Стара підпис','Підпис А','Підпис Б'],'Unicode captions retained');check($m['flipbook_gallery']['data']===[],'source media field cleared without overwriting destination');check($m['image']['url']==='keep.jpg','unrelated media fields preserved');
$gallery=['entries'=>[['name'=>'x.jpg','caption'=>'<b>text</b>']],'featured'=>'x.jpg'];Adapters::write('gallery',$b,$gallery,23);check(Adapters::read('gallery',$b)===$gallery,'Content Gallery round trip without HTML interpretation');
$b['gallery']['value']=Adapters::encode(['gallery_content'=>['gallery_image'=>['x.jpg'],'caption'=>['legacy caption']]]);check(Adapters::read('gallery',$b)['entries'][0]['caption']==='legacy caption','legacy Gallery caption alias read safely');
foreach(['../escape.jpg','x.php','x.jpg/../../a.jpg',"bad\0.jpg"] as $name){$blocked=false;try{Files::filename($name);}catch(RuntimeException $e){$blocked=true;}check($blocked,'unsafe filename rejected');}
