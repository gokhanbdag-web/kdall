<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Modules/Shipping/ShippingController.php';
require_once dirname(__DIR__).'/app/Modules/Returns/ReturnController.php';
$shipping=(new ReflectionClass(\KodVadAll\Modules\Shipping\ShippingController::class))->getMethod('validShippingNumber');
$return=(new ReflectionClass(\KodVadAll\Modules\Returns\ReturnController::class))->getMethod('validAmount');
foreach(['0','1','12.50','999999999'] as $v)if(!$shipping->invoke(null,$v,2))throw new RuntimeException('Valid shipping number rejected: '.$v);
foreach(['-1','NaN','INF','1e6','1.234','1000000001',''] as $v)if($shipping->invoke(null,$v,2))throw new RuntimeException('Invalid shipping number accepted: '.$v);
foreach([['1',3,false],['0',3,true],['1.125',3,false],['10.25',2,false]] as [$v,$p,$z])if(!$return->invoke(null,$v,$p,$z))throw new RuntimeException('Valid return amount rejected: '.$v);
foreach([['0',3,false],['-1',3,true],['1e4',2,false],['1.234',2,false],['NAN',2,false],['1000000001',2,false]] as [$v,$p,$z])if($return->invoke(null,$v,$p,$z))throw new RuntimeException('Invalid return amount accepted: '.$v);
$shippingSource=file_get_contents(dirname(__DIR__).'/app/Modules/Shipping/ShippingController.php');
$returnSource=file_get_contents(dirname(__DIR__).'/app/Modules/Returns/ReturnController.php');
$orderSource=file_get_contents(dirname(__DIR__).'/app/Modules/Orders/OrderController.php');
$invoiceSource=file_get_contents(dirname(__DIR__).'/app/Modules/Invoices/InvoiceController.php');
foreach(['SELECT COUNT(*) FROM shipments WHERE order_id=?','FOR UPDATE','order_status_history'] as $needle)if(!str_contains($shippingSource,$needle))throw new RuntimeException('Shipping integration guard missing: '.$needle);
foreach(['validAmount($rawQty,3,true)','validAmount($rawAmount,2,false)','postSalesReturnRestock','postSalesReturnRefund','product_warehouse_stocks'] as $needle)if(!str_contains($returnSource,$needle))throw new RuntimeException('Return integration guard missing: '.$needle);
foreach(['deductStock','restoreStock','stock_deducted_at'] as $needle)if(!str_contains($orderSource,$needle))throw new RuntimeException('Order stock integration missing: '.$needle);
foreach(['postFinancialTransaction','financial_advance_allocations','source_order_id'] as $needle)if(!str_contains($invoiceSource,$needle))throw new RuntimeException('Invoice payment integration missing: '.$needle);
echo "Sales lifecycle validation and integration wiring checks passed\n";
