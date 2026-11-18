<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\POS\POSController::class)){fwrite(STDERR,"POS controller failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/026_pos.sql');
foreach(['pos_shifts','pos_cash_movements','pos_shift_id','pos.use'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"POS migration missing {$needle}.\n");exit(1);}}
if(!is_file(dirname(__DIR__).'/pos_api.php')){fwrite(STDERR,"POS API endpoint missing.\n");exit(1);}
echo "KodVadAll POS smoke tests passed.\n";
