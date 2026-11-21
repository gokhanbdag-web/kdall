<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$controller=file_get_contents($root.'/app/Modules/Purchasing/PurchasingController.php');
$view=file_get_contents($root.'/app/Views/purchasing/index.php');
$invoice=file_get_contents($root.'/app/Modules/Invoices/InvoiceController.php');
$finance=file_get_contents($root.'/app/Modules/Finance/FinanceController.php');
foreach(['incoming_quantity','GREATEST(poi.quantity-poi.received_quantity,0)',"po.status IN ('draft','ordered','partial')",'open_order_value','received_order_value','pending_units'] as $needle){if(!str_contains($controller,$needle))throw new RuntimeException("Missing purchasing report/planning logic: ".$needle);}
foreach(['incoming_quantity','open_order_value','received_order_value','pendingReceipts'] as $needle){if(!str_contains($view,$needle))throw new RuntimeException("Missing purchasing report/planning UI: ".$needle);}
if(!str_contains($invoice,'source_purchase_id')||!str_contains($finance,'supplier_payment'))throw new RuntimeException('Existing invoice or supplier payment integration missing');
echo "Purchasing planning and reporting smoke checks passed\n";
