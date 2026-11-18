<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Modules\CMS\CMSController::class)){fwrite(STDERR,"CMS controller autoload failed.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/016_storefront_cms.sql');
foreach(['site_pages','site_menus','site_menu_items','site_banners','blog_posts','site.manage','order_source','tax_rate'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"CMS migration missing {$needle}.\n");exit(1);}}
if(!is_file(dirname(__DIR__).'/store.php')){fwrite(STDERR,"Storefront entrypoint missing.\n");exit(1);}
$nav=require dirname(__DIR__).'/app/config/navigation.php';$found=false;foreach($nav as $g)foreach($g['items'] as $i)if(($i['page']??'')==='site')$found=true;
if(!$found){fwrite(STDERR,"CMS navigation missing.\n");exit(1);}
echo "KodVadAll CMS and storefront smoke tests passed.\n";
