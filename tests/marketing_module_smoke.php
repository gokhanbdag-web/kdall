<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Marketing\MarketingController::class)){fwrite(STDERR,"Marketing controller autoload failed.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/015_marketing.sql');
foreach(['campaigns','coupon_redemptions','gift_cards','loyalty_accounts','loyalty_transactions','marketing.manage','discount_total'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Marketing migration missing {$needle}.\n");exit(1);}}
$nav=require dirname(__DIR__).'/app/config/navigation.php';$found=false;foreach($nav as $g)foreach($g['items'] as $i)if(($i['page']??'')==='marketing')$found=true;
if(!$found){fwrite(STDERR,"Marketing navigation missing.\n");exit(1);}
echo "KodVadAll marketing smoke tests passed.\n";
