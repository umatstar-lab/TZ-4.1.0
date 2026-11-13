<?php
namespace Umatstar\Component\TzMediaTransfer\Administrator\View\Transfer;
defined('_JEXEC') or die;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseView;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
class HtmlView extends BaseView {
 public function display($tpl=null) {
  ToolbarHelper::title('Перенос изображений TZ Portfolio', 'images');
  if(Factory::getApplication()->getIdentity()->authorise('core.admin','com_tz_media_transfer'))ToolbarHelper::preferences('com_tz_media_transfer');
  $doc=Factory::getApplication()->getDocument();
  $doc->addScriptOptions('com_tz_media_transfer',['url'=>'index.php?option=com_tz_media_transfer&task=api.run&format=raw','token'=>Session::getFormToken()]);
  $wa=$doc->getWebAssetManager();$base=Uri::root().'media/com_tz_media_transfer/';
  $wa->registerAndUseStyle('com_tz_media_transfer.app',$base.'app.css',['version'=>'1.0.5']);
  $wa->registerAndUseScript('com_tz_media_transfer.app',$base.'app.js',['version'=>'1.0.5'],['defer'=>true],['core']);
  parent::display($tpl);
 }
}
