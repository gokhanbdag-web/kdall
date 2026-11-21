<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Shipping;
use PDO;
use RuntimeException;
use Throwable;

final class ShippingController
{
 private const STATUSES=['pending'=>'Bekliyor','preparing'=>'Hazırlanıyor','shipped'=>'Kargoya verildi','in_transit'=>'Dağıtımda','delivered'=>'Teslim edildi','returned'=>'İade edildi','cancelled'=>'İptal'];
 private static function validShippingNumber(mixed $value,int $precision):bool
 {
  if(!is_scalar($value)||is_bool($value))return false;
  $raw=trim((string)$value);
  if(!preg_match('/^\\d{1,9}(?:\\.\\d{1,'.$precision.'})?$/D',$raw))return false;
  return is_finite((float)$raw)&&(float)$raw<=1000000000;
 }
 public static function handle(PDO $db,array $admin):void
 {
  kd_require_permission('shipments.view');$action=(string)($_GET['action']??'list');$id=max(0,(int)($_GET['id']??0));$errors=[];
  if($_SERVER['REQUEST_METHOD']==='POST'){
   kd_csrf_check();kd_require_permission('shipments.manage');
   try{
    $form=(string)($_POST['form_action']??'');
    if($form==='carrier'){
     $name=trim((string)($_POST['name']??''));$url=trim((string)($_POST['tracking_url_template']??''));if($name===''||mb_strlen($name)>120)throw new RuntimeException('Kargo firması adı zorunludur.');
     if($url!==''&&(!filter_var(str_replace('{tracking}','TEST123',$url),FILTER_VALIDATE_URL)||!in_array(strtolower((string)parse_url(str_replace('{tracking}','TEST123',$url),PHP_URL_SCHEME)),['http','https'],true)))throw new RuntimeException('Takip adresi geçerli bir HTTP/HTTPS adresi olmalıdır.');
     $db->prepare("INSERT INTO shipping_carriers (name,tracking_url_template,status,created_at) VALUES (?,?, 'active',NOW())")->execute([$name,$url?:null]);$cid=(int)$db->lastInsertId();kd_audit('shipping.carrier_created','shipping_carrier',$cid);self::go('carriers');
    }
    if($form==='shipment'){
     $orderId=(int)($_POST['order_id']??0);$carrierId=(int)($_POST['carrier_id']??0);$tracking=trim((string)($_POST['tracking_number']??''));$rawCost=$_POST['shipping_cost']??0;$rawWeight=$_POST['weight']??0;$rawPackages=$_POST['package_count']??1;if(!self::validShippingNumber($rawCost,2)||!self::validShippingNumber($rawWeight,3)||!preg_match('/^[1-9][0-9]{0,2}$/D',(string)$rawPackages))throw new RuntimeException('Kargo bedeli, ağırlık veya paket sayısı geçersiz.');$cost=(float)$rawCost;$weight=(float)$rawWeight;$packages=(int)$rawPackages;
     if(!is_finite($cost)||!is_finite($weight)||$cost<0||$weight<0||$packages<1||$packages>100)throw new RuntimeException('Kargo bedeli, ağırlık veya paket sayısı geçersiz.');
     $db->beginTransaction();$q=$db->prepare("SELECT id,order_number,status FROM orders WHERE id=? AND status IN ('confirmed','processing') FOR UPDATE");$q->execute([$orderId]);$order=$q->fetch();if(!$order)throw new RuntimeException('Kargoya hazırlanabilecek onaylı bir sipariş seçin.');$existing=$db->prepare("SELECT COUNT(*) FROM shipments WHERE order_id=? AND status<>'cancelled'");$existing->execute([$orderId]);if((int)$existing->fetchColumn()>0)throw new RuntimeException('Bu sipariş için zaten aktif bir gönderi var.');
     if($carrierId>0){$q=$db->prepare("SELECT id FROM shipping_carriers WHERE id=? AND status='active'");$q->execute([$carrierId]);if(!$q->fetchColumn())throw new RuntimeException('Aktif kargo firması seçin.');}
     $tmp='TMP-'.bin2hex(random_bytes(8));$q=$db->prepare("INSERT INTO shipments (shipment_number,order_id,carrier_id,tracking_number,status,shipping_cost,weight,package_count,notes,created_by,created_at) VALUES (?,?,?,?,'pending',?,?,?,?,?,NOW())");$q->execute([$tmp,$orderId,$carrierId?:null,$tracking?:null,$cost,$weight,$packages,trim((string)($_POST['notes']??''))?:null,(int)$admin['id']]);$sid=(int)$db->lastInsertId();$number='KRG-'.date('Y').'-'.str_pad((string)$sid,6,'0',STR_PAD_LEFT);$db->prepare('UPDATE shipments SET shipment_number=? WHERE id=?')->execute([$number,$sid]);$db->prepare('INSERT INTO shipment_events (shipment_id,from_status,to_status,note,user_id,created_at) VALUES (?,NULL,?,?,?,NOW())')->execute([$sid,'pending','Gönderi kaydı oluşturuldu',(int)$admin['id']]);$db->commit();kd_audit('shipping.shipment_created','shipment',$sid);self::go('view',$sid);
    }
    if($form==='status'){
     $sid=(int)($_POST['shipment_id']??0);$to=(string)($_POST['status']??'');$note=trim((string)($_POST['note']??''));if(!isset(self::STATUSES[$to]))throw new RuntimeException('Gönderi durumu geçersiz.');
     $db->beginTransaction();$q=$db->prepare('SELECT s.*,o.status order_status FROM shipments s JOIN orders o ON o.id=s.order_id WHERE s.id=? FOR UPDATE');$q->execute([$sid]);$s=$q->fetch();if(!$s)throw new RuntimeException('Gönderi bulunamadı.');
     $allowed=['pending'=>['preparing','shipped','cancelled'],'preparing'=>['shipped','cancelled'],'shipped'=>['in_transit','delivered','returned'],'in_transit'=>['delivered','returned'],'delivered'=>[],'returned'=>[],'cancelled'=>[]];$from=$s['status'];if(!in_array($to,$allowed[$from]??[],true))throw new RuntimeException('Bu gönderi durum geçişine izin verilmiyor.');if(in_array($s['order_status'],['cancelled','draft'],true))throw new RuntimeException('İptal edilmiş veya taslak siparişin gönderi durumu değiştirilemez.');
     $db->prepare('UPDATE shipments SET status=?,shipped_at=IF(? IN (\'shipped\',\'in_transit\',\'delivered\'),COALESCE(shipped_at,NOW()),shipped_at),delivered_at=IF(?=\'delivered\',NOW(),delivered_at),updated_at=NOW() WHERE id=?')->execute([$to,$to,$to,$sid]);
     $db->prepare('INSERT INTO shipment_events (shipment_id,from_status,to_status,note,user_id,created_at) VALUES (?,?,?,?,?,NOW())')->execute([$sid,$from,$to,$note?:null,(int)$admin['id']]);
     $orderStatus=(string)$s['order_status'];$newOrderStatus=null;if(in_array($to,['shipped','in_transit'],true)&&in_array($orderStatus,['confirmed','processing'],true))$newOrderStatus='shipped';if($to==='delivered'&&$orderStatus==='shipped')$newOrderStatus='completed';
     if($newOrderStatus){$db->prepare('UPDATE orders SET status=?,updated_at=NOW() WHERE id=?')->execute([$newOrderStatus,$s['order_id']]);$db->prepare('INSERT INTO order_status_history (order_id,user_id,from_status,to_status,note,created_at) VALUES (?,?,?,?,?,NOW())')->execute([$s['order_id'],(int)$admin['id'],$orderStatus,$newOrderStatus,'Kargo '.$s['shipment_number'].' durum güncellemesi',]);}
     $db->commit();kd_audit('shipping.status_changed','shipment',$sid);self::go('view',$sid);
    }
   }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('KodVadAll shipping: '.$e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'Kargo işlemi tamamlanamadı.';$action=(string)($_GET['action']??'list');$id=max(0,(int)($_GET['id']??0));}
  }
  $carriers=$db->query("SELECT * FROM shipping_carriers WHERE status='active' ORDER BY name")->fetchAll();
  if($action==='create'){$orders=$db->query("SELECT o.id,o.order_number,c.name customer_name FROM orders o LEFT JOIN customers c ON c.id=o.customer_id WHERE o.status IN ('confirmed','processing') ORDER BY o.id DESC LIMIT 200")->fetchAll();}
  elseif($action==='carriers'){$allCarriers=$db->query('SELECT * FROM shipping_carriers ORDER BY id DESC')->fetchAll();}
  elseif($action==='view'){$q=$db->prepare('SELECT s.*,o.order_number,o.grand_total,c.name customer_name,sc.name carrier_name,sc.tracking_url_template FROM shipments s JOIN orders o ON o.id=s.order_id LEFT JOIN customers c ON c.id=o.customer_id LEFT JOIN shipping_carriers sc ON sc.id=s.carrier_id WHERE s.id=?');$q->execute([$id]);$shipment=$q->fetch();if(!$shipment)self::notFound();$trackingUrl=$shipment['tracking_url_template']&&$shipment['tracking_number']?str_replace('{tracking}',rawurlencode($shipment['tracking_number']),$shipment['tracking_url_template']):null;$q=$db->prepare('SELECT e.*,u.name user_name FROM shipment_events e LEFT JOIN users u ON u.id=e.user_id WHERE e.shipment_id=? ORDER BY e.id DESC');$q->execute([$id]);$events=$q->fetchAll();}
  else{$status=in_array($_GET['status']??'',array_keys(self::STATUSES),true)?$_GET['status']:'';$q=trim((string)($_GET['q']??''));$where=[];$params=[];if($q!==''){$where[]='(s.shipment_number LIKE ? OR s.tracking_number LIKE ? OR o.order_number LIKE ? OR c.name LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like,$like);}if($status!==''){$where[]='s.status=?';$params[]=$status;}$sql=$where?' WHERE '.implode(' AND ',$where):'';$st=$db->prepare('SELECT s.*,o.order_number,c.name customer_name,sc.name carrier_name FROM shipments s JOIN orders o ON o.id=s.order_id LEFT JOIN customers c ON c.id=o.customer_id LEFT JOIN shipping_carriers sc ON sc.id=s.carrier_id'.$sql.' ORDER BY s.id DESC LIMIT 150');$st->execute($params);$shipments=$st->fetchAll();}
  $pageTitle='Kargo & Lojistik';ob_start();require KD_ROOT.'/app/Views/shipping/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
 private static function go(string $action='list',int $id=0):never{$u='index.php?page=shipping';if($action!=='list')$u.='&action='.$action;if($id)$u.='&id='.$id;header('Location: '.$u,true,303);exit;}
 private static function notFound():never{http_response_code(404);exit('Gönderi bulunamadı.');}
}
