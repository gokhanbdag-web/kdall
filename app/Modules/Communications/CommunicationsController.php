<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Communications;
use PDO;
use RuntimeException;
use Throwable;

final class CommunicationsController
{
 public static function handle(PDO $db,array $admin):void
 {
  kd_require_permission('communications.view');$action=(string)($_GET['action']??'list');$errors=[];$success='';
  if($_SERVER['REQUEST_METHOD']==='POST'){
   kd_csrf_check();kd_require_permission('communications.manage');
   try{
    $form=(string)($_POST['form_action']??'');
    if($form==='settings'){
     $host=trim((string)($_POST['smtp_host']??''));$port=(int)($_POST['smtp_port']??587);$encryption=(string)($_POST['smtp_encryption']??'tls');$username=trim((string)($_POST['smtp_username']??''));$password=(string)($_POST['smtp_password']??'');$from=trim((string)($_POST['from_email']??''));$fromName=trim((string)($_POST['from_name']??''));
     if($host===''||$port<1||$port>65535||!in_array($encryption,['none','tls','ssl'],true)||!filter_var($from,FILTER_VALIDATE_EMAIL)||$fromName==='')throw new RuntimeException('SMTP ayarlarını kontrol edin.');
     $old=$db->query('SELECT smtp_password_cipher FROM communication_settings WHERE id=1')->fetchColumn();$cipher=$password!==''?self::encrypt($password):($old?:null);
     $db->prepare('INSERT INTO communication_settings (id,smtp_host,smtp_port,smtp_encryption,smtp_username,smtp_password_cipher,from_email,from_name,updated_at) VALUES (1,?,?,?,?,?,?,?,NOW()) ON DUPLICATE KEY UPDATE smtp_host=VALUES(smtp_host),smtp_port=VALUES(smtp_port),smtp_encryption=VALUES(smtp_encryption),smtp_username=VALUES(smtp_username),smtp_password_cipher=VALUES(smtp_password_cipher),from_email=VALUES(from_email),from_name=VALUES(from_name),updated_at=NOW()')->execute([$host,$port,$encryption,$username?:null,$cipher,$from,$fromName]);kd_audit('communications.smtp_updated','communication_settings');$success='SMTP ayarları kaydedildi.';
    }
    if($form==='template'){
     $key=trim((string)($_POST['template_key']??''));$name=trim((string)($_POST['name']??''));$channel=(string)($_POST['channel']??'email');$subject=trim((string)($_POST['subject']??''));$body=trim((string)($_POST['body']??''));if(!preg_match('/^[a-z0-9_.-]{3,100}$/',$key)||$name===''||$body===''||!in_array($channel,['email','sms','whatsapp'],true))throw new RuntimeException('Mesaj şablonu alanlarını kontrol edin.');
     $db->prepare('INSERT INTO message_templates (template_key,channel,name,subject,body,status,created_at) VALUES (?,?,?,?,?,\'active\',NOW()) ON DUPLICATE KEY UPDATE channel=VALUES(channel),name=VALUES(name),subject=VALUES(subject),body=VALUES(body),status=\'active\',updated_at=NOW()')->execute([$key,$channel,$name,$subject?:null,$body]);kd_audit('communications.template_saved','message_template');$success='Mesaj şablonu kaydedildi.';
    }
    if($form==='send'){
     $channel=(string)($_POST['channel']??'email');$recipient=trim((string)($_POST['recipient']??''));$subject=trim(str_replace(["\r","\n"],' ',(string)($_POST['subject']??'')));$body=(string)($_POST['body']??'');if($channel!=='email')throw new RuntimeException('SMS ve WhatsApp gönderimi için sağlayıcı API bağlantısı henüz tanımlanmadı; şablonları hazırlayabilirsiniz.');if(!filter_var($recipient,FILTER_VALIDATE_EMAIL)||$subject===''||trim($body)==='')throw new RuntimeException('Alıcı e-posta, konu ve mesaj zorunludur.');
     $settings=$db->query('SELECT * FROM communication_settings WHERE id=1')->fetch();if(!$settings)throw new RuntimeException('Önce SMTP ayarlarını kaydedin.');
     try{$response=self::smtpSend($settings,$recipient,$subject,$body);$db->prepare("INSERT INTO message_logs (channel,recipient,subject,body,status,provider_response,sent_at,created_by,created_at) VALUES ('email',?,?,?,'sent',?,NOW(),?,NOW())")->execute([$recipient,$subject,$body,$response,(int)$admin['id']]);$success='E-posta başarıyla gönderildi.';kd_audit('communications.email_sent','message_log');}
     catch(Throwable $e){$db->prepare("INSERT INTO message_logs (channel,recipient,subject,body,status,provider_response,created_by,created_at) VALUES ('email',?,?,?,'failed',?,?,NOW())")->execute([$recipient,$subject,$body,mb_substr($e->getMessage(),0,1000),(int)$admin['id']]);throw $e;}
    }
   }catch(Throwable $e){error_log('KodVadAll communications: '.$e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'İletişim işlemi tamamlanamadı.';}
  }
  $settings=$db->query('SELECT smtp_host,smtp_port,smtp_encryption,smtp_username,from_email,from_name,updated_at FROM communication_settings WHERE id=1')->fetch()?:['smtp_host'=>'','smtp_port'=>587,'smtp_encryption'=>'tls','smtp_username'=>'','from_email'=>'','from_name'=>'','updated_at'=>null];
  $templates=$db->query('SELECT * FROM message_templates ORDER BY channel,name')->fetchAll();$logs=$db->query('SELECT * FROM message_logs ORDER BY id DESC LIMIT 100')->fetchAll();
  $pageTitle='İletişim & Mesajlar';ob_start();require KD_ROOT.'/app/Views/communications/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
 private static function encrypt(string $value):string{$path=KD_STORAGE.'/communications.key';if(!is_file($path)){if(file_put_contents($path,random_bytes(32),LOCK_EX)===false)throw new RuntimeException('İletişim şifreleme anahtarı oluşturulamadı.');@chmod($path,0600);}$key=file_get_contents($path);if($key===false||strlen($key)!==32)throw new RuntimeException('İletişim şifreleme anahtarı okunamadı.');$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($value,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)throw new RuntimeException('SMTP parolası şifrelenemedi.');return base64_encode($iv.$tag.$cipher);}
 private static function smtpSend(array $s,string $to,string $subject,string $body):string
 {
  $host=(string)$s['smtp_host'];$port=(int)$s['smtp_port'];$enc=(string)$s['smtp_encryption'];$target=($enc==='ssl'?'ssl://':'').$host.':'.$port;$errno=0;$errstr='';$context=stream_context_create(['ssl'=>['verify_peer'=>true,'verify_peer_name'=>true,'allow_self_signed'=>false]]);
  $fp=stream_socket_client($target,$errno,$errstr,15,STREAM_CLIENT_CONNECT,$context);if(!$fp)throw new RuntimeException('SMTP bağlantısı kurulamadı: '.$errstr);stream_set_timeout($fp,20);
  try{
   self::expect($fp,[220]);$ehlo=gethostname()?:'localhost';fwrite($fp,'EHLO '.$ehlo."\r\n");self::expect($fp,[250]);
   if($enc==='tls'){fwrite($fp,"STARTTLS\r\n");self::expect($fp,[220]);if(!stream_socket_enable_crypto($fp,true,STREAM_CRYPTO_METHOD_TLS_CLIENT))throw new RuntimeException('SMTP TLS başlatılamadı.');fwrite($fp,'EHLO '.$ehlo."\r\n");self::expect($fp,[250]);}
   $user=(string)($s['smtp_username']??'');if($user!==''){$raw=base64_decode((string)$s['smtp_password_cipher'],true);if($raw===false||strlen($raw)<29)throw new RuntimeException('SMTP parola verisi eksik.');$key=file_get_contents(KD_STORAGE.'/communications.key');$pass=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));if($pass===false)throw new RuntimeException('SMTP parolası çözülemedi.');fwrite($fp,"AUTH LOGIN\r\n");self::expect($fp,[334]);fwrite($fp,base64_encode($user)."\r\n");self::expect($fp,[334]);fwrite($fp,base64_encode($pass)."\r\n");self::expect($fp,[235]);}
   $from=(string)$s['from_email'];fwrite($fp,'MAIL FROM:<'.$from.">\r\n");self::expect($fp,[250]);fwrite($fp,'RCPT TO:<'.$to.">\r\n");self::expect($fp,[250,251]);fwrite($fp,"DATA\r\n");self::expect($fp,[354]);
   $fromName='=?UTF-8?B?'.base64_encode((string)$s['from_name']).'?=';$encodedSubject='=?UTF-8?B?'.base64_encode($subject).'?=';$message="From: {$fromName} <{$from}>\r\nTo: <{$to}>\r\nSubject: {$encodedSubject}\r\nDate: ".date(DATE_RFC2822)."\r\nMIME-Version: 1.0\r\nContent-Type: text/plain; charset=UTF-8\r\nContent-Transfer-Encoding: base64\r\n\r\n".chunk_split(base64_encode($body))."\r\n.\r\n";
   fwrite($fp,$message);$result=self::expect($fp,[250]);fwrite($fp,"QUIT\r\n");return $result;
  }finally{fclose($fp);}
 }
 private static function expect($fp,array $codes):string{$response='';do{$line=fgets($fp,515);if($line===false)throw new RuntimeException('SMTP sunucusundan yanıt alınamadı.');$response.=$line;}while(isset($line[3])&&$line[3]==='-');$code=(int)substr($response,0,3);if(!in_array($code,$codes,true))throw new RuntimeException('SMTP hatası: '.trim($response));return trim($response);}
}
