<?php
require '/var/www/html/configuration.php';$c=new JConfig();if($c->dbprefix!=='migrationj54_')throw new RuntimeException('Fixture database required.');$db=new PDO('mysql:host='.$c->host.';dbname='.$c->db.';charset=utf8mb4',$c->user,$c->password);
$out=['articles'=>[],'galleries'=>[],'files'=>[]];
foreach($db->query('SELECT id,title,state,media,attribs,type FROM '.$c->dbprefix.'tz_portfolio_plus_content WHERE id BETWEEN 91001 AND 91008 ORDER BY id',PDO::FETCH_ASSOC) as $r)$out['articles'][$r['id']]=$r;
foreach($db->query("SELECT * FROM {$c->dbprefix}tz_portfolio_plus_addon_data WHERE content_id BETWEEN 91001 AND 91008 AND element='gallery' ORDER BY content_id",PDO::FETCH_ASSOC) as $r)$out['galleries'][$r['content_id']]=$r;
foreach(['images/tz_portfolio/flipbook_gallery','images/tz_portfolio_plus/gallery'] as $base)for($i=91001;$i<=91008;$i++){
 $p='/var/www/html/'.$base.'/'.$i;if(!is_dir($p))continue;
 foreach(new RecursiveIteratorIterator(new RecursiveDirectoryIterator($p,FilesystemIterator::SKIP_DOTS)) as $f)if($f->isFile())$out['files'][substr($f->getPathname(),strlen('/var/www/html/'))]=hash_file('sha256',$f->getPathname());
}
ksort($out['files']);echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
