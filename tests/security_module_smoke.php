<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Core\SecurityService::class)||!class_exists(KodVadAll\Modules\Security\SecurityController::class)){fwrite(STDERR,"Security classes failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/022_security.sql');
foreach(['login_attempts','user_sessions','security_events','security.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Security migration missing {$needle}.\n");exit(1);}}
echo "KodVadAll security smoke tests passed.\n";
