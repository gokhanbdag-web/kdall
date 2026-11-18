<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Notifications;
use PDO;
final class NotificationsController
{
 public static function handle(PDO $db,array $admin):void
 {
  kd_require_permission('notifications.view');
  if($_SERVER['REQUEST_METHOD']==='POST'){kd_csrf_check();$action=(string)($_POST['form_action']??'');if($action==='read'){$db->prepare('UPDATE notifications SET read_at=NOW() WHERE id=? AND user_id=? AND read_at IS NULL')->execute([(int)($_POST['notification_id']??0),(int)$admin['id']]);}elseif($action==='read_all'){$db->prepare('UPDATE notifications SET read_at=NOW() WHERE user_id=? AND read_at IS NULL')->execute([(int)$admin['id']]);}header('Location: index.php?page=notifications',true,303);exit;}
  $q=$db->prepare('SELECT * FROM notifications WHERE user_id=? ORDER BY created_at DESC LIMIT 100');$q->execute([(int)$admin['id']]);$notifications=$q->fetchAll();$unread=count(array_filter($notifications,static fn($n)=>$n['read_at']===null));$pageTitle='Bildirim Merkezi';ob_start();require KD_ROOT.'/app/Views/notifications/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
