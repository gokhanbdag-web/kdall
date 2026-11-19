<?php
declare(strict_types=1);
namespace KodVadAll\Core;
use PDO;
use InvalidArgumentException;

final class PartyLedgerService
{
 public static function statement(PDO $db,string $party,int $id):array
 {
  if(!in_array($party,['customer','supplier'],true)||$id<1)throw new InvalidArgumentException('Invalid ledger party.');
  $customer=$party==='customer';$column=$customer?'customer_id':'supplier_id';$invoiceType=$customer?'sales':'purchase';$paymentType=$customer?'collection':'supplier_payment';$direction=$customer?'credit':'debit';
  $q=$db->prepare("SELECT currency,COALESCE(SUM(grand_total),0) total FROM invoices WHERE {$column}=? AND invoice_type=? AND status IN ('issued','paid') GROUP BY currency");
  $q->execute([$id,$invoiceType]);$totals=[];
  foreach($q->fetchAll() as $r)$totals[$r['currency']]=['invoiced'=>(float)$r['total'],'paid'=>0.0];
  $q=$db->prepare("SELECT currency,COALESCE(SUM(amount),0) total FROM financial_transactions WHERE party_type=? AND party_id=? AND transaction_type=? AND direction=? GROUP BY currency");
  $q->execute([$party,$id,$paymentType,$direction]);
  foreach($q->fetchAll() as $r){$currency=$r['currency'];$totals[$currency]??=['invoiced'=>0.0,'paid'=>0.0];$totals[$currency]['paid']=(float)$r['total'];}
  ksort($totals);
  $q=$db->prepare("SELECT id,invoice_number reference_no,issue_date entry_date,currency,grand_total amount,'invoice' entry_type FROM invoices WHERE {$column}=? AND invoice_type=? AND status IN ('issued','paid') ORDER BY id DESC LIMIT 100");
  $q->execute([$id,$invoiceType]);$entries=$q->fetchAll();
  $q=$db->prepare("SELECT id,COALESCE(NULLIF(reference_no,''),CONCAT('FIN-',id)) reference_no,transaction_date entry_date,currency,amount,'payment' entry_type FROM financial_transactions WHERE party_type=? AND party_id=? AND transaction_type=? AND direction=? ORDER BY id DESC LIMIT 100");
  $q->execute([$party,$id,$paymentType,$direction]);$entries=array_merge($entries,$q->fetchAll());
  usort($entries,static fn(array $a,array $b):int=>strcmp((string)$b['entry_date'],(string)$a['entry_date'])?:strcmp((string)$b['entry_type'],(string)$a['entry_type'])?:((int)$b['id']<=>(int)$a['id']));
  return ['totals'=>$totals,'entries'=>array_slice($entries,0,100)];
 }
}
