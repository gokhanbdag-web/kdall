<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Accounting\AccountingController::class)){fwrite(STDERR,"Accounting controller failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/033_accounting.sql');
foreach(['accounting_accounts','accounting_journals','accounting_journal_lines','accounting.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Accounting migration missing {$needle}.\n");exit(1);}}
$nav=require dirname(__DIR__).'/app/config/navigation.php';$found=false;foreach($nav as $g)foreach($g['items'] as $i)if(($i['page']??'')==='accounting')$found=true;
if(!$found){fwrite(STDERR,"Accounting navigation missing.\n");exit(1);}
echo "KodVadAll accounting smoke tests passed.\n";
