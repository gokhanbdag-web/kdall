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
  kd_require_permission('accounting.view');$errors=[];$action=(string)($_GET['action']??'list');
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
     $date=(string)($_POST['journal_date']??date('Y-m-d'));$description=trim((string)($_POST['description']??''));$ids=is_array($_POST['account_id']??null)?$_POST['account_id']:[];$debits=is_array($_POST['debit']??null)?$_POST['debit']:[];$credits=is_array($_POST['credit']??null)?$_POST['credit']:[];
     if(!preg_match('/^\d{4}-\d{2}-\d{2}$/',$date)||$description==='')throw new RuntimeException('Fiş tarihi ve açıklaması zorunludur.');
     $lines=[];$totalDebit=0.0;$totalCredit=0.0;
     foreach($ids as $i=>$accountId){$accountId=(int)$accountId;$debit=round((float)($debits[$i]??0),2);$credit=round((float)($credits[$i]??0),2);if($accountId<1||$debit<0||$credit<0||($debit>0&&$credit>0)||($debit==0&&$credit==0))continue;$lines[]=[$accountId,$debit,$credit];$totalDebit+=$debit;$totalCredit+=$credit;}
     if(count($lines)<2||abs($totalDebit-$totalCredit)>0.009||$totalDebit<=0)throw new RuntimeException('En az iki satır girilmeli; borç ve alacak toplamları eşit olmalıdır.');
     $db->beginTransaction();$number='MF-'.date('Ymd').'-'.bin2hex(random_bytes(3));
     $db->prepare("INSERT INTO accounting_journals(journal_no,journal_date,description,created_by,created_at) VALUES(?,?,?,?,NOW())")->execute([$number,$date,$description,(int)$admin['id']]);$journalId=(int)$db->lastInsertId();
     $check=$db->prepare('SELECT id FROM accounting_accounts WHERE id=? AND is_active=1');$insert=$db->prepare('INSERT INTO accounting_journal_lines(journal_id,account_id,debit,credit,created_at) VALUES(?,?,?,?,NOW())');
     foreach($lines as [$accountId,$debit,$credit]){$check->execute([$accountId]);if(!$check->fetchColumn())throw new RuntimeException('Fişte pasif veya bulunmayan hesap seçildi.');$insert->execute([$journalId,$accountId,$debit,$credit]);}
     $db->commit();kd_audit('accounting.journal_posted','accounting_journal',$journalId);self::go();
    }
   }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('KodVadAll accounting: '.$e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'Muhasebe işlemi tamamlanamadı.';}
  }
  $accounts=$db->query('SELECT a.*,p.name parent_name FROM accounting_accounts a LEFT JOIN accounting_accounts p ON p.id=a.parent_id ORDER BY a.code')->fetchAll();
  $active=$db->query('SELECT id,code,name,account_type FROM accounting_accounts WHERE is_active=1 ORDER BY code')->fetchAll();
  $journals=$db->query('SELECT j.*,COUNT(l.id) line_count,SUM(l.debit) total_debit,SUM(l.credit) total_credit FROM accounting_journals j LEFT JOIN accounting_journal_lines l ON l.journal_id=j.id GROUP BY j.id ORDER BY j.journal_date DESC,j.id DESC LIMIT 100')->fetchAll();
  $pageTitle='Genel Muhasebe';ob_start();require KD_ROOT.'/app/Views/accounting/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
 private static function go():never{header('Location: index.php?page=accounting',true,303);exit;}
}
