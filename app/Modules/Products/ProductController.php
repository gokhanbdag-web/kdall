<?php
declare(strict_types=1);

namespace KodVadAll\Modules\Products;

use PDO;
use RuntimeException;
use Throwable;

final class ProductController
{
    public static function handle(PDO $pdo, array $admin): void
    {
        kd_require_permission('products.view');
        $action = (string)($_GET['action'] ?? 'list');
        $id = max(0,(int)($_GET['id'] ?? 0));
        $errors = [];
        $formInput = [];

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            kd_csrf_check();
            kd_require_permission('products.manage');
            try {
                $formAction = (string)($_POST['form_action'] ?? '');
                if ($formAction === 'create') {
                    $data = self::validatedInput($_POST,$errors);
                    $formInput = $data;
                    if (!$errors) {
                        $pdo->beginTransaction();
                        $stmt = $pdo->prepare(
                            'INSERT INTO products (sku,name,description,purchase_price,sale_price,stock_quantity,status,category_id,barcode,unit,min_stock,track_stock,created_at)
                             VALUES (?,?,?,?,?,?,?,?,?,?,?,1,NOW())'
                        );
                        $stmt->execute([$data['sku'],$data['name'],$data['description'],$data['purchase_price'],$data['sale_price'],$data['stock_quantity'],'active',$data['category_id'],$data['barcode'],$data['unit'],$data['min_stock']]);
                        $productId = (int)$pdo->lastInsertId();
                        if (!$data['sku']) {
                            $sku = 'URUN-' . date('Y') . '-' . str_pad((string)$productId,5,'0',STR_PAD_LEFT);
                            $pdo->prepare('UPDATE products SET sku=? WHERE id=?')->execute([$sku,$productId]);
                        }
                        $defaultWarehouse=(int)$pdo->query("SELECT id FROM warehouses WHERE is_default=1 AND status='active' LIMIT 1")->fetchColumn();
                        if($defaultWarehouse<1)throw new RuntimeException('Varsayılan depo bulunamadı.');
                        $pdo->prepare('INSERT INTO product_warehouse_stocks(product_id,warehouse_id,quantity,updated_at) VALUES(?,?,?,NOW())')->execute([$productId,$defaultWarehouse,$data['stock_quantity']]);
                        if ($data['stock_quantity'] > 0) {
                            self::recordMovement($pdo,$productId,$defaultWarehouse,null,(int)$admin['id'],'initial',$data['stock_quantity'],0,$data['stock_quantity'],'İlk stok kaydı');
                        }
                        $pdo->commit();
                        kd_audit('product.created','product',$productId);
                        self::redirect('view',$productId);
                    }
                    $action='create';
                } elseif ($formAction === 'update') {
                    $productId = max(0,(int)($_POST['product_id'] ?? 0));
                    $data = self::validatedInput($_POST,$errors);
                    $formInput = $data;
                    if (!$errors && $productId > 0) {
                        $stmt=$pdo->prepare('UPDATE products SET sku=?,name=?,description=?,purchase_price=?,sale_price=?,category_id=?,barcode=?,unit=?,min_stock=?,status=?,updated_at=NOW() WHERE id=?');
                        $status=in_array($_POST['status']??'', ['active','inactive'], true)?(string)$_POST['status']:'active';
                        $stmt->execute([$data['sku'],$data['name'],$data['description'],$data['purchase_price'],$data['sale_price'],$data['category_id'],$data['barcode'],$data['unit'],$data['min_stock'],$status,$productId]);
                        kd_audit('product.updated','product',$productId);
                        self::redirect('view',$productId);
                    }
                    $id=$productId;
                    $formInput['status']=(string)($_POST['status']??'active');
                    $action='edit';
                } elseif ($formAction === 'stock') {
                    $productId=max(0,(int)($_POST['product_id']??0));
                    $type=(string)($_POST['movement_type']??'');
                    $quantity=(float)($_POST['quantity']??0);
                    $warehouseId=max(0,(int)($_POST['warehouse_id']??0));
                    $note=trim((string)($_POST['note']??''));
                    if (!in_array($type,['in','out','adjustment'],true) || $quantity < 0 || ($type!=='adjustment' && $quantity<=0)) {
                        $errors[]='Stok hareketi türünü ve miktarını kontrol edin.';
                    }
                    if ($productId<1) {
                        throw new RuntimeException('Ürün kaydı bulunamadı.');
                    }
                    if (!$errors) {
                        $pdo->beginTransaction();
                        $lock=$pdo->prepare('SELECT stock_quantity,track_stock FROM products WHERE id=? FOR UPDATE');
                        $lock->execute([$productId]);
                        $product=$lock->fetch();
                        if (!$product) {
                            throw new RuntimeException('Ürün kaydı bulunamadı.');
                        }
                        if (!(int)$product['track_stock']) {
                            throw new RuntimeException('Bu üründe stok takibi kapalı.');
                        }
                        $warehouse=$pdo->prepare("SELECT id FROM warehouses WHERE id=? AND status='active' FOR UPDATE");
                        $warehouse->execute([$warehouseId]);
                        if(!$warehouse->fetchColumn())throw new RuntimeException('Aktif depo seçin.');
                        $stock=$pdo->prepare('SELECT quantity FROM product_warehouse_stocks WHERE product_id=? AND warehouse_id=? FOR UPDATE');
                        $stock->execute([$productId,$warehouseId]);$stockRow=$stock->fetch();
                        if(!$stockRow){$pdo->prepare('INSERT INTO product_warehouse_stocks(product_id,warehouse_id,quantity,updated_at) VALUES(?,?,0,NOW())')->execute([$productId,$warehouseId]);$before=0.0;}
                        else{$before=(float)$stockRow['quantity'];}
                        $after=$type==='in'?$before+$quantity:($type==='out'?$before-$quantity:$quantity);
                        if ($after < 0) throw new RuntimeException('Seçilen depodaki stok eksiye düşemez.');
                        $delta=abs($after-$before);
                        if ($delta<=0) throw new RuntimeException('Stok miktarında değişiklik yok.');
                        $pdo->prepare('UPDATE product_warehouse_stocks SET quantity=?,updated_at=NOW() WHERE product_id=? AND warehouse_id=?')->execute([$after,$productId,$warehouseId]);
                        $total=(float)$pdo->query('SELECT COALESCE(SUM(quantity),0) FROM product_warehouse_stocks WHERE product_id='.(int)$productId)->fetchColumn();
                        $pdo->prepare('UPDATE products SET stock_quantity=?,updated_at=NOW() WHERE id=?')->execute([$total,$productId]);
                        self::recordMovement($pdo,$productId,$warehouseId,null,(int)$admin['id'],$type,$delta,$before,$after,$note);
                        $pdo->commit();
                        kd_audit('product.stock_changed','product',$productId);
                        self::redirect('view',$productId);
                    }
                    $id=$productId;
                    $action='view';
                } elseif ($formAction === 'archive') {
                    $productId=max(0,(int)($_POST['product_id']??0));
                    $pdo->prepare("UPDATE products SET status='inactive',updated_at=NOW() WHERE id=?")->execute([$productId]);
                    kd_audit('product.archived','product',$productId);
                    self::redirect('list');
                }
            } catch (Throwable $e) {
                if ($pdo->inTransaction()) {
                    $pdo->rollBack();
                }
                error_log('KodVadAll product module: '.$e->getMessage());
                $errors[]=$e instanceof RuntimeException ? $e->getMessage() : 'İşlem tamamlanamadı. Bilgileri kontrol edip tekrar deneyin.';
            }
        }

