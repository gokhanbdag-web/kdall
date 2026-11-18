<?php
declare(strict_types=1);
namespace KodVadAll\Core;
use PDO;
use RuntimeException;

final class AccountingPostingService
{
 private static function account(PDO $db,string $code):int
 {
  $q=$db->prepare('SELECT id FROM accounting_accounts WHERE code=? AND is_active=1 LIMIT 1');$q->execute([$code]);$id=(int)$q->fetchColumn();
  if($id<1)throw new RuntimeException('Muhasebe hesap planında '.$code.' kodlu aktif hesap bulunamadı.');
  return $id;
 }
 private static function journal(PDO $db,string $sourceType,int $sourceId,string $date,string $description,int $userId,string $currency,array $lines):void
 {
  $check=$db->prepare('SELECT id FROM accounting_journals WHERE source_type=? AND source_id=? LIMIT 1');$check->execute([$sourceType,$sourceId]);if($check->fetchColumn())return;
  $debit=0.0;$credit=0.0;foreach($lines as $line){$debit+=(float)$line[1];$credit+=(float)$line[2];}
  if(count($lines)<2||abs($debit-$credit)>0.009||$debit<=0)throw new RuntimeException('Otomatik muhasebe fişi dengeli oluşturulamadı.');
  $number='OTO-'.strtoupper(substr(hash('sha256',$sourceType),0,8)).'-'.$sourceId;
  $db->prepare("INSERT INTO accounting_journals(journal_no,journal_date,description,source_type,source_id,created_by,created_at) VALUES(?,?,?,?,?,?,NOW())")->execute([$number,$date,$description,$sourceType,$sourceId,$userId?:null]);
  $journalId=(int)$db->lastInsertId();$insert=$db->prepare('INSERT INTO accounting_journal_lines(journal_id,account_id,currency,debit,credit,line_description,created_at) VALUES(?,?,?,?,?,?,NOW())');
  foreach($lines as $line)$insert->execute([$journalId,(int)$line[0],$currency,(float)$line[1],(float)$line[2],$line[3]??null]);
 }
 public static function postFinancialTransaction(PDO $db,int $transactionId,int $userId=0):void
 {
  $q=$db->prepare('SELECT t.*,a.account_type FROM financial_transactions t JOIN financial_accounts a ON a.id=t.account_id WHERE t.id=? FOR UPDATE');$q->execute([$transactionId]);$t=$q->fetch();if(!$t)throw new RuntimeException('Muhasebeleştirilecek finans hareketi bulunamadı.');
  $currency=strtoupper((string)$t['currency']);if(!preg_match('/^[A-Z]{3}$/',$currency))throw new RuntimeException('Finans hareketinin para birimi geçersiz.');
  $cash=self::account($db,$t['account_type']==='bank'?'102':'100');$amount=round((float)$t['amount'],2);if($amount<=0)throw new RuntimeException('Muhasebeleştirilecek tutar geçersiz.');
  if($t['transaction_type']==='transfer_out'){
   $q=$db->prepare("SELECT t.*,a.account_type FROM financial_transactions t JOIN financial_accounts a ON a.id=t.account_id WHERE t.transfer_code=? AND t.transaction_type='transfer_in' LIMIT 1 FOR UPDATE");$q->execute([$t['transfer_code']]);$to=$q->fetch();if(!$to)throw new RuntimeException('Transferin karşı hareketi bulunamadı.');
   if($to['currency']!==$currency||(float)$to['amount']!== (float)$t['amount'])throw new RuntimeException('Transfer hareketlerinin tutarı veya para birimi eşleşmiyor.');
   $destination=self::account($db,$to['account_type']==='bank'?'102':'100');
   self::journal($db,'finance_transfer',$transactionId,(string)$t['transaction_date'],'Kasa/banka transferi '.$t['transfer_code'],$userId,$currency,[[$destination,$amount,0,'Transfer girişi'],[$cash,0,$amount,'Transfer çıkışı']]);return;
  }
  if($t['transaction_type']==='transfer_in')return;
  $lines=match((string)$t['transaction_type']){
   'income'=>[[$cash,$amount,0,'Gelir tahsilatı'],[self::account($db,'600'),0,$amount,'Gelir']],
   'expense'=>[[self::account($db,'770'),$amount,0,'Gider'],[$cash,0,$amount,'Kasa/banka çıkışı']],
   'collection'=>[[$cash,$amount,0,'Müşteri tahsilatı'],[self::account($db,'120'),0,$amount,'Alıcılar tahsilatı']],
   'supplier_payment'=>[[self::account($db,'320'),$amount,0,'Satıcı ödemesi'],[$cash,0,$amount,'Kasa/banka çıkışı']],
   default=>throw new RuntimeException('Finans hareketi türü otomatik muhasebeye uygun değil.')
  };
  self::journal($db,'finance_transaction',$transactionId,(string)$t['transaction_date'],'Finans hareketi #'.$transactionId,$userId,$currency,$lines);
 }
 public static function postFinancialAccountOpening(PDO $db,int $accountId,int $userId=0):void
 {
  $q=$db->prepare('SELECT * FROM financial_accounts WHERE id=? FOR UPDATE');$q->execute([$accountId]);$a=$q->fetch();if(!$a)throw new RuntimeException('Finans hesabı bulunamadı.');
  $amount=round((float)$a['opening_balance'],2);if($amount<=0)return;
  $cash=self::account($db,$a['account_type']==='bank'?'102':'100');$capital=self::account($db,'500');
  self::journal($db,'finance_account_opening',$accountId,date('Y-m-d'),'Açılış bakiyesi · '.$a['name'],$userId,(string)$a['currency'],[[$cash,$amount,0,'Kasa/banka açılış bakiyesi'],[$capital,0,$amount,'Açılış sermayesi']]);
 }
 public static function postPurchaseReceipt(PDO $db,int $receiptId,int $userId=0):void
 {
  $q=$db->prepare('SELECT pr.*,po.currency FROM purchase_receipts pr JOIN purchase_orders po ON po.id=pr.purchase_order_id WHERE pr.id=? FOR UPDATE');$q->execute([$receiptId]);$receipt=$q->fetch();
  if(!$receipt)throw new RuntimeException('Muhasebeleştirilecek mal kabul kaydı bulunamadı.');
  $q=$db->prepare('SELECT COALESCE(SUM(pri.quantity*poi.unit_cost),0) FROM purchase_receipt_items pri JOIN purchase_order_items poi ON poi.id=pri.purchase_order_item_id JOIN products p ON p.id=pri.product_id WHERE pri.receipt_id=? AND p.track_stock=1');$q->execute([$receiptId]);$cost=round((float)$q->fetchColumn(),2);if($cost<=0)return;
  $inventory=self::account($db,'153');$receivedNotInvoiced=self::account($db,'159');
  self::journal($db,'purchase_receipt',$receiptId,substr((string)$receipt['received_at'],0,10),'Mal kabul '.$receipt['receipt_number'],$userId,(string)$receipt['currency'],[[$inventory,$cost,0,'Teslim alınan ticari mallar'],[$receivedNotInvoiced,0,$cost,'Faturası beklenen mal kabul']]);
 }
 public static function postInvoice(PDO $db,int $invoiceId,int $userId=0):void
 {
  $q=$db->prepare('SELECT * FROM invoices WHERE id=? FOR UPDATE');$q->execute([$invoiceId]);$i=$q->fetch();if(!$i)throw new RuntimeException('Muhasebeleştirilecek fatura bulunamadı.');
  if($i['invoice_type']==='proforma')return;
  $currency=(string)$i['currency'];$total=round((float)$i['grand_total'],2);$subtotal=round((float)$i['subtotal'],2);$tax=round((float)$i['tax_total'],2);$discount=round((float)($i['discount_total']??0),2);
  if($i['invoice_type']==='sales'){
   $receivable=self::account($db,'120');$revenue=self::account($db,'600');$vat=self::account($db,'391');$discountAccount=$discount>0?self::account($db,'610'):0;
   $lines=[[$receivable,$total,0,'Satış faturası alacağı'],[$revenue,0,$subtotal,'Satış geliri']];
   if($tax>0)$lines[]=[$vat,0,$tax,'Hesaplanan KDV'];if($discount>0)$lines[]=[$discountAccount,$discount,0,'Satış iskontosu'];
   $costQuery=$db->prepare('SELECT COALESCE(SUM(ii.quantity*ii.unit_cost),0) FROM invoice_items ii JOIN products p ON p.id=ii.product_id WHERE ii.invoice_id=? AND p.track_stock=1');
   $costQuery->execute([$invoiceId]);$cost=round((float)$costQuery->fetchColumn(),2);
   if($cost>0){$lines[]=[self::account($db,'621'),$cost,0,'Satılan ürünlerin maliyeti'];$lines[]=[self::account($db,'153'),0,$cost,'Stok maliyet çıkışı'];}
  }elseif($i['invoice_type']==='purchase'){
   $stockQuery=$db->prepare('SELECT COALESCE(SUM(pri.quantity*poi.unit_cost),0) FROM purchase_receipt_items pri JOIN purchase_order_items poi ON poi.id=pri.purchase_order_item_id JOIN products p ON p.id=pri.product_id JOIN purchase_receipts pr ON pr.id=pri.receipt_id WHERE poi.purchase_order_id=? AND p.track_stock=1');
   $stockQuery->execute([(int)$i['source_purchase_id']]);$stockCost=round((float)$stockQuery->fetchColumn(),2);
   if($stockCost>$subtotal+0.02)throw new RuntimeException('Mal kabul stok maliyeti alış faturası ara toplamını aşıyor.');
   $nonStockCost=round(max(0,$subtotal-$stockCost),2);$lines=[];
   if($stockCost>0)$lines[]=[self::account($db,'159'),$stockCost,0,'Mal kabul geçici hesabının kapatılması'];
   if($nonStockCost>0)$lines[]=[self::account($db,'770'),$nonStockCost,0,'Stok dışı satın alma gideri'];
   if($tax>0)$lines[]=[self::account($db,'191'),$tax,0,'İndirilecek KDV'];
   $lines[]=[self::account($db,'320'),0,$total,'Satıcı borcu'];
  }else{return;}
  self::journal($db,'invoice',$invoiceId,(string)$i['issue_date'],'Fatura '.$i['invoice_number'],$userId,$currency,$lines);
 }
 public static function postProductionOrder(PDO $db,int $orderId,int $userId=0):void
 {
  $q=$db->prepare("SELECT * FROM production_orders WHERE id=? AND status='completed' FOR UPDATE");$q->execute([$orderId]);$order=$q->fetch();
  if(!$order)throw new RuntimeException('Muhasebeleştirilecek tamamlanmış üretim emri bulunamadı.');
  $material=round((float)$order['material_cost'],2);$total=round((float)$order['total_cost'],2);$conversion=round($total-$material,2);
  if($total<=0)return;
  $inventory=self::account($db,'153');$lines=[[$inventory,$total,0,'Üretim mamul stok maliyeti']];
  if($material>0)$lines[]=[$inventory,0,$material,'Üretimde tüketilen hammadde'];
  $labor=round((float)$order['labor_cost'],2);$overhead=round((float)$order['overhead_cost'],2);
  if($labor>0)$lines[]=[self::account($db,'335'),0,$labor,'Üretim işçilik tahakkuku'];
  if($overhead>0)$lines[]=[self::account($db,'320'),0,$overhead,'Üretim genel gider tahakkuku'];
  self::journal($db,'production_order',$orderId,(string)$order['completed_at'],'Üretim maliyeti '.$order['order_number'],$userId,'TRY',$lines);
 }
 public static function postPosSale(PDO $db,int $orderId,int $userId=0):void
 {
  $q=$db->prepare("SELECT * FROM orders WHERE id=? AND order_source='pos' AND status='confirmed' AND payment_status='paid' FOR UPDATE");$q->execute([$orderId]);$order=$q->fetch();
  if(!$order)throw new RuntimeException('Muhasebeleştirilecek tamamlanmış POS satışı bulunamadı.');
  $currency=strtoupper((string)$order['currency']);if(!preg_match('/^[A-Z]{3}$/',$currency))throw new RuntimeException('POS satışının para birimi geçersiz.');
  $total=round((float)$order['grand_total'],2);$subtotal=round((float)$order['subtotal'],2);$tax=round((float)$order['tax_total'],2);
  if($total<=0||abs($total-($subtotal+$tax-(float)($order['discount_total']??0)))>0.02)throw new RuntimeException('POS satış toplamları tutarsız.');
  $cash=self::account($db,(string)$order['payment_method']==='cash'?'100':'102');$revenue=self::account($db,'600');
  $lines=[[$cash,$total,0,'POS tahsilatı'],[$revenue,0,$subtotal,'POS satış geliri']];
  if($tax>0)$lines[]=[self::account($db,'391'),0,$tax,'POS hesaplanan KDV'];
  $costQuery=$db->prepare('SELECT COALESCE(SUM(oi.quantity*oi.unit_cost),0) FROM order_items oi JOIN products p ON p.id=oi.product_id WHERE oi.order_id=? AND p.track_stock=1');
  $costQuery->execute([$orderId]);$cost=round((float)$costQuery->fetchColumn(),2);
  if($cost>0){$lines[]=[self::account($db,'621'),$cost,0,'POS satılan ürünlerin maliyeti'];$lines[]=[self::account($db,'153'),0,$cost,'POS stok maliyet çıkışı'];}
  self::journal($db,'pos_sale',$orderId,substr((string)$order['created_at'],0,10),'POS satışı '.$order['order_number'],$userId,$currency,$lines);
 }
 public static function postSalesReturnRestock(PDO $db,int $returnId,int $userId=0):void
 {
  $q=$db->prepare("SELECT r.*,o.currency FROM sales_returns r JOIN orders o ON o.id=r.order_id WHERE r.id=? AND r.status='received' FOR UPDATE");$q->execute([$returnId]);$r=$q->fetch();
  if(!$r)throw new RuntimeException('Muhasebeleştirilecek teslim alınmış iade bulunamadı.');
  $q=$db->prepare('SELECT COALESCE(SUM(ri.restock_quantity*oi.unit_cost),0) FROM sales_return_items ri JOIN order_items oi ON oi.id=ri.order_item_id JOIN products p ON p.id=ri.product_id WHERE ri.return_id=? AND p.track_stock=1');$q->execute([$returnId]);$cost=round((float)$q->fetchColumn(),2);if($cost<=0)return;
  $inventory=self::account($db,'153');$cogs=self::account($db,'621');
  self::journal($db,'sales_return_stock',$returnId,substr((string)$r['received_at'],0,10),'İade stok girişi '.$r['return_number'],$userId,(string)$r['currency'],[[$inventory,$cost,0,'İade alınan ürün stok maliyeti'],[$cogs,0,$cost,'Satılan mal maliyetinin iadesi']]);
 }
 public static function postSalesReturnRefund(PDO $db,int $transactionId,int $returnId,int $userId=0):void
 {
  $q=$db->prepare('SELECT t.*,fa.account_type FROM financial_transactions t JOIN financial_accounts fa ON fa.id=t.account_id WHERE t.id=? FOR UPDATE');$q->execute([$transactionId]);$t=$q->fetch();
  if(!$t||$t['transaction_type']!=='expense'||$t['category']!=='Müşteri iadesi')throw new RuntimeException('İade ödeme hareketi bulunamadı veya geçersiz.');
  $q=$db->prepare('SELECT r.*,o.currency FROM sales_returns r JOIN orders o ON o.id=r.order_id WHERE r.id=? FOR UPDATE');$q->execute([$returnId]);$r=$q->fetch();
  if(!$r||$t['reference_no']!==$r['return_number']||$t['currency']!==$r['currency'])throw new RuntimeException('İade ödeme hareketi ile iade kaydı eşleşmiyor.');
  $amount=round((float)$t['amount'],2);$refundTotal=round((float)$r['refund_total'],2);$paidBefore=round((float)$r['refunded_total'],2);
  if($amount<=0||$refundTotal<=0||$amount>$refundTotal-$paidBefore+0.01)throw new RuntimeException('İade ödeme tutarı kalan iade tutarını aşıyor.');
  $q=$db->prepare('SELECT ri.quantity,ri.refund_amount,oi.quantity original_quantity,oi.line_subtotal,oi.line_tax FROM sales_return_items ri JOIN order_items oi ON oi.id=ri.order_item_id WHERE ri.return_id=?');$q->execute([$returnId]);$netTotal=0.0;$taxTotal=0.0;
  foreach($q->fetchAll() as $item){$originalQty=(float)$item['original_quantity'];if($originalQty<=0)throw new RuntimeException('İade kaleminin sipariş miktarı geçersiz.');$netTotal+=round((float)$item['line_subtotal']/(float)$originalQty*(float)$item['quantity'],2);$taxTotal+=(float)$item['refund_amount']-round((float)$item['line_subtotal']/(float)$originalQty*(float)$item['quantity'],2);}
  $netTotal=round($netTotal,2);$taxTotal=round($taxTotal,2);
  $isFinal=$paidBefore+$amount>=$refundTotal-0.01;$netPart=$isFinal?round($netTotal-round($paidBefore*$netTotal/$refundTotal,2),2):round($amount*$netTotal/$refundTotal,2);$taxPart=round($amount-$netPart,2);
  $cash=self::account($db,$t['account_type']==='bank'?'102':'100');$lines=[];if($netPart>0)$lines[]=[self::account($db,'610'),$netPart,0,'Satış iadesi'];if($taxPart>0)$lines[]=[self::account($db,'391'),$taxPart,0,'İade edilen satış KDV'];$lines[]=[$cash,0,$amount,'Müşteriye iade ödemesi'];
  self::journal($db,'sales_return_refund',$transactionId,(string)$t['transaction_date'],'Müşteri iadesi '.$r['return_number'],$userId,(string)$t['currency'],$lines);
 }
 public static function postStocktake(PDO $db,int $stocktakeId,int $userId=0):void
 {
  $q=$db->prepare("SELECT * FROM warehouse_stocktakes WHERE id=? AND status='completed' FOR UPDATE");$q->execute([$stocktakeId]);$stocktake=$q->fetch();
  if(!$stocktake)throw new RuntimeException('Muhasebeleştirilecek tamamlanmış stok sayımı bulunamadı.');
  $q=$db->prepare('SELECT i.product_id,i.variance,p.name,p.purchase_price FROM warehouse_stocktake_items i JOIN products p ON p.id=i.product_id WHERE i.stocktake_id=? AND i.variance IS NOT NULL AND ABS(i.variance)>0.0005 ORDER BY i.product_id FOR UPDATE');$q->execute([$stocktakeId]);$lines=[];
  foreach($q->fetchAll() as $item){$amount=round(abs((float)$item['variance'])*(float)$item['purchase_price'],2);if($amount<=0)continue;if((float)$item['variance']>0){$lines[]=[self::account($db,'153'),$amount,0,'Sayım fazlası · '.$item['name']];$lines[]=[self::account($db,'679'),0,$amount,'Stok sayım fazlası geliri'];}else{$lines[]=[self::account($db,'689'),$amount,0,'Stok sayım eksiği · '.$item['name']];$lines[]=[self::account($db,'153'),0,$amount,'Sayım nedeniyle stok çıkışı'];}}
  if(!$lines)return;
  self::journal($db,'stocktake',(int)$stocktake['id'],substr((string)$stocktake['completed_at'],0,10),'Depo sayımı '.$stocktake['stocktake_number'],$userId,'TRY',$lines);
 }
 public static function reverseInvoice(PDO $db,int $invoiceId,int $userId=0):void
 {
  $q=$db->prepare("SELECT j.id,j.journal_no,j.journal_date FROM accounting_journals j WHERE j.source_type='invoice' AND j.source_id=? LIMIT 1 FOR UPDATE");$q->execute([$invoiceId]);$original=$q->fetch();if(!$original)return;
  $check=$db->prepare("SELECT id FROM accounting_journals WHERE source_type='invoice_reversal' AND source_id=? LIMIT 1");$check->execute([$invoiceId]);if($check->fetchColumn())return;
  $q=$db->prepare('SELECT account_id,currency,debit,credit,line_description FROM accounting_journal_lines WHERE journal_id=?');$q->execute([(int)$original['id']]);$lines=[];
  foreach($q->fetchAll() as $line)$lines[]=[(int)$line['account_id'],(float)$line['credit'],(float)$line['debit'],'İptal ters kaydı · '.($line['line_description']??'')];
  if(!$lines)return;
    $currencyQuery=$db->prepare('SELECT currency FROM accounting_journal_lines WHERE journal_id=? LIMIT 1');$currencyQuery->execute([(int)$original['id']]);$currency=(string)$currencyQuery->fetchColumn();
  self::journal($db,'invoice_reversal',$invoiceId,date('Y-m-d'),'Fatura iptal ters kaydı #'.$invoiceId,$userId,$currency,$lines);
 }
}
