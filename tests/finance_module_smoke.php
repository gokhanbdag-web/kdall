<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Finance\FinanceController::class)){fwrite(STDERR,"Finance controller autoload failed.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/006_finance.sql');
foreach(['financial_accounts','financial_transactions','finance.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Finance migration missing {$needle}.\n");exit(1);}}
$nav=require dirname(__DIR__).'/app/config/navigation.php';$found=false;foreach($nav as $g)foreach($g['items'] as $i)if(($i['page']??'')==='finance')$found=true;
if(!$found){fwrite(STDERR,"Finance navigation missing.\n");exit(1);}
echo "KodVadAll finance module smoke tests passed.\n";
