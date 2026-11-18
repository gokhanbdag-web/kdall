<?php
declare(strict_types=1);

namespace KodVadAll\Modules\Warehouses;

use PDO;
use RuntimeException;
use Throwable;

final class WarehousesController
{
    public static function handle(PDO $pdo,array $admin):void
    {
        kd_require_permission('warehouses.view');
        $errors=[];$action=(string)($_GET['action']??'list');
        if($_SERVER['REQUEST_METHOD']==='POST'){
            kd_csrf_check();kd_require_permission('warehouses.manage');
            try{
                $form=(string)($_POST['form_action']??'');
                if($form==='create'){
                    $name=trim((string)($_POST['name']??''));$code=strtoupper(trim((string)($_POST['code']??'')));$address=trim((string)($_POST['address']??''));
                    if($name===''||mb_strlen($name)>140)throw new RuntimeException('Depo adı zorunludur ve 140 karakteri geçemez.');
                    if(!preg_match('/^[A-Z0-9_-]{2,40}$/',$code))throw new RuntimeException('Depo kodu 2-40 karakter olmalı; harf, rakam, tire ve alt çizgi kullanılabilir.');
                    $q=$pdo->prepare("INSERT INTO warehouses(code,name,address,status,is_default,created_at) VALUES(?,?,?,'active',0,NOW())");
                    $q->execute([$code,$name,$address?:null]);$id=(int)$pdo->lastInsertId();kd_audit('warehouse.created','warehouse',$id);self::go();
                }
                if($form==='archive'){
                    $id=max(0,(int)($_POST['warehouse_id']??0));$pdo->beginTransaction();
                    $q=$pdo->prepare('SELECT id,is_default FROM warehouses WHERE id=? FOR UPDATE');$q->execute([$id]);$w=$q->fetch();
                    if(!$w)throw new RuntimeException('Depo bulunamadı.');
                    if((int)$w['is_default']===1)throw new RuntimeException('Varsayılan depo pasife alınamaz.');
                    $q=$pdo->prepare('SELECT COALESCE(SUM(quantity),0) FROM product_warehouse_stocks WHERE warehouse_id=?');$q->execute([$id]);
                    if((float)$q->fetchColumn()>0)throw new RuntimeException('Stoğu bulunan depo pasife alınamaz. Önce stokları başka depoya transfer edin.');
                    $pdo->prepare("UPDATE warehouses SET status='inactive',updated_at=NOW() WHERE id=?")->execute([$id]);$pdo->commit();kd_audit('warehouse.archived','warehouse',$id);self::go();
                }
                if($form==='transfer'){
                    $from=max(0,(int)($_POST['from_warehouse_id']??0));$to=max(0,(int)($_POST['to_warehouse_id']??0));
                    if($from<1||$to<1||$from===$to)throw new RuntimeException('Farklı kaynak ve hedef depolar seçin.');
                    $ids=is_array($_POST['product_id']??null)?$_POST['product_id']:[];$qtys=is_array($_POST['quantity']??null)?$_POST['quantity']:[];
                    $items=[];foreach($ids as $i=>$raw){$pid=(int)$raw;$qty=(float)($qtys[$i]??0);if($pid<1||$qty<0)throw new RuntimeException('Ürün veya transfer miktarı geçersiz.');if($qty>0)$items[$pid]=($items[$pid]??0)+$qty;}
                    if(!$items)throw new RuntimeException('Transfer için en az bir ürün miktarı girin.');
                    $note=trim((string)($_POST['note']??''));if(mb_strlen($note)>255)throw new RuntimeException('Transfer açıklaması 255 karakteri geçemez.');
                    $pdo->beginTransaction();
                    $q=$pdo->prepare("SELECT id FROM warehouses WHERE id IN (?,?) AND status='active' ORDER BY id FOR UPDATE");$q->execute([$from,$to]);if(count($q->fetchAll())!==2)throw new RuntimeException('Kaynak ve hedef depolar aktif olmalıdır.');
                    $tmp='TMP-'.bin2hex(random_bytes(8));
                    $pdo->prepare("INSERT INTO warehouse_transfers(transfer_number,from_warehouse_id,to_warehouse_id,status,note,created_by,created_at) VALUES(?,?,?,'completed',?,?,NOW())")->execute([$tmp,$from,$to,$note?:null,(int)$admin['id']]);
                    $transferId=(int)$pdo->lastInsertId();$number='TRF-'.date('Y').'-'.str_pad((string)$transferId,6,'0',STR_PAD_LEFT);
                    $pdo->prepare('UPDATE warehouse_transfers SET transfer_number=? WHERE id=?')->execute([$number,$transferId]);
                    $itemInsert=$pdo->prepare('INSERT INTO warehouse_transfer_items(transfer_id,product_id,quantity,created_at) VALUES(?,?,?,NOW())');
                    $movement=$pdo->prepare('INSERT INTO product_stock_movements(product_id,warehouse_id,transfer_id,user_id,movement_type,quantity,before_quantity,after_quantity,note,created_at) VALUES(?,?,?,?,?,?,?,?,?,NOW())');
                    foreach($items as $pid=>$qty){
                        $q=$pdo->prepare('SELECT id,track_stock,name FROM products WHERE id=? FOR UPDATE');$q->execute([$pid]);$product=$q->fetch();
                        if(!$product||!(int)$product['track_stock'])throw new RuntimeException('Stok takibi açık geçerli bir ürün seçin.');
                        $q=$pdo->prepare('SELECT quantity FROM product_warehouse_stocks WHERE product_id=? AND warehouse_id=? FOR UPDATE');$q->execute([$pid,$from]);$source=$q->fetch();$beforeFrom=$source?(float)$source['quantity']:0.0;
                        if($beforeFrom+0.000001<$qty)throw new RuntimeException($product['name'].' için kaynak depoda yeterli stok yok.');
                        $q=$pdo->prepare('SELECT quantity FROM product_warehouse_stocks WHERE product_id=? AND warehouse_id=? FOR UPDATE');$q->execute([$pid,$to]);$dest=$q->fetch();$beforeTo=$dest?(float)$dest['quantity']:0.0;
                        if(!$source)$pdo->prepare('INSERT INTO product_warehouse_stocks(product_id,warehouse_id,quantity,updated_at) VALUES(?,?,0,NOW())')->execute([$pid,$from]);
                        if(!$dest)$pdo->prepare('INSERT INTO product_warehouse_stocks(product_id,warehouse_id,quantity,updated_at) VALUES(?,?,0,NOW())')->execute([$pid,$to]);
                        $afterFrom=round($beforeFrom-$qty,3);$afterTo=round($beforeTo+$qty,3);
                        $pdo->prepare('UPDATE product_warehouse_stocks SET quantity=?,updated_at=NOW() WHERE product_id=? AND warehouse_id=?')->execute([$afterFrom,$pid,$from]);
                        $pdo->prepare('UPDATE product_warehouse_stocks SET quantity=?,updated_at=NOW() WHERE product_id=? AND warehouse_id=?')->execute([$afterTo,$pid,$to]);
                        $itemInsert->execute([$transferId,$pid,$qty]);
                        $movement->execute([$pid,$from,$transferId,(int)$admin['id'],'transfer_out',$qty,$beforeFrom,$afterFrom,'Depo transferi '.$number]);
                        $movement->execute([$pid,$to,$transferId,(int)$admin['id'],'transfer_in',$qty,$beforeTo,$afterTo,'Depo transferi '.$number]);
                    }
                    $pdo->commit();kd_audit('warehouse.transfer_completed','warehouse_transfer',$transferId);self::go('transfers');
                }
            }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();error_log('KodVadAll warehouses: '.$e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'Depo işlemi tamamlanamadı. Bilgileri kontrol edin.';}
        }
        if($action==='transfers'){
            $warehouses=$pdo->query("SELECT id,name,code FROM warehouses WHERE status='active' ORDER BY is_default DESC,name")->fetchAll();
            $products=$pdo->query("SELECT id,name,sku,unit FROM products WHERE status='active' AND track_stock=1 ORDER BY name")->fetchAll();
            $transfers=$pdo->query("SELECT t.*,f.name AS from_name,to_w.name AS to_name,u.name AS user_name,(SELECT COUNT(*) FROM warehouse_transfer_items i WHERE i.transfer_id=t.id) item_count FROM warehouse_transfers t JOIN warehouses f ON f.id=t.from_warehouse_id JOIN warehouses to_w ON to_w.id=t.to_warehouse_id LEFT JOIN users u ON u.id=t.created_by ORDER BY t.id DESC LIMIT 100")->fetchAll();
            $pageTitle='Depo Transferleri';ob_start();require KD_ROOT.'/app/Views/warehouses/transfers.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';return;
        }
        if($action!=='list'){http_response_code(404);exit('Depo sayfası bulunamadı.');}
        $warehouses=$pdo->query("SELECT w.*,COUNT(DISTINCT s.product_id) product_count,COALESCE(SUM(s.quantity),0) total_quantity,COALESCE(SUM(s.quantity*p.purchase_price),0) stock_value FROM warehouses w LEFT JOIN product_warehouse_stocks s ON s.warehouse_id=w.id LEFT JOIN products p ON p.id=s.product_id GROUP BY w.id ORDER BY w.is_default DESC,w.name")->fetchAll();
        $pageTitle='Depo Yönetimi';ob_start();require KD_ROOT.'/app/Views/warehouses/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
    }
    private static function go(string $action='list'):never{header('Location: index.php?page=warehouses'.($action==='list'?'':'&action='.rawurlencode($action)),true,303);exit;}
}
