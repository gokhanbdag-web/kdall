<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Invoices\InvoiceController::class)){fwrite(STDERR,"Invoice controller autoload failed.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/007_invoices.sql');
foreach(['CREATE TABLE IF NOT EXISTS invoices','CREATE TABLE IF NOT EXISTS invoice_items','financial_transactions ADD COLUMN invoice_id','invoices.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Invoice migration missing {$needle}.\n");exit(1);}}
$controller=file_get_contents(dirname(__DIR__).'/app/Modules/Invoices/InvoiceController.php');
$orderController=file_get_contents(dirname(__DIR__).'/app/Modules/Orders/OrderController.php');
if($orderController===false||strpos($orderController,'$orderNumber=$number;')===false){fwrite(STDERR,"Order notification number consistency missing.\\n");exit(1);}

foreach(['FOR UPDATE', "invoice_type=? AND status<>", 'Bu kaynak belge için zaten aktif bir fatura var.'] as $needle){if($controller===false||strpos($controller,$needle)===false){fwrite(STDERR,"Duplicate invoice protection missing: {$needle}.\\n");exit(1);}}
$controller=file_get_contents(dirname(__DIR__).'/app/Modules/Invoices/InvoiceController.php');
foreach(['amountInput', 'Ödeme tutarı en fazla iki ondalık basamak içermelidir.'] as $needle){if($controller===false||strpos($controller,$needle)===false){fwrite(STDERR,"Invoice payment precision guard missing: {$needle}.\\n");exit(1);}}
foreach(['invoice_id=? AND direction=? AND transaction_type=? AND currency=?', 'invoice_id=? AND transaction_type=? AND direction=? AND currency=?', 't.invoice_id=? AND t.transaction_type=? AND t.direction=? AND t.currency=?'] as $needle){if(strpos($controller,$needle)===false){fwrite(STDERR,"Invoice payment reconciliation filter missing: {$needle}.\\n");exit(1);}}
$nav=require dirname(__DIR__).'/app/config/navigation.php';$found=false;foreach($nav as $g)foreach($g['items'] as $i)if(($i['page']??'')==='invoices')$found=true;
if(!$found){fwrite(STDERR,"Invoice navigation missing.\n");exit(1);}
if($controller===false||strpos($controller,"Avans mahsubu bulunan fatura iptal edilemez.")===false||substr_count($controller,'SELECT COALESCE(SUM(amount),0) FROM financial_advance_allocations WHERE invoice_id=?')<2){fwrite(STDERR,"Invoice payment and cancellation advance reconciliation missing.\\n");exit(1);}
foreach(["status='active' FOR UPDATE","opening_balance","CASE WHEN direction='credit' THEN amount ELSE -amount END","Tedarikçi ödemesi için kasa/banka bakiyesi yetersiz."] as $needle){if(!str_contains($controller,$needle))throw new RuntimeException('Invoice account balance guard missing: '.$needle);}
$paymentSection=substr($controller,strpos($controller,"if(\$form==='payment')"));
$accountLock=strpos($paymentSection,"status='active' FOR UPDATE");$invoiceLock=strpos($paymentSection,"SELECT * FROM invoices WHERE id=? FOR UPDATE");
if($accountLock===false||$invoiceLock===false||$accountLock>$invoiceLock)throw new RuntimeException('Invoice payment lock order must be account before invoice.');
echo "KodVadAll invoice module smoke tests passed.\n";
