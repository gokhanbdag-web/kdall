<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Security;
use PDO;
final class SecurityController
{
 public static function handle(PDO $db,array $admin):void
 {
  kd_require_permission('security.view');
  if($_SERVER['REQUEST_METHOD']==='POST'){kd_csrf_check();kd_require_permission('security.manage');$action=(string)($_POST['form_action']??'');if($action==='revoke'){$id=(int)($_POST['session_id']??0);$db->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE id=? AND revoked_at IS NULL')->execute([$id]);\KodVadAll\Core\SecurityService::log($db,(int)$admin['id'],'session.revoked','warning','Bir kullanıcı oturumu yönetici tarafından kapatıldı.',['session_id'=>$id]);}elseif($action==='revoke_others'){$db->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND session_hash<>? AND revoked_at IS NULL')->execute([(int)$admin['id'],hash('sha256',(string)($_SESSION['_kd_session_token']??''))]);}header('Location: index.php?page=security',true,303);exit;}
  $sessions=$db->query("SELECT s.*,u.name,u.email FROM user_sessions s JOIN users u ON u.id=s.user_id WHERE s.revoked_at IS NULL AND s.expires_at>NOW() ORDER BY s.last_activity DESC LIMIT 100")->fetchAll();$attempts=$db->query('SELECT ip_address,COUNT(*) attempts,MAX(attempted_at) last_attempt FROM login_attempts WHERE success=0 AND attempted_at>=DATE_SUB(NOW(),INTERVAL 24 HOUR) GROUP BY ip_address ORDER BY last_attempt DESC LIMIT 50')->fetchAll();$events=$db->query('SELECT e.*,u.name user_name FROM security_events e LEFT JOIN users u ON u.id=e.user_id ORDER BY e.id DESC LIMIT 50')->fetchAll();$pageTitle='Güvenlik Merkezi';ob_start();require KD_ROOT.'/app/Views/security/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
