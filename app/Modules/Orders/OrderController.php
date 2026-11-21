<?php
declare(strict_types=1);

namespace KodVadAll\Modules\Orders;

use PDO;
use RuntimeException;
use Throwable;

final class OrderController
{
    private const STATUSES = ['draft','confirmed','processing','shipped','completed','cancelled'];

    public static function handle(PDO $pdo,array $admin): void
    {
        kd_require_permission('orders.view');
        $action=(string)($_GET['action']??'list');
        $id=max(0,(int)($_GET['id']??0));
        $errors=[];

        if ($_SERVER['REQUEST_METHOD']==='POST') {
            kd_csrf_check();
            kd_require_permission('orders.manage');
            try {
                $formAction=(string)($_POST['form_action']??'');
                if ($formAction==='create') {
                    $customerId=max(0,(int)($_POST['customer_id']??0));
                    $warehouseId=max(0,(int)($_POST['warehouse_id']??0));
                    $productIds=is_array($_POST['product_id']??null)?$_POST['product_id']:[];
                    $quantities=is_array($_POST['quantity']??null)?$_POST['quantity']:[];
                    $prices=is_array($_POST['unit_price']??null)?$_POST['unit_price']:[];
                    $taxes=is_array($_POST['tax_rate']??null)?$_POST['tax_rate']:[];
                    $items=[];$seenProducts=[];
                    foreach ($productIds as $i=>$rawId) {
                        $productId=(int)$rawId;
                        $rawQty=$quantities[$i]??null;
                        if ($productId<1 && ($rawQty===null || trim((string)$rawQty)==='')) continue;
                        if ($productId<1 || !self::validOrderNumber($rawQty,3,false)) throw new RuntimeException('Ürün ve miktar bilgilerini kontrol edin.');
                        if(isset($seenProducts[$productId]))throw new RuntimeException('Aynı ürün bir siparişte birden fazla satırda olamaz.');
                        $seenProducts[$productId]=true;$qty=(float)$rawQty;
                        $stmt=$pdo->prepare("SELECT id,name,sku,sale_price,purchase_price,status FROM products WHERE id=? LIMIT 1");
                        $stmt->execute([$productId]);$product=$stmt->fetch();
                        if (!$product || $product['status']!=='active') throw new RuntimeException('Seçilen ürün bulunamadı veya aktif değil.');
                        $rawPrice=$prices[$i]??$product['sale_price'];$rawTax=$taxes[$i]??0;
                        if(!self::validOrderNumber($rawPrice,4,true)||!self::validOrderNumber($rawTax,2,true))throw new RuntimeException('Fiyat veya vergi oranı geçersiz.');
                        $price=(float)$rawPrice;$tax=(float)$rawTax;
                        if ($tax>100) throw new RuntimeException('Fiyat veya vergi oranı geçersiz.');
                        $subtotal=round($qty*$price,2);
                        $taxAmount=round($subtotal*$tax/100,2);
                        $items[]=[
                            'product_id'=>$productId,'name'=>$product['name'],'sku'=>$product['sku'],
                            'quantity'=>$qty,'unit_price'=>$price,'unit_cost'=>(float)$product['purchase_price'],'tax_rate'=>$tax,
                            'subtotal'=>$subtotal,'tax'=>$taxAmount,'total'=>$subtotal+$taxAmount
                        ];
                    }
                    if (!$items) throw new RuntimeException('Siparişe en az bir ürün ekleyin.');
                    if ($customerId>0) {
                        $check=$pdo->prepare("SELECT id FROM customers WHERE id=? AND status='active'");
                        $check->execute([$customerId]);
                        if (!$check->fetchColumn()) throw new RuntimeException('Seçilen müşteri bulunamadı veya aktif değil.');
                    }
                    $subtotal=round(array_sum(array_column($items,'subtotal')),2);
                    $taxTotal=round(array_sum(array_column($items,'tax')),2);
                    $grand=round($subtotal+$taxTotal,2);
                    if(!is_finite($grand)||$grand>1000000000)throw new RuntimeException('Sipariş toplamı geçersiz.');
                    $couponCode=strtoupper(trim((string)($_POST['coupon_code']??'')));
                    $discountTotal=0.0;
                    $campaign=null;
                    $pdo->beginTransaction();
                    if($warehouseId<1)$warehouseId=(int)$pdo->query("SELECT id FROM warehouses WHERE is_default=1 AND status='active' LIMIT 1")->fetchColumn();
                    $warehouseCheck=$pdo->prepare("SELECT id FROM warehouses WHERE id=? AND status='active' FOR UPDATE");$warehouseCheck->execute([$warehouseId]);
                    if(!$warehouseCheck->fetchColumn())throw new RuntimeException('Aktif bir çıkış deposu seçin.');
                    if($couponCode!==''){
                        $couponQuery=$pdo->prepare("SELECT * FROM campaigns WHERE code=? AND status='active' AND starts_at<=NOW() AND (ends_at IS NULL OR ends_at>=NOW()) FOR UPDATE");
                        $couponQuery->execute([$couponCode]);
                        $campaign=$couponQuery->fetch();
                        if(!$campaign) throw new RuntimeException('Kupon kodu geçersiz, süresi dolmuş veya kullanıma kapalı.');
                        if((float)$campaign['minimum_order']>$subtotal) throw new RuntimeException('Kupon için minimum sepet tutarı karşılanmıyor.');
                        if((int)$campaign['usage_limit']>0 && (int)$campaign['usage_count']>=(int)$campaign['usage_limit']) throw new RuntimeException('Kupon kullanım limiti dolmuş.');
                        if($customerId>0 && (int)$campaign['per_customer_limit']>0){
                            $used=$pdo->prepare('SELECT COUNT(*) FROM coupon_redemptions WHERE campaign_id=? AND customer_id=?');
                            $used->execute([(int)$campaign['id'],$customerId]);
                            if((int)$used->fetchColumn()>=(int)$campaign['per_customer_limit']) throw new RuntimeException('Bu kupon müşteri kullanım limitine ulaştı.');
                        }
                        $discountTotal=$campaign['discount_type']==='percentage'?round($subtotal*(float)$campaign['discount_value']/100,2):min($subtotal,(float)$campaign['discount_value']);
                        if((float)$campaign['maximum_discount']>0)$discountTotal=min($discountTotal,(float)$campaign['maximum_discount']);
                        $discountTotal=min($discountTotal,$grand);
                        if($discountTotal<=0)throw new RuntimeException('Kupon indirimi hesaplanamadı.');
                        $grand-=$discountTotal;
                    }
                    $temporaryNumber='TMP-'.bin2hex(random_bytes(10));
                    $stmt=$pdo->prepare("INSERT INTO orders (order_number,customer_id,warehouse_id,status,subtotal,tax_total,discount_total,grand_total,coupon_code,currency,created_at) VALUES (?,?,?, 'draft',?,?,?,?,?,'TRY',NOW())");
                    $stmt->execute([$temporaryNumber,$customerId?:null,$warehouseId,$subtotal,$taxTotal,$discountTotal,$grand,$couponCode?:null]);
                    $orderId=(int)$pdo->lastInsertId();
                    $number='SIP-'.date('Y').'-'.str_pad((string)$orderId,6,'0',STR_PAD_LEFT);
                    $pdo->prepare('UPDATE orders SET order_number=? WHERE id=?')->execute([$number,$orderId]);
                    $insert=$pdo->prepare('INSERT INTO order_items (order_id,product_id,product_name,sku,quantity,unit_price,unit_cost,tax_rate,line_subtotal,line_tax,line_total,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,NOW())');
                    foreach($items as $item) {
                        $insert->execute([$orderId,$item['product_id'],$item['name'],$item['sku'],$item['quantity'],$item['unit_price'],$item['unit_cost'],$item['tax_rate'],$item['subtotal'],$item['tax'],$item['total']]);
                    }
                    if($discountTotal>0 && $campaign){
                        $insert->execute([$orderId,null,'Kampanya indirimi: '.$couponCode,$couponCode,1,-$discountTotal,0,0,-$discountTotal,0,-$discountTotal]);
                        $pdo->prepare('INSERT INTO coupon_redemptions (campaign_id,order_id,customer_id,discount_amount,redeemed_at) VALUES (?,?,?,?,NOW())')->execute([(int)$campaign['id'],$orderId,$customerId?:null,$discountTotal]);
                        $pdo->prepare('UPDATE campaigns SET usage_count=usage_count+1 WHERE id=?')->execute([(int)$campaign['id']]);
                    }
                    self::history($pdo,$orderId,(int)$admin['id'],null,'draft','Sipariş oluşturuldu');
                    $pdo->commit();
                    kd_audit('order.created','order',$orderId);
                    $orderNumber=$number;
                    \KodVadAll\Core\NotificationService::notifyRoles($pdo,['owner','admin','manager'],'Yeni satış siparişi',$orderNumber.' numaralı sipariş oluşturuldu.','index.php?page=orders&action=view&id='.$orderId,'success');
                    \KodVadAll\Core\WebhookService::enqueue($pdo,'order.created',['id'=>$orderId,'order_number'=>$orderNumber,'status'=>'draft','grand_total'=>$grand]);
                    self::redirect('view',$orderId);
                } elseif ($formAction==='status') {
                    $orderId=max(0,(int)($_POST['order_id']??0));
                    $target=(string)($_POST['status']??'');
                    $note=trim((string)($_POST['note']??''));
                    if (!in_array($target,self::STATUSES,true)) throw new RuntimeException('Sipariş durumu geçersiz.');
                    $pdo->beginTransaction();
                    $lock=$pdo->prepare('SELECT * FROM orders WHERE id=? FOR UPDATE');
                    $lock->execute([$orderId]);$order=$lock->fetch();
                    if (!$order) throw new RuntimeException('Sipariş bulunamadı.');
                    $from=(string)$order['status'];
                    self::validateTransition($from,$target);
                    if($target==='confirmed' && in_array((string)($order["payment_method"]??'manual'),['paytr','bank_transfer'],true) && (string)($order["payment_status"]??'pending')!=='paid') throw new RuntimeException('Çevrimiçi ödeme veya banka havalesi doğrulanmadan sipariş onaylanamaz.');
                    if($target==='cancelled'){
                        if((string)($order['order_source']??'')==='pos'||(string)($order['payment_status']??'')==='paid')throw new RuntimeException('Ödemesi alınmış veya POS üzerinden tamamlanmış satış doğrudan iptal edilemez. İade işlemini kullanın.');
                        $invoiceCheck=$pdo->prepare("SELECT COUNT(*) FROM invoices WHERE source_order_id=? AND status IN ('issued','paid')");$invoiceCheck->execute([$orderId]);
                        if((int)$invoiceCheck->fetchColumn()>0)throw new RuntimeException('Bu siparişe ait muhasebeleştirilmiş fatura var. Önce faturayı iptal edin, ardından siparişi iptal edebilirsiniz.');
                    }
                    if ($target==='confirmed' && $from==='draft') self::deductStock($pdo,$orderId,$number=(string)$order['order_number'],(int)($order['warehouse_id']??0));
                    if ($target==='cancelled' && !empty($order['stock_deducted_at']) && empty($order['stock_reversed_at'])) self::restoreStock($pdo,$orderId,(string)$order['order_number'],(int)($order['warehouse_id']??0));
                    $pdo->prepare('UPDATE orders SET status=?,stock_deducted_at=IF(?=?,NOW(),stock_deducted_at),stock_reversed_at=IF(?=? AND stock_deducted_at IS NOT NULL,NOW(),stock_reversed_at),updated_at=NOW() WHERE id=?')->execute([$target,$target,'confirmed',$target,'cancelled',$orderId]);
                    self::history($pdo,$orderId,(int)$admin['id'],$from,$target,$note);
                    if($target==='completed' && !empty($order['customer_id'])){
                        $points=(int)floor((float)$order['grand_total']/100);
                        if($points>0){
                            $pdo->prepare('INSERT IGNORE INTO loyalty_accounts (customer_id,points_balance,updated_at) VALUES (?,0,NOW())')->execute([(int)$order['customer_id']]);
                            $pointTx=$pdo->prepare("INSERT IGNORE INTO loyalty_transactions (customer_id,order_id,direction,points,description,created_by,created_at) VALUES (?,?,'earn',?,?,?,NOW())");
                            $pointTx->execute([(int)$order['customer_id'],$orderId,$points,'Tamamlanan sipariş '.$order['order_number'],(int)$admin['id']]);
                            if($pointTx->rowCount()>0)$pdo->prepare('UPDATE loyalty_accounts SET points_balance=points_balance+?,updated_at=NOW() WHERE customer_id=?')->execute([$points,(int)$order['customer_id']]);
                        }
                    }
                    $pdo->commit();
                    kd_audit('order.status_changed','order',$orderId);
                    \KodVadAll\Core\NotificationService::notifyRoles($pdo,['owner','admin','manager'],'Sipariş durumu güncellendi',$order['order_number'].' siparişi: '.$target.'.','index.php?page=orders&action=view&id='.$orderId,'info');
                    \KodVadAll\Core\WebhookService::enqueue($pdo,'order.updated',['id'=>$orderId,'order_number'=>$order['order_number'],'status'=>$target]);
                    self::redirect('view',$orderId);
                }
            } catch(Throwable $e) {
                if($pdo->inTransaction()) $pdo->rollBack();
                error_log('KodVadAll order module: '.$e->getMessage());
                $errors[]=$e instanceof RuntimeException?$e->getMessage():'İşlem tamamlanamadı. Lütfen tekrar deneyin.';
                $action=(string)($_GET['action']??'list');
                $id=max(0,(int)($_GET['id']??0));
            }
        }

        if($action==='create') {
            kd_require_permission('orders.manage');
            $customers=$pdo->query("SELECT id,name FROM customers WHERE status='active' ORDER BY name")->fetchAll();
            $products=$pdo->query("SELECT id,name,sku,sale_price,stock_quantity,unit FROM products WHERE status='active' ORDER BY name")->fetchAll();
            $warehouses=$pdo->query("SELECT id,name,code,is_default FROM warehouses WHERE status='active' ORDER BY is_default DESC,name")->fetchAll();
            $defaultWarehouseId=(int)$pdo->query("SELECT id FROM warehouses WHERE is_default=1 AND status='active' LIMIT 1")->fetchColumn();
            $pageTitle='Yeni sipariş';
            ob_start();require KD_ROOT.'/app/Views/orders/form.php';$content=ob_get_clean();
            require KD_ROOT.'/app/Views/layouts/admin.php';return;
        }
        if($action==='view') {
            $stmt=$pdo->prepare('SELECT o.*,c.name AS customer_name,w.name AS warehouse_name FROM orders o LEFT JOIN customers c ON c.id=o.customer_id LEFT JOIN warehouses w ON w.id=o.warehouse_id WHERE o.id=? LIMIT 1');
            $stmt->execute([$id]);$order=$stmt->fetch();
            if(!$order) self::notFound();
            $stmt=$pdo->prepare('SELECT * FROM order_items WHERE order_id=? ORDER BY id');
            $stmt->execute([$id]);$items=$stmt->fetchAll();
            $stmt=$pdo->prepare('SELECT h.*,u.name AS user_name FROM order_status_history h LEFT JOIN users u ON u.id=h.user_id WHERE h.order_id=? ORDER BY h.id DESC');
            $stmt->execute([$id]);$history=$stmt->fetchAll();
            $pageTitle='Sipariş '.$order['order_number'];
            ob_start();require KD_ROOT.'/app/Views/orders/detail.php';$content=ob_get_clean();
            require KD_ROOT.'/app/Views/layouts/admin.php';return;
        }
        if($action!=='list') self::notFound();

        $q=trim((string)($_GET['q']??''));
        $status=in_array($_GET['status']??'',self::STATUSES,true)?(string)$_GET['status']:'';
        $page=max(1,(int)($_GET['p']??1));$limit=20;
        $where=[];$params=[];
        if($q!=='') {$where[]='(o.order_number LIKE ? OR c.name LIKE ?)';$like='%'.$q.'%';$params[]=$like;$params[]=$like;}
        if($status!=='') {$where[]='o.status=?';$params[]=$status;}
        $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
        $count=$pdo->prepare('SELECT COUNT(*) FROM orders o LEFT JOIN customers c ON c.id=o.customer_id'.$whereSql);$count->execute($params);
        $total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/$limit));$page=min($page,$pages);$offset=($page-1)*$limit;
        $stmt=$pdo->prepare('SELECT o.*,c.name AS customer_name,w.name AS warehouse_name FROM orders o LEFT JOIN customers c ON c.id=o.customer_id LEFT JOIN warehouses w ON w.id=o.warehouse_id'.$whereSql.' ORDER BY o.id DESC LIMIT '.$limit.' OFFSET '.$offset);
        $stmt->execute($params);$orders=$stmt->fetchAll();
        $pageTitle='Satış & Siparişler';
        ob_start();require KD_ROOT.'/app/Views/orders/list.php';$content=ob_get_clean();
        require KD_ROOT.'/app/Views/layouts/admin.php';
    }

    private static function validOrderNumber(mixed $value,int $precision,bool $allowZero):bool
    {
        if(!is_scalar($value)||is_bool($value))return false;
        $raw=trim((string)$value);
        if(!preg_match('/^\\d{1,9}(?:\\.\\d{1,'.$precision.'})?$/D',$raw))return false;
        $number=(float)$raw;
        return is_finite($number)&&$number<=1000000000&&($allowZero?$number>=0:$number>0);
    }

    private static function validateTransition(string $from,string $to): void
    {
        $allowed=[
            'draft'=>['confirmed','cancelled'],
            'confirmed'=>['processing','cancelled'],
            'processing'=>['shipped','cancelled'],
            'shipped'=>['completed'],
            'completed'=>[],
            'cancelled'=>[]
        ];
        if(!in_array($to,$allowed[$from]??[],true)) throw new RuntimeException('Bu sipariş durum geçişine izin verilmiyor.');
    }

    private static function resolveWarehouseId(PDO $pdo,int $warehouseId,bool $activeOnly): int
    {
        if($warehouseId<1)$warehouseId=(int)$pdo->query("SELECT id FROM warehouses WHERE is_default=1 AND status='active' LIMIT 1")->fetchColumn();
        if($warehouseId<1)throw new RuntimeException('Sipariş deposu bulunamadı.');
        $sql='SELECT id FROM warehouses WHERE id=?'.($activeOnly?" AND status='active'":'').' LIMIT 1';
        $check=$pdo->prepare($sql);$check->execute([$warehouseId]);
        if(!$check->fetchColumn())throw new RuntimeException($activeOnly?'Siparişin çıkış deposu aktif değil.':'Siparişin bağlı olduğu depo bulunamadı.');
        return $warehouseId;
    }

    private static function deductStock(PDO $pdo,int $orderId,string $number,int $warehouseId): void
    {
        $warehouseId=self::resolveWarehouseId($pdo,$warehouseId,true);
        $items=$pdo->prepare('SELECT product_id,quantity,product_name FROM order_items WHERE order_id=?');$items->execute([$orderId]);
        foreach($items->fetchAll() as $item){
            if(!$item['product_id'])continue;$pid=(int)$item['product_id'];
            $lock=$pdo->prepare('SELECT stock_quantity,track_stock FROM products WHERE id=? FOR UPDATE');$lock->execute([$pid]);$product=$lock->fetch();
            if(!$product||!(int)$product['track_stock'])continue;
            $q=$pdo->prepare('SELECT quantity FROM product_warehouse_stocks WHERE product_id=? AND warehouse_id=? FOR UPDATE');$q->execute([$pid,$warehouseId]);$stock=$q->fetch();$before=$stock?(float)$stock['quantity']:0.0;$qty=(float)$item['quantity'];$after=$before-$qty;
            if($after<0)throw new RuntimeException('Varsayılan depoda yetersiz stok: '.$item['product_name']);
            if(!$stock)$pdo->prepare('INSERT INTO product_warehouse_stocks(product_id,warehouse_id,quantity,updated_at) VALUES(?,?,0,NOW())')->execute([$pid,$warehouseId]);
            $pdo->prepare('UPDATE product_warehouse_stocks SET quantity=?,updated_at=NOW() WHERE product_id=? AND warehouse_id=?')->execute([$after,$pid,$warehouseId]);
            $total=(float)$pdo->query('SELECT COALESCE(SUM(quantity),0) FROM product_warehouse_stocks WHERE product_id='.$pid)->fetchColumn();
            $pdo->prepare('UPDATE products SET stock_quantity=?,updated_at=NOW() WHERE id=?')->execute([$total,$pid]);
            $move=$pdo->prepare("INSERT INTO product_stock_movements(product_id,warehouse_id,user_id,movement_type,quantity,before_quantity,after_quantity,note,reference_type,reference_id,created_at) VALUES(?,?,?,'out',?,?,?,?,?,?,NOW())");
            $move->execute([$pid,$warehouseId,(int)($_SESSION['user']['id']??0),$qty,$before,$after,'Sipariş '.$number,'order',$orderId]);
        }
    }

    private static function restoreStock(PDO $pdo,int $orderId,string $number,int $warehouseId): void
    {
        $warehouseId=self::resolveWarehouseId($pdo,$warehouseId,false);
        $items=$pdo->prepare('SELECT product_id,quantity,product_name FROM order_items WHERE order_id=?');$items->execute([$orderId]);
        foreach($items->fetchAll() as $item){
            if(!$item['product_id'])continue;$pid=(int)$item['product_id'];
            $lock=$pdo->prepare('SELECT stock_quantity,track_stock FROM products WHERE id=? FOR UPDATE');$lock->execute([$pid]);$product=$lock->fetch();
            if(!$product||!(int)$product['track_stock'])continue;
            $q=$pdo->prepare('SELECT quantity FROM product_warehouse_stocks WHERE product_id=? AND warehouse_id=? FOR UPDATE');$q->execute([$pid,$warehouseId]);$stock=$q->fetch();$before=$stock?(float)$stock['quantity']:0.0;$qty=(float)$item['quantity'];$after=$before+$qty;
            if(!$stock)$pdo->prepare('INSERT INTO product_warehouse_stocks(product_id,warehouse_id,quantity,updated_at) VALUES(?,?,0,NOW())')->execute([$pid,$warehouseId]);
            $pdo->prepare('UPDATE product_warehouse_stocks SET quantity=?,updated_at=NOW() WHERE product_id=? AND warehouse_id=?')->execute([$after,$pid,$warehouseId]);
            $total=(float)$pdo->query('SELECT COALESCE(SUM(quantity),0) FROM product_warehouse_stocks WHERE product_id='.$pid)->fetchColumn();
            $pdo->prepare('UPDATE products SET stock_quantity=?,updated_at=NOW() WHERE id=?')->execute([$total,$pid]);
            $move=$pdo->prepare("INSERT INTO product_stock_movements(product_id,warehouse_id,user_id,movement_type,quantity,before_quantity,after_quantity,note,reference_type,reference_id,created_at) VALUES(?,?,?,'in',?,?,?,?,?,?,NOW())");
            $move->execute([$pid,$warehouseId,(int)($_SESSION['user']['id']??0),$qty,$before,$after,'İptal edilen sipariş '.$number,'order',$orderId]);
        }
    }

    private static function history(PDO $pdo,int $orderId,int $userId,?string $from,string $to,string $note): void
    {
        $stmt=$pdo->prepare('INSERT INTO order_status_history (order_id,user_id,from_status,to_status,note,created_at) VALUES (?,?,?,?,?,NOW())');
        $stmt->execute([$orderId,$userId,$from,$to,$note?:null]);
    }

    private static function redirect(string $action,int $id=0): never
    {
        $url='index.php?page=orders';
        if($action!=='list') $url.='&action='.rawurlencode($action);
        if($id>0) $url.='&id='.$id;
        header('Location: '.$url,true,303);exit;
    }

    private static function notFound(): never
    {
        http_response_code(404);exit('Sipariş bulunamadı.');
    }
}
