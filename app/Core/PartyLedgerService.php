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
  $page=max(1,min(100000,(int)($_GET['ledger_page']??1)));
  $perPage=50;
  $count=$db->prepare("SELECT (SELECT COUNT(*) FROM invoices WHERE {$column}=? AND invoice_type=? AND status IN ('issued','paid'))+(SELECT COUNT(*) FROM financial_transactions WHERE party_type=? AND party_id=? AND transaction_type=? AND direction=?)");
  $count->execute([$id,$invoiceType,$party,$id,$paymentType,$direction]);
  $entryCount=(int)$count->fetchColumn();
  $pages=max(1,(int)ceil($entryCount/$perPage));
  $page=min($page,$pages);
  $offset=($page-1)*$perPage;
  $sql="SELECT id,reference_no,entry_date,currency,amount,entry_type FROM (
   SELECT id,invoice_number reference_no,issue_date entry_date,currency,grand_total amount,'invoice' entry_type FROM invoices WHERE {$column}=? AND invoice_type=? AND status IN ('issued','paid')
   UNION ALL
   SELECT id,COALESCE(NULLIF(reference_no,''),CONCAT('FIN-',id)) reference_no,transaction_date entry_date,currency,amount,'payment' entry_type FROM financial_transactions WHERE party_type=? AND party_id=? AND transaction_type=? AND direction=?
  ) ledger_entries ORDER BY entry_date DESC,entry_type DESC,id DESC LIMIT {$perPage} OFFSET {$offset}";
  $q=$db->prepare($sql);
  $q->execute([$id,$invoiceType,$party,$id,$paymentType,$direction]);
  return ['totals'=>$totals,'entries'=>$q->fetchAll(),'page'=>$page,'pages'=>$pages,'entry_count'=>$entryCount];
 }
}
