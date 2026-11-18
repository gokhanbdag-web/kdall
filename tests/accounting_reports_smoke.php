<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$controller=file_get_contents(dirname(__DIR__).'/app/Modules/Accounting/AccountingController.php');
$view=file_get_contents(dirname(__DIR__).'/app/Views/accounting/index.php');
foreach(["accounting.view","accounting.manage","form==='account'","form==='journal'","accounting_journal_lines","j.status='posted'","report==='trial'","report==='balance'","report==='profit'","report==='ledger'"] as $needle){if($controller===false||strpos($controller,$needle)===false){fwrite(STDERR,"Accounting report logic missing {$needle}.\n");exit(1);}}
foreach(['Mizan','Bilanço','Gelir tablosu','Hesap ekstresi','Dönem net sonucu','Son muhasebe fişleri'] as $needle){if($view===false||strpos($view,$needle)===false){fwrite(STDERR,"Accounting report view missing {$needle}.\n");exit(1);}}
if(substr_count($controller,"j.status='posted'")<5){fwrite(STDERR,"Accounting reports must only use posted journal entries.\n");exit(1);}
echo "KodVadAll accounting reports smoke tests passed.\n";
