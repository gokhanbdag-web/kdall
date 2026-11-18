<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Communications\CommunicationsController::class)){fwrite(STDERR,"Communications controller autoload failed.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/013_communications.sql');
foreach(['communication_settings','message_templates','message_logs','communications.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Communications migration missing {$needle}.\n");exit(1);}}
$nav=require dirname(__DIR__).'/app/config/navigation.php';$found=false;foreach($nav as $g)foreach($g['items'] as $i)if(($i['page']??'')==='communications')$found=true;
if(!$found){fwrite(STDERR,"Communications navigation missing.\n");exit(1);}
echo "KodVadAll communications smoke tests passed.\n";