        if ($action==='create' || $action==='edit') {
            kd_require_permission('products.manage');
            $product=['id'=>0,'sku'=>'','name'=>'','description'=>'','purchase_price'=>'0.00','sale_price'=>'0.00','stock_quantity'=>'0.000','status'=>'active','category_id'=>0,'barcode'=>'','unit'=>'Adet','min_stock'=>'0.000'];
            if ($action==='edit') {
                $product=self::find($pdo,$id);
                if (!$product) self::notFound();
            }
            if ($formInput) $product=array_merge($product,$formInput);
            $categories=self::categories($pdo);
            $pageTitle=$action==='create'?'Yeni ürün':'Ürünü düzenle';
            ob_start(); require KD_ROOT.'/app/Views/products/form.php'; $content=ob_get_clean();
            require KD_ROOT.'/app/Views/layouts/admin.php';
            return;
        }

        if ($action==='view') {
            $product=self::find($pdo,$id);
            if (!$product) self::notFound();
            $stmt=$pdo->prepare('SELECT m.*,u.name AS user_name,w.name AS warehouse_name FROM product_stock_movements m LEFT JOIN users u ON u.id=m.user_id LEFT JOIN warehouses w ON w.id=m.warehouse_id WHERE m.product_id=? ORDER BY m.id DESC LIMIT 100');
            $stmt->execute([$id]);
            $movements=$stmt->fetchAll();
            $warehouseStocks=$pdo->prepare("SELECT w.id,w.name,w.code,w.is_default,s.quantity FROM warehouses w LEFT JOIN product_warehouse_stocks s ON s.warehouse_id=w.id AND s.product_id=? WHERE w.status='active' ORDER BY w.is_default DESC,w.name");
            $warehouseStocks->execute([$id]);$warehouseStocks=$warehouseStocks->fetchAll();
            $warehouses=$pdo->query("SELECT id,name FROM warehouses WHERE status='active' ORDER BY is_default DESC,name")->fetchAll();
            $pageTitle=(string)$product['name'];
            ob_start(); require KD_ROOT.'/app/Views/products/detail.php'; $content=ob_get_clean();
            require KD_ROOT.'/app/Views/layouts/admin.php';
            return;
        }

