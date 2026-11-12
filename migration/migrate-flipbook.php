<?php
/** TZ Portfolio Plus 2.7.3 -> TZ Portfolio 4.1.2: local, CLI-only migration.
 * Default: read-only preflight. Requires PHP >= 8.4.1 and pdo_mysql.
 * No database credentials are accepted on the command line or printed.
 */
declare(strict_types=1);
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }

function encode(mixed $value): string {
    return json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
}
function objectData(?string $json): array {
    if ($json === null || $json === '') return [];
    $v = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
    if (!is_array($v)) throw new RuntimeException('Expected a JSON object/array.');
    return $v;
}
function filename(string $name): string {
    if ($name === '' || $name === '.' || $name === '..' || strpbrk($name, "/\\\0") !== false) {
        throw new RuntimeException('Unsafe image filename.');
    }
    return $name;
}
function inside(string $path, string $root): bool {
    return $path === $root || str_starts_with($path, $root . DIRECTORY_SEPARATOR);
}
function safePath(string $path, string $root): void {
    $p = $path;
    while (!file_exists($p) && !is_link($p)) {
        $next = dirname($p);
        if ($next === $p) throw new RuntimeException('Cannot resolve path.');
        $p = $next;
    }
    $real = realpath($p);
    if ($real === false || !inside($real, $root) || is_link($p)) {
        throw new RuntimeException('Path is outside the site or is a symbolic link: ' . $path);
    }
}
function rows(PDO $db, string $sql, array $args = []): array {
    $s = $db->prepare($sql); $s->execute($args); return $s->fetchAll(PDO::FETCH_ASSOC);
}
function setRow(PDO $db, string $table, array $values, int $id): void {
    $names = array_keys($values);
    $sql = 'UPDATE ' . $table . ' SET ' . implode(',', array_map(fn($n) => '`'.$n.'`=?', $names)) . ' WHERE id=?';
    $s = $db->prepare($sql); $s->execute([...array_values($values), $id]);
}
function versionCheck(string $path, string $expected, string $label): void {
    if (!is_file($path)) throw new RuntimeException($label . ' is not installed: ' . $path);
    $xml = simplexml_load_file($path);
    if (!$xml || (string)$xml->version !== $expected) throw new RuntimeException($label . ': expected version ' . $expected);
}
function migrate(array $options): array {
    if (PHP_VERSION_ID < 80401) throw new RuntimeException('Use PHP 8.4.1 or newer (also for the website).');
    $site = realpath((string)($options['site'] ?? ''));
    if (!$site || !is_file($site.'/configuration.php')) throw new RuntimeException('--site must be the Joomla root.');
    require_once $site.'/configuration.php';
    $config = new JConfig();
    if (!preg_match('/^[a-zA-Z0-9_]+$/D', $config->dbprefix)) throw new RuntimeException('Invalid database prefix.');
    $host = $config->host;
    $dsn = 'mysql:host='.$host.';dbname='.$config->db.';charset=utf8mb4';
    if (str_contains($host, ':') && !str_starts_with($host, '[')) {
        [$hostname, $port] = explode(':', $host, 2);
        $dsn = ctype_digit($port) ? 'mysql:host='.$hostname.';port='.$port.';dbname='.$config->db.';charset=utf8mb4'
            : 'mysql:unix_socket='.$port.';dbname='.$config->db.';charset=utf8mb4';
    }
    $db = new PDO($dsn, $config->user, $config->password, [PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_EMULATE_PREPARES=>false]);
    $db->exec("SET SESSION sql_mode='NO_AUTO_VALUE_ON_ZERO'");
    $table = fn(string $s): string => '`'.$config->dbprefix.$s.'`';
    $ct = $table('tz_portfolio_plus_content'); $dt = $table('tz_portfolio_plus_addon_data');
    $mt = $table('menu'); $et = $table('extensions'); $at = $table('tz_portfolio_plus_extensions');
    $apply = isset($options['apply']);
    if (isset($options['apply']) && isset($options['rollback'])) throw new RuntimeException('Choose apply OR rollback.');
    // Rollback only reverses this script, never the component installation itself.
    if (isset($options['rollback'])) {
        $backup = objectData(file_get_contents((string)$options['rollback']));
        if (($backup['site'] ?? '') !== $site || ($backup['prefix'] ?? '') !== $config->dbprefix || ($backup['database'] ?? '') !== $config->db) {
            throw new RuntimeException('Backup belongs to another site/database.');
        }
        if (($backup['status'] ?? '') !== 'committed') throw new RuntimeException('Only a committed migration can be rolled back.');
        $db->beginTransaction();
        try {
            foreach ($backup['changes'] as $change) {
                $t = match($change['table']) {'article'=>$ct, 'addon'=>$dt, 'menu'=>$mt, default=>throw new RuntimeException('Unknown backup table.')};
                $current = rows($db,'SELECT * FROM '.$t.' WHERE id=? FOR UPDATE',[$change['id']]);
                if (count($current)!==1) throw new RuntimeException('Row was removed after migration.');
                foreach ($change['after'] as $key=>$value) {
                    if ((string)$current[0][$key] !== (string)$value) throw new RuntimeException('Row was edited after migration; rollback refused.');
                }
            }
            foreach (array_reverse($backup['changes']) as $change) {
                $t = match($change['table']) {'article'=>$ct,'addon'=>$dt,'menu'=>$mt};
                if ($change['inserted']) {
                    $s=$db->prepare('DELETE FROM '.$t.' WHERE id=?'); $s->execute([$change['id']]);
                } else setRow($db,$t,$change['before'],$change['id']);
            }
            $db->commit();
        } catch (Throwable $e) { if ($db->inTransaction()) $db->rollBack(); throw $e; }
        return ['status'=>'rolled_back','restored_rows'=>count($backup['changes']),'note'=>'Copied images are retained; component installation is not reverted.'];
    }
    versionCheck($site.'/administrator/components/com_tz_portfolio/tz_portfolio.xml','4.1.2','TZ Portfolio');
    versionCheck($site.'/components/com_tz_portfolio/add-ons/content/gallery/gallery.xml','2.1.4','Gallery');
    $component = rows($db,'SELECT extension_id FROM '.$et." WHERE type='component' AND element='com_tz_portfolio' AND enabled=1");
    $addons = rows($db,'SELECT id FROM '.$at." WHERE folder='content' AND element='gallery' AND published=1");
    if (count($component)!==1 || count($addons)!==1) throw new RuntimeException('Enable TZ Portfolio and the Gallery content add-on first.');
    $galleryId=(int)$addons[0]['id']; $componentId=(int)$component[0]['extension_id'];
    $articles=rows($db,'SELECT * FROM '.$ct.' ORDER BY id');
    $byId=array_column($articles,null,'id');
    $report=['status'=>'preflight','materials_total'=>count($articles),'trash_skipped'=>0,'galleries'=>0,'photos'=>0,'captions'=>0,'menus'=>0,'menus_to_trash_skipped'=>0,'files_to_copy'=>0,'existing_identical_files'=>0,'missing_files'=>[],'conflicts'=>[],'warnings'=>[],'items'=>[]];
    $plans=[]; $copies=[]; $menus=[];
    foreach ($articles as $a) {
        if ((int)$a['state']===-2) { $report['trash_skipped']++; continue; }
        if (!in_array($a['type'],['flipbook_gallery','grid_gallery'],true)) continue;
        $id=(int)$a['id']; $media=objectData($a['media']); $legacy=$media[$a['type']] ?? null;
        if (!is_array($legacy) || !isset($legacy['data']) || !is_array($legacy['data'])) throw new RuntimeException('Malformed source gallery, article '.$id);
        $images=array_values($legacy['data']); $titles=array_values($legacy['title'] ?? []);
        if (!$images || count($images)!==count($titles)) throw new RuntimeException('Missing images or mismatched captions, article '.$id);
        if (count($images)!==count(array_unique($images))) throw new RuntimeException('Duplicate source images, article '.$id);
        foreach ($images as $n) { if (!is_string($n)) throw new RuntimeException('Non-string filename.'); filename($n); }
        foreach ($titles as $t) { if (!is_string($t)) throw new RuntimeException('Non-string caption.'); }
        $oldRows=rows($db,'SELECT * FROM '.$dt.' WHERE content_id=? AND element=?',[$id,'gallery']);
        if (count($oldRows)>1) throw new RuntimeException('Multiple gallery rows, article '.$id);
        $old=$oldRows[0] ?? null; $value=$old ? objectData($old['value']) : [];
        $existing=$value['gallery_content'] ?? [];
        if (is_string($existing)) $existing=objectData($existing);
        if (!is_array($existing)) throw new RuntimeException('Malformed destination gallery.');
        $previousImages=array_values($existing['gallery_image'] ?? []);
        $previousTitles=array_values($existing['gallery_image_title'] ?? []);
        // Preserve nonempty destination captions; never silently replace conflicting text.
        foreach ($previousImages as $i=>$name) {
            $pos=array_search($name,$images,true);
            if ($pos===false) { $report['conflicts'][]="Article $id: destination contains an extra image; manual merge required."; continue; }
            $caption=$previousTitles[$i] ?? '';
            if ($caption!=='' && $titles[$pos]!=='' && $caption!==$titles[$pos]) $report['conflicts'][]="Article $id: different caption for $name";
            elseif ($titles[$pos]==='' && $caption!=='') $titles[$pos]=$caption;
        }
        $featured=(string)($legacy['featured'] ?? '');
        if ($featured!=='' && !in_array($featured,$images,true)) { $report['warnings'][]="Article $id: unknown featured image; using first image."; $featured=''; }
        $value['gallery_content']=['gallery_image'=>$images,'gallery_image_title'=>$titles,'image_featured'=>$featured!==''?$featured:$images[0]];
        $attribs=objectData($a['attribs']);
        $attribs=array_replace($attribs,['mt_gallery_show'=>'1','mt_gallery_size'=>'o','mt_gallery_type'=>'masonry','mt_gallery_width'=>'400','mt_grid_image_title_position'=>'under_image']);
        $afterArticle=['type'=>'none','attribs'=>encode($attribs)];
        $afterAddon=['extension_id'=>$galleryId,'element'=>'gallery','value'=>encode($value),'content_id'=>$id,'published'=>1];
        $plans[]=['article'=>$a,'old'=>$old,'article_after'=>$afterArticle,'addon_after'=>$afterAddon];
        $report['galleries']++; $report['photos']+=count($images); $report['captions']+=count(array_filter($titles,fn($t)=>$t!==''));
        $report['items'][]=['id'=>$id,'source'=>$a['type'],'photos'=>count($images),'existing_gallery'=>$old!==null];
        $source=$site.'/images/tz_portfolio_plus/'.$a['type'].'/'.$id;
        $dest=$site.'/images/tz_portfolio_plus/gallery/'.$id;
        foreach ($images as $name) {
            $src=$source.'/'.$name; $dst=$dest.'/'.$name;
            safePath($src,$site); safePath($dst,$site);
            // An existing destination is sufficient only when the original can verify it.
            if (!is_file($src) || !is_readable($src)) { $report['missing_files'][]=substr($src,strlen($site)+1); continue; }
            $hash=hash_file('sha256',$src);
            if (file_exists($dst)) {
                if (!is_file($dst) || hash_file('sha256',$dst)!==$hash) $report['conflicts'][]='Different destination file: '.substr($dst,strlen($site)+1);
                else $report['existing_identical_files']++;
            } else $copies[$dst]=['source'=>$src,'destination'=>$dst,'sha256'=>$hash];
        }
        // Copy only derivatives of the listed original filenames, when present.
        if (is_dir($source.'/resize')) {
            safePath($source.'/resize',$site);
            foreach (new DirectoryIterator($source.'/resize') as $entry) {
                if (!$entry->isFile() || $entry->isLink()) continue;
                $name=$entry->getFilename(); $related=false;
                foreach ($images as $img) {
                    if (str_starts_with($name,pathinfo($img,PATHINFO_FILENAME).'_') && strtolower($entry->getExtension())===strtolower(pathinfo($img,PATHINFO_EXTENSION))) { $related=true; break; }
                }
                if (!$related) continue;
                $src=$entry->getPathname(); $dst=$dest.'/resize/'.$name; safePath($src,$site);safePath($dst,$site);$hash=hash_file('sha256',$src);
                if (file_exists($dst)) { if (!is_file($dst) || hash_file('sha256',$dst)!==$hash) $report['conflicts'][]='Different resized file: '.substr($dst,strlen($site)+1); }
                else $copies[$dst]=['source'=>$src,'destination'=>$dst,'sha256'=>$hash];
            }
        }
    }
    foreach (rows($db,'SELECT * FROM '.$mt.' WHERE client_id=0 AND type=?',['component']) as $m) {
        if (!preg_match('/(?:^|[?&])option=com_tz_portfolio_plus(?:&|$)/',$m['link'])) continue;
        parse_str((string)parse_url($m['link'],PHP_URL_QUERY),$query);
        if (($query['view'] ?? '')==='article') {
            $target=$byId[(int)($query['id'] ?? 0)] ?? null;
            if (!$target || (int)$target['state']===-2) { $report['menus_to_trash_skipped']++; continue; }
        }
        if ((int)$m['published']===-2) continue;
        $after=['link'=>preg_replace('/([?&])option=com_tz_portfolio_plus(?=&|$)/','$1option=com_tz_portfolio',$m['link']),'component_id'=>$componentId];
        $menus[]=['before'=>$m,'after'=>$after];
    }
    $report['menus']=count($menus); $report['files_to_copy']=count($copies);
    $report['ready']=!$report['missing_files'] && !$report['conflicts'];
    if (!$apply) return $report;
    if (!$report['ready']) throw new RuntimeException('Preflight failed: '.encode($report));
    if (!$plans && !$menus) { $report['status']='already_migrated'; return $report; }
    $backupDir=realpath((string)($options['backup-dir'] ?? ''));
    if (!$backupDir || !is_dir($backupDir) || !is_writable($backupDir) || inside($backupDir,$site)) throw new RuntimeException('--backup-dir must be an existing writable directory OUTSIDE the public site root.');
    // Prevent two copies of this tool running together.
    $lock=fopen($backupDir.'/tz-migration-'.hash('sha256',$site).'.lock','c');
    if (!$lock || !flock($lock,LOCK_EX|LOCK_NB)) throw new RuntimeException('Another migration is running.');
    $backup=['format'=>1,'site'=>$site,'prefix'=>$config->dbprefix,'database'=>$config->db,'status'=>'prepared','changes'=>[],'files'=>array_values($copies)];
    $backupPath=$backupDir.'/tz-migration-'.gmdate('Ymd-His').'-'.bin2hex(random_bytes(4)).'.json';
    foreach ($plans as $p) {
        $backup['changes'][]=['table'=>'article','id'=>(int)$p['article']['id'],'inserted'=>false,'before'=>array_intersect_key($p['article'],$p['article_after']),'after'=>$p['article_after']];
        if ($p['old']) $backup['changes'][]=['table'=>'addon','id'=>(int)$p['old']['id'],'inserted'=>false,'before'=>array_intersect_key($p['old'],$p['addon_after']),'after'=>$p['addon_after']];
    }
    foreach ($menus as $m) $backup['changes'][]=['table'=>'menu','id'=>(int)$m['before']['id'],'inserted'=>false,'before'=>array_intersect_key($m['before'],$m['after']),'after'=>$m['after']];
    if (file_put_contents($backupPath,encode($backup),LOCK_EX)===false) throw new RuntimeException('Cannot write backup.');
    chmod($backupPath,0600);
    $created=[];
    $db->beginTransaction();
    try {
        foreach ([$ct,$dt,$mt] as $t) {
            $tableName=trim($t,'`');
            $engine=rows($db,'SELECT ENGINE FROM information_schema.TABLES WHERE TABLE_SCHEMA=DATABASE() AND TABLE_NAME=?',[$tableName]);
            if (strtoupper($engine[0]['ENGINE'] ?? '')!=='INNODB') throw new RuntimeException('Migration requires InnoDB tables.');
        }
        foreach ($plans as $p) {
            $current=rows($db,'SELECT * FROM '.$ct.' WHERE id=? FOR UPDATE',[$p['article']['id']]);
            if ($current!==[$p['article']]) throw new RuntimeException('Article changed during preflight.');
            $currentAddon=rows($db,'SELECT * FROM '.$dt.' WHERE content_id=? AND element=? FOR UPDATE',[$p['article']['id'],'gallery']);
            if ($currentAddon!==($p['old']?[$p['old']]:[])) throw new RuntimeException('Gallery changed during preflight.');
        }
        foreach ($menus as $m) if (rows($db,'SELECT * FROM '.$mt.' WHERE id=? FOR UPDATE',[$m['before']['id']])!==[$m['before']]) throw new RuntimeException('Menu changed during preflight.');
        foreach ($copies as $copy) {
            $src=$copy['source'];$dst=$copy['destination'];safePath($src,$site);safePath($dst,$site);
            if (hash_file('sha256',$src)!==$copy['sha256']) throw new RuntimeException('Source image changed during preflight.');
            if (!is_dir(dirname($dst)) && !mkdir(dirname($dst),0755,true) && !is_dir(dirname($dst))) throw new RuntimeException('Cannot create destination directory.');
            $out=@fopen($dst,'xb'); if (!$out) throw new RuntimeException('Destination exists or cannot be written.');
            $created[]=$dst; $in=fopen($src,'rb');
            try { if (!$in || stream_copy_to_stream($in,$out)===false) throw new RuntimeException('Copy failed.'); }
            finally { if (is_resource($in)) fclose($in); fclose($out); }
            chmod($dst,0644);
            if (hash_file('sha256',$dst)!==$copy['sha256']) throw new RuntimeException('Copied image checksum mismatch.');
        }
        foreach ($plans as $p) {
            setRow($db,$ct,$p['article_after'],(int)$p['article']['id']);
            if ($p['old']) setRow($db,$dt,$p['addon_after'],(int)$p['old']['id']);
            else {
                $v=$p['addon_after']; $s=$db->prepare('INSERT INTO '.$dt.' (`'.implode('`,`',array_keys($v)).'`) VALUES ('.implode(',',array_fill(0,count($v),'?')).')'); $s->execute(array_values($v));
                $backup['changes'][]=['table'=>'addon','id'=>(int)$db->lastInsertId(),'inserted'=>true,'before'=>[],'after'=>$v];
            }
        }
        foreach ($menus as $m) setRow($db,$mt,$m['after'],(int)$m['before']['id']);
        $backup['status']='committed';
        if (file_put_contents($backupPath,encode($backup),LOCK_EX)===false) throw new RuntimeException('Cannot finish backup.');
        $db->commit();
    } catch (Throwable $e) {
        if ($db->inTransaction()) $db->rollBack();
        foreach ($created as $path) if (is_file($path)) unlink($path);
        $backup['status']='failed'; file_put_contents($backupPath,encode($backup),LOCK_EX);
        throw $e;
    } finally { flock($lock,LOCK_UN); fclose($lock); }
    $report['status']='committed';$report['backup']=$backupPath;
    return $report;
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '')===__FILE__) {
    try {
        $options=getopt('', ['site:','apply','backup-dir:','rollback:']);
        $report=migrate($options);
        echo encode($report),PHP_EOL;
        exit(($report['ready'] ?? true)?0:2);
    } catch (Throwable $e) {
        // PDO messages may reveal host/user names: keep connection diagnostics off stdout.
        $message=$e instanceof PDOException ? 'Database operation failed (check configuration and database permissions).' : $e->getMessage();
        fwrite(STDERR,$message.PHP_EOL); exit(1);
    }
}
