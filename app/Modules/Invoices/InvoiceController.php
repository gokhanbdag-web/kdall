<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Invoices;
use PDO;
use RuntimeException;
use Throwable;

final class InvoiceController
{
 public static function handle(PDO $db,array $admin):void
 {
  kd_require_permission('invoices.view');$action=(string)($_GET['action']??'list');$id=max(0,(int)($_GET['id']??0));$errors=[];
  if($_SERVER['REQUEST_METHOD']==='POST'){
   kd_csrf_check();kd_require_permission('invoices.manage');
   try{
    $form=(string)($_POST['form_action']??'');
    if($form==='create'){
     $kind=(string)($_POST['invoice_type']??'sales');$source=(int)($_POST[$kind==='purchase'?'source_purchase_id':'source_order_id']??0);$due=trim((string)($_POST['due_date']??''));$notes=trim((string)($_POST['notes']??''));
     if(!in_array($kind,['sales','purchase','proforma'],true)||$source<1)throw new RuntimeException('Fatura türü ve kaynak belge seçin.');
     if($due!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$due))throw new RuntimeException('Vade tarihi geçersiz.');
     if($kind==='purchase'){$q=$db->prepare("SELECT * FROM purchase_orders WHERE id=? AND status='received'");$q->execute([$source]);$src=$q->fetch();if(!$src)throw new RuntimeException('Fatura için mal kabulü yapılmış satın alma siparişi seçin.');$q=$db->prepare('SELECT * FROM purchase_order_items WHERE purchase_order_id=?');$q->execute([$source]);$sourceItems=$q->fetchAll();$customerId=null;$supplierId=(int)$src['supplier_id'];$sub=(float)$src['subtotal'];$tax=(float)$src['tax_total'];$total=(float)$src['grand_total'];$currency=(string)$src['currency'];}
     else{$q=$db->prepare("SELECT * FROM orders WHERE id=? AND customer_id IS NOT NULL AND status IN ('confirmed','processing','shipped','completed')");$q->execute([$source]);$src=$q->fetch();if(!$src)throw new RuntimeException('Fatura için müşterili ve onaylanmış bir satış siparişi seçin.');$q=$db->prepare('SELECT * FROM order_items WHERE order_id=?');$q->execute([$source]);$sourceItems=$q->fetchAll();$customerId=(int)$src['customer_id'];$supplierId=null;$sub=(float)$src['subtotal'];$tax=(float)$src['tax_total'];$total=(float)$src['grand_total'];$currency=(string)$src['currency'];}
     if(!$sourceItems)throw new RuntimeException('Kaynak belgede faturalandırılabilir ürün kalemi yok.');
     $db->beginTransaction();$tmp='TMP-'.bin2hex(random_bytes(8));$number='FTR-'.date('Y').'-'.str_pad((string)random_int(1,999999),6,'0',STR_PAD_LEFT);
     $q=$db->prepare('INSERT INTO invoices (invoice_number,invoice_type,status,customer_id,supplier_id,source_order_id,source_purchase_id,issue_date,due_date,currency,subtotal,tax_total,grand_total,notes,created_by,created_at) VALUES (?,?,\'draft\',?,?,?,?,?,?,?,?,?,?,?,?,NOW())');
     $q->execute([$tmp,$kind,$customerId,$supplierId,$kind==='purchase'?null:$source,$kind==='purchase'?$source:null,date('Y-m-d'),$due?:null,$currency,$sub,$tax,$total,$notes?:null,(int)$admin['id']]);
     $invoiceId=(int)$db->lastInsertId();$number='FTR-'.date('Y').'-'.str_pad((string)$invoiceId,6,'0',STR_PAD_LEFT);$db->prepare('UPDATE invoices SET invoice_number=? WHERE id=?')->execute([$number,$invoiceId]);
     $ins=$db->prepare('INSERT INTO invoice_items (invoice_id,product_id,description,sku,quantity,unit_price,tax_rate,line_subtotal,line_tax,line_total,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,NOW())');
     foreach($sourceItems as $it){$ins->execute([$invoiceId,$it['product_id']??null,$it['product_name'],$it['sku']??null,$it['quantity'],$kind==='purchase'?$it['unit_cost']:$it['unit_price'],$it['tax_rate'],$it['line_subtotal'],$it['line_tax'],$it['line_total']]);}
     $db->commit();kd_audit('invoice.created','invoice',$invoiceId);self::go('view',$invoiceId);
    }
    if($form==='status'){
     $invoiceId=(int)($_POST['invoice_id']??0);$target=(string)($_POST['status']??'');$db->beginTransaction();$q=$db->prepare('SELECT * FROM invoices WHERE id=? FOR UPDATE');$q->execute([$invoiceId]);$invoice=$q->fetch();if(!$invoice)throw new RuntimeException('Fatura bulunamadı.');
     if($target==='issued'&&$invoice['status']==='draft'){$db->prepare("UPDATE invoices SET status='issued',issued_at=NOW(),issue_date=COALESCE(issue_date,CURDATE()),external_reference=?,updated_at=NOW() WHERE id=?")->execute([trim((string)($_POST['external_reference']??''))?:null,$invoiceId]);}
     elseif($target==='cancelled'&&in_array($invoice['status'],['draft','issued'],true)){$q=$db->prepare('SELECT COUNT(*) FROM financial_transactions WHERE invoice_id=?');$q->execute([$invoiceId]);if((int)$q->fetchColumn()>0)throw new RuntimeException('Ödeme hareketi bulunan fatura iptal edilemez.');$db->prepare("UPDATE invoices SET status='cancelled',updated_at=NOW() WHERE id=?")->execute([$invoiceId]);}
     else throw new RuntimeException('Bu fatura durum geçişine izin verilmiyor.');
     $db->commit();kd_audit('invoice.status_changed','invoice',$invoiceId);self::go('view',$invoiceId);
    }
    if($form==='payment'){
     $invoiceId=(int)($_POST['invoice_id']??0);$accountId=(int)($_POST['account_id']??0);$amount=(float)($_POST['amount']??0);$date=(string)($_POST['transaction_date']??date('Y-m-d'));
     $db->beginTransaction();$q=$db->prepare('SELECT * FROM invoices WHERE id=? FOR UPDATE');$q->execute([$invoiceId]);$inv=$q->fetch();if(!$inv||$inv['status']!=='issued'||$inv['invoice_type']==='proforma')throw new RuntimeException('Bu fatura için ödeme alınamaz veya ödeme yapılamaz.');
     $direction=$inv['invoice_type']==='purchase'?'debit':'credit';$type=$inv['invoice_type']==='purchase'?'supplier_payment':'collection';
     $q=$db->prepare('SELECT COALESCE(SUM(amount),0) FROM financial_transactions WHERE invoice_id=? AND direction=?');$q->execute([$invoiceId,$direction]);$paid=(float)$q->fetchColumn();$remaining=(float)$inv['grand_total']-$paid;
     if($amount<=0||$amount>$remaining+0.000001||!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date))throw new RuntimeException('Ödeme tutarı kalan fatura tutarını aşamaz.');
     $q=$db->prepare("SELECT id,currency FROM financial_accounts WHERE id=? AND status='active'");$q->execute([$accountId]);$acc=$q->fetch();if(!$acc||$acc['currency']!==$inv['currency'])throw new RuntimeException('Fatura ile aynı para biriminde aktif hesap seçin.');
     $partyType=$type==='collection'?'customer':'supplier';$partyId=$type==='collection'?(int)$inv['customer_id']:(int)$inv['supplier_id'];
     $db->prepare('INSERT INTO financial_transactions (account_id,invoice_id,transaction_type,direction,amount,currency,transaction_date,party_type,party_id,reference_no,description,created_by,created_at) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW())')->execute([$accountId,$invoiceId,$type,$direction,$amount,$inv['currency'],$date,$partyType,$partyId,$inv['invoice_number'],'Fatura '.$inv['invoice_number'].' ödemesi',(int)$admin['id']]);
     if($remaining-$amount<=0.000001)$db->prepare("UPDATE invoices SET status='paid',updated_at=NOW() WHERE id=?")->execute([$invoiceId]);
     $db->commit();kd_audit('invoice.payment_recorded','invoice',$invoiceId);self::go('view',$invoiceId);
    }
   }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('KodVadAll invoices: '.$e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'Fatura işlemi tamamlanamadı.';$action=(string)($_GET['action']??'list');$id=max(0,(int)($_GET['id']??0));}
  }
  if($action==='create'){$orders=$db->query("SELECT o.id,o.order_number,c.name customer_name FROM orders o JOIN customers c ON c.id=o.customer_id WHERE o.status IN ('confirmed','processing','shipped','completed') ORDER BY o.id DESC LIMIT 200")->fetchAll();$purchases=$db->query("SELECT p.id,p.purchase_number,s.name supplier_name FROM purchase_orders p JOIN suppliers s ON s.id=p.supplier_id WHERE p.status='received' ORDER BY p.id DESC LIMIT 200")->fetchAll();}
  elseif($action==='view'){$q=$db->prepare('SELECT i.*,c.name customer_name,s.name supplier_name FROM invoices i LEFT JOIN customers c ON c.id=i.customer_id LEFT JOIN suppliers s ON s.id=i.supplier_id WHERE i.id=?');$q->execute([$id]);$invoice=$q->fetch();if(!$invoice)self::notFound();$q=$db->prepare('SELECT * FROM invoice_items WHERE invoice_id=? ORDER BY id');$q->execute([$id]);$items=$q->fetchAll();$q=$db->prepare('SELECT COALESCE(SUM(amount),0) FROM financial_transactions WHERE invoice_id=?');$q->execute([$id]);$paid=(float)$q->fetchColumn();$remaining=max(0,(float)$invoice['grand_total']-$paid);$q=$db->prepare('SELECT t.*,a.name account_name FROM financial_transactions t JOIN financial_accounts a ON a.id=t.account_id WHERE t.invoice_id=? ORDER BY t.id DESC');$q->execute([$id]);$payments=$q->fetchAll();$accounts=$db->prepare('SELECT id,name,currency FROM financial_accounts WHERE status=? AND currency=? ORDER BY name');$accounts->execute(['active',$invoice['currency']]);$paymentAccounts=$accounts->fetchAll();}
  else{$type=in_array($_GET['type']??'', ['sales','purchase','proforma'],true)?$_GET['type']:'';$status=in_array($_GET['status']??'', ['draft','issued','paid','cancelled'],true)?$_GET['status']:'';$where=[];$params=[];if($type!==''){$where[]='i.invoice_type=?';$params[]=$type;}if($status!==''){$where[]='i.status=?';$params[]=$status;}$sql=$where?' WHERE '.implode(' AND ',$where):'';$q=$db->prepare('SELECT i.*,c.name customer_name,s.name supplier_name FROM invoices i LEFT JOIN customers c ON c.id=i.customer_id LEFT JOIN suppliers s ON s.id=i.supplier_id'.$sql.' ORDER BY i.id DESC LIMIT 150');$q->execute($params);$invoices=$q->fetchAll();}
  $pageTitle='Faturalandırma';ob_start();require KD_ROOT.'/app/Views/invoices/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
 private static function go(string $action='list',int $id=0):never{$u='index.php?page=invoices';if($action!=='list')$u.='&action='.$action;if($id)$u.='&id='.$id;header('Location: '.$u,true,303);exit;}
 private static function notFound():never{http_response_code(404);exit('Fatura bulunamadı.');}
}
