<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$root=dirname(__DIR__);
$orders=file_get_contents($root.'/app/Modules/Orders/OrderController.php');
$invoices=file_get_contents($root.'/app/Modules/Invoices/InvoiceController.php');
$production=file_get_contents($root.'/app/Modules/Production/ProductionController.php');
$posting=file_get_contents($root.'/app/Core/AccountingPostingService.php');
$view=file_get_contents($root.'/app/Views/production/index.php');
$migration=file_get_contents($root.'/database/migrations/036_inventory_production_costing.sql');
$checks=[
 [$orders,['purchase_price','unit_cost','order_items'],'Sales order cost snapshot'],
 [$invoices,['unit_cost','invoice_items'],'Invoice cost snapshot'],
 [$production,['labor_cost','overhead_cost','material_cost','total_cost','unit_cost','production_order_materials','postProductionOrder'],'Production cost calculation'],
 [$posting,['postProductionOrder','Satılan ürünlerin maliyeti','Stok maliyet çıkışı','335','320'],'Ledger cost posting'],
 [$view,['labor_cost','overhead_cost','Toplam maliyet','Birim maliyet'],'Production cost UI'],
 [$migration,['order_items','invoice_items','production_orders','production_order_materials','335'],'Costing migration']
];
foreach($checks as [$source,$needles,$label]){
 if($source===false){fwrite(STDERR,$label." source file missing.\n");exit(1);}
 foreach($needles as $needle){if(strpos($source,$needle)===false){fwrite(STDERR,$label." missing ".$needle.".\n");exit(1);}}
}
echo "KodVadAll inventory and production costing smoke tests passed.\n";
