<?php
declare(strict_types=1);
namespace KodVadAll\Core;
use PDO;
final class SecurityService
{
 public static function lockedOut(PDO $db,string $email,string $ip):bool
 {
  $hash=hash('sha256',strtolower(trim($email)));
  $q=$db->prepare('SELECT COUNT(*) FROM login_attempts WHERE ip_address=? AND success=0 AND attempted_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)');$q->execute([$ip]);if((int)$q->fetchColumn()>=10)return true;
  $q=$db->prepare('SELECT COUNT(*) FROM login_attempts WHERE email_hash=? AND success=0 AND attempted_at>=DATE_SUB(NOW(),INTERVAL 15 MINUTE)');$q->execute([$hash]);return (int)$q->fetchColumn()>=5;
 }
 public static function recordAttempt(PDO $db,string $email,string $ip,bool $success):void
 {
  $db->prepare('INSERT INTO login_attempts (email_hash,ip_address,success,attempted_at) VALUES (?,?,?,NOW())')->execute([hash('sha256',strtolower(trim($email))),$ip,$success?1:0]);
  if($success)$db->prepare('DELETE FROM login_attempts WHERE email_hash=? AND ip_address=? AND success=0')->execute([hash('sha256',strtolower(trim($email))),$ip]);
 }
 public static function createSession(PDO $db,int $userId):void
 {
  $token=bin2hex(random_bytes(32));$_SESSION['_kd_session_token']=$token;
  $db->prepare('INSERT INTO user_sessions (user_id,session_hash,ip_address,user_agent,last_activity,expires_at,created_at) VALUES (?,?,?,?,NOW(),DATE_ADD(NOW(),INTERVAL 30 DAY),NOW())')->execute([$userId,hash('sha256',$token),$_SERVER['REMOTE_ADDR']??null,substr((string)($_SERVER['HTTP_USER_AGENT']??''),0,255)]);
 }
 public static function ensureSession(PDO $db,int $userId):void
 {
  $token=(string)($_SESSION['_kd_session_token']??'');
  if($token===''){$_SESSION=[];session_destroy();header('Location: index.php',true,303);exit;}
  $hash=hash('sha256',$token);$q=$db->prepare('SELECT id FROM user_sessions WHERE user_id=? AND session_hash=? AND revoked_at IS NULL AND expires_at>NOW()');$q->execute([$userId,$hash]);$id=(int)$q->fetchColumn();
  if(!$id){$_SESSION=[];session_destroy();header('Location: index.php',true,303);exit;}
  $db->prepare('UPDATE user_sessions SET last_activity=NOW() WHERE id=?')->execute([$id]);
 }
 public static function revokeCurrentSession(PDO $db,int $userId):void
 {
  $token=(string)($_SESSION['_kd_session_token']??'');if($token!=='')$db->prepare('UPDATE user_sessions SET revoked_at=NOW() WHERE user_id=? AND session_hash=? AND revoked_at IS NULL')->execute([$userId,hash('sha256',$token)]);
 }
 public static function log(PDO $db,?int $userId,string $event,string $severity,string $message,array $context=[]):void
 {
  try{$db->prepare('INSERT INTO security_events (user_id,event_type,severity,ip_address,message,context_json,created_at) VALUES (?,?,?,?,?,?,NOW())')->execute([$userId,$event,$severity,$_SERVER['REMOTE_ADDR']??null,mb_substr($message,0,500),$context?json_encode($context,JSON_UNESCAPED_UNICODE):null]);}catch(\Throwable $e){error_log('Security event log: '.$e->getMessage());}
 }
}
