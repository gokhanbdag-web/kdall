<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Production;

use PDO;
use RuntimeException;
use Throwable;
use KodVadAll\Core\NotificationService;
use KodVadAll\Core\AccountingPostingService;

final class ProductionController
{
    public static function handle(PDO $db, array $admin): void
    {
        kd_require_permission('production.view');
        $errors = [];
        $success = '';

        if ($_SERVER['REQUEST_METHOD'] === 'POST') {
            kd_csrf_check();
            kd_require_permission('production.manage');
            $action = (string)($_POST['form_action'] ?? '');

            try {
                if ($action === 'recipe') {
                    $name = trim((string)($_POST['name'] ?? ''));
                    $output = (int)($_POST['output_product_id'] ?? 0);
                    $batch = (float)($_POST['batch_size'] ?? 1);
                    $components = is_array($_POST['component_product_id'] ?? null) ? $_POST['component_product_id'] : [];
                    $quantities = is_array($_POST['quantity_per_batch'] ?? null) ? $_POST['quantity_per_batch'] : [];
                    if ($name === '' || $output < 1 || $batch <= 0) {
                        throw new RuntimeException('Reçete adı, çıktı ürünü ve parti miktarı gereklidir.');
                    }
                    $items = [];
                    foreach ($components as $i => $component) {
                        $pid = (int)$component;
                        $qty = (float)($quantities[$i] ?? 0);
                        if ($pid > 0 && $qty > 0) {
                            if ($pid === $output) {
                                throw new RuntimeException('Üretilen ürün kendi hammaddesi olamaz.');
                            }
                            $items[$pid] = ($items[$pid] ?? 0) + $qty;
                        }
                    }
                    if (!$items) {
                        throw new RuntimeException('En az bir hammadde ekleyin.');
                    }

                    $db->beginTransaction();
                    $db->prepare("INSERT INTO production_recipes (name,output_product_id,batch_size,status,notes,created_by,created_at) VALUES (?,?,?,'active',?,?,NOW())")
                        ->execute([$name, $output, $batch, trim((string)($_POST['notes'] ?? '')) ?: null, (int)$admin['id']]);
                    $recipeId = (int)$db->lastInsertId();
                    $ins = $db->prepare('INSERT INTO production_recipe_items (recipe_id,component_product_id,quantity_per_batch,created_at) VALUES (?,?,?,NOW())');
                    foreach ($items as $pid => $qty) {
                        $ins->execute([$recipeId, $pid, $qty]);
                    }
                    $db->commit();
                    kd_audit('production.recipe_created', 'production_recipe', $recipeId);
                    $success = 'Üretim reçetesi oluşturuldu.';
                } elseif ($action === 'order') {
                    $recipe = (int)($_POST['recipe_id'] ?? 0);
                    $qty = (float)($_POST['output_quantity'] ?? 0);
                    $labor = round((float)($_POST['labor_cost'] ?? 0), 2);
                    $overhead = round((float)($_POST['overhead_cost'] ?? 0), 2);
                    if ($recipe < 1 || $qty <= 0 || $labor < 0 || $overhead < 0) {
                        throw new RuntimeException('Reçete, üretim miktarı ve üretim giderlerini kontrol edin.');
                    }
                    $warehouseId=(int)($_POST['warehouse_id']??0);
                    $db->beginTransaction();
                    if($warehouseId<1)$warehouseId=(int)$db->query("SELECT id FROM warehouses WHERE is_default=1 AND status='active' LIMIT 1")->fetchColumn();
                    $warehouseCheck=$db->prepare("SELECT id FROM warehouses WHERE id=? AND status='active' FOR UPDATE");
                    $warehouseCheck->execute([$warehouseId]);
                    if(!$warehouseCheck->fetchColumn())throw new RuntimeException('Aktif bir üretim deposu seçin.');
                    $db->prepare("INSERT INTO production_orders (order_number,recipe_id,warehouse_id,output_quantity,status,planned_at,created_by,notes,labor_cost,overhead_cost,created_at) VALUES (?,?,?,?,'planned',?,?,?,?,?,NOW())")
                        ->execute(['TMP-PROD-' . bin2hex(random_bytes(4)), $recipe, $warehouseId, $qty, trim((string)($_POST['planned_at'] ?? '')) ?: null, (int)$admin['id'], trim((string)($_POST['notes'] ?? '')) ?: null, $labor, $overhead]);
                    $id = (int)$db->lastInsertId();
                    $number = 'PRD-' . date('Y') . '-' . str_pad((string)$id, 6, '0', STR_PAD_LEFT);
                    $db->prepare('UPDATE production_orders SET order_number=? WHERE id=?')->execute([$number, $id]);
                    $db->commit();
                    $success = 'Üretim emri oluşturuldu.';
                } elseif ($action === 'complete') {
                    $orderId = (int)($_POST['order_id'] ?? 0);
                    $db->beginTransaction();
                    $q = $db->prepare("SELECT po.*,r.output_product_id,r.batch_size,r.name recipe_name FROM production_orders po JOIN production_recipes r ON r.id=po.recipe_id WHERE po.id=? FOR UPDATE");
                    $q->execute([$orderId]);
                    $order = $q->fetch();
                    if (!$order || $order['status'] !== 'planned') {
                        throw new RuntimeException('Tamamlanabilir üretim emri bulunamadı.');
                    }

                    $warehouseId=(int)($order['warehouse_id']??0);
                    if($warehouseId<1)$warehouseId=(int)$db->query("SELECT id FROM warehouses WHERE is_default=1 AND status='active' LIMIT 1")->fetchColumn();
                    $warehouseCheck=$db->prepare("SELECT id FROM warehouses WHERE id=? AND status='active' FOR UPDATE");
                    $warehouseCheck->execute([$warehouseId]);
                    if(!$warehouseCheck->fetchColumn())throw new RuntimeException('Üretim emrinin bağlı olduğu depo aktif değil.');
                    $q = $db->prepare('SELECT ri.*,p.name,p.stock_quantity,p.track_stock,p.purchase_price,COALESCE(pws.quantity,0) AS warehouse_quantity FROM production_recipe_items ri JOIN products p ON p.id=ri.component_product_id LEFT JOIN product_warehouse_stocks pws ON pws.product_id=p.id AND pws.warehouse_id=? WHERE ri.recipe_id=? ORDER BY p.id FOR UPDATE');
                    $q->execute([$warehouseId,(int)$order['recipe_id']]);
                    $components = $q->fetchAll();
                    $materials = [];
                    $materialCost = 0.0;
                    foreach ($components as $component) {
                        $required = round((float)$component['quantity_per_batch'] * (float)$order['output_quantity'] / (float)$order['batch_size'], 3);
                        if ((int)$component['track_stock'] && (float)$component['warehouse_quantity'] < $required) {
                            throw new RuntimeException('Yetersiz hammadde: ' . $component['name']);
                        }
                        $unitCost = round((float)$component['purchase_price'], 4);
                        $lineCost = round($required * $unitCost, 2);
                        $materialCost += $lineCost;
                        $materials[] = [$component, $required, $unitCost, $lineCost];
                    }
                    $materialCost = round($materialCost, 2);
                    $laborCost = round((float)$order['labor_cost'], 2);
                    $overheadCost = round((float)$order['overhead_cost'], 2);
                    $totalCost = round($materialCost + $laborCost + $overheadCost, 2);
                    $outputQty = (float)$order['output_quantity'];
                    $unitCost = $outputQty > 0 ? round($totalCost / $outputQty, 4) : 0.0;

                    $q = $db->prepare('SELECT * FROM products WHERE id=? FOR UPDATE');
                    $q->execute([(int)$order['output_product_id']]);
                    $output = $q->fetch();
                    if (!$output) {
                        throw new RuntimeException('Üretim çıktı ürünü bulunamadı.');
                    }

                    foreach ($materials as [$component, $required, $componentUnitCost, $lineCost]) {
                        if ((int)$component['track_stock']) {
                            $productId=(int)$component['component_product_id'];
                            $before=(float)$component['warehouse_quantity'];$after=round($before-$required,3);
                            $db->prepare('UPDATE product_warehouse_stocks SET quantity=?,updated_at=NOW() WHERE product_id=? AND warehouse_id=?')->execute([$after,$productId,$warehouseId]);
                            $total=(float)$db->query('SELECT COALESCE(SUM(quantity),0) FROM product_warehouse_stocks WHERE product_id='.$productId)->fetchColumn();
                            $db->prepare('UPDATE products SET stock_quantity=?,updated_at=NOW() WHERE id=?')->execute([$total,$productId]);
                            $db->prepare("INSERT INTO product_stock_movements(product_id,warehouse_id,user_id,movement_type,quantity,before_quantity,after_quantity,note,reference_type,reference_id,created_at) VALUES(?,?,?,'out',?,?,?,?,?,?,NOW())")
                                ->execute([$productId,$warehouseId,(int)$admin['id'],$required,$before,$after,'Üretim '.$order['order_number'],'production',$orderId]);
                        }
                        $db->prepare('INSERT INTO production_order_materials (production_order_id,product_id,product_name,quantity_required,quantity_consumed,unit_cost,line_cost,created_at) VALUES (?,?,?,?,?,?,?,NOW())')
                            ->execute([$orderId, (int)$component['component_product_id'], $component['name'], $required, $required, $componentUnitCost, $lineCost]);
                    }

                    $oldQty=(float)$output['stock_quantity'];$oldUnitCost=(float)$output['purchase_price'];$newQty=$oldQty+$outputQty;
                    $weightedCost=$newQty>0?(($oldQty*$oldUnitCost)+($outputQty*$unitCost))/$newQty:$unitCost;
                    if((int)$output['track_stock']){
                        $q=$db->prepare('SELECT quantity FROM product_warehouse_stocks WHERE product_id=? AND warehouse_id=? FOR UPDATE');$q->execute([(int)$output['id'],$warehouseId]);$outputStock=$q->fetch();$warehouseBefore=$outputStock?(float)$outputStock['quantity']:0.0;$warehouseAfter=round($warehouseBefore+$outputQty,3);
                        if(!$outputStock)$db->prepare('INSERT INTO product_warehouse_stocks(product_id,warehouse_id,quantity,updated_at) VALUES(?,?,?,NOW())')->execute([(int)$output['id'],$warehouseId,$warehouseAfter]);
                        else $db->prepare('UPDATE product_warehouse_stocks SET quantity=?,updated_at=NOW() WHERE product_id=? AND warehouse_id=?')->execute([$warehouseAfter,(int)$output['id'],$warehouseId]);
                        $total=(float)$db->query('SELECT COALESCE(SUM(quantity),0) FROM product_warehouse_stocks WHERE product_id='.(int)$output['id'])->fetchColumn();
                        $db->prepare('UPDATE products SET stock_quantity=?,purchase_price=?,updated_at=NOW() WHERE id=?')->execute([$total,round($weightedCost,4),(int)$output['id']]);
                        $db->prepare("INSERT INTO product_stock_movements(product_id,warehouse_id,user_id,movement_type,quantity,before_quantity,after_quantity,note,reference_type,reference_id,created_at) VALUES(?,?,?,'in',?,?,?,?,?,?,NOW())")
                            ->execute([(int)$output['id'],$warehouseId,(int)$admin['id'],$outputQty,$warehouseBefore,$warehouseAfter,'Üretim '.$order['order_number'],'production',$orderId]);
                    }else{$db->prepare('UPDATE products SET purchase_price=?,updated_at=NOW() WHERE id=?')->execute([round($weightedCost,4),(int)$output['id']]);}

                    $db->prepare("UPDATE production_orders SET status='completed',started_at=COALESCE(started_at,NOW()),completed_at=NOW(),material_cost=?,total_cost=?,unit_cost=? WHERE id=?")
                        ->execute([$materialCost, $totalCost, $unitCost, $orderId]);
                    if ($totalCost > 0) {
                        AccountingPostingService::postProductionOrder($db, $orderId, (int)$admin['id']);
                    }
                    $db->commit();
                    kd_audit('production.completed', 'production_order', $orderId);
                    NotificationService::notifyRoles($db, ['owner','admin','manager'], 'Üretim tamamlandı', $order['order_number'] . ' üretim emri tamamlandı.', 'index.php?page=production', 'success');
                    $success = 'Üretim tamamlandı. Hammadde, mamul stokları ve üretim maliyeti güncellendi.';
                }
            } catch (Throwable $e) {
                if ($db->inTransaction()) {
                    $db->rollBack();
                }
                error_log('KodVadAll production: ' . $e->getMessage());
                $errors[] = $e instanceof RuntimeException ? $e->getMessage() : 'Üretim işlemi tamamlanamadı.';
            }
        }

        $products = $db->query("SELECT id,name,sku,stock_quantity FROM products WHERE status='active' ORDER BY name")->fetchAll();
        $warehouses = $db->query("SELECT id,name,code,is_default FROM warehouses WHERE status='active' ORDER BY is_default DESC,name")->fetchAll();
        $defaultWarehouseId = (int)$db->query("SELECT id FROM warehouses WHERE is_default=1 AND status='active' LIMIT 1")->fetchColumn();
        $recipes = $db->query("SELECT r.*,p.name output_name FROM production_recipes r JOIN products p ON p.id=r.output_product_id WHERE r.status='active' ORDER BY r.id DESC")->fetchAll();
        $orders = $db->query("SELECT po.*,r.name recipe_name,p.name output_name,w.name warehouse_name FROM production_orders po JOIN production_recipes r ON r.id=po.recipe_id JOIN products p ON p.id=r.output_product_id LEFT JOIN warehouses w ON w.id=po.warehouse_id ORDER BY po.id DESC LIMIT 100")->fetchAll();
        $pageTitle = 'Üretim & Reçete Yönetimi';
        ob_start();
        require KD_ROOT . '/app/Views/production/index.php';
        $content = ob_get_clean();
        require KD_ROOT . '/app/Views/layouts/admin.php';
    }
}
