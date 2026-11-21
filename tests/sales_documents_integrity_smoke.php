<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Modules/SalesDocuments/SalesDocumentsController.php';
$method=(new ReflectionClass(\KodVadAll\Modules\SalesDocuments\SalesDocumentsController::class))->getMethod('validDocumentNumber');
foreach([['1',3,false],['1.125',3,false],['0',2,true],['25.50',2,true]] as [$v,$p,$z])if(!$method->invoke(null,$v,$p,$z))throw new RuntimeException('Valid document number rejected: '.$v);
foreach([['0',3,false],['-1',3,false],['INF',2,true],['NAN',2,true],['1e4',2,true],['1.1234',3,false],['1000000001',2,true],['',3,false]] as [$v,$p,$z])if($method->invoke(null,$v,$p,$z))throw new RuntimeException('Invalid document number accepted: '.$v);
$source=file_get_contents(dirname(__DIR__).'/app/Modules/SalesDocuments/SalesDocumentsController.php');
foreach(['seenProducts[$pid]','Teklif toplamı geçersiz.','SELECT status FROM contracts WHERE id=? FOR UPDATE','converted_order_id','beginTransaction()'] as $needle)if(!str_contains($source,$needle))throw new RuntimeException('Sales document integration guard missing: '.$needle);
$invoice=file_get_contents(dirname(__DIR__).'/app/Modules/Invoices/InvoiceController.php');
foreach(['source_order_id','FOR UPDATE','financial_advance_allocations','postFinancialTransaction','remaining=round'] as $needle)if(!str_contains($invoice,$needle))throw new RuntimeException('Invoice/finance integration missing: '.$needle);
echo "Sales document and invoice integration checks passed\n";
