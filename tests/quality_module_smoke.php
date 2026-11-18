<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Quality\QualityController::class)){fwrite(STDERR,"Quality controller failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/032_quality.sql');
foreach(['quality_checks','quality_check_items','quality.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Quality migration missing {$needle}.\n");exit(1);}}
echo "KodVadAll quality smoke tests passed.\n";
