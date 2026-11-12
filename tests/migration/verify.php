<?php
/** Integration test: run against an ISOLATED fixture only, never a live site.
 * php verify.php /tmp/migration-site /tmp/migration-backups /tmp/migrate-flipbook.php
 * Fixture: component tables imported from a backup under a separate prefix;
 * installed manifests + enabled component/Gallery registry; synthetic originals.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
[$script,$site,$backupDir,$library]=$argv;
require $library;
require_once $site.'/configuration.php';
$c=new JConfig();
if (!str_starts_with($c->dbprefix,'migration273_')) throw new RuntimeException('Test fixture prefix required.');
$db=new PDO('mysql:host='.$c->host.';dbname='.$c->db.';charset=utf8mb4',$c->user,$c->password,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
function check(bool $ok,string $message): void { if (!$ok) throw new RuntimeException($message); echo 'PASS '.$message.PHP_EOL; }
function snapshot(PDO $db, string $prefix): array {
 $out=[];
 foreach(['tz_portfolio_plus_content','tz_portfolio_plus_addon_data','menu','tz_portfolio_plus_content_category_map','tz_portfolio_plus_categories','tz_portfolio_plus_tag_content_map','tz_portfolio_plus_field_content_map'] as $t) {
  $data=$db->query('SELECT * FROM `'.$prefix.$t.'`')->fetchAll(PDO::FETCH_ASSOC);
  usort($data,fn($a,$b)=>strcmp(encode($a),encode($b)));$out[$t]=$data;
 }
 return $out;
}
$before=snapshot($db,$c->dbprefix);
$pre=migrate(['site'=>$site]);
check($pre['ready'] && $pre['galleries']===9 && $pre['photos']===156 && $pre['captions']===156 && $pre['trash_skipped']===4,'9 galleries, 156 photos/captions; 4 trashed articles excluded');
check($before===snapshot($db,$c->dbprefix),'dry run leaves all tables unchanged');
$done=migrate(['site'=>$site,'apply'=>true,'backup-dir'=>$backupDir]);
check($done['status']==='committed' && $done['menus']===9,'commit migrates 9 menu links');
$after=snapshot($db,$c->dbprefix);
$articles=array_column($after['tz_portfolio_plus_content'],null,'id');
$galleries=[];foreach($after['tz_portfolio_plus_addon_data'] as $r) if($r['element']==='gallery') $galleries[$r['content_id']]=$r;
$unchangedTrash=true;$allImages=true;$allCaptions=true;$sourceFiles=true;$allOtherFields=true;
foreach($before['tz_portfolio_plus_content'] as $a){
 $id=$a['id'];$new=$articles[$id];
 if((int)$a['state']===-2){$unchangedTrash=$unchangedTrash && $a===$new;continue;}
 if(!in_array($a['type'],['flipbook_gallery','grid_gallery']))continue;
 $src=objectData($a['media'])[$a['type']];$dst=objectData($galleries[$id]['value'])['gallery_content'];
 $allImages=$allImages && $src['data']===$dst['gallery_image'];
 $allCaptions=$allCaptions && $src['title']===$dst['gallery_image_title'];
 foreach($src['data'] as $f){$oldPath=$site.'/images/tz_portfolio_plus/'.$a['type'].'/'.$id.'/'.$f;$newPath=$site.'/images/tz_portfolio_plus/gallery/'.$id.'/'.$f;$sourceFiles=$sourceFiles && is_file($oldPath) && hash_file('sha256',$oldPath)===hash_file('sha256',$newPath);}
 unset($a['type'],$a['attribs'],$new['type'],$new['attribs']);$allOtherFields=$allOtherFields && $a===$new;
}
check($unchangedTrash,'trashed article rows unchanged byte for byte');
check($allImages && $allCaptions,'all filenames, order and Unicode captions match source exactly');
check($sourceFiles,'every destination checksum matches retained source');
check($allOtherFields,'IDs, texts, aliases, languages, dates, authors, media and access preserved');
foreach(['tz_portfolio_plus_categories','tz_portfolio_plus_content_category_map','tz_portfolio_plus_tag_content_map','tz_portfolio_plus_field_content_map'] as $t) check($before[$t]===$after[$t],$t.' preserved');
$again=migrate(['site'=>$site,'apply'=>true,'backup-dir'=>$backupDir]);
check($again['status']==='already_migrated' && $again['galleries']===0 && $after===snapshot($db,$c->dbprefix),'rerun has no duplicates or database changes');
$id=$done['items'][0]['id'];$q=$db->prepare('UPDATE '.$c->dbprefix.'tz_portfolio_plus_content SET type=? WHERE id=?');$q->execute(['image',$id]);
$refused=false;try{migrate(['site'=>$site,'rollback'=>$done['backup']]);}catch(RuntimeException $e){$refused=true;}
check($refused,'rollback refuses to overwrite a later edit');$q->execute(['none',$id]);
$back=migrate(['site'=>$site,'rollback'=>$done['backup']]);
check($back['status']==='rolled_back' && $before===snapshot($db,$c->dbprefix),'rollback restores all database rows exactly');
$eligible=array_values(array_filter($before['tz_portfolio_plus_content'],fn($r)=>(int)$r['state']!==-2 && $r['type']==='flipbook_gallery'));$a=$eligible[0];
$f=objectData($a['media'])['flipbook_gallery']['data'][0];$src=$site.'/images/tz_portfolio_plus/flipbook_gallery/'.$a['id'].'/'.$f;$dst=$site.'/images/tz_portfolio_plus/gallery/'.$a['id'].'/'.$f;
rename($src,$src.'.test-missing');
try{
 $r=migrate(['site'=>$site]);check(!$r['ready'] && count($r['missing_files'])===1,'missing original is detected');
 $refused=false;try{migrate(['site'=>$site,'apply'=>true,'backup-dir'=>$backupDir]);}catch(RuntimeException $e){$refused=true;}
 check($refused && $before===snapshot($db,$c->dbprefix),'missing image blocks apply before any database changes');
}finally{rename($src.'.test-missing',$src);}
$bytes=file_get_contents($dst);file_put_contents($dst,'different bytes');
try{$r=migrate(['site'=>$site]);check(!$r['ready'] && count($r['conflicts'])===1,'different destination file is not overwritten');}finally{file_put_contents($dst,$bytes);}
$refused=false;try{filename('../escape.jpg');}catch(RuntimeException $e){$refused=true;}check($refused,'path traversal is rejected');
check($before===snapshot($db,$c->dbprefix),'failure checks leave every database table unchanged');
echo 'Migration integration checks completed.'.PHP_EOL;
