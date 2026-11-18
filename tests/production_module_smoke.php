<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Production\ProductionController::class)){fwrite(STDERR,"Production controller failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/029_production.sql');
foreach(['production_recipes','production_orders','production_order_materials','production.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Production migration missing {$needle}.\n");exit(1);}}
echo "KodVadAll production smoke tests passed.\n";
