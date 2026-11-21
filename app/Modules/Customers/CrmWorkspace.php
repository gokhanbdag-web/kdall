<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Customers;
use PDO;
use Throwable;
final class CrmWorkspace {
 public static function handle(PDO $pdo,array $admin,int $id): void {
  kd_require_permission('customers.view');
  $stmt=$pdo->prepare('SELECT id,name FROM customers WHERE id=?');$stmt->execute([$id]);$customer=$stmt->fetch();
  if (!$customer) {http_response_code(404);exit('Müşteri bulunamadı.');}
  $errors=[];
  if (($_SERVER['REQUEST_METHOD']??'')==='POST') {
   kd_csrf_check();kd_require_permission('customers.manage');
   $kind=(string)($_POST['kind']??'');$title=trim((string)($_POST['title']??''));
   if (in_array($kind,['contact','opportunity','followup'],true) && ($title==='' || mb_strlen($title)>190)) {$errors[]='Başlık 1-190 karakter olmalıdır.';}
   try {
    if (!$errors) {
     if ($kind==='contact') {
      $email=trim((string)($_POST['email']??''));
      if ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL)) {$errors[]='E-posta geçersiz.';}
      else {$pdo->prepare('INSERT INTO crm_contacts(customer_id,name,role,email,phone) VALUES(?,?,?,?,?)')->execute([$id,$title,substr(trim((string)($_POST['role']??'')),0,120),$email?:null,substr(trim((string)($_POST['phone']??'')),0,40)]);}
     } elseif ($kind==='opportunity') {
      $stage=(string)($_POST['stage']??'lead');$amount=$_POST['amount']??null;
      if (!in_array($stage,['lead','qualified','proposal','negotiation','won','lost'],true) || !is_numeric($amount) || (float)$amount<0) {$errors[]='Fırsat bilgileri geçersiz.';}
      else {$pdo->prepare('INSERT INTO crm_opportunities(customer_id,title,amount,stage) VALUES(?,?,?,?)')->execute([$id,$title,(float)$amount,$stage]);}
     } elseif ($kind==='followup') {
      $date=(string)($_POST['due_at']??'');$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$date);
      if (!$parsed || $parsed->format('Y-m-d\TH:i')!==$date) {$errors[]='Tarih geçersiz.';}
      else {$pdo->prepare('INSERT INTO crm_followups(customer_id,title,due_at,assigned_user_id) VALUES(?,?,?,?)')->execute([$id,$title,$parsed->format('Y-m-d H:i:s'),(int)$admin['id']]);}
     } elseif ($kind==='stage') {
      $stage=(string)($_POST['stage']??'');
      if (!in_array($stage,['lead','qualified','proposal','negotiation','won','lost'],true)) {$errors[]='Aşama geçersiz.';}
      else {$pdo->prepare('UPDATE crm_opportunities SET stage=?,updated_at=NOW() WHERE id=? AND customer_id=?')->execute([$stage,(int)($_POST['item_id']??0),$id]);}
     } elseif ($kind==='done' || $kind==='reopen') {
      $pdo->prepare('UPDATE crm_followups SET status=? WHERE id=? AND customer_id=?')->execute([$kind==='done'?'done':'open',(int)($_POST['item_id']??0),$id]);
     } elseif ($kind==='link_followup_opportunity') {
      $followupId=(int)($_POST['item_id']??0);
      $opportunityId=(int)($_POST['opportunity_id']??0);
      $followup=$pdo->prepare('SELECT id FROM crm_followups WHERE id=? AND customer_id=?');
      $followup->execute([$followupId,$id]);
      if ($followupId<1 || !$followup->fetchColumn()) {$errors[]='Takip kaydı bu müşteriye ait değil.';}
      elseif ($opportunityId<0) {$errors[]='Satış fırsatı geçersiz.';}
      else {
       if ($opportunityId>0) {
        $opportunity=$pdo->prepare('SELECT id FROM crm_opportunities WHERE id=? AND customer_id=?');
        $opportunity->execute([$opportunityId,$id]);
        if (!$opportunity->fetchColumn()) {$errors[]='Satış fırsatı bu müşteriye ait değil.';}
       }
       if (!$errors) {
        $pdo->prepare('UPDATE crm_followups SET opportunity_id=? WHERE id=? AND customer_id=?')
            ->execute([$opportunityId>0?$opportunityId:null,$followupId,$id]);
       }
      }
     } elseif ($kind==='link_quote') {
      kd_require_permission('quotes.view');
      $quoteId=(int)($_POST['quote_id']??0);
      $check=$pdo->prepare('SELECT id FROM quotes WHERE id=? AND customer_id=?');$check->execute([$quoteId,$id]);
      if (!$check->fetchColumn()) {$errors[]='Teklif bu müşteriye ait değil.';}
      else {$pdo->prepare('UPDATE crm_opportunities SET quote_id=? WHERE id=? AND customer_id=?')->execute([$quoteId,(int)($_POST['item_id']??0),$id]);}
     } elseif ($kind==='link_task') {
      kd_require_permission('tasks.view');
      $taskId=(int)($_POST['task_id']??0);
      $check=$pdo->prepare('SELECT id FROM tasks WHERE id=?');$check->execute([$taskId]);
      if (!$check->fetchColumn()) {$errors[]='Görev bulunamadı.';}
      else {$pdo->prepare('UPDATE crm_followups SET task_id=? WHERE id=? AND customer_id=?')->execute([$taskId,(int)($_POST['item_id']??0),$id]);}
     } elseif ($kind==='edit_contact') {
      $email=trim((string)($_POST['email']??''));
      if ($title==='' || mb_strlen($title)>190 || ($email!=='' && !filter_var($email,FILTER_VALIDATE_EMAIL))) {$errors[]='Kişi bilgileri geçersiz.';}
      else {$pdo->prepare('UPDATE crm_contacts SET name=?,role=?,email=?,phone=? WHERE id=? AND customer_id=?')->execute([$title,substr(trim((string)($_POST['role']??'')),0,120),$email?:null,substr(trim((string)($_POST['phone']??'')),0,40),(int)($_POST['item_id']??0),$id]);}
     } elseif ($kind==='edit_opportunity') {
      $stage=(string)($_POST['stage']??'');$amount=$_POST['amount']??null;
      if ($title==='' || mb_strlen($title)>190 || !in_array($stage,['lead','qualified','proposal','negotiation','won','lost'],true) || !is_numeric($amount) || (float)$amount<0) {$errors[]='Fırsat bilgileri geçersiz.';}
      else {$pdo->prepare('UPDATE crm_opportunities SET title=?,amount=?,stage=?,updated_at=NOW() WHERE id=? AND customer_id=?')->execute([$title,(float)$amount,$stage,(int)($_POST['item_id']??0),$id]);}
     } elseif ($kind==='edit_followup') {
      $date=(string)($_POST['due_at']??'');$parsed=\DateTimeImmutable::createFromFormat('!Y-m-d\TH:i',$date);
      if ($title==='' || mb_strlen($title)>190 || !$parsed || $parsed->format('Y-m-d\TH:i')!==$date) {$errors[]='Takip bilgileri geçersiz.';}
      else {$pdo->prepare('UPDATE crm_followups SET title=?,due_at=? WHERE id=? AND customer_id=?')->execute([$title,$parsed->format('Y-m-d H:i:s'),(int)($_POST['item_id']??0),$id]);}
     } elseif ($kind==='delete_opportunity' || $kind==='delete_followup') {
      $itemId=(int)($_POST['item_id']??0);
      if ($itemId<1 || (string)($_POST['confirm_delete']??'')!=='1') {$errors[]='Silme işlemi onaylanmalıdır.';}
      else {
       $table=$kind==='delete_opportunity'?'crm_opportunities':'crm_followups';
       $check=$pdo->prepare("SELECT id FROM {$table} WHERE id=? AND customer_id=?");
       $check->execute([$itemId,$id]);
       if (!$check->fetchColumn()) {$errors[]='Silinecek kayıt bu müşteriye ait değil.';}
       else {
        $delete=$pdo->prepare("DELETE FROM {$table} WHERE id=? AND customer_id=?");
        $delete->execute([$itemId,$id]);
        kd_audit('crm.'.$kind,$table,$itemId);
       }
      }
     } elseif ($kind==='delete_contact') {
      $pdo->prepare('DELETE FROM crm_contacts WHERE id=? AND customer_id=?')->execute([(int)($_POST['item_id']??0),$id]);
     } else {$errors[]='İşlem geçersiz.';}
     if (!$errors) {kd_audit('crm.'.$kind,'customer',$id);header('Location: index.php?page=customers&action=crm&id='.$id,true,303);exit;}
    }
   } catch (Throwable $e) {error_log('CRM: '.$e->getMessage());$errors[]='İşlem başarısız.';}
  }
  $data=[];$pagination=[];$perPage=25;
  foreach (['contacts'=>'crm_contacts','opportunities'=>'crm_opportunities','followups'=>'crm_followups'] as $key=>$table) {
   $count=$pdo->prepare("SELECT COUNT(*) FROM {$table} WHERE customer_id=?");
   $count->execute([$id]);$total=(int)$count->fetchColumn();
   $pages=max(1,(int)ceil($total/$perPage));
   $page=min($pages,max(1,(int)($_GET[$key.'_page']??1)));
   $pagination[$key]=['page'=>$page,'pages'=>$pages,'total'=>$total];
   $offset=($page-1)*$perPage;
   $stmt=$pdo->prepare("SELECT * FROM {$table} WHERE customer_id=? ORDER BY id DESC LIMIT {$perPage} OFFSET {$offset}");
   $stmt->execute([$id]);$data[$key]=$stmt->fetchAll();
  }
  $quotes=[];$tasks=[];
  if (kd_can('quotes.view')) {$stmt=$pdo->prepare('SELECT id,quote_number FROM quotes WHERE customer_id=? ORDER BY id DESC LIMIT 100');$stmt->execute([$id]);$quotes=$stmt->fetchAll();}
  if (kd_can('tasks.view')) {$tasks=$pdo->query('SELECT id,title FROM tasks ORDER BY id DESC LIMIT 100')->fetchAll();}
  $stmt=$pdo->prepare("SELECT stage,COUNT(*) AS total,COALESCE(SUM(amount),0) AS value FROM crm_opportunities WHERE customer_id=? GROUP BY stage");
  $stmt->execute([$id]);$pipeline=$stmt->fetchAll();
  $stmt=$pdo->prepare("SELECT COUNT(*) FROM crm_followups WHERE customer_id=? AND status='open' AND due_at<NOW()");
  $stmt->execute([$id]);$overdue=(int)$stmt->fetchColumn();
  $pageTitle='CRM: '.$customer['name'];ob_start();require KD_ROOT.'/app/Views/customers/crm.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
