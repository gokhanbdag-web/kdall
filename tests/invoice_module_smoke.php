<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Invoices\InvoiceController::class)){fwrite(STDERR,"Invoice controller autoload failed.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/007_invoices.sql');
foreach(['CREATE TABLE IF NOT EXISTS invoices','CREATE TABLE IF NOT EXISTS invoice_items','financial_transactions ADD COLUMN invoice_id','invoices.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Invoice migration missing {$needle}.\n");exit(1);}}
$controller=file_get_contents(dirname(__DIR__).'/app/Modules/Invoices/InvoiceController.php');
foreach(['FOR UPDATE', "invoice_type=? AND status<>", 'Bu kaynak belge için zaten aktif bir fatura var.'] as $needle){if($controller===false||strpos($controller,$needle)===false){fwrite(STDERR,"Duplicate invoice protection missing: {$needle}.\\n");exit(1);}}
$controller=file_get_contents(dirname(__DIR__).'/app/Modules/Invoices/InvoiceController.php');
foreach(['amountInput', 'Ödeme tutarı en fazla iki ondalık basamak içermelidir.'] as $needle){if($controller===false||strpos($controller,$needle)===false){fwrite(STDERR,"Invoice payment precision guard missing: {$needle}.\\n");exit(1);}}
$nav=require dirname(__DIR__).'/app/config/navigation.php';$found=false;foreach($nav as $g)foreach($g['items'] as $i)if(($i['page']??'')==='invoices')$found=true;
if(!$found){fwrite(STDERR,"Invoice navigation missing.\n");exit(1);}
echo "KodVadAll invoice module smoke tests passed.\n";
