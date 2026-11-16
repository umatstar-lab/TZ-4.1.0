<?php
if(PHP_SAPI!=='cli') exit(1);
define('_JEXEC',1);define('JPATH_BASE','/var/www/html');require JPATH_BASE.'/includes/defines.php';require JPATH_BASE.'/includes/framework.php';
require JPATH_BASE.'/components/com_tz_portfolio/add-ons/content/accordion/src/Extension/SafeContent.php';
use TemPlaza\Component\TZ_Portfolio\AddOn\Content\Accordion\Extension\SafeContent as S;
$bad=['<script>alert(1)</script>','<img src=x onerror=alert(1)>','<svg onload=alert(1)>','<a href="javascript:alert(1)">x</a>','<a href="jav&#x61;script:alert(1)">x</a>','<iframe srcdoc="<script>alert(1)</script>"></iframe>','<div style="background:url(javascript:alert(1))">x</div>','<object data="data:text/html,evil"></object>','<form action="https://example.test"><input name=x></form>'];
foreach($bad as $html){$clean=S::html($html);$dom=new DOMDocument();@$dom->loadHTML('<body>'.$clean.'</body>');$xp=new DOMXPath($dom);if($xp->query('//script|//svg|//iframe|//object|//form|//@style|//@onerror|//@onload')->length)throw new RuntimeException('Unsafe node');foreach($xp->query('//@href|//@src') as $attr)if(preg_match('/^\s*(javascript|vbscript|data):/i',$attr->value))throw new RuntimeException('Unsafe URL');}
foreach(['null','true','123','"text"','{"md":"malformed"}','[1,2]'] as $value){$v=S::data(['title_font'=>$value,'accordion_margin'=>$value,'accordion_items'=>['bad'=>'text']]);if($v['accordion_items'])throw new RuntimeException('Malformed item not removed');}
foreach(['en-GB'=>'Accordion','ru-RU'=>'Аккордеон','uk-UA'=>'Акордеон'] as $tag=>$expected){$language=new Joomla\CMS\Language\Language($tag);if(!$language->load('tp_addon_content_accordion',JPATH_BASE.'/components/com_tz_portfolio/add-ons/content/accordion',$tag,true,false))throw new RuntimeException('Language load failed: '.$tag);if($language->_('PLG_CONTENT_ACCORDION_TITLE')!==$expected)throw new RuntimeException('Translation mismatch');}
echo "PASS HTML policy matrix, malformed settings, native EN/RU/UK language loading\n";
