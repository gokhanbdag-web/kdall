<?php
declare(strict_types=1);
namespace KodVadAll\Core;
use PDO;
use RuntimeException;

final class AdvanceAllocationService
{
 public static function allocate(PDO $db,int $transactionId,int $invoiceId,string $rawAmount,int $userId):int
 {
  if($transactionId<1||$invoiceId<1||!preg_match('/^\\d{1,13}(?:\\.\\d{1,2})?$/D',$rawAmount)||(float)$rawAmount<=0)throw new RuntimeException('Mahsup bilgileri geçersiz.');
  $amount=round((float)$rawAmount,2);
  $db->beginTransaction();
  try{
   $q=$db->prepare("SELECT * FROM financial_transactions WHERE id=? AND invoice_id IS NULL AND transaction_type IN ('collection','supplier_payment') FOR UPDATE");$q->execute([$transactionId]);$tx=$q->fetch();
   $q=$db->prepare("SELECT * FROM invoices WHERE id=? AND status='issued' AND invoice_type IN ('sales','purchase') FOR UPDATE");$q->execute([$invoiceId]);$invoice=$q->fetch();
   if(!$tx||!$invoice)throw new RuntimeException('Açık avans veya fatura bulunamadı.');
   $customer=$tx['transaction_type']==='collection';
   if($tx['party_type']!==($customer?'customer':'supplier')||(int)$tx['party_id']!==(int)$invoice[$customer?'customer_id':'supplier_id']||$invoice['invoice_type']!==($customer?'sales':'purchase')||$tx['currency']!==$invoice['currency']||$tx['direction']!==($customer?'credit':'debit'))throw new RuntimeException('Cari hesap, işlem türü veya para birimi uyuşmuyor.');
   $q=$db->prepare('SELECT COALESCE(SUM(amount),0) FROM financial_advance_allocations WHERE financial_transaction_id=?');$q->execute([$transactionId]);$available=round((float)$tx['amount']-(float)$q->fetchColumn(),2);
   $q=$db->prepare('SELECT COALESCE(SUM(amount),0) FROM financial_transactions WHERE invoice_id=? AND transaction_type=? AND direction=? AND currency=?');$q->execute([$invoiceId,$tx['transaction_type'],$tx['direction'],$tx['currency']]);$direct=(float)$q->fetchColumn();
   $q=$db->prepare('SELECT COALESCE(SUM(amount),0) FROM financial_advance_allocations WHERE invoice_id=?');$q->execute([$invoiceId]);$remaining=round((float)$invoice['grand_total']-$direct-(float)$q->fetchColumn(),2);
   if($amount>$available+0.000001||$amount>$remaining+0.000001||$available<=0||$remaining<=0)throw new RuntimeException('Mahsup tutarı avans veya fatura kalanını aşıyor.');
   $q=$db->prepare('INSERT INTO financial_advance_allocations (financial_transaction_id,invoice_id,amount,created_by,created_at) VALUES (?,?,?,?,NOW())');$q->execute([$transactionId,$invoiceId,$amount,$userId?:null]);$id=(int)$db->lastInsertId();
   if($remaining-$amount<=0.000001)$db->prepare("UPDATE invoices SET status='paid',updated_at=NOW() WHERE id=?")->execute([$invoiceId]);
   $db->commit();return $id;
  }catch(\Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 }
}
