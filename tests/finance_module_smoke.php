<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Finance\FinanceController::class)){fwrite(STDERR,"Finance controller autoload failed.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/006_finance.sql');
foreach(['financial_accounts','financial_transactions','finance.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Finance migration missing {$needle}.\n");exit(1);}}
$controller=file_get_contents(dirname(__DIR__).'/app/Modules/Finance/FinanceController.php');
$view=file_get_contents(dirname(__DIR__).'/app/Views/finance/index.php');
foreach(['FOR UPDATE', "invoice_id", "status='paid'", 'kalan fatura bakiyesini aşamaz'] as $needle){if($controller===false||strpos($controller,$needle)===false){fwrite(STDERR,"Invoice allocation guard missing: {$needle}.\\n");exit(1);}}
if($view===false||strpos($view,'name="invoice_id"')===false){fwrite(STDERR,"Finance invoice selector missing.\\n");exit(1);}
foreach(["self::moneyAmount(\$_POST['opening_balance']??'0',true)","self::moneyAmount(\$_POST['amount']??'')","private static function moneyAmount","\\d{1,2}"] as $needle){if($controller===false||strpos($controller,$needle)===false){fwrite(STDERR,"Finance money precision guard missing: {$needle}.\\n");exit(1);}}
foreach(["$amount=self::moneyAmount(\$_POST['amount']??'');$date=","remaining FROM invoices i","t.transaction_type='collection'","t.transaction_type='supplier_payment'"] as $needle){if($controller===false||strpos($controller,$needle)===false){fwrite(STDERR,"Partial finance tracking missing {$needle}.\\n");exit(1);}}
if($view===false||strpos($view,"Kalan:")===false||strpos($view,"(avans)")===false){fwrite(STDERR,"Finance partial/advance display missing.\\n");exit(1);}
$nav=require dirname(__DIR__).'/app/config/navigation.php';$found=false;foreach($nav as $g)foreach($g['items'] as $i)if(($i['page']??'')==='finance')$found=true;
if(!$found){fwrite(STDERR,"Finance navigation missing.\n");exit(1);}
echo "KodVadAll finance module smoke tests passed.\n";
