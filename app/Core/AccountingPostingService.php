<?php
declare(strict_types=1);
namespace KodVadAll\\Core;
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
  $number='OTO-'.strtoupper(substr($sourceType,0,8)).'-'.$sourceId;
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
  $receivable=self::account($db,'120');$payable=self::account($db,'320');$revenue=self::account($db,'600');$expense=self::account($db,'770');
  $lines=match((string)$t['transaction_type']){
   'income'=>[[$cash,$amount,0,'Gelir tahsilatı'],[$revenue,0,$amount,'Gelir']],
   'expense'=>[[$expense,$amount,0,'Gider'],[$cash,0,$amount,'Kasa/banka çıkışı']],
   'collection'=>[[$cash,$amount,0,'Müşteri tahsilatı'],[$receivable,0,$amount,'Alıcılar tahsilatı']],
   'supplier_payment'=>[[$payable,$amount,0,'Satıcı ödemesi'],[$cash,0,$amount,'Kasa/banka çıkışı']],
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
 public static function postInvoice(PDO $db,int $invoiceId,int $userId=0):void
 {
  $q=$db->prepare('SELECT * FROM invoices WHERE id=? FOR UPDATE');$q->execute([$invoiceId]);$i=$q->fetch();if(!$i)throw new RuntimeException('Muhasebeleştirilecek fatura bulunamadı.');
  if($i['invoice_type']==='proforma')return;
  $currency=(string)$i['currency'];$total=round((float)$i['grand_total'],2);$subtotal=round((float)$i['subtotal'],2);$tax=round((float)$i['tax_total'],2);$discount=round((float)($i['discount_total']??0),2);
  if($i['invoice_type']==='sales'){
   $receivable=self::account($db,'120');$revenue=self::account($db,'600');$vat=self::account($db,'391');$discountAccount=self::account($db,'610');
   $lines=[[$receivable,$total,0,'Satış faturası alacağı'],[$revenue,0,$subtotal,'Satış geliri']];
   if($tax>0)$lines[]=[$vat,0,$tax,'Hesaplanan KDV'];if($discount>0)$lines[]=[$discountAccount,$discount,0,'Satış iskontosu'];
  }elseif($i['invoice_type']==='purchase'){
   $inventory=self::account($db,'153');$vat=self::account($db,'191');$payable=self::account($db,'320');
   $lines=[[$inventory,$subtotal,0,'Alış / stok maliyeti']];if($tax>0)$lines[]=[$vat,$tax,0,'İndirilecek KDV'];$lines[]=[$payable,0,$total,'Satıcı borcu'];
  }else{return;}
  self::journal($db,'invoice',$invoiceId,(string)$i['issue_date'],'Fatura '.$i['invoice_number'],$userId,$currency,$lines);
 }
 public static function reverseInvoice(PDO $db,int $invoiceId,int $userId=0):void
 {
  $q=$db->prepare("SELECT j.id,j.journal_no,j.journal_date FROM accounting_journals j WHERE j.source_type='invoice' AND j.source_id=? LIMIT 1 FOR UPDATE");$q->execute([$invoiceId]);$original=$q->fetch();if(!$original)return;
  $check=$db->prepare("SELECT id FROM accounting_journals WHERE source_type='invoice_reversal' AND source_id=? LIMIT 1");$check->execute([$invoiceId]);if($check->fetchColumn())return;
  $q=$db->prepare('SELECT account_id,currency,debit,credit,line_description FROM accounting_journal_lines WHERE journal_id=?');$q->execute([(int)$original['id']]);$lines=[];
  foreach($q->fetchAll() as $line)$lines[]=[(int)$line['account_id'],(float)$line['credit'],(float)$line['debit'],'İptal ters kaydı · '.($line['line_description']??'')];
  if(!$lines)return;
  $currency=(string)$lines[0][1]; // replaced below from original journal lines
  $currencyQuery=$db->prepare('SELECT currency FROM accounting_journal_lines WHERE journal_id=? LIMIT 1');$currencyQuery->execute([(int)$original['id']]);$currency=(string)$currencyQuery->fetchColumn();
  self::journal($db,'invoice_reversal',$invoiceId,(string)$original['journal_date'],'Fatura iptal ters kaydı #'.$invoiceId,$userId,$currency,$lines);
 }
}
