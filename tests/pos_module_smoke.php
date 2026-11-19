<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\POS\POSController::class)){fwrite(STDERR,"POS controller failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/026_pos.sql');
foreach(['pos_shifts','pos_cash_movements','pos_shift_id','pos.use'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"POS migration missing {$needle}.\n");exit(1);}}
if(!is_file(dirname(__DIR__).'/pos_api.php')){fwrite(STDERR,"POS API endpoint missing.\n");exit(1);}
$posController=file_get_contents(dirname(__DIR__).'/app/Modules/POS/POSController.php');
$posJs=file_get_contents(dirname(__DIR__).'/assets/pos.js');
$posView=file_get_contents(dirname(__DIR__).'/app/Views/pos/index.php');
if($posController===false||strpos($posController,"\$_GET['warehouse_id']")===false||strpos($posController,"\$_POST['warehouse_id']")===false||strpos($posController,'warehouse_quantity')===false||$posJs===false||strpos($posJs,'warehouse_id')===false||strpos($posJs,'warehouse_quantity')===false||$posView===false||strpos($posView,'id="pos-warehouse"')===false){fwrite(STDERR,"POS warehouse stock handling missing.\\n");exit(1);}
$accounting=file_get_contents(dirname(__DIR__).'/app/Core/AccountingPostingService.php');
if($accounting===false||strpos($accounting,'postPosSale')===false||strpos($accounting,"'pos_sale'")===false||strpos($posController,'AccountingPostingService::postPosSale')===false||strpos($posController,'unit_cost')===false){fwrite(STDERR,"POS sales must snapshot cost and create an accounting journal.\n");exit(1);}
foreach(["cash_movement'){\$db->beginTransaction();\$shift=self::openShift","close_shift'){\$db->beginTransaction();\$shift=self::openShift","if(\$db->inTransaction())\$db->rollBack();"] as $needle){if($posController===false||strpos($posController,$needle)===false){fwrite(STDERR,"POS shift reconciliation transaction guard missing.\\n");exit(1);}}
echo "KodVadAll POS smoke tests passed.\n";
