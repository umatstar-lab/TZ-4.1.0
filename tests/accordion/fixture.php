<?php
if(PHP_SAPI!=='cli')exit(1);
require '/var/www/html/configuration.php';$c=new JConfig();if($c->dbprefix!=='joom_'||!is_file('/tmp/tzmt-j62-fixtures-authorized'))throw new RuntimeException('Isolated Joomla 6 fixture required');
$db=new PDO('mysql:host='.$c->host.';dbname='.$c->db.';charset=utf8mb4',$c->user,$c->password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$db->exec("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");$p=$c->dbprefix;$id=91030;$backup='/tmp/tz-accordion-audit.json';
function insert($db,$table,$r){$db->prepare('INSERT INTO '.$table.' (`'.implode('`,`',array_keys($r)).'`) VALUES ('.implode(',',array_fill(0,count($r),'?')).')')->execute(array_values($r));return (int)$db->lastInsertId();}
$action=$argv[1]??'';
if($action==='prepare'){
 if(file_exists($backup)||$db->query("SELECT id FROM {$p}tz_portfolio_plus_content WHERE id=$id")->fetchColumn())throw new RuntimeException('Fixture occupied');
 $state=['rules'=>$db->query("SELECT rules FROM {$p}assets WHERE name='com_tz_portfolio'")->fetchColumn()];file_put_contents($backup,json_encode($state));chmod($backup,0600);
 $state['templates']=$db->query("SELECT id,params FROM {$p}tz_portfolio_plus_templates")->fetchAll(PDO::FETCH_ASSOC);
 foreach($state['templates'] as $style){$pr=json_decode($style['params'],true)?:[];$pr['use_single_layout_builder']=0;$db->prepare("UPDATE {$p}tz_portfolio_plus_templates SET params=? WHERE id=?")->execute([json_encode($pr),$style['id']]);}
 $state['group']=insert($db,$p.'usergroups',['parent_id'=>2,'lft'=>200000,'rgt'=>200001,'title'=>'TZ Accordion audit']);
 $user=$db->query("SELECT * FROM {$p}users WHERE username='developer'")->fetch(PDO::FETCH_ASSOC);unset($user['id']);$user['username']='tz_accordion_auditor';$user['name']='TZ Accordion audit';$user['email']='accordion-audit@example.test';$user['sendEmail']=0;$state['user']=insert($db,$p.'users',$user);insert($db,$p.'user_usergroup_map',['user_id'=>$state['user'],'group_id'=>$state['group']]);
 $rules=json_decode($state['rules'],true)?:[];foreach(['core.manage','core.edit','core.login.admin'] as $right)$rules[$right][(string)$state['group']]=true;
 $db->prepare("UPDATE {$p}assets SET rules=? WHERE name='com_tz_portfolio'")->execute([json_encode($rules)]);
 // Administrator login is a root action; retain its previous rules for cleanup.
 $state['root_rules']=$db->query("SELECT rules FROM {$p}assets WHERE name='root.1'")->fetchColumn();$rr=json_decode($state['root_rules'],true)?:[];$rr['core.login.admin'][(string)$state['group']]=true;$rr['core.edit'][(string)$state['group']]=true;$db->prepare("UPDATE {$p}assets SET rules=? WHERE name='root.1'")->execute([json_encode($rr)]);file_put_contents($backup,json_encode($state));
 $base=$db->query("SELECT * FROM {$p}tz_portfolio_plus_content WHERE state=1 AND id<90000 ORDER BY id LIMIT 1")->fetch(PDO::FETCH_ASSOC);$baseId=$base['id'];$base['id']=$id;$base['created_by']=$state['user'];$base['title']='Accordion security review';$base['alias']='accordion-security-review';$base['attribs']='{}';$base['media']='{}';$base['type']='none';$base['language']='*';$base['access']=1;$base['checked_out']=0;$base['checked_out_time']='0000-00-00 00:00:00';$base['introtext']='<p>Isolated compatibility fixture.</p>';$base['fulltext']='';insert($db,$p.'tz_portfolio_plus_content',$base);
 foreach($db->query("SELECT * FROM {$p}tz_portfolio_plus_content_category_map WHERE contentid=".(int)$baseId)->fetchAll(PDO::FETCH_ASSOC) as $r){unset($r['id']);$r['contentid']=$id;insert($db,$p.'tz_portfolio_plus_content_category_map',$r);}
 echo json_encode(['article'=>$id,'user'=>$state['user']]);
}elseif($action==='all')echo json_encode($db->query("SELECT element,extension_id,value FROM {$p}tz_portfolio_plus_addon_data WHERE content_id=$id")->fetchAll(PDO::FETCH_ASSOC));
elseif($action==='snapshot')echo json_encode($db->query("SELECT value FROM {$p}tz_portfolio_plus_addon_data WHERE element='accordion' AND content_id=$id")->fetchColumn());
elseif($action==='normal' || $action==='malicious'){
 $ext=$db->query("SELECT id FROM {$p}tz_portfolio_plus_extensions WHERE element='accordion' AND folder='content'")->fetchColumn();$db->exec("DELETE FROM {$p}tz_portfolio_plus_addon_data WHERE element='accordion' AND content_id=$id");
 insert($db,$p.'tz_portfolio_plus_addon_data',['extension_id'=>$ext,'element'=>'accordion','content_id'=>$id,'value'=>json_encode(['title'=>'Review title','title_element'=>'h3','accordion_items'=>['accordion_items0'=>['title'=>'First','content'=>'<p>First body</p>'],'accordion_items1'=>['title'=>'Second','content'=>'<p>Second body</p>']]]),'published'=>1]);
 if($action==='malicious'){
 $value=['title'=>'<img src=x onerror="window.accordionProbe=1">Title','title_element'=>'script','custom_class'=>'ok" onmouseover="window.accordionProbe=2','title_color'=>'red;}body{display:none}/*','title_font'=>'{"fontFamily":"x;}body{display:none}","fontSize":"22px"}', 'accordion_items'=>['bad" onclick="window.accordionProbe=3'=>['title'=>'<svg onload="window.accordionProbe=4">Section','content'=>'<p>Safe paragraph <strong>bold</strong></p><img src=x onerror="window.accordionProbe=5"><a href="javascript:window.accordionProbe=6">Link</a><script>window.accordionProbe=7</script>'],'second'=>['title'=>'Second','content'=>'<p>Second body</p>']]];
 $db->prepare("UPDATE {$p}tz_portfolio_plus_addon_data SET value=? WHERE element='accordion' AND content_id=$id")->execute([json_encode($value)]);
 }
}elseif($action==='cleanup'){
 $db->exec("DELETE FROM {$p}tz_portfolio_plus_addon_data WHERE content_id=$id");$db->exec("DELETE FROM {$p}tz_portfolio_plus_content_category_map WHERE contentid=$id");$db->exec("DELETE FROM {$p}tz_portfolio_plus_content WHERE id=$id");
 if(file_exists($backup)){$s=json_decode(file_get_contents($backup),true);$db->prepare("UPDATE {$p}assets SET rules=? WHERE name='com_tz_portfolio'")->execute([$s['rules']]);if(isset($s['root_rules']))$db->prepare("UPDATE {$p}assets SET rules=? WHERE name='root.1'")->execute([$s['root_rules']]);if(isset($s['templates']))foreach($s['templates'] as $style)$db->prepare("UPDATE {$p}tz_portfolio_plus_templates SET params=? WHERE id=?")->execute([$style['params'],$style['id']]);if(isset($s['user'])){$db->exec("DELETE FROM {$p}user_usergroup_map WHERE user_id=".(int)$s['user']);$db->exec("DELETE FROM {$p}users WHERE id=".(int)$s['user']);}if(isset($s['group']))$db->exec("DELETE FROM {$p}usergroups WHERE id=".(int)$s['group']);unlink($backup);}
}else throw new RuntimeException('Unknown action');
