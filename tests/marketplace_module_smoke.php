<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\Marketplaces\MarketplaceController::class)){fwrite(STDERR,"Marketplace controller autoload failed.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/008_marketplaces.sql');
foreach(['marketplace_connections','marketplace_product_map','marketplace_sync_jobs','marketplaces.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"Marketplace migration missing {$needle}.\n");exit(1);}}
$nav=require dirname(__DIR__).'/app/config/navigation.php';$found=false;foreach($nav as $g)foreach($g['items'] as $i)if(($i['page']??'')==='marketplaces')$found=true;
if(!$found){fwrite(STDERR,"Marketplace navigation missing.\n");exit(1);}
$controller=file_get_contents(dirname(__DIR__).'/app/Modules/Marketplaces/MarketplaceController.php');
if($controller===false||strpos($controller,'->execute([$connection,$batch,count($items),')===false){fwrite(STDERR,"Trendyol batch ID must be saved with marketplace sync job.\\n");exit(1);}
echo "KodVadAll marketplace smoke tests passed.\n";
