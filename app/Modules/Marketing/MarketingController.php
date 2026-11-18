<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Marketing;
use PDO;
use RuntimeException;
use Throwable;

final class MarketingController
{
 public static function handle(PDO $db,array $admin):void
 {
  kd_require_permission('marketing.view');$errors=[];$success='';
  if($_SERVER['REQUEST_METHOD']==='POST'){
   kd_csrf_check();kd_require_permission('marketing.manage');
   try{
    $form=(string)($_POST['form_action']??'');
    if($form==='campaign'){
     $name=trim((string)($_POST['name']??''));$code=strtoupper(trim((string)($_POST['code']??'')));$type=(string)($_POST['discount_type']??'percentage');$value=(float)($_POST['discount_value']??0);$minimum=(float)($_POST['minimum_order']??0);$maximum=(float)($_POST['maximum_discount']??0);$limit=max(0,(int)($_POST['usage_limit']??0));$perCustomer=max(0,(int)($_POST['per_customer_limit']??0));$start=(string)($_POST['starts_at']??date('Y-m-d\TH:i'));$end=trim((string)($_POST['ends_at']??''));
     if($name===''||!preg_match('/^[A-Z0-9_-]{3,60}$/',$code)||!in_array($type,['percentage','fixed'],true)||$value<=0||$minimum<0||$maximum<0)throw new RuntimeException('Kampanya bilgilerini kontrol edin.');
     if($type==='percentage'&&$value>100)throw new RuntimeException('Yüzdelik indirim 100 değerini aşamaz.');
     $startSql=str_replace('T',' ',$start).(strlen($start)===16?':00':'');$endSql=$end!==''?str_replace('T',' ',$end).(strlen($end)===16?':00':''):null;
     if($endSql!==null&&$endSql<=$startSql)throw new RuntimeException('Kampanya bitiş tarihi başlangıçtan sonra olmalıdır.');
     $db->prepare("INSERT INTO campaigns (name,code,description,discount_type,discount_value,minimum_order,maximum_discount,usage_limit,per_customer_limit,starts_at,ends_at,status,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,'active',?,NOW())")->execute([$name,$code,trim((string)($_POST['description']??''))?:null,$type,$value,$minimum,$maximum?:null,$limit,$perCustomer,$startSql,$endSql,(int)$admin['id']]);$id=(int)$db->lastInsertId();kd_audit('marketing.campaign_created','campaign',$id);$success='Kampanya oluşturuldu.';
    }
    if($form==='campaign_status'){$id=(int)($_POST['campaign_id']??0);$status=(string)($_POST['status']??'');if(!in_array($status,['active','inactive'],true))throw new RuntimeException('Kampanya durumu geçersiz.');$db->prepare('UPDATE campaigns SET status=?,updated_at=NOW() WHERE id=?')->execute([$status,$id]);kd_audit('marketing.campaign_status','campaign',$id);$success='Kampanya durumu güncellendi.';}
    if($form==='gift_card'){
     $balance=(float)($_POST['balance']??0);$currency=strtoupper((string)($_POST['currency']??'TRY'));$expiry=trim((string)($_POST['expires_at']??''));if($balance<=0||!in_array($currency,['TRY','USD','EUR','GBP'],true)||($expiry!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$expiry)))throw new RuntimeException('Hediye çeki tutarı, para birimi veya bitiş tarihi geçersiz.');
     $code=strtoupper(bin2hex(random_bytes(5)));$db->prepare("INSERT INTO gift_cards (code,initial_balance,balance,currency,expires_at,status,notes,created_by,created_at) VALUES (?,?,?,?,?,'active',?,?,NOW())")->execute([$code,$balance,$balance,$currency,$expiry?:null,trim((string)($_POST['notes']??''))?:null,(int)$admin['id']]);$id=(int)$db->lastInsertId();kd_audit('marketing.gift_card_created','gift_card',$id);$success='Hediye çeki oluşturuldu: '.$code;
    }
    if($form==='points'){
     $customer=(int)($_POST['customer_id']??0);$points=(int)($_POST['points']??0);$direction=(string)($_POST['direction']??'earn');if($points<1||$points>1000000||!in_array($direction,['earn','redeem'],true))throw new RuntimeException('Puan miktarı veya işlem türü geçersiz.');$db->beginTransaction();$q=$db->prepare('SELECT points_balance FROM loyalty_accounts WHERE customer_id=? FOR UPDATE');$q->execute([$customer]);$balance=$q->fetchColumn();if($balance===false){$db->prepare('INSERT INTO loyalty_accounts (customer_id,points_balance,updated_at) VALUES (?,0,NOW())')->execute([$customer]);$balance=0;}if($direction==='redeem'&&(int)$balance<$points)throw new RuntimeException('Müşterinin yeterli puanı yok.');$delta=$direction==='earn'?$points:-$points;$db->prepare('UPDATE loyalty_accounts SET points_balance=points_balance+?,updated_at=NOW() WHERE customer_id=?')->execute([$delta,$customer]);$db->prepare('INSERT INTO loyalty_transactions (customer_id,direction,points,description,created_by,created_at) VALUES (?,?,?,?,?,NOW())')->execute([$customer,$direction,$points,trim((string)($_POST['description']??''))?:null,(int)$admin['id']]);$db->commit();kd_audit('marketing.points_adjusted','customer',$customer);$success='Sadakat puanı güncellendi.';
    }
   }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('KodVadAll marketing: '.$e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'Pazarlama işlemi tamamlanamadı.';}
  }
  $campaigns=$db->query('SELECT * FROM campaigns ORDER BY id DESC LIMIT 100')->fetchAll();$giftCards=$db->query('SELECT * FROM gift_cards ORDER BY id DESC LIMIT 100')->fetchAll();$customers=$db->query("SELECT c.id,c.name,COALESCE(l.points_balance,0) points_balance FROM customers c LEFT JOIN loyalty_accounts l ON l.customer_id=c.id WHERE c.status='active' ORDER BY c.name LIMIT 500")->fetchAll();$points=$db->query('SELECT t.*,c.name customer_name FROM loyalty_transactions t JOIN customers c ON c.id=t.customer_id ORDER BY t.id DESC LIMIT 50')->fetchAll();
  $pageTitle='Pazarlama & Kampanyalar';ob_start();require KD_ROOT.'/app/Views/marketing/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
