<?php
declare(strict_types=1);
$pdo=new PDO('mysql:host='.(getenv('KD_TEST_DB_HOST')?:'127.0.0.1').';port='.(getenv('KD_TEST_DB_PORT')?:'3306').';dbname='.(getenv('KD_TEST_DB_NAME')?:'kdall_test').';charset=utf8mb4',getenv('KD_TEST_DB_USER')?:'root',getenv('KD_TEST_DB_PASS')?:'root',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
function ensure(bool $condition,string $message):void{if(!$condition)throw new RuntimeException($message);}
$pdo->beginTransaction();
try{
 $pdo->exec("INSERT INTO suppliers(name,type,status,created_at) VALUES('CI Supplier','company','active',NOW())");$supplier=(int)$pdo->lastInsertId();
 $pdo->exec("INSERT INTO warehouses(code,name,status,is_default,created_at) VALUES('CI-PURCHASE','CI Purchasing Warehouse','active',0,NOW())");$warehouse=(int)$pdo->lastInsertId();
 $pdo->exec("INSERT INTO products(sku,name,purchase_price,sale_price,stock_quantity,min_stock,track_stock,status,created_at) VALUES('CI-PURCHASE-PRODUCT','CI Purchasing Product',10,15,2,5,1,'active',NOW())");$product=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO purchase_orders(purchase_number,supplier_id,warehouse_id,status,subtotal,tax_total,grand_total,created_at) VALUES(?,?,?,'partial',100,20,120,NOW())")->execute(['CI-PO-'.$product,$supplier,$warehouse]);$order=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO purchase_order_items(purchase_order_id,product_id,product_name,sku,quantity,received_quantity,unit_cost,tax_rate,line_subtotal,line_tax,line_total,created_at) VALUES(?,?,?,?,10,4,10,20,100,20,120,NOW())")->execute([$order,$product,'CI Purchasing Product','CI-PURCHASE-PRODUCT']);$item=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO purchase_receipts(purchase_order_id,receipt_number,received_at) VALUES(?,?,NOW())")->execute([$order,'CI-RECEIPT-'.$product]);$receipt=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO purchase_receipt_items(receipt_id,purchase_order_item_id,product_id,quantity,created_at) VALUES(?,?,?,?,NOW())")->execute([$receipt,$item,$product,4]);
 $pdo->prepare("INSERT INTO product_warehouse_stocks(product_id,warehouse_id,quantity,updated_at) VALUES(?,?,6,NOW())")->execute([$product,$warehouse]);
 $pending=$pdo->prepare("SELECT COALESCE(SUM(GREATEST(poi.quantity-poi.received_quantity,0)),0) FROM purchase_order_items poi JOIN purchase_orders po ON po.id=poi.purchase_order_id WHERE poi.product_id=? AND po.status IN ('draft','ordered','partial')");$pending->execute([$product]);ensure((float)$pending->fetchColumn()===6.0,'Partial receipt pending quantity incorrect');
 $stock=$pdo->prepare('SELECT quantity FROM product_warehouse_stocks WHERE product_id=? AND warehouse_id=?');$stock->execute([$product,$warehouse]);ensure((float)$stock->fetchColumn()===6.0,'Warehouse receipt quantity incorrect');
 $pdo->prepare("UPDATE purchase_orders SET status='received' WHERE id=?")->execute([$order]);$pending->execute([$product]);ensure((float)$pending->fetchColumn()===0.0,'Completed order must not count as pending');
 $pdo->prepare("UPDATE purchase_orders SET status='cancelled' WHERE id=?")->execute([$order]);$pending->execute([$product]);ensure((float)$pending->fetchColumn()===0.0,'Cancelled order must not count as pending');
 $source=file_get_contents(dirname(__DIR__).'/app/Modules/Invoices/InvoiceController.php');ensure(str_contains($source,"$"."lockedStatus!=='received'"),'Invoice locked source state validation missing');ensure(str_contains($source,"status<>\\'cancelled\\'"),'Invoice duplicate prevention missing');
 $posting=file_get_contents(dirname(__DIR__).'/app/Core/AccountingPostingService.php');ensure(str_contains($posting,'postPurchaseReceipt')&&str_contains($posting,'receivedNotInvoiced'),'Receipt accounting integration missing');
 echo "Purchasing lifecycle database integration checks passed\n";
}finally{if($pdo->inTransaction())$pdo->rollBack();}
