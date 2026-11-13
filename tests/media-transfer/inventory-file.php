<?php
// Hide one local fixture original; no production data or database writes.
require '/var/www/html/configuration.php';$c=new JConfig();if($c->dbprefix!=='migrationj54_')throw new RuntimeException('Fixture database required');
$source='/var/www/html/images/tz_portfolio/flipbook_gallery/91001/a.jpg';$hidden='/var/www/html/tmp/tzmt-inventory-a.jpg';
if(($argv[1]??'')==='hide') {if(!is_file($source)||file_exists($hidden)||!rename($source,$hidden))throw new RuntimeException('Cannot hide fixture original');}
else {if(is_file($hidden)&&!rename($hidden,$source))throw new RuntimeException('Cannot restore fixture original');}
