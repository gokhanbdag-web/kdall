<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Data\DataController::class)){fwrite(STDERR,"Data transfer controller failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/023_data_transfer.sql');
foreach(['data_transfer_logs','data.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Data transfer migration missing {$needle}.\n");exit(1);}}
if(!is_file(dirname(__DIR__).'/export.php')){fwrite(STDERR,"CSV export endpoint missing.\n");exit(1);}
echo "KodVadAll data transfer smoke tests passed.\n";
