<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Core\LicenseService::class)||!class_exists(KodVadAll\Modules\License\LicenseController::class)){fwrite(STDERR,"License classes failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/024_license_updates.sql');
foreach(['license_activations','update_history','license.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"License migration missing {$needle}.\n");exit(1);}}
if(!is_file(dirname(__DIR__).'/license_request.php')){fwrite(STDERR,"License request endpoint missing.\n");exit(1);}
echo "KodVadAll license smoke tests passed.\n";
