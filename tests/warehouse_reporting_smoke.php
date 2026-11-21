<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$controller=file_get_contents($root.'/app/Modules/Warehouses/WarehousesController.php');
$index=file_get_contents($root.'/app/Views/warehouses/index.php');
$report=file_get_contents($root.'/app/Views/warehouses/stock_report.php');
$movements=file_get_contents($root.'/app/Views/warehouses/movements.php');
foreach (["action==='stock_report'","action==='movements'","COALESCE(s.quantity,0)","p.min_stock","ROUND(COALESCE(s.quantity,0)*p.purchase_price,2)","m.warehouse_id=?","m.product_id=?","m.movement_type=?","m.created_at>=?","m.created_at<?","$"."q->execute($"."params)"] as $needle) {
 if (!str_contains($controller,$needle)) throw new RuntimeException("Missing warehouse report feature: ".$needle);
}
foreach (['stock_report','movements'] as $action) if(!str_contains($index,'action='.$action))throw new RuntimeException("Warehouse navigation missing ".$action);
foreach ([$report,$movements] as $view) if(!str_contains($view,'kd_e(')||!str_contains($view,'method="get"'))throw new RuntimeException('Report escaping or filtering missing');
echo "Warehouse reporting smoke tests passed\n";
