<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Service\ServiceController::class)){fwrite(STDERR,"Service controller failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/030_service.sql');
foreach(['service_jobs','service_job_parts','service_job_history','service.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Service migration missing {$needle}.\n");exit(1);}}
$warehouseMigration=file_get_contents(dirname(__DIR__).'/database/migrations/042_service_job_warehouse.sql');
$serviceController=file_get_contents(dirname(__DIR__).'/app/Modules/Service/ServiceController.php');
$serviceView=file_get_contents(dirname(__DIR__).'/app/Views/service/index.php');
foreach(['ADD COLUMN warehouse_id','fk_service_job_warehouse'] as $needle){if($warehouseMigration===false||strpos($warehouseMigration,$needle)===false){fwrite(STDERR,"Service warehouse migration missing {$needle}.\\n");exit(1);}}
foreach(['warehouse_id','product_warehouse_stocks','warehouse_id,user_id'] as $needle){if($serviceController===false||strpos($serviceController,$needle)===false){fwrite(STDERR,"Service warehouse stock handling missing {$needle}.\\n");exit(1);}}
if($serviceView===false||strpos($serviceView,'name="warehouse_id"')===false||strpos($serviceView,'warehouse_name')===false){fwrite(STDERR,"Service warehouse UI missing.\\n");exit(1);}
echo "KodVadAll technical service smoke tests passed.\n";
