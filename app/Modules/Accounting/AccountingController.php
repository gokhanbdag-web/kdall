<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Accounting;
use PDO;
use RuntimeException;
use Throwable;

final class AccountingController
{
 public static function handle(PDO $db,array $admin):void
 {
  kd_require_permission('accounting.view');$errors=[];
  if($_SERVER['REQUEST_METHOD']==='POST'){
   kd_csrf_check();kd_require_permission('accounting.manage');
   try{
    $form=(string)($_POST['form_action']??'');
    if($form==='account'){
     $code=trim((string)($_POST['code']??''));$name=trim((string)($_POST['name']??''));$type=(string)($_POST['account_type']??'asset');
     if($code===''||$name===''||!in_array($type,['asset','liability','equity','income','expense'],true))throw new RuntimeException('Hesap kodu, adı veya türü geçersiz.');
     $db->prepare('INSERT INTO accounting_accounts(code,name,account_type,parent_id,is_active,created_at) VALUES(?,?,?,?,1,NOW())')->execute([$code,$name,$type,(int)($_POST['parent_id']??0)?:null]);
     kd_audit('accounting.account_created','accounting_account',(int)$db->lastInsertId());self::go();
    }
    if($form==='journal'){
     $date=(string)($_POST['journal_date']??date('Y-m-d'));$currency=strtoupper((string)($_POST['currency']??'TRY'));$description=trim((string)($_POST['description']??''));$ids=is_array($_POST['account_id']??null)?$_POST['account_id']:[];$debits=is_array($_POST['debit']??null)?$_POST['debit']:[];$credits=is_array($_POST['credit']??null)?$_POST['credit']:[];
     if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||!in_array($currency,['TRY','USD','EUR','GBP'],true)||$description==='')throw new RuntimeException('Fiş tarihi ve açıklaması zorunludur.');
     $lines=[];$totalDebit=0.0;$totalCredit=0.0;
     foreach($ids as $i=>$accountId){$accountId=(int)$accountId;$debit=round((float)($debits[$i]??0),2);$credit=round((float)($credits[$i]??0),2);if($accountId<1||$debit<0||$credit<0||($debit>0&&$credit>0)||($debit==0&&$credit==0))continue;$lines[]=[$accountId,$debit,$credit];$totalDebit+=$debit;$totalCredit+=$credit;}
     if(count($lines)<2||abs($totalDebit-$totalCredit)>0.009||$totalDebit<=0)throw new RuntimeException('En az iki satır girilmeli; borç ve alacak toplamları eşit olmalıdır.');
     $db->beginTransaction();$number='MF-'.date('Ymd').'-'.bin2hex(random_bytes(3));
     $db->prepare("INSERT INTO accounting_journals(journal_no,journal_date,description,created_by,created_at) VALUES(?,?,?,?,NOW())")->execute([$number,$date,$description,(int)$admin['id']]);$journalId=(int)$db->lastInsertId();
     $check=$db->prepare('SELECT id FROM accounting_accounts WHERE id=? AND is_active=1');$insert=$db->prepare('INSERT INTO accounting_journal_lines(journal_id,account_id,currency,debit,credit,created_at) VALUES(?,?,?,?,?,NOW())');
     foreach($lines as [$accountId,$debit,$credit]){$check->execute([$accountId]);if(!$check->fetchColumn())throw new RuntimeException('Fişte pasif veya bulunmayan hesap seçildi.');$insert->execute([$journalId,$accountId,$currency,$debit,$credit]);}
     $db->commit();kd_audit('accounting.journal_posted','accounting_journal',$journalId);self::go();
    }
   }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('KodVadAll accounting: '.$e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'Muhasebe işlemi tamamlanamadı.';}
  }
  $currency=strtoupper((string)($_GET['currency']??'TRY'));if(!in_array($currency,['TRY','USD','EUR','GBP'],true))$currency='TRY';
  $today=date('Y-m-d');$from=(string)($_GET['from']??date('Y-01-01'));$to=(string)($_GET['to']??$today);
  foreach([$from,$to] as $date)if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$date))throw new RuntimeException('Rapor tarih aralığı geçersiz.');
  if($from>$to)[$from,$to]=[$to,$from];
  $report=(string)($_GET['report']??'overview');if(!in_array($report,['overview','trial','balance','profit','ledger','reconcile'],true))$report='overview';
  $accountId=max(0,(int)($_GET['account_id']??0));
  $accounts=$db->query('SELECT id,code,name,account_type,is_active FROM accounting_accounts ORDER BY code')->fetchAll();
  $journalQuery=$db->prepare('SELECT j.*,COUNT(l.id) line_count,COALESCE(SUM(l.debit),0) total_debit,COALESCE(SUM(l.credit),0) total_credit FROM accounting_journals j LEFT JOIN accounting_journal_lines l ON l.journal_id=j.id AND l.currency=? GROUP BY j.id ORDER BY j.journal_date DESC,j.id DESC LIMIT 100');$journalQuery->execute([$currency]);$journals=$journalQuery->fetchAll();
  $trial=[];$statement=[];$ledger=[];$opening=0.0;$ledgerTotals=['debit'=>0.0,'credit'=>0.0,'closing'=>0.0];
  $byType=[];
  $pnl=$db->prepare("SELECT a.account_type,COALESCE(SUM(l.debit),0) debit,COALESCE(SUM(l.credit),0) credit FROM accounting_accounts a JOIN accounting_journal_lines l ON l.account_id=a.id AND l.currency=? JOIN accounting_journals j ON j.id=l.journal_id AND j.status='posted' WHERE a.is_active=1 AND a.account_type IN ('income','expense') AND j.journal_date BETWEEN ? AND ? GROUP BY a.account_type");
  $pnl->execute([$currency,$from,$to]);foreach($pnl->fetchAll() as $row)$byType[$row['account_type']]=['debit'=>(float)$row['debit'],'credit'=>(float)$row['credit']];
  $balance=$db->prepare("SELECT a.account_type,COALESCE(SUM(l.debit),0) debit,COALESCE(SUM(l.credit),0) credit FROM accounting_accounts a JOIN accounting_journal_lines l ON l.account_id=a.id AND l.currency=? JOIN accounting_journals j ON j.id=l.journal_id AND j.status='posted' WHERE a.is_active=1 AND a.account_type IN ('asset','liability','equity') AND j.journal_date<=? GROUP BY a.account_type");
  $balance->execute([$currency,$to]);foreach($balance->fetchAll() as $row)$byType[$row['account_type']]=['debit'=>(float)$row['debit'],'credit'=>(float)$row['credit']];
  $income=($byType['income']['credit']??0)-($byType['income']['debit']??0);
  $expense=($byType['expense']['debit']??0)-($byType['expense']['credit']??0);
  $netProfit=$income-$expense;
  $cumulativePnl=$db->prepare("SELECT a.account_type,COALESCE(SUM(l.debit),0) debit,COALESCE(SUM(l.credit),0) credit FROM accounting_accounts a JOIN accounting_journal_lines l ON l.account_id=a.id AND l.currency=? JOIN accounting_journals j ON j.id=l.journal_id AND j.status='posted' WHERE a.is_active=1 AND a.account_type IN ('income','expense') AND j.journal_date<=? GROUP BY a.account_type");
  $cumulativePnl->execute([$currency,$to]);$cumulative=[];foreach($cumulativePnl->fetchAll() as $row)$cumulative[$row['account_type']]=['debit'=>(float)$row['debit'],'credit'=>(float)$row['credit']];
  $balanceNetProfit=(($cumulative['income']['credit']??0)-($cumulative['income']['debit']??0))-(($cumulative['expense']['debit']??0)-($cumulative['expense']['credit']??0));
  $totals=['asset'=>0.0,'liability'=>0.0,'equity'=>0.0,'income'=>$income,'expense'=>$expense];
  foreach(['asset','liability','equity'] as $type){$v=$byType[$type]??['debit'=>0,'credit'=>0];$totals[$type]=$type==='asset'?$v['debit']-$v['credit']:$v['credit']-$v['debit'];}
  $totals['equity_with_profit']=$totals['equity']+$balanceNetProfit;
  $totals['balance_difference']=$totals['asset']-($totals['liability']+$totals['equity_with_profit']);
  $reconciliation=[];
  if($report==='reconcile'){
   $q=$db->prepare("SELECT a.id,a.name,a.account_type,a.opening_balance,COALESCE((SELECT SUM(CASE WHEN t.direction='credit' THEN t.amount ELSE -t.amount END) FROM financial_transactions t WHERE t.account_id=a.id AND t.currency=? AND t.transaction_date<=?),0) movements FROM financial_accounts a WHERE a.currency=? ORDER BY a.name");$q->execute([$currency,$to,$currency]);
   $reconciliation=$q->fetchAll();
   foreach($reconciliation as &$row){
    $accountCode=$row['account_type']==='bank'?'102':'100';
    $row['finance_balance']=round((float)$row['opening_balance']+(float)$row['movements'],2);
    $row['account_code']=$accountCode;
   }unset($row);
   $q=$db->prepare("SELECT a.code,COALESCE(SUM(CASE WHEN j.status='posted' AND j.journal_date<=? THEN l.debit-l.credit ELSE 0 END),0) balance FROM accounting_accounts a LEFT JOIN accounting_journal_lines l ON l.account_id=a.id AND l.currency=? LEFT JOIN accounting_journals j ON j.id=l.journal_id WHERE a.code IN ('100','102') GROUP BY a.code");$q->execute([$to,$currency]);$generalLedger=[];foreach($q->fetchAll() as $r)$generalLedger[$r['code']]=(float)$r['balance'];
   $financeByCode=['100'=>0.0,'102'=>0.0];foreach($reconciliation as $r)$financeByCode[$r['account_code']]+=$r['finance_balance'];
   $reconciliationTotals=[];foreach($financeByCode as $code=>$total)$reconciliationTotals[]=['code'=>$code,'finance'=>round($total,2),'ledger'=>round($generalLedger[$code]??0,2),'difference'=>round($total-($generalLedger[$code]??0),2)];
  }
  if($report==='trial'){
   $q=$db->prepare("SELECT a.id,a.code,a.name,a.account_type,COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.debit ELSE 0 END),0) debit,COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.credit ELSE 0 END),0) credit FROM accounting_accounts a LEFT JOIN accounting_journal_lines l ON l.account_id=a.id AND l.currency=? LEFT JOIN accounting_journals j ON j.id=l.journal_id AND j.status='posted' AND j.journal_date<=? WHERE a.is_active=1 GROUP BY a.id,a.code,a.name,a.account_type ORDER BY a.code");
   $q->execute([$currency,$to]);$trial=$q->fetchAll();
  }
  if($report==='balance'||$report==='profit'){
   $types=$report==='balance'?['asset','liability','equity']:['income','expense'];
   foreach($types as $type){if($report==='balance'){$q=$db->prepare("SELECT a.id,a.code,a.name,COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.debit ELSE 0 END),0) debit,COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.credit ELSE 0 END),0) credit FROM accounting_accounts a LEFT JOIN accounting_journal_lines l ON l.account_id=a.id AND l.currency=? LEFT JOIN accounting_journals j ON j.id=l.journal_id AND j.status='posted' AND j.journal_date<=? WHERE a.is_active=1 AND a.account_type=? GROUP BY a.id,a.code,a.name ORDER BY a.code");$q->execute([$currency,$to,$type]);}else{$q=$db->prepare("SELECT a.id,a.code,a.name,COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.debit ELSE 0 END),0) debit,COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.credit ELSE 0 END),0) credit FROM accounting_accounts a LEFT JOIN accounting_journal_lines l ON l.account_id=a.id AND l.currency=? LEFT JOIN accounting_journals j ON j.id=l.journal_id AND j.status='posted' AND j.journal_date BETWEEN ? AND ? WHERE a.is_active=1 AND a.account_type=? GROUP BY a.id,a.code,a.name ORDER BY a.code");$q->execute([$currency,$from,$to,$type]);}$statement[$type]=$q->fetchAll();}
  }
  if($report==='ledger'){
   if($accountId<1)throw new RuntimeException('Hesap ekstresi için hesap seçin.');
   $q=$db->prepare("SELECT COALESCE(SUM(l.debit-l.credit),0) FROM accounting_journal_lines l JOIN accounting_journals j ON j.id=l.journal_id WHERE l.account_id=? AND l.currency=? AND j.status='posted' AND j.journal_date<?");$q->execute([$accountId,$currency,$from]);$opening=(float)$q->fetchColumn();
   $q=$db->prepare("SELECT j.journal_no,j.journal_date,j.description,l.debit,l.credit,l.line_description FROM accounting_journal_lines l JOIN accounting_journals j ON j.id=l.journal_id WHERE l.account_id=? AND l.currency=? AND j.status='posted' AND j.journal_date BETWEEN ? AND ? ORDER BY j.journal_date,j.id,l.id");$q->execute([$accountId,$currency,$from,$to]);$ledger=$q->fetchAll();$running=$opening;
   foreach($ledger as &$line){$running+=(float)$line['debit']-(float)$line['credit'];$line['balance']=$running;$ledgerTotals['debit']+=(float)$line['debit'];$ledgerTotals['credit']+=(float)$line['credit'];}unset($line);$ledgerTotals['closing']=$running;
  }
  $pageTitle='Genel Muhasebe';ob_start();require KD_ROOT.'/app/Views/accounting/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
