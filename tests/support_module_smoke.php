<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Support\SupportController::class)){fwrite(STDERR,"Support controller failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/027_support.sql');
foreach(['support_tickets','support_messages','support.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Support migration missing {$needle}.\n");exit(1);}}
echo "KodVadAll support smoke tests passed.\n";
