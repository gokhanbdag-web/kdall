<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(\KodVadAll\Core\PartyLedgerService::class)){fwrite(STDERR,"Party ledger service missing.\n");exit(1);}
$service=file_get_contents(dirname(__DIR__).'/app/Core/PartyLedgerService.php');
$customer=file_get_contents(dirname(__DIR__).'/app/Modules/Customers/CustomerController.php');
$supplier=file_get_contents(dirname(__DIR__).'/app/Modules/Purchasing/PurchasingController.php');
$view=file_get_contents(dirname(__DIR__).'/app/Views/finance/party_ledger.php');
foreach(["invoice_type=?","transaction_type=?","direction=?","GROUP BY currency","issue_date entry_date","UNION ALL","ledger_page","LIMIT {$perPage} OFFSET {$offset}"] as $needle){if($service===false||strpos($service,$needle)===false){fwrite(STDERR,"Ledger guard missing: {$needle}\n");exit(1);}}
if(strpos((string)$customer,"PartyLedgerService::statement")===false||strpos((string)$supplier,"PartyLedgerService::statement")===false||strpos((string)$view,"Kalan bakiye")===false){fwrite(STDERR,"Ledger view integration missing.\n");exit(1);}
foreach(["ledger['pages']","ledger['page']","http_build_query","Önceki","Sonraki"] as $needle){if(strpos((string)$view,$needle)===false){fwrite(STDERR,"Ledger pagination view missing: {$needle}\n");exit(1);}}
echo "Party ledger smoke tests passed.\n";
