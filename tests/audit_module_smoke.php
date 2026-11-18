<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Audit\AuditController::class)){fwrite(STDERR,"Audit controller failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/025_audit_view.sql');
if($sql===false||strpos($sql,'audit.view')===false){fwrite(STDERR,"Audit permission migration missing.\n");exit(1);}
echo "KodVadAll audit smoke tests passed.\n";
