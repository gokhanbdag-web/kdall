<?php
declare(strict_types=1);
namespace KodVadAll\Core;
use PDO;
use RuntimeException;
use Throwable;

final class PaymentGateway
{
 private static function key():string
 {
  $path=KD_STORAGE.'/payment.key';if(!is_file($path)){if(file_put_contents($path,random_bytes(32),LOCK_EX)===false)throw new RuntimeException('Ödeme şifreleme anahtarı oluşturulamadı.');@chmod($path,0600);}
  $key=file_get_contents($path);if($key===false||strlen($key)!==32)throw new RuntimeException('Ödeme şifreleme anahtarı okunamadı.');return $key;
 }
 private static function encrypt(array $config):string
 {
  $iv=random_bytes(12);$tag='';$cipher=openssl_encrypt(json_encode($config,JSON_UNESCAPED_UNICODE|JSON_THROW_ON_ERROR),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)throw new RuntimeException('Ödeme ayarları şifrelenemedi.');return base64_encode($iv.$tag.$cipher);
 }
 private static function decrypt(?string $value):array
 {
  if(!$value)return [];$raw=base64_decode($value,true);if($raw===false||strlen($raw)<29)throw new RuntimeException('Ödeme ayarları bozuk.');$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',self::key(),OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));if($plain===false)throw new RuntimeException('Ödeme ayarları çözülemedi.');$data=json_decode($plain,true);return is_array($data)?$data:[];
 }
 public static function config(PDO $db):array
 {
  $row=$db->query('SELECT enabled,test_mode,config_cipher FROM payment_gateway_settings WHERE id=1')->fetch();if(!$row)return ['enabled'=>false,'test_mode'=>true];return array_merge(['enabled'=>(bool)$row['enabled'],'test_mode'=>(bool)$row['test_mode']],self::decrypt($row['config_cipher']));
 }
 public static function savePaytr(PDO $db,array $input):void
 {
  $old=self::config($db);$cfg=['merchant_id'=>trim((string)($input['merchant_id']??''))?:($old['merchant_id']??''),'merchant_key'=>trim((string)($input['merchant_key']??''))?:($old['merchant_key']??''),'merchant_salt'=>trim((string)($input['merchant_salt']??''))?:($old['merchant_salt']??'')];
  if($cfg['merchant_id']===''||$cfg['merchant_key']===''||$cfg['merchant_salt']==='')throw new RuntimeException('PayTR mağaza numarası, anahtar ve salt bilgileri zorunludur.');
  $enabled=isset($input['enabled'])?1:0;$test=isset($input['test_mode'])?1:0;
  $db->prepare('INSERT INTO payment_gateway_settings (id,provider,enabled,test_mode,config_cipher,updated_at) VALUES (1,\'paytr\',?,?,?,NOW()) ON DUPLICATE KEY UPDATE provider=VALUES(provider),enabled=VALUES(enabled),test_mode=VALUES(test_mode),config_cipher=VALUES(config_cipher),updated_at=NOW()')->execute([$enabled,$test,self::encrypt($cfg)]);
 }
 public static function startPaytr(PDO $db,int $orderId,string $orderNumber,float $amount,string $email,string $name,string $phone,string $address,array $items):string
 {
  $cfg=self::config($db);if(empty($cfg['enabled']))throw new RuntimeException('PayTR çevrimiçi ödeme yöntemi etkin değil.');if(!function_exists('curl_init'))throw new RuntimeException('cURL PHP eklentisi etkin değil.');
  $merchantId=(string)$cfg['merchant_id'];$merchantKey=(string)$cfg['merchant_key'];$merchantSalt=(string)$cfg['merchant_salt'];$oid='KD'.str_pad((string)$orderId,12,'0',STR_PAD_LEFT);$ip=(string)($_SERVER['REMOTE_ADDR']??'127.0.0.1');$paymentAmount=(string)round($amount*100);$basket=[];
  foreach($items as $item)$basket[]=[mb_substr((string)$item[0],0,100),(string)number_format((float)$item[1],2,'.',''),(int)$item[2]];
  $basketEncoded=base64_encode(json_encode($basket,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR));$noInstallment='0';$maxInstallment='0';$currency='TL';$testMode=!empty($cfg['test_mode'])?'1':'0';
  $hashString=$merchantId.$ip.$oid.$email.$paymentAmount.$basketEncoded.$noInstallment.$maxInstallment.$currency.$testMode;
  $token=base64_encode(hash_hmac('sha256',$hashString.$merchantSalt,$merchantKey,true));
  $host=preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/i',(string)($_SERVER['HTTP_HOST']??''))?(string)$_SERVER['HTTP_HOST']:'localhost';
  $base=(!empty($_SERVER['HTTPS'])&&$_SERVER['HTTPS']!=='off'?'https://':'https://').$host;
  $fields=['merchant_id'=>$merchantId,'user_ip'=>$ip,'merchant_oid'=>$oid,'email'=>$email,'payment_amount'=>$paymentAmount,'paytr_token'=>$token,'user_basket'=>$basketEncoded,'debug_on'=>'0','no_installment'=>$noInstallment,'max_installment'=>$maxInstallment,'user_name'=>$name,'user_address'=>$address,'user_phone'=>$phone,'merchant_ok_url'=>$base.'/store.php?action=payment_result&order_id='.$orderId,'merchant_fail_url'=>$base.'/store.php?action=payment_result&order_id='.$orderId,'timeout_limit'=>'30','currency'=>$currency,'test_mode'=>$testMode,'lang'=>'tr','iframe_v2'=>'1'];
  $db->prepare("INSERT INTO payment_transactions (order_id,provider,provider_reference,amount,currency,status,created_at) VALUES (?,'paytr',?,?,'TRY','pending',NOW())")->execute([$orderId,$oid,$amount]);
  $ch=curl_init('https://www.paytr.com/odeme/api/get-token');curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_POST=>true,CURLOPT_POSTFIELDS=>http_build_query($fields),CURLOPT_HTTPHEADER=>['Content-Type: application/x-www-form-urlencoded'],CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>25,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);$raw=curl_exec($ch);$http=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$err=curl_error($ch);curl_close($ch);
  if($raw===false)throw new RuntimeException('PayTR bağlantı hatası: '.$err);$response=json_decode((string)$raw,true);if($http<200||$http>=300||!is_array($response)||($response['status']??'')!=='success'||empty($response['token'])){$db->prepare("UPDATE payment_transactions SET status='failed',provider_response=?,updated_at=NOW() WHERE provider_reference=?")->execute([mb_substr((string)$raw,0,4000),$oid]);throw new RuntimeException('PayTR ödeme formu başlatılamadı: '.mb_substr((string)($response['reason']??$raw),0,300));}
  $db->prepare('UPDATE payment_transactions SET provider_response=?,updated_at=NOW() WHERE provider_reference=?')->execute([json_encode(['status'=>'token_created'],JSON_UNESCAPED_UNICODE),$oid]);return (string)$response['token'];
 }
 public static function handlePaytrCallback(PDO $db,array $post):bool
 {
  $cfg=self::config($db);$oid=(string)($post['merchant_oid']??'');$status=(string)($post['status']??'');$total=(int)($post['total_amount']??0);$hash=(string)($post['hash']??'');if($oid===''||!in_array($status,['success','failed'],true)||$total<1||$hash==='')return false;
  $expected=base64_encode(hash_hmac('sha256',$oid.(string)$cfg['merchant_salt'].$status.(string)$total,(string)$cfg['merchant_key'],true));if(!hash_equals($expected,$hash))return false;
  $db->beginTransaction();try{$q=$db->prepare('SELECT * FROM payment_transactions WHERE provider=? AND provider_reference=? FOR UPDATE');$q->execute(['paytr',$oid]);$tx=$q->fetch();if(!$tx){$db->rollBack();return false;}if(in_array($tx['status'],['paid','failed'],true)){$db->commit();return true;}if($status==='success' && $total<(int)round((float)$tx['amount']*100)){$db->rollBack();return false;}
   $new=$status==='success'?'paid':'failed';$db->prepare('UPDATE payment_transactions SET status=?,provider_response=?,updated_at=NOW() WHERE id=?')->execute([$new,json_encode(['callback_status'=>$status,'total_amount'=>$total],JSON_UNESCAPED_UNICODE),(int)$tx['id']]);$db->prepare('UPDATE orders SET payment_status=?,updated_at=NOW() WHERE id=?')->execute([$new==='paid'?'paid':'failed',(int)$tx['order_id']]);$db->commit();return true;
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();error_log('PayTR callback: '.$e->getMessage());return false;}
 }
}
