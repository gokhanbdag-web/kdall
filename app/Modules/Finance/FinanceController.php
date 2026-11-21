<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Finance;
use PDO;
use RuntimeException;
use Throwable;

final class FinanceController
{
 public static function handle(PDO $db,array $admin):void
 {
  kd_require_permission('finance.view');$action=(string)($_GET['action']??'list');$errors=[];
  if($_SERVER['REQUEST_METHOD']==='POST'){
   kd_csrf_check();kd_require_permission('finance.manage');
   try{
    $form=(string)($_POST['form_action']??'');
    if($form==='allocate_advance'){
     $id=\KodVadAll\Core\AdvanceAllocationService::allocate($db,max(0,(int)($_POST['advance_id']??0)),max(0,(int)($_POST['invoice_id']??0)),trim((string)($_POST['amount']??'')),(int)$admin['id']);
     kd_audit('finance.advance_allocated','financial_advance_allocation',$id);self::go('list');
    }
    if($form==='account'){
     $name=trim((string)($_POST['name']??''));$type=(string)($_POST['account_type']??'cash');$currency=strtoupper((string)($_POST['currency']??'TRY'));$opening=self::moneyAmount($_POST['opening_balance']??'0',true);
     if($name===''||mb_strlen($name)>150||!in_array($type,['cash','bank'],true)||!in_array($currency,['TRY','USD','EUR','GBP'],true)||$opening<0)throw new RuntimeException('Hesap bilgilerini kontrol edin.');
     $db->beginTransaction();$db->prepare('INSERT INTO financial_accounts (name,account_type,currency,opening_balance,bank_name,iban,notes,status,created_at) VALUES (?,?,?,?,?,?,?,\'active\',NOW())')->execute([$name,$type,$currency,$opening,trim((string)($_POST['bank_name']??''))?:null,trim((string)($_POST['iban']??''))?:null,trim((string)($_POST['notes']??''))?:null]);
     $id=(int)$db->lastInsertId();\KodVadAll\Core\AccountingPostingService::postFinancialAccountOpening($db,$id,(int)$admin['id']);$db->commit();kd_audit('finance.account_created','financial_account',$id);self::go('accounts');
    }
    if($form==='transaction'){
     $account=(int)($_POST['account_id']??0);$type=(string)($_POST['transaction_type']??'');$amount=self::moneyAmount($_POST['amount']??'');$date=(string)($_POST['transaction_date']??date('Y-m-d'));
     if(!in_array($type,['income','expense','collection','supplier_payment'],true)||$amount<=0||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new RuntimeException('İşlem türü, tutar veya tarih geçersiz.');
     $db->beginTransaction();$q=$db->prepare("SELECT id,currency FROM financial_accounts WHERE id=? AND status='active' FOR UPDATE");$q->execute([$account]);$acc=$q->fetch();if(!$acc)throw new RuntimeException('Aktif bir kasa veya banka hesabı seçin.');
     $partyType=null;$partyId=null;
     if($type==='collection'){$partyType='customer';$partyId=(int)($_POST['party_id']??0);$q=$db->prepare("SELECT id FROM customers WHERE id=? AND status='active'");$q->execute([$partyId]);if(!$q->fetchColumn())throw new RuntimeException('Tahsilat için aktif müşteri seçin.');}
     if($type==='supplier_payment'){$partyType='supplier';$partyId=(int)($_POST['party_id']??0);$q=$db->prepare("SELECT id FROM suppliers WHERE id=? AND status='active'");$q->execute([$partyId]);if(!$q->fetchColumn())throw new RuntimeException('Ödeme için aktif tedarikçi seçin.');}
     $invoiceId=max(0,(int)($_POST['invoice_id']??0));
     if($invoiceId>0){
      if(!in_array($type,['collection','supplier_payment'],true))throw new RuntimeException('Fatura yalnızca tahsilat veya tedarikçi ödemesine bağlanabilir.');
      $q=$db->prepare('SELECT * FROM invoices WHERE id=? FOR UPDATE');$q->execute([$invoiceId]);$invoice=$q->fetch();
      $expectedType=$type==='collection'?'sales':'purchase';
      if(!$invoice||$invoice['invoice_type']!==$expectedType||$invoice['status']!=='issued')throw new RuntimeException('Ödeme için açık ve uygun türde fatura seçin.');
      $expectedParty=(int)($type==='collection'?$invoice['customer_id']:$invoice['supplier_id']);
      if($partyId!==$expectedParty||$acc['currency']!==$invoice['currency'])throw new RuntimeException('Fatura, cari hesap ve para birimi eşleşmiyor.');
      $q=$db->prepare('SELECT COALESCE(SUM(amount),0) FROM financial_transactions WHERE invoice_id=? AND transaction_type=? AND direction=? AND currency=?');$q->execute([$invoiceId,$type,$type==='collection'?'credit':'debit',$invoice['currency']]);
      $direct=(float)$q->fetchColumn();
      $q=$db->prepare('SELECT COALESCE(SUM(amount),0) FROM financial_advance_allocations WHERE invoice_id=?');$q->execute([$invoiceId]);
      $remaining=round((float)$invoice['grand_total']-$direct-(float)$q->fetchColumn(),2);
      if($amount>$remaining+0.000001)throw new RuntimeException('Ödeme tutarı kalan fatura bakiyesini aşamaz.');
     }
     $direction=in_array($type,['income','collection'],true)?'credit':'debit';
     $db->prepare('INSERT INTO financial_transactions (account_id,invoice_id,transaction_type,direction,amount,currency,transaction_date,party_type,party_id,category,reference_no,description,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())')->execute([$account,$invoiceId?:null,$type,$direction,$amount,$acc['currency'],$date,$partyType,$partyId,trim((string)($_POST['category']??''))?:null,trim((string)($_POST['reference_no']??''))?:null,trim((string)($_POST['description']??''))?:null,(int)$admin['id']]);
     $id=(int)$db->lastInsertId();\KodVadAll\Core\AccountingPostingService::postFinancialTransaction($db,$id,(int)$admin['id']);if($invoiceId>0&&$remaining-$amount<=0.000001)$db->prepare("UPDATE invoices SET status='paid',updated_at=NOW() WHERE id=?")->execute([$invoiceId]);$db->commit();kd_audit('finance.transaction_created','financial_transaction',$id);self::go('list');
    }
    if($form==='transfer'){
     $from=(int)($_POST['from_account']??0);$to=(int)($_POST['to_account']??0);$amount=self::moneyAmount($_POST['amount']??'');$date=(string)($_POST['transaction_date']??date('Y-m-d'));
     if($from<1||$to<1||$from===$to||$amount<=0||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new RuntimeException('Transfer bilgilerini kontrol edin.');
     $db->beginTransaction();$q=$db->prepare("SELECT id,currency FROM financial_accounts WHERE id IN (?,?) AND status='active' ORDER BY id FOR UPDATE");$q->execute([$from,$to]);$accs=$q->fetchAll();if(count($accs)!==2||$accs[0]['currency']!==$accs[1]['currency'])throw new RuntimeException('İki aktif hesap ve aynı para birimi seçilmelidir.');
     $code='TRF-'.bin2hex(random_bytes(8));$ins=$db->prepare('INSERT INTO financial_transactions (account_id,transaction_type,direction,amount,currency,transaction_date,transfer_code,description,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,NOW())');
     $ins->execute([$from,'transfer_out','debit',$amount,$accs[0]['currency'],$date,$code,'Hesaplar arası transfer · '.$code,(int)$admin['id']]);$transferOutId=(int)$db->lastInsertId();
     $ins->execute([$to,'transfer_in','credit',$amount,$accs[0]['currency'],$date,$code,'Hesaplar arası transfer · '.$code,(int)$admin['id']]);
     \KodVadAll\Core\AccountingPostingService::postFinancialTransaction($db,$transferOutId,(int)$admin['id']);$db->commit();kd_audit('finance.transfer_created','financial_transaction');self::go('list');
    }
   }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('KodVadAll finance: '.$e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'Finans işlemi tamamlanamadı.';}
  }
  $accounts=$db->query("SELECT a.*,(a.opening_balance+COALESCE(SUM(CASE WHEN t.direction='credit' THEN t.amount ELSE -t.amount END),0)) balance FROM financial_accounts a LEFT JOIN financial_transactions t ON t.account_id=a.id GROUP BY a.id ORDER BY a.status,a.name")->fetchAll();
  $active=$db->query("SELECT id,name,currency,account_type FROM financial_accounts WHERE status='active' ORDER BY name")->fetchAll();
  $customers=$db->query("SELECT id,name FROM customers WHERE status='active' ORDER BY name")->fetchAll();
  $suppliers=$db->query("SELECT id,name FROM suppliers WHERE status='active' ORDER BY name")->fetchAll();
  $openInvoices=$db->query("SELECT i.id,i.invoice_number,i.invoice_type,i.currency,i.customer_id,i.supplier_id,i.grand_total,ROUND(i.grand_total-COALESCE((SELECT SUM(t.amount) FROM financial_transactions t WHERE t.invoice_id=i.id AND t.currency=i.currency AND ((i.invoice_type='sales' AND t.transaction_type='collection' AND t.direction='credit') OR (i.invoice_type='purchase' AND t.transaction_type='supplier_payment' AND t.direction='debit'))),0)-COALESCE((SELECT SUM(a.amount) FROM financial_advance_allocations a WHERE a.invoice_id=i.id),0),2) remaining FROM invoices i WHERE i.status='issued' AND i.invoice_type IN ('sales','purchase') ORDER BY i.id DESC LIMIT 200")->fetchAll();
  $advances=$db->query("SELECT t.id,t.party_type,t.party_id,t.currency,t.transaction_type,t.amount,ROUND(t.amount-COALESCE((SELECT SUM(a.amount) FROM financial_advance_allocations a WHERE a.financial_transaction_id=t.id),0),2) remaining FROM financial_transactions t WHERE t.invoice_id IS NULL AND t.transaction_type IN ('collection','supplier_payment') AND t.party_id IS NOT NULL HAVING remaining>0 ORDER BY t.id DESC LIMIT 200")->fetchAll();
  $q=trim((string)($_GET['q']??''));$type=in_array($_GET['type']??'', ['income','expense','collection','supplier_payment','transfer_in','transfer_out'],true)?$_GET['type']:'';
  if($action==='account'){$id=max(0,(int)($_GET['id']??0));$st=$db->prepare("SELECT a.*,(a.opening_balance+COALESCE(SUM(CASE WHEN t.direction='credit' THEN t.amount ELSE -t.amount END),0)) balance FROM financial_accounts a LEFT JOIN financial_transactions t ON t.account_id=a.id WHERE a.id=? GROUP BY a.id");$st->execute([$id]);$selected=$st->fetch();if(!$selected)self::notFound();$st=$db->prepare('SELECT * FROM financial_transactions WHERE account_id=? ORDER BY transaction_date DESC,id DESC LIMIT 200');$st->execute([$id]);$transactions=$st->fetchAll();}
  else{$where=[];$params=[];if($q!==''){$where[]='(t.reference_no LIKE ? OR t.description LIKE ? OR a.name LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like);}if($type!==''){$where[]='t.transaction_type=?';$params[]=$type;}$sql=$where?' WHERE '.implode(' AND ',$where):'';$st=$db->prepare('SELECT t.*,a.name account_name FROM financial_transactions t JOIN financial_accounts a ON a.id=t.account_id'.$sql.' ORDER BY t.transaction_date DESC,t.id DESC LIMIT 150');$st->execute($params);$transactions=$st->fetchAll();}
  $pageTitle='Finans & Ön Muhasebe';ob_start();require KD_ROOT.'/app/Views/finance/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
 private static function moneyAmount(mixed $value,bool $allowZero=false):float
 {
  $raw=trim((string)$value);
  if(!preg_match('/^\\d{1,13}(?:\\.\\d{1,2})?$/D',$raw))throw new RuntimeException('Tutar en fazla iki ondalık basamak içeren geçerli bir sayı olmalıdır.');
  $amount=(float)$raw;
  if($amount<0||(!$allowZero&&$amount<=0))throw new RuntimeException('Tutar sıfırdan büyük olmalıdır.');
  return $amount;
 }
 private static function go(string $action='list',int $id=0):never{$u='index.php?page=finance';if($action!=='list')$u.='&action='.$action;if($id)$u.='&id='.$id;header('Location: '.$u,true,303);exit;}
 private static function notFound():never{http_response_code(404);exit('Finans hesabı bulunamadı.');}
}
