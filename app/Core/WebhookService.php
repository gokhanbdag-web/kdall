<?php
declare(strict_types=1);
namespace KodVadAll\Core;
use PDO;
use RuntimeException;
use Throwable;
final class WebhookService
{
 private static function key():string{$path=KD_STORAGE.'/webhooks.key';if(!is_file($path)){if(file_put_contents($path,random_bytes(32),LOCK_EX)===false)throw new RuntimeException('Webhook şifreleme anahtarı oluşturulamadı.');@chmod($path,0600);}$key=file_get_contents($path);if($key===false||strlen($key)!==32)throw new RuntimeException('Webhook anahtarı okunamadı.');return $key;}
 private static function encrypt(string $value):string{$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($value,'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)throw new RuntimeException('Webhook sırrı şifrelenemedi.');return base64_encode($iv.$tag.$cipher);}
 private static function decrypt(string $value):string{$raw=base64_decode($value,true);if($raw===false||strlen($raw)<29)throw new RuntimeException('Webhook sırrı geçersiz.');$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));if($plain===false)throw new RuntimeException('Webhook sırrı çözülemedi.');return $plain;}
 public static function create(PDO $db,int $userId,string $name,string $url,array $events):string
 {
  if(trim($name)==='')throw new RuntimeException('Webhook adı zorunludur.');
  $parts=parse_url($url);if(!filter_var($url,FILTER_VALIDATE_URL)||!is_array($parts)||($parts['scheme']??'')!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass'])||isset($parts['port'])&&(int)$parts['port']!==443)throw new RuntimeException('Webhook adresi geçerli bir HTTPS URL olmalıdır.');
  $host=(string)$parts['host'];$ips=gethostbynamel($host);if(!$ips)throw new RuntimeException('Webhook sunucusunun DNS kaydı doğrulanamadı.');foreach($ips as $ip)if(!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE))throw new RuntimeException('Özel veya yerel ağ adreslerine webhook gönderilemez.');
  $allowed=['order.created','order.updated','payment.paid','payment.failed','return.created'];$events=array_values(array_intersect($events,$allowed));if(!$events)throw new RuntimeException('En az bir webhook olayı seçin.');$secret=bin2hex(random_bytes(32));$db->prepare('INSERT INTO webhook_endpoints (user_id,name,url,secret_cipher,events_json,status,created_at) VALUES (?,?,?,?,?,\'active\',NOW())')->execute([$userId,mb_substr($name,0,120),$url,self::encrypt($secret),json_encode($events)]);return $secret;
 }
 public static function enqueue(PDO $db,string $event,array $payload):void
 {
  try{$q=$db->query("SELECT id,events_json FROM webhook_endpoints WHERE status='active'");$ins=$db->prepare("INSERT INTO webhook_deliveries (endpoint_id,event_name,payload_json,status,attempts,next_attempt_at,created_at) VALUES (?,?,?,'pending',0,NOW(),NOW())");$json=json_encode(['event'=>$event,'created_at'=>date(DATE_ATOM),'data'=>$payload],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);foreach($q->fetchAll() as $endpoint){$events=json_decode($endpoint['events_json'],true);if(is_array($events)&&in_array($event,$events,true))$ins->execute([(int)$endpoint['id'],$event,$json]);}}catch(Throwable $e){error_log('KodVadAll webhook enqueue: '.$e->getMessage());}
 }
 public static function deliverPending(PDO $db,int $limit=25):int
 {
  if(!function_exists('curl_init'))return 0;$q=$db->prepare("SELECT d.*,e.url,e.secret_cipher FROM webhook_deliveries d JOIN webhook_endpoints e ON e.id=d.endpoint_id WHERE d.status='pending' AND d.next_attempt_at<=NOW() AND e.status='active' ORDER BY d.id LIMIT ?");$q->bindValue(1,max(1,min(100,$limit)),PDO::PARAM_INT);$q->execute();$rows=$q->fetchAll();$sent=0;
  foreach($rows as $row){$parts=parse_url($row['url']);$host=(string)($parts['host']??'');$ip=gethostbyname($host);if($ip===$host||!filter_var($ip,FILTER_VALIDATE_IP,FILTER_FLAG_NO_PRIV_RANGE|FILTER_FLAG_NO_RES_RANGE)){$db->prepare("UPDATE webhook_deliveries SET status='failed',attempts=attempts+1,response_body='Webhook DNS güvenlik kontrolünden geçemedi.',updated_at=NOW() WHERE id=?")->execute([(int)$row['id']]);continue;}
   try{$secret=self::decrypt($row['secret_cipher']);$body=(string)$row['payload_json'];$signature='sha256='.hash_hmac('sha256',$body,$secret);$ch=curl_init($row['url']);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>$body,CURLOPT_HTTPHEADER=>['Content-Type: application/json','X-KodVadAll-Event: '.$row['event_name'],'X-KodVadAll-Signature: '.$signature],CURLOPT_CONNECTTIMEOUT=>8,CURLOPT_TIMEOUT=>15,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2,CURLOPT_FOLLOWLOCATION=>false,CURLOPT_PROTOCOLS=>CURLPROTO_HTTPS,CURLOPT_RESOLVE=>[$host.':443:'.$ip]]);$response=curl_exec($ch);$code=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);$ok=$response!==false&&$code>=200&&$code<300;$attempts=(int)$row['attempts']+1;$next=date('Y-m-d H:i:s',time()+min(3600,60*(2**min(5,$attempts))));$db->prepare('UPDATE webhook_deliveries SET status=?,attempts=?,next_attempt_at=?,response_code=?,response_body=?,delivered_at=?,updated_at=NOW() WHERE id=?')->execute([$ok?'delivered':($attempts>=5?'failed':'pending'),$attempts,$next,$code?:null,mb_substr((string)($response===false?$error:$response),0,1000),$ok?date('Y-m-d H:i:s'):null,(int)$row['id']]);if($ok)$sent++;
   }catch(Throwable $e){error_log('Webhook delivery: '.$e->getMessage());}
  }return $sent;
 }
}
