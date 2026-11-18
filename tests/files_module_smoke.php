<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Files\FilesController::class)){fwrite(STDERR,"Files controller failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/020_files.sql');
foreach(['documents','document_versions','files.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Files migration missing {$needle}.\n");exit(1);}}
if(!is_file(dirname(__DIR__).'/download.php')){fwrite(STDERR,"Secure download endpoint missing.\n");exit(1);}
echo "KodVadAll files smoke tests passed.\n";
