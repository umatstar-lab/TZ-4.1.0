<?php
namespace Joomla\CMS { class Factory { public static $app; public static function getApplication(){return self::$app;} } }
namespace Joomla\CMS\Uri { class Uri {public static function root(){return 'http://127.0.0.1:8765/';}} }
namespace Joomla\Filesystem { class File {public static function exists($p){return is_file($p);}public static function stripExt($p){return pathinfo($p,PATHINFO_FILENAME);}public static function getExt($p){return pathinfo($p,PATHINFO_EXTENSION);}} }
namespace Joomla\Registry {class Registry {private $a;public function __construct($a=[]){$this->a=is_array($a)?$a:[];}public function get($k,$v=null){return $this->a[$k]??$v;}}}
namespace TemPlaza\Component\TZ_Portfolio\Site\Helper {class ArticleHelper {public static function font_style($font){return '';}}}
namespace Joomla\CMS\MVC\View {
class HtmlView {
 public $model;
 public function getModel(){return $this->model;}
 public function display($tpl=null){
 $wa=\Joomla\CMS\Factory::$app->getDocument()->getWebAssetManager();
 echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><style>body{font:16px Arial;margin:20px}*{box-sizing:border-box}</style>';
 foreach($wa->styles as $path)echo '<link rel="stylesheet" href="'.$path.'">';
 echo '<style>'.implode('\n',$wa->inlineStyles).'</style></head><body>';
 include '/tmp/gallery-addon/tmpl/article/default.php';
 echo '<script src="/media/vendor/jquery/js/jquery.min.js"></script>';
 foreach($wa->scripts as $path) echo '<script src="'.$path.'"></script>';
 foreach($wa->inlineScripts as $script)echo '<script>'.$script.'</script>';
 echo '</body></html>';
 }
}
}
namespace {
define('_JEXEC',1);define('JPATH_ROOT','/tmp/gallery-test-images');
class Assets {
 public $styles=[],$scripts=[],$inlineStyles=[],$inlineScripts=[];
 public function assetExists($type,$key){return false;}
 public function registerStyle($key,$uri,...$args){$this->styles[$key]=$uri;return $this;}
 public function registerScript($key,$uri,...$args){$this->scripts[$key]=$uri;return $this;}
 public function useStyle($key){return $this;}public function useScript($key){return $this;}
 public function registerAndUseStyle(...$args){return $this->registerStyle(...$args);}
 public function registerAndUseScript(...$args){return $this->registerScript(...$args);}
 public function addInlineStyle($css){$this->inlineStyles[]=$css;return $this;}
 public function addInlineScript($js){$this->inlineScripts[]=$js;return $this;}
}
$wa=new Assets();$document=new class($wa){private $wa;public function __construct($wa){$this->wa=$wa;}public function getWebAssetManager(){return $this->wa;}};
\Joomla\CMS\Factory::$app=new class($document){private $doc;public function __construct($doc){$this->doc=$doc;}public function getDocument(){return $this->doc;}};
require '/tmp/gallery-addon/src/View/Article/HtmlView.php';
$format=$argv[1]??'json';$type=$argv[2]??'masonry';
$data=['gallery_image'=>array_map(fn($i)=>'image-'.$i.'.svg',range(1,8)),'gallery_image_title'=>array_map(fn($i)=>'Підпис фотографії '.$i,range(1,8))];
$content=$format==='json'?json_encode($data):($format==='object'?(object)$data:($format==='invalid'?'invalid-json':$data));
$gallery=(object)['gallery_content'=>$content];
if($format==='root-array')$gallery=(array)$gallery;
if($format==='root-json')$gallery=json_encode($gallery);
$model=new class($gallery,$type){private $data,$type;public function __construct($data,$type){$this->data=$data;$this->type=$type;}public function getAddonData(){return $this->data;}public function getItem(){return (object)['id'=>42];}public function getAddon(){return (object)['params'=>new \Joomla\Registry\Registry()];}public function getState($key){return new \Joomla\Registry\Registry(['mt_gallery_type'=>$this->type,'mt_gallery_width'=>400,'mt_gallery_height'=>250,'mt_gallery_carousel_autoplay'=>0]);}};
$view=new \TemPlaza\Component\TZ_Portfolio\AddOn\Content\Gallery\Site\View\Article\HtmlView();$view->model=$model;$view->display();
if($format!=='invalid' && count($wa->styles)===0)throw new \RuntimeException('Gallery CSS was not registered');
}
