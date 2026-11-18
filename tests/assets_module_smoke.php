<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Assets\AssetsController::class)){fwrite(STDERR,"Assets controller failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/028_assets.sql');
foreach(['fixed_assets','asset_maintenance','assets.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Assets migration missing {$needle}.\n");exit(1);}}
echo "KodVadAll fixed assets smoke tests passed.\n";
