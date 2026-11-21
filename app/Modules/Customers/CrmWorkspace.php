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
   if ($title==='' || mb_strlen($title)>190) {$errors[]='Başlık 1-190 karakter olmalıdır.';}
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
     } else {$errors[]='İşlem geçersiz.';}
     if (!$errors) {kd_audit('crm.'.$kind,'customer',$id);header('Location: index.php?page=customers&action=crm&id='.$id,true,303);exit;}
    }
   } catch (Throwable $e) {error_log('CRM: '.$e->getMessage());$errors[]='İşlem başarısız.';}
  }
  $data=[];
  foreach (['contacts'=>'crm_contacts','opportunities'=>'crm_opportunities','followups'=>'crm_followups'] as $key=>$table) {
   $stmt=$pdo->prepare("SELECT * FROM {$table} WHERE customer_id=? ORDER BY id DESC LIMIT 200");$stmt->execute([$id]);$data[$key]=$stmt->fetchAll();
  }
  $pageTitle='CRM: '.$customer['name'];ob_start();require KD_ROOT.'/app/Views/customers/crm.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
