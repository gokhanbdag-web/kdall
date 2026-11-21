<?php
declare(strict_types=1);
$pdo=new PDO('mysql:host='.(getenv('KD_TEST_DB_HOST')?:'127.0.0.1').';port='.(getenv('KD_TEST_DB_PORT')?:'3306').';dbname='.(getenv('KD_TEST_DB_NAME')?:'kdall_test').';charset=utf8mb4',getenv('KD_TEST_DB_USER')?:'root',getenv('KD_TEST_DB_PASS')?:'root',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
function verify(bool $condition,string $message):void {if(!$condition)throw new RuntimeException($message);}
$pdo->beginTransaction();
try {
 $pdo->exec("INSERT INTO warehouses(code,name,status,is_default,created_at) VALUES('CI-WH-A','CI Warehouse A','active',0,NOW()),('CI-WH-B','CI Warehouse B','active',0,NOW())");
 $wids=$pdo->query("SELECT id FROM warehouses WHERE code IN ('CI-WH-A','CI-WH-B') ORDER BY id")->fetchAll(PDO::FETCH_COLUMN);verify(count($wids)===2,'Warehouse fixtures missing');
 [$a,$b]=array_map('intval',$wids);
 $pdo->exec("INSERT INTO products(sku,name,purchase_price,sale_price,stock_quantity,min_stock,track_stock,status,created_at) VALUES('CI-WH-ITEM','CI Warehouse Item',12,18,10,3,1,'active',NOW())");
 $pid=(int)$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO product_warehouse_stocks(product_id,warehouse_id,quantity,updated_at) VALUES(?,?,?,NOW()),(?,?,?,NOW())')->execute([$pid,$a,10,$pid,$b,0]);
 $select=$pdo->prepare('SELECT quantity FROM product_warehouse_stocks WHERE product_id=? AND warehouse_id=? FOR UPDATE');
 $select->execute([$pid,$a]);$beforeA=(float)$select->fetchColumn();$select->execute([$pid,$b]);$beforeB=(float)$select->fetchColumn();
 verify($beforeA===10.0&&$beforeB===0.0,'Initial stock mismatch');
 $pdo->prepare('UPDATE product_warehouse_stocks SET quantity=quantity-?,updated_at=NOW() WHERE product_id=? AND warehouse_id=? AND quantity>=?')->execute([4,$pid,$a,4]);
 $pdo->prepare('UPDATE product_warehouse_stocks SET quantity=quantity+?,updated_at=NOW() WHERE product_id=? AND warehouse_id=?')->execute([4,$pid,$b]);
 $select->execute([$pid,$a]);$afterA=(float)$select->fetchColumn();$select->execute([$pid,$b]);$afterB=(float)$select->fetchColumn();
 verify($afterA===6.0&&$afterB===4.0,'Transfer did not preserve warehouse balances');
 $sum=$pdo->prepare('SELECT SUM(quantity) FROM product_warehouse_stocks WHERE product_id=?');$sum->execute([$pid]);
 verify((float)$sum->fetchColumn()===10.0,'Transfer changed global inventory');
 $pdo->prepare('UPDATE product_warehouse_stocks SET quantity=quantity-? WHERE product_id=? AND warehouse_id=? AND quantity>=?')->execute([100,$pid,$a,100]);
 $select->execute([$pid,$a]);verify((float)$select->fetchColumn()===6.0,'Insufficient-stock withdrawal changed balance');
 $pdo->prepare('INSERT INTO warehouse_stocktakes(stocktake_number,warehouse_id,status,created_at) VALUES(?,?,\'draft\',NOW())')->execute(['CI-STOCKTAKE-'.$pid,$a]);
 $sid=(int)$pdo->lastInsertId();
 $pdo->prepare('INSERT INTO warehouse_stocktake_items(stocktake_id,product_id,expected_quantity,counted_quantity,variance) VALUES(?,?,?,?,?)')->execute([$sid,$pid,6,5,-1]);
 $stocktake=$pdo->prepare('SELECT expected_quantity,counted_quantity FROM warehouse_stocktake_items WHERE stocktake_id=? AND product_id=?');$stocktake->execute([$sid,$pid]);$row=$stocktake->fetch();
 verify((float)$row['expected_quantity']===6.0&&(float)$row['counted_quantity']===5.0,'Stocktake snapshot mismatch');
 $pdo->prepare('UPDATE product_warehouse_stocks SET quantity=quantity-1 WHERE product_id=? AND warehouse_id=?')->execute([$pid,$a]);
 $select->execute([$pid,$a]);verify((float)$select->fetchColumn()!==(float)$row['expected_quantity'],'Concurrent change guard fixture invalid');
 $pdo->prepare('UPDATE product_warehouse_stocks SET quantity=6 WHERE product_id=? AND warehouse_id=?')->execute([$pid,$a]);
 $pdo->prepare('UPDATE product_warehouse_stocks SET quantity=5 WHERE product_id=? AND warehouse_id=?')->execute([$pid,$a]);
 $sum->execute([$pid]);$global=(float)$sum->fetchColumn();
 $pdo->prepare('UPDATE products SET stock_quantity=? WHERE id=?')->execute([$global,$pid]);
 verify($global===9.0,'Stocktake global stock sum incorrect');
 $check=$pdo->prepare('SELECT stock_quantity FROM products WHERE id=?');$check->execute([$pid]);verify((float)$check->fetchColumn()===$global,'Product total differs from warehouse sum');
 $value=$pdo->prepare('SELECT SUM(s.quantity*p.purchase_price) FROM product_warehouse_stocks s JOIN products p ON p.id=s.product_id WHERE s.product_id=?');$value->execute([$pid]);verify((float)$value->fetchColumn()===108.0,'Inventory valuation mismatch');
 $source=file_get_contents(dirname(__DIR__).'/app/Modules/Warehouses/WarehousesController.php');
 verify(str_contains($source,'ksort($items,SORT_NUMERIC)'),'Deterministic product lock order missing');
 foreach(['app/Modules/Orders/OrderController.php','app/Modules/Purchasing/PurchasingController.php','app/Modules/Production/ProductionController.php','app/Modules/POS/POSController.php','app/Modules/Returns/ReturnController.php'] as $file){
  $contents=file_get_contents(dirname(__DIR__).'/'.$file);
  verify(str_contains($contents,'product_warehouse_stocks')&&str_contains($contents,'warehouse_id'),"Warehouse integration missing: ".$file);
 }
 echo "Warehouse integrity database integration tests passed\n";
} finally {if($pdo->inTransaction())$pdo->rollBack();}
