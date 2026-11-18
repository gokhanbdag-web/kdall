<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$root=dirname(__DIR__);
$service=file_get_contents($root.'/app/Core/AccountingPostingService.php');
$finance=file_get_contents($root.'/app/Modules/Finance/FinanceController.php');
$invoices=file_get_contents($root.'/app/Modules/Invoices/InvoiceController.php');
$accounting=file_get_contents($root.'/app/Modules/Accounting/AccountingController.php');
$migration=file_get_contents($root.'/database/migrations/034_accounting_automation.sql');
foreach(['postFinancialTransaction','postFinancialAccountOpening','postInvoice','reverseInvoice','finance_transfer','invoice_reversal'] as $needle){if($service===false||strpos($service,$needle)===false){fwrite(STDERR,"Accounting posting service missing {$needle}.\n");exit(1);}}
foreach(['AccountingPostingService::postFinancialAccountOpening','AccountingPostingService::postFinancialTransaction'] as $needle){if($finance===false||strpos($finance,$needle)===false){fwrite(STDERR,"Finance integration missing {$needle}.\n");exit(1);}}
foreach(['AccountingPostingService::postInvoice','AccountingPostingService::reverseInvoice','AccountingPostingService::postFinancialTransaction'] as $needle){if($invoices===false||strpos($invoices,$needle)===false){fwrite(STDERR,"Invoice integration missing {$needle}.\n");exit(1);}}
foreach(['currency CHAR(3)','uq_accounting_source','391'] as $needle){if($migration===false||strpos($migration,$needle)===false){fwrite(STDERR,"Accounting automation migration missing {$needle}.\n");exit(1);}}
if($accounting===false||strpos($accounting,'l.currency=?')===false){fwrite(STDERR,"Accounting reports must filter by currency.\n");exit(1);}
echo "KodVadAll accounting automation smoke tests passed.\n";
