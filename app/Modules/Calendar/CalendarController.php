<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Calendar;
use PDO;
use RuntimeException;
use Throwable;
use KodVadAll\Core\NotificationService;
final class CalendarController
{
 public static function handle(PDO $db,array $admin):void
 {
  kd_require_permission('calendar.view');$errors=[];$success='';
  if($_SERVER['REQUEST_METHOD']==='POST'){kd_csrf_check();kd_require_permission('calendar.manage');try{$action=(string)($_POST['form_action']??'');if($action==='create'){$title=trim((string)($_POST['title']??''));$start=str_replace('T',' ',(string)($_POST['start_at']??''));$end=str_replace('T',' ',(string)($_POST['end_at']??''));if($title===''||strtotime($start)===false)throw new RuntimeException('Etkinlik başlığı ve başlangıç zamanı gereklidir.');if($end!==''&&strtotime($end)<strtotime($start))throw new RuntimeException('Bitiş zamanı başlangıçtan önce olamaz.');$reminder=(int)($_POST['reminder_minutes']??15);if(!in_array($reminder,[0,5,15,30,60,1440],true))$reminder=15;$db->prepare('INSERT INTO calendar_events (title,description,event_type,status,start_at,end_at,location,customer_id,assigned_user_id,reminder_minutes,created_by,created_at) VALUES (?,?,?,\'scheduled\',?,?,?,?,?,?,?,NOW())')->execute([$title,trim((string)($_POST['description']??''))?:null,in_array($_POST['event_type']??'', ['meeting','appointment','delivery','service','reminder','other'],true)?$_POST['event_type']:'meeting',$start?:null,$end?:null,trim((string)($_POST['location']??''))?:null,(int)($_POST['customer_id']??0)?:null,(int)($_POST['assigned_user_id']??0)?:null,$reminder?:null,(int)$admin['id']]);$id=(int)$db->lastInsertId();kd_audit('calendar.created','calendar_event',$id);if(!empty($_POST['assigned_user_id']))NotificationService::notifyUser($db,(int)$_POST['assigned_user_id'],'Yeni takvim etkinliği',$title,'index.php?page=calendar','info');$success='Takvim etkinliği oluşturuldu.';}
   if($action==='cancel'){$id=(int)($_POST['event_id']??0);$db->prepare("UPDATE calendar_events SET status='cancelled',updated_at=NOW() WHERE id=?")->execute([$id]);kd_audit('calendar.cancelled','calendar_event',$id);$success='Etkinlik iptal edildi.';}
  }catch(Throwable $e){$errors[]=$e instanceof RuntimeException?$e->getMessage():'Takvim işlemi tamamlanamadı.';}}
  $month=(string)($_GET['month']??date('Y-m'));if(!preg_match('/^\\d{4}-\\d{2}$/',$month))$month=date('Y-m');$first=$month.'-01';$start=date('Y-m-d 00:00:00',strtotime($first));$end=date('Y-m-d 00:00:00',strtotime($first.' +1 month'));$q=$db->prepare("SELECT e.*,c.name customer_name,u.name assignee_name FROM calendar_events e LEFT JOIN customers c ON c.id=e.customer_id LEFT JOIN users u ON u.id=e.assigned_user_id WHERE e.start_at>=? AND e.start_at<? AND e.status<>'cancelled' ORDER BY e.start_at");$q->execute([$start,$end]);$events=$q->fetchAll();$customers=$db->query("SELECT id,name FROM customers WHERE status='active' ORDER BY name LIMIT 500")->fetchAll();$users=$db->query("SELECT id,name FROM users WHERE status='active' ORDER BY name")->fetchAll();$pageTitle='Takvim & Randevular';ob_start();require KD_ROOT.'/app/Views/calendar/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
