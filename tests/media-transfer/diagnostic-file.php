<?php
if(PHP_SAPI!=='cli')exit(1);
require '/var/www/html/configuration.php';$c=new JConfig();if($c->dbprefix!=='migrationj54_')throw new RuntimeException('Fixture database required');
$original='/var/www/html/images/tz_portfolio_plus/grid_gallery/91009/legacy.jpg';$backup='/var/www/html/tmp/tzmt-diagnostic-original.jpg';
if(($argv[1]??'')==='append'){
 if(!is_file($original)||file_exists($backup))throw new RuntimeException('Diagnostic fixture occupied');
 copy($original,$backup);chmod($backup,0600);file_put_contents($original,'<?=' . "\x00\xFF" . '<script>window.tzmtDiagnosticXss=1</script>',FILE_APPEND);
}else{if(is_file($backup)){if(!copy($backup,$original))throw new RuntimeException('Cannot restore diagnostic fixture');unlink($backup);}}
