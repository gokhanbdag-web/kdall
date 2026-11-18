<?php
declare(strict_types=1);
namespace KodVadAll\\Modules\\Accounting;
use PDO;
use RuntimeException;

final class AccountingController
{
 public static function handle(PDO $db,array $admin):void
 {
  kd_require_permission('accounting.view');$errors=[];
  $today=date('Y-m-d');$from=(string)($_GET['from']??date('Y-01-01'));$to=(string)($_GET['to']??$today);
  foreach([$from,$to] as $date)if(!preg_match('/^\\d{4}-\\d{2}-\\d{2}$/',$date))throw new RuntimeException('Rapor tarih aralığı geçersiz.');
  if($from>$to)[$from,$to]=[$to,$from];
  $report=(string)($_GET['report']??'overview');if(!in_array($report,['overview','trial','balance','profit','ledger'],true))$report='overview';
  $accountId=max(0,(int)($_GET['account_id']??0));
  $accounts=$db->query('SELECT id,code,name,account_type,is_active FROM accounting_accounts ORDER BY code')->fetchAll();
  $trial=[];$statement=[];$ledger=[];$opening=0.0;$ledgerTotals=['debit'=>0.0,'credit'=>0.0,'closing'=>0.0];
  $summary=$db->prepare("SELECT a.account_type,COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.debit ELSE 0 END),0) debit,COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.credit ELSE 0 END),0) credit FROM accounting_accounts a LEFT JOIN accounting_journal_lines l ON l.account_id=a.id LEFT JOIN accounting_journals j ON j.id=l.journal_id AND j.status='posted' AND j.journal_date BETWEEN ? AND ? WHERE a.is_active=1 GROUP BY a.account_type");
  $summary->execute([$from,$to]);$byType=[];foreach($summary->fetchAll() as $row)$byType[$row['account_type']]=['debit'=>(float)$row['debit'],'credit'=>(float)$row['credit']];
  $income=max(0.0,($byType['income']['credit']??0)-($byType['income']['debit']??0));
  $expense=max(0.0,($byType['expense']['debit']??0)-($byType['expense']['credit']??0));
  $netProfit=$income-$expense;
  $totals=['asset'=>0.0,'liability'=>0.0,'equity'=>0.0,'income'=>$income,'expense'=>$expense];
  foreach(['asset','liability','equity'] as $type){$v=$byType[$type]??['debit'=>0,'credit'=>0];$totals[$type]=$type==='asset'?$v['debit']-$v['credit']:$v['credit']-$v['debit'];}
  $totals['equity_with_profit']=$totals['equity']+$netProfit;
  $totals['balance_difference']=$totals['asset']-($totals['liability']+$totals['equity_with_profit']);
  if($report==='trial'){
   $q=$db->prepare("SELECT a.id,a.code,a.name,a.account_type,COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.debit ELSE 0 END),0) debit,COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.credit ELSE 0 END),0) credit FROM accounting_accounts a LEFT JOIN accounting_journal_lines l ON l.account_id=a.id LEFT JOIN accounting_journals j ON j.id=l.journal_id AND j.status='posted' AND j.journal_date BETWEEN ? AND ? WHERE a.is_active=1 GROUP BY a.id,a.code,a.name,a.account_type ORDER BY a.code");
   $q->execute([$from,$to]);$trial=$q->fetchAll();
  }
  if($report==='balance'||$report==='profit'){
   $types=$report==='balance'?['asset','liability','equity']:['income','expense'];
   foreach($types as $type){$q=$db->prepare("SELECT a.id,a.code,a.name,COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.debit ELSE 0 END),0) debit,COALESCE(SUM(CASE WHEN j.id IS NOT NULL THEN l.credit ELSE 0 END),0) credit FROM accounting_accounts a LEFT JOIN accounting_journal_lines l ON l.account_id=a.id LEFT JOIN accounting_journals j ON j.id=l.journal_id AND j.status='posted' AND j.journal_date BETWEEN ? AND ? WHERE a.is_active=1 AND a.account_type=? GROUP BY a.id,a.code,a.name ORDER BY a.code");$q->execute([$from,$to,$type]);$statement[$type]=$q->fetchAll();}
  }
  if($report==='ledger'){
   if($accountId<1)throw new RuntimeException('Hesap ekstresi için hesap seçin.');
   $q=$db->prepare("SELECT COALESCE(SUM(l.debit-l.credit),0) FROM accounting_journal_lines l JOIN accounting_journals j ON j.id=l.journal_id WHERE l.account_id=? AND j.status='posted' AND j.journal_date<?");$q->execute([$accountId,$from]);$opening=(float)$q->fetchColumn();
   $q=$db->prepare("SELECT j.journal_no,j.journal_date,j.description,l.debit,l.credit,l.line_description FROM accounting_journal_lines l JOIN accounting_journals j ON j.id=l.journal_id WHERE l.account_id=? AND j.status='posted' AND j.journal_date BETWEEN ? AND ? ORDER BY j.journal_date,j.id,l.id");$q->execute([$accountId,$from,$to]);$ledger=$q->fetchAll();$running=$opening;
   foreach($ledger as &$line){$running+=(float)$line['debit']-(float)$line['credit'];$line['balance']=$running;$ledgerTotals['debit']+=(float)$line['debit'];$ledgerTotals['credit']+=(float)$line['credit'];}unset($line);$ledgerTotals['closing']=$running;
  }
  $pageTitle='Genel Muhasebe';ob_start();require KD_ROOT.'/app/Views/accounting/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
