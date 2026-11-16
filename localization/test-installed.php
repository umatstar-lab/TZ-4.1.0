<?php
if(PHP_SAPI!=='cli')exit(1);
define('_JEXEC',1);define('JPATH_BASE','/var/www/html');require JPATH_BASE.'/includes/defines.php';require JPATH_BASE.'/includes/framework.php';
$expect=json_decode(file_get_contents('/tmp/tz-loc-expected.json'),true,32,JSON_THROW_ON_ERROR);$count=0;
foreach($expect as $target=>$row){$path=JPATH_ROOT.'/'.$target;if(!is_file($path)||hash_file('sha256',$path)!==$row['sha256'])throw new RuntimeException('Installed file mismatch: '.$target);
 $data=Joomla\CMS\Language\LanguageHelper::parseIniFile($path);if(!is_array($data)||count($data)!==count($row['values']))throw new RuntimeException('INI parse or key count: '.$target);
 foreach($row['values'] as $key=>$value)if(($data[$key]??null)!==$value)throw new RuntimeException('Value mismatch: '.$target.' '.$key.' actual='.($data[$key]??'MISSING').' expected='.$value);
 $lang=new Joomla\CMS\Language\Language($row['lang'],false);
 if(!$lang->load($row['extension'],JPATH_ROOT.($row['base']?'/'.$row['base']:''),$row['lang'],true,false))throw new RuntimeException('Native language load failed: '.$target);
 foreach($row['values'] as $key=>$value)if($lang->_($key)!==$value)throw new RuntimeException('Native language lookup mismatch: '.$target.' '.$key);
 $count++;
}
echo 'PASS '.$count.' installed INI files: hashes, all keys/values, native Joomla language load and lookup.'.PHP_EOL;
