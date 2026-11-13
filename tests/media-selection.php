<?php
/** Dispatch contract: run inside a PHP CLI with the two installed add-ons. */
namespace Joomla\CMS {
 class Factory {public static function getApplication(){return new class {public function getLanguage(){return new class {public function getTag(){return 'ru-RU';}};}};}}
}
namespace TemPlaza\Component\TZ_Portfolio\Administrator\Library\AddOn {
 class AddOn {
  public function onContentPrepareForm($form,$data){return true;}
  public function onContentDisplayArticleView($context,&$article,$params,$page=0,$layout='default'){return 'listing';}
  protected function _getViewHtml($context,&$article,$params,$layout=null){return 'rendered';}
  public function setVariable($name,$value){}
 }
}
namespace {
 define('_JEXEC',1);
 $root=$argv[1]??'/var/www/html/components/com_tz_portfolio/add-ons';
 require $root.'/content/gallery/src/Extension/Gallery.php';
 require $root.'/mediatype/flipbook_gallery/src/Extension/Flipbook_gallery.php';
 function check($ok,$label){if(!$ok)throw new \RuntimeException($label);echo "PASS $label\n";}
 $form=new class {
  public $xml;
  public function __construct(){$this->xml=simplexml_load_string('<form><field name="type" type="mediatypes"><option value="none">None</option><option value="image">Image</option></field></form>');}
  public function getName(){return 'com_tz_portfolio.article';}
  public function getXml(){return $this->xml;}
 };
 $g=new \TemPlaza\Component\TZ_Portfolio\AddOn\Content\Gallery\Extension\Gallery();
 $g->onContentPrepareForm($form,null);$g->onContentPrepareForm($form,null);
 check(count($form->xml->xpath('//option[@value="content_gallery"]'))===1,'selector option added once');
 check((string)$form->xml->field->option[2]==='Галерея','Russian selector label');
 check(count($form->xml->xpath('//option[@value="image"]'))===1,'other selector options retained');
 foreach(['none','content_gallery','flipbook_gallery','image','grid_gallery','img_gallery'] as $type){
  $item=(object)['id'=>42,'type'=>$type,'media'=>(object)['flipbook_gallery'=>(object)['data'=>['photo.jpg'],'title'=>['Підпис']]]];
  $f=new \TemPlaza\Component\TZ_Portfolio\AddOn\Mediatype\Flipbook_gallery\Extension\Flipbook_gallery();
  $snapshot=serialize($item);
  check(($g->onContentDisplayMediaType('com_tz_portfolio.article',$item,null)!==null)===($type==='content_gallery'),"Gallery selection $type");
  check($g->onContentDisplayArticleView('com_tz_portfolio.article',$item,null)===null,'no duplicate content Gallery');
  check($g->onAfterDisplayAdditionInfo('com_tz_portfolio.article',$item,null)===null,'no duplicate addition-info Gallery');
  check(($f->onContentDisplayArticleView('com_tz_portfolio.article',$item,null)!==null)===($type==='flipbook_gallery'),"Flipbook fallback selection $type");
  check(serialize($item)===$snapshot,'photos and captions untouched');
 }
}
