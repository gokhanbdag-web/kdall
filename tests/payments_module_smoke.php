<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Core\PaymentGateway::class)||!class_exists(KodVadAll\Modules\Payments\PaymentsController::class)){fwrite(STDERR,"Payment classes failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/017_payments.sql');
foreach(['payment_gateway_settings','payment_transactions','payments.manage','payment_status'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Payments migration missing {$needle}.\n");exit(1);}}
if(!is_file(dirname(__DIR__).'/payment_callback.php')){fwrite(STDERR,"Payment callback endpoint missing.\n");exit(1);}
echo "KodVadAll payments smoke tests passed.\n";