        if ($action!=='list') self::notFound();
        $q=trim((string)($_GET['q']??''));
        $status=in_array($_GET['status']??'', ['active','inactive'], true)?(string)$_GET['status']:'';
        $stockStatus=in_array($_GET['stock_status']??'', ['low','out','healthy'], true)?(string)$_GET['stock_status']:'';
        $categoryId=max(0,(int)($_GET['category_id']??0));
        $page=max(1,(int)($_GET['p']??1));
        $limit=20;
        $where=[];$params=[];
        if ($q!=='') {
            $where[]='(p.name LIKE ? OR p.sku LIKE ? OR p.barcode LIKE ?)';
            $like='%'.$q.'%'; array_push($params,$like,$like,$like);
        }
        if ($status!=='') {$where[]='p.status=?';$params[]=$status;}
        if ($categoryId>0) {$where[]='p.category_id=?';$params[]=$categoryId;}
        if ($stockStatus==='low') {$where[]='p.track_stock=1 AND p.stock_quantity<=p.min_stock';}
        elseif ($stockStatus==='out') {$where[]='p.track_stock=1 AND p.stock_quantity<=0';}
        elseif ($stockStatus==='healthy') {$where[]='p.track_stock=1 AND p.stock_quantity>p.min_stock';}
        $summary=$pdo->query("SELECT COUNT(*) tracked_products,COALESCE(SUM(stock_quantity*purchase_price),0) stock_value,SUM(CASE WHEN stock_quantity<=min_stock THEN 1 ELSE 0 END) low_stock_count,SUM(CASE WHEN stock_quantity<=0 THEN 1 ELSE 0 END) out_of_stock_count FROM products WHERE status='active' AND track_stock=1")->fetch();
        $whereSql=$where?' WHERE '.implode(' AND ',$where):'';
        $count=$pdo->prepare('SELECT COUNT(*) FROM products p'.$whereSql);$count->execute($params);
        $total=(int)$count->fetchColumn();$pages=max(1,(int)ceil($total/$limit));$page=min($page,$pages);$offset=($page-1)*$limit;
        $stmt=$pdo->prepare('SELECT p.*,c.name AS category_name FROM products p LEFT JOIN product_categories c ON c.id=p.category_id'.$whereSql.' ORDER BY p.id DESC LIMIT '.$limit.' OFFSET '.$offset);
        $stmt->execute($params);$products=$stmt->fetchAll();
        $categories=self::categories($pdo);$pageTitle='Ürün & Stok';
        ob_start(); require KD_ROOT.'/app/Views/products/list.php'; $content=ob_get_clean();
        require KD_ROOT.'/app/Views/layouts/admin.php';
    }

    private static function validatedInput(array $input,array &$errors): array
    {
        $name=trim((string)($input['name']??''));
        $sku=trim((string)($input['sku']??''))?:null;
        $purchase=(float)($input['purchase_price']??0);
        $sale=(float)($input['sale_price']??0);
        $stock=(float)($input['stock_quantity']??0);
        $min=(float)($input['min_stock']??0);
        $category=max(0,(int)($input['category_id']??0));
        if ($name==='' || mb_strlen($name)>190) $errors[]='Ürün adı zorunludur ve 190 karakteri geçemez.';
        if ($purchase<0 || $sale<0 || $stock<0 || $min<0) $errors[]='Fiyat ve stok değerleri negatif olamaz.';
        if ($sku!==null && mb_strlen($sku)>80) $errors[]='Stok kodu 80 karakteri geçemez.';
        return [
            'sku'=>$sku,'name'=>$name,'description'=>trim((string)($input['description']??''))?:null,
            'purchase_price'=>number_format($purchase,2,'.',''),'sale_price'=>number_format($sale,2,'.',''),
            'stock_quantity'=>number_format($stock,3,'.',''),'category_id'=>$category?:null,
            'barcode'=>trim((string)($input['barcode']??''))?:null,'unit'=>trim((string)($input['unit']??'Adet'))?:'Adet',
            'min_stock'=>number_format($min,3,'.','')
        ];
    }

    private static function find(PDO $pdo,int $id): ?array
    {
        if ($id<1) return null;
        $stmt=$pdo->prepare('SELECT p.*,c.name AS category_name FROM products p LEFT JOIN product_categories c ON c.id=p.category_id WHERE p.id=? LIMIT 1');
        $stmt->execute([$id]);$row=$stmt->fetch();return $row?:null;
    }

    private static function categories(PDO $pdo): array
    {
        return $pdo->query("SELECT id,name FROM product_categories WHERE status='active' ORDER BY name")->fetchAll();
    }

    private static function recordMovement(PDO $pdo,int $productId,int $warehouseId,?int $transferId,int $userId,string $type,float $quantity,float $before,float $after,string $note): void
    {
        $stmt=$pdo->prepare('INSERT INTO product_stock_movements (product_id,warehouse_id,transfer_id,user_id,movement_type,quantity,before_quantity,after_quantity,note,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())');
        $stmt->execute([$productId,$warehouseId,$transferId,$userId,$type,$quantity,$before,$after,$note?:null]);
    }

    private static function redirect(string $action,int $id=0): never
    {
        $url='index.php?page=products';
        if ($action!=='list') $url.='&action='.rawurlencode($action);
        if ($id>0) $url.='&id='.$id;
        header('Location: '.$url,true,303);exit;
    }

    private static function notFound(): never
    {
        http_response_code(404);exit('Ürün kaydı bulunamadı.');
    }
}
