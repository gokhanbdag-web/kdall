<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\SalesDocuments\SalesDocumentsController::class)){fwrite(STDERR,"Sales documents controller autoload failed.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/011_quotes_contracts.sql');
foreach(['CREATE TABLE IF NOT EXISTS quotes','CREATE TABLE IF NOT EXISTS quote_items','CREATE TABLE IF NOT EXISTS contracts','contracts.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Sales documents migration missing {$needle}.\n");exit(1);}}
$nav=require dirname(__DIR__).'/app/config/navigation.php';$found=[];foreach($nav as $g)foreach($g['items'] as $i)$found[]=$i['page'];foreach(['quotes','contracts'] as $p)if(!in_array($p,$found,true)){fwrite(STDERR,"Navigation missing {$p}.\n");exit(1);}
$salesDocumentsController=file_get_contents(dirname(__DIR__).'/app/Modules/SalesDocuments/SalesDocumentsController.php');
$salesDocumentsView=file_get_contents(dirname(__DIR__).'/app/Views/sales-documents/index.php');
if($salesDocumentsController===false||strpos($salesDocumentsController,'warehouse_id')===false||$salesDocumentsView===false||strpos($salesDocumentsView,'name="warehouse_id"')===false){fwrite(STDERR,"Quote conversion must select a sales warehouse.\\n");exit(1);}
if($salesDocumentsController===false||strpos($salesDocumentsController,'purchase_price')===false||strpos($salesDocumentsController,'unit_cost')===false||strpos($salesDocumentsController,'$itemsCost')===false){fwrite(STDERR,"Quote conversion must preserve the product cost snapshot.\n");exit(1);}
$costMigration=file_get_contents(dirname(__DIR__).'/database/migrations/046_quote_item_costing.sql');
if($costMigration===false||strpos($costMigration,'unit_cost')===false){fwrite(STDERR,"Quote item cost migration missing.\n");exit(1);}
echo "KodVadAll sales documents smoke tests passed.\n";
