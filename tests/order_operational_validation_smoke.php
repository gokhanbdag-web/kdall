<?php
declare(strict_types=1);
require_once dirname(__DIR__).'/app/Modules/Orders/OrderController.php';
$reflect=new ReflectionClass(\KodVadAll\Modules\Orders\OrderController::class);
$method=$reflect->getMethod('validOrderNumber');
$valid=[['1',3,false],['1.125',3,false],['0',4,true],['99.99',2,true]];
$invalid=[['0',3,false],['-1',3,false],['abc',3,false],['INF',4,true],['NAN',4,true],['1e8',4,true],['1.1234',3,false],['1000000001',4,true],['1.001',2,true],['',3,false]];
foreach($valid as [$value,$precision,$zero]){if(!$method->invoke(null,$value,$precision,$zero))throw new RuntimeException('Valid order amount rejected: '.$value);}
foreach($invalid as [$value,$precision,$zero]){if($method->invoke(null,$value,$precision,$zero))throw new RuntimeException('Invalid order amount accepted: '.$value);}
$source=file_get_contents(dirname(__DIR__).'/app/Modules/Orders/OrderController.php');
foreach(['seenProducts[$productId]','Aynı ürün bir siparişte birden fazla satırda olamaz.','is_finite($grand)','FOR UPDATE','stock_reversed_at'] as $needle){if(!str_contains($source,$needle))throw new RuntimeException('Order safeguard missing: '.$needle);}
echo "Sales order operational validation tests passed\n";
