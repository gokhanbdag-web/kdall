<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Payments;
use PDO;
use RuntimeException;
use Throwable;
use KodVadAll\Core\PaymentGateway;
final class PaymentsController
{
 public static function handle(PDO $db):void
 {
  kd_require_permission('payments.view');$errors=[];$success='';
  if($_SERVER['REQUEST_METHOD']==='POST'){kd_csrf_check();kd_require_permission('payments.manage');try{$f=(string)($_POST['form_action']??'');if($f==='paytr'){PaymentGateway::savePaytr($db,$_POST);$success='PayTR ayarları şifrelenerek kaydedildi.';}if($f==='manual_status'){$txId=(int)($_POST['transaction_id']??0);$status=(string)($_POST['status']??'');if(!in_array($status,['paid','failed'],true))throw new RuntimeException('Ödeme durumu geçersiz.');$db->beginTransaction();$q=$db->prepare('SELECT * FROM payment_transactions WHERE id=? FOR UPDATE');$q->execute([$txId]);$tx=$q->fetch();if(!$tx||!in_array($tx['provider'],['bank_transfer','cash_on_delivery'],true)||$tx['status']!=='pending')throw new RuntimeException('Bekleyen manuel ödeme bulunamadı.');$db->prepare('UPDATE payment_transactions SET status=?,updated_at=NOW() WHERE id=?')->execute([$status,$txId]);$db->prepare('UPDATE orders SET payment_status=?,updated_at=NOW() WHERE id=?')->execute([$status,(int)$tx['order_id']]);$db->commit();$success='Ödeme durumu güncellendi.';}if($f==='manual'){$values=['bank_transfer_enabled'=>isset($_POST['bank_transfer_enabled'])?'1':'0','cash_on_delivery_enabled'=>isset($_POST['cash_on_delivery_enabled'])?'1':'0','bank_transfer_instructions'=>trim((string)($_POST['bank_transfer_instructions']??''))];$q=$db->prepare('INSERT INTO settings (setting_key,setting_value,updated_at) VALUES (?,?,NOW()) ON DUPLICATE KEY UPDATE setting_value=VALUES(setting_value),updated_at=NOW()');foreach($values as $k=>$v)$q->execute([$k,$v]);$success='Manuel ödeme ayarları kaydedildi.';}}catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('KodVadAll payments: '.$e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'Ödeme ayarları kaydedilemedi.';}}
  $cfg=PaymentGateway::config($db);$settings=[];foreach($db->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('bank_transfer_enabled','cash_on_delivery_enabled','bank_transfer_instructions')")->fetchAll() as $r)$settings[$r['setting_key']]=$r['setting_value'];$transactions=$db->query('SELECT t.*,o.order_number FROM payment_transactions t JOIN orders o ON o.id=t.order_id ORDER BY t.id DESC LIMIT 100')->fetchAll();$pageTitle='Ödeme Sistemleri';ob_start();require KD_ROOT.'/app/Views/payments/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
