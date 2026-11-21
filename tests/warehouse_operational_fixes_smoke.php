<?php
declare(strict_types=1);
$src=file_get_contents(dirname(__DIR__).'/app/Modules/Warehouses/WarehousesController.php');
foreach (['en fazla 3 ondalık basamak','warehouse_stocktakes WHERE warehouse_id=? AND status=\'draft\'','Açık sayımı bulunan depo pasife alınamaz.','round(($items[$pid]??0)+$qty,3)'] as $needle) {
 if (!str_contains($src,$needle)) {fwrite(STDERR,"Missing warehouse guard: $needle\n");exit(1);}
}
$pattern='/^\\d+(?:\\.\\d{1,3})?$/D';
foreach (['1','0.001','123.999'] as $valid) if(!preg_match($pattern,$valid))throw new RuntimeException("Rejected valid quantity: $valid");
foreach (['-1','1.0001','1e3','abc','1,2'] as $invalid) if(preg_match($pattern,$invalid))throw new RuntimeException("Accepted invalid quantity: $invalid");
echo "Warehouse operational fixes smoke tests passed\n";
