<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$root=dirname(__DIR__);
$service=file_get_contents($root.'/app/Core/AccountingPostingService.php');
$finance=file_get_contents($root.'/app/Modules/Finance/FinanceController.php');
$invoices=file_get_contents($root.'/app/Modules/Invoices/InvoiceController.php');
$accounting=file_get_contents($root.'/app/Modules/Accounting/AccountingController.php');
$migration=file_get_contents($root.'/database/migrations/034_accounting_automation.sql');
$backfill=file_get_contents($root.'/database/migrations/035_accounting_backfill.sql');
foreach(['postFinancialTransaction','postFinancialAccountOpening','postInvoice','reverseInvoice','finance_transfer','invoice_reversal'] as $needle){if($service===false||strpos($service,$needle)===false){fwrite(STDERR,"Accounting posting service missing {$needle}.\n");exit(1);}}
foreach(['AccountingPostingService::postFinancialAccountOpening','AccountingPostingService::postFinancialTransaction'] as $needle){if($finance===false||strpos($finance,$needle)===false){fwrite(STDERR,"Finance integration missing {$needle}.\n");exit(1);}}
foreach(['AccountingPostingService::postInvoice','AccountingPostingService::reverseInvoice','AccountingPostingService::postFinancialTransaction'] as $needle){if($invoices===false||strpos($invoices,$needle)===false){fwrite(STDERR,"Invoice integration missing {$needle}.\n");exit(1);}}
foreach(['currency CHAR(3)','uq_accounting_source','391'] as $needle){if($migration===false||strpos($migration,$needle)===false){fwrite(STDERR,"Accounting automation migration missing {$needle}.\n");exit(1);}}
foreach(['Sync historical non-transfer financial transactions','Sync historical paired cash/bank transfers','Bring existing finance account opening balances into the ledger','already issued invoices'] as $needle){if($backfill===false||strpos($backfill,$needle)===false){fwrite(STDERR,"Accounting backfill migration missing {$needle}.\n");exit(1);}}
if($accounting===false||strpos($accounting,'l.currency=?')===false){fwrite(STDERR,"Accounting reports must filter by currency.\n");exit(1);}
$accountingView=file_get_contents($root.'/app/Views/accounting/index.php');
foreach(["'reconcile'","reconciliationTotals","finance_balance","account_code"] as $needle){if($accounting===false||strpos($accounting,$needle)===false){fwrite(STDERR,"Finance reconciliation query missing {$needle}.\\n");exit(1);}}
if($accountingView===false||strpos($accountingView,'Finans mutabakatı')===false||strpos($accountingView,'Fark var')===false){fwrite(STDERR,"Finance reconciliation display missing.\\n");exit(1);}
echo "KodVadAll accounting automation smoke tests passed.\n";
