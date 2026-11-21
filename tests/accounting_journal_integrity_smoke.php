<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Modules/Accounting/AccountingController.php';
$method=(new ReflectionClass(\KodVadAll\Modules\Accounting\AccountingController::class))->getMethod('journalAmount');
foreach(['0','1','123.45','9999999999999.99'] as $value)if($method->invoke(null,$value)!==(float)$value)throw new RuntimeException('Valid journal amount rejected: '.$value);
foreach(['-1','NaN','INF','1e5','1.234','99999999999999','',[]] as $value){
 try{$method->invoke(null,$value);throw new RuntimeException('Invalid journal amount accepted');}
 catch(ReflectionException $e){throw $e;}
 catch(RuntimeException $e){if($e->getMessage()==='Invalid journal amount accepted')throw $e;}
}
$controller=file_get_contents(dirname(__DIR__).'/app/Modules/Accounting/AccountingController.php');
$view=file_get_contents(dirname(__DIR__).'/app/Views/accounting/index.php');
foreach(["source_type='manual_journal_reversal'","SELECT * FROM accounting_journals WHERE id=? AND source_type IS NULL AND status='posted' FOR UPDATE","accounting.journal_reversed","$"."parent->fetchColumn()!==$"."type"] as $needle)if(!str_contains($controller,$needle))throw new RuntimeException('Accounting safeguard missing: '.$needle);
if(!str_contains($view,'name="form_action" value="reverse_journal"'))throw new RuntimeException('Manual reversal UI missing');
echo "Accounting journal integrity smoke passed\n";
