<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$controller=file_get_contents(dirname(__DIR__).'/app/Modules/Accounting/AccountingController.php');
$view=file_get_contents(dirname(__DIR__).'/app/Views/accounting/index.php');
foreach(['accounting.view','accounting_journal_lines','j.status=\\'posted\\'','report===\\'trial\\'','report===\\'balance\\'','report===\\'profit\\'','report===\\'ledger\\''] as $needle){if($controller===false||strpos($controller,$needle)===false){fwrite(STDERR,"Accounting report logic missing {$needle}.\\n");exit(1);}}
foreach(['Mizan','Bilanço','Gelir tablosu','Hesap ekstresi','Dönem net sonucu'] as $needle){if($view===false||strpos($view,$needle)===false){fwrite(STDERR,"Accounting report view missing {$needle}.\\n");exit(1);}}
if(substr_count($controller,'CASE WHEN j.id IS NOT NULL THEN l.debit ELSE 0 END')<3){fwrite(STDERR,"Accounting reports must restrict totals to posted journals in selected dates.\\n");exit(1);}
echo "KodVadAll accounting reports smoke tests passed.\\n";
