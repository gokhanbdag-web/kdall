<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Shipping\ShippingController::class)){fwrite(STDERR,"Shipping controller autoload failed.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/009_shipping.sql');
foreach(['shipping_carriers','shipments','shipment_events','shipments.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Shipping migration missing {$needle}.\n");exit(1);}}
$nav=require dirname(__DIR__).'/app/config/navigation.php';$found=false;foreach($nav as $g)foreach($g['items'] as $i)if(($i['page']??'')==='shipping')$found=true;
if(!$found){fwrite(STDERR,"Shipping navigation missing.\n");exit(1);}
echo "KodVadAll shipping module smoke tests passed.\n";
