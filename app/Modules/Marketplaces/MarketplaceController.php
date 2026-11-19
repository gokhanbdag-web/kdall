<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Marketplaces;
use PDO;
use RuntimeException;
use Throwable;

final class MarketplaceController
{
 private const PROVIDERS=['trendyol'=>'Trendyol','hepsiburada'=>'Hepsiburada','n11'=>'N11','pazarama'=>'Pazarama','amazon'=>'Amazon','ciceksepeti'=>'ÇiçekSepeti','pttavm'=>'PTTAVM'];
 public static function handle(PDO $db,array $admin):void
 {
  kd_require_permission('marketplaces.view');$errors=[];$success='';$selected=max(0,(int)($_GET['connection_id']??0));
  if($_SERVER['REQUEST_METHOD']==='POST'){
   kd_csrf_check();kd_require_permission('marketplaces.manage');
   try{
    $form=(string)($_POST['form_action']??'');
    if($form==='connection'){
     $provider=(string)($_POST['marketplace']??'');$name=trim((string)($_POST['store_name']??''));$seller=trim((string)($_POST['seller_id']??''));$apiKey=trim((string)($_POST['api_key']??''));$secret=trim((string)($_POST['api_secret']??''));
     if(!isset(self::PROVIDERS[$provider])||$name===''||$seller==='')throw new RuntimeException('Pazaryeri, mağaza adı ve satıcı hesabı zorunludur.');
     if($provider==='trendyol'&&($apiKey===''||$secret===''))throw new RuntimeException('Trendyol için API anahtarı ve API gizli anahtarı zorunludur.');
     $environment=in_array($_POST['environment']??'', ['production','stage'],true)?$_POST['environment']:'production';$storefront=strtoupper(trim((string)($_POST['storefront']??'TR')));
     if(!preg_match('/^[A-Z]{2,5}$/',$storefront))throw new RuntimeException('Mağaza bölge kodu geçersiz.');
     $key=self::encryptionKey();$encKey=$apiKey!==''?self::encrypt($apiKey,$key):null;$encSecret=$secret!==''?self::encrypt($secret,$key):null;
     $db->prepare('INSERT INTO marketplace_connections (marketplace,store_name,seller_id,api_key_cipher,api_secret_cipher,environment,storefront,status,created_at) VALUES (?,?,?,?,?,?,?,\'active\',NOW())')->execute([$provider,$name,$seller,$encKey,$encSecret,$environment,$storefront]);
     $id=(int)$db->lastInsertId();kd_audit('marketplace.connection_created','marketplace_connection',$id);self::go($id);
    }
    if($form==='map'){
     $connection=(int)($_POST['connection_id']??0);$product=(int)($_POST['product_id']??0);$barcode=trim((string)($_POST['external_barcode']??''));
     if($connection<1||$product<1||$barcode==='')throw new RuntimeException('Bağlantı, ürün ve pazaryeri barkodu zorunludur.');
     $q=$db->prepare("SELECT id FROM products WHERE id=? AND status='active'");$q->execute([$product]);if(!$q->fetchColumn())throw new RuntimeException('Aktif ürün seçin.');
     $db->prepare('INSERT INTO marketplace_product_map (connection_id,product_id,external_barcode,sync_enabled,created_at) VALUES (?,?,?,1,NOW()) ON DUPLICATE KEY UPDATE external_barcode=VALUES(external_barcode),sync_enabled=1,updated_at=NOW()')->execute([$connection,$product,$barcode]);
     kd_audit('marketplace.product_mapped','marketplace_connection',$connection);self::go($connection);
    }
    if($form==='test'){
     $connection=(int)($_POST['connection_id']??0);$conn=self::connection($db,$connection);if($conn['marketplace']!=='trendyol')throw new RuntimeException('Bu pazaryeri için API test adaptörü henüz etkin değil.');
     $result=self::request($conn,'GET','/integration/sellers/'.rawurlencode($conn['seller_id']).'/addresses',null);
     $db->prepare('UPDATE marketplace_connections SET last_test_at=NOW(),last_error=NULL WHERE id=?')->execute([$connection]);
     kd_audit('marketplace.connection_tested','marketplace_connection',$connection);$success='Trendyol API bağlantısı doğrulandı.'; $selected=$connection;
    }
    if($form==='sync'){
     $connection=(int)($_POST['connection_id']??0);$conn=self::connection($db,$connection);if($conn['marketplace']!=='trendyol')throw new RuntimeException('Bu sürümde canlı stok/fiyat gönderimi Trendyol için etkin. Diğer sağlayıcı adaptörleri sıradaki entegrasyon adımlarında eklenecek.');
     $recent=$db->prepare("SELECT COUNT(*) FROM marketplace_sync_jobs WHERE connection_id=? AND job_type='stock_price' AND status='queued' AND created_at>DATE_SUB(NOW(),INTERVAL 15 MINUTE)");$recent->execute([$connection]);if((int)$recent->fetchColumn()>0)throw new RuntimeException('Trendyol aynı stok/fiyat isteğinin 15 dakika içinde tekrarlanmasını kabul etmiyor. Önceki toplu işlemi bekleyin.');
     $q=$db->prepare('SELECT m.id map_id,m.external_barcode,p.stock_quantity,p.sale_price FROM marketplace_product_map m JOIN products p ON p.id=m.product_id WHERE m.connection_id=? AND m.sync_enabled=1 ORDER BY m.id LIMIT 1000');$q->execute([$connection]);$maps=$q->fetchAll();$items=[];
     foreach($maps as $m){$price=(float)$m['sale_price'];if($price<=0)continue;$items[]=['barcode'=>$m['external_barcode'],'quantity'=>max(0,min(20000,(int)floor((float)$m['stock_quantity']))),'salePrice'=>$price,'listPrice'=>$price];}
     if(!$items)throw new RuntimeException('Gönderilecek eşleştirilmiş ürün bulunamadı. Ürünlerin barkod ve satış fiyatlarını kontrol edin.');
     $body=['items'=>$items];$response=self::request($conn,'POST','/integration/inventory/sellers/'.rawurlencode($conn['seller_id']).'/products/price-and-inventory',$body);
     $batch=(string)($response['batchRequestId']??'');if($batch==='')throw new RuntimeException('Pazaryeri yanıtında batchRequestId bulunamadı.');
     $db->prepare("INSERT INTO marketplace_sync_jobs (connection_id,job_type,status,batch_request_id,item_count,request_summary,response_body,created_by,created_at) VALUES (?,'stock_price','queued',?,?,?,?,?,NOW())")->execute([$connection,$batch,count($items),json_encode(['items'=>count($items)],JSON_UNESCAPED_UNICODE),json_encode($response,JSON_UNESCAPED_UNICODE),(int)$admin['id']]);
     $db->prepare('UPDATE marketplace_product_map SET last_synced_at=NOW() WHERE connection_id=? AND sync_enabled=1')->execute([$connection]);
     kd_audit('marketplace.stock_price_submitted','marketplace_connection',$connection);$success='Stok/fiyat güncellemesi Trendyol kuyruğuna gönderildi. Batch: '.$batch;$selected=$connection;
    }
   }catch(Throwable $e){error_log('KodVadAll marketplace: '.$e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'Pazaryeri işlemi tamamlanamadı.';$selected=max(0,(int)($_POST['connection_id']??$_GET['connection_id']??0));}
  }
  $connections=$db->query('SELECT id,marketplace,store_name,seller_id,environment,storefront,status,last_test_at,last_error FROM marketplace_connections ORDER BY id DESC')->fetchAll();
  $products=$db->query("SELECT id,name,sku,barcode,stock_quantity,sale_price FROM products WHERE status='active' ORDER BY name LIMIT 500")->fetchAll();
  $maps=[];$jobs=[];$activeConnection=null;
  if($selected>0){$q=$db->prepare('SELECT id,marketplace,store_name,seller_id,environment,storefront,status,last_test_at,last_error FROM marketplace_connections WHERE id=?');$q->execute([$selected]);$activeConnection=$q->fetch()?:null;$q=$db->prepare('SELECT m.*,p.name product_name,p.sku,p.stock_quantity,p.sale_price FROM marketplace_product_map m JOIN products p ON p.id=m.product_id WHERE m.connection_id=? ORDER BY m.id DESC');$q->execute([$selected]);$maps=$q->fetchAll();$q=$db->prepare('SELECT * FROM marketplace_sync_jobs WHERE connection_id=? ORDER BY id DESC LIMIT 30');$q->execute([$selected]);$jobs=$q->fetchAll();}
  $pageTitle='Pazaryeri Entegrasyonları';ob_start();require KD_ROOT.'/app/Views/marketplaces/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
 private static function encryptionKey():string
 {
  $path=KD_STORAGE.'/marketplace.key';if(!is_file($path)){$key=random_bytes(32);if(file_put_contents($path,$key,LOCK_EX)===false)throw new RuntimeException('Entegrasyon şifreleme anahtarı oluşturulamadı.');@chmod($path,0600);} $key=file_get_contents($path);if($key===false||strlen($key)!==32)throw new RuntimeException('Entegrasyon şifreleme anahtarı okunamadı.');return $key;
 }
 private static function encrypt(string $value,string $key):string{$iv=random_bytes(12);$tag='';$cipher=openssl_encrypt($value,'aes-256-gcm',$key,OPENSSL_RAW_DATA,$iv,$tag);if($cipher===false)throw new RuntimeException('API bilgisi şifrelenemedi.');return base64_encode($iv.$tag.$cipher);}
 private static function decrypt(?string $value,string $key):string{if(!$value)return ''; $raw=base64_decode($value,true);if($raw===false||strlen($raw)<29)throw new RuntimeException('Şifreli API bilgisi bozuk.');$plain=openssl_decrypt(substr($raw,28),'aes-256-gcm',$key,OPENSSL_RAW_DATA,substr($raw,0,12),substr($raw,12,16));if($plain===false)throw new RuntimeException('API bilgisi çözülemedi.');return $plain;}
 private static function connection(PDO $db,int $id):array{$q=$db->prepare("SELECT * FROM marketplace_connections WHERE id=? AND status='active'");$q->execute([$id]);$conn=$q->fetch();if(!$conn)throw new RuntimeException('Aktif pazaryeri bağlantısı bulunamadı.');$key=self::encryptionKey();$conn['api_key']=self::decrypt($conn['api_key_cipher'],$key);$conn['api_secret']=self::decrypt($conn['api_secret_cipher'],$key);return $conn;}
 private static function request(array $conn,string $method,string $path,?array $body):array
 {
  if(!function_exists('curl_init'))throw new RuntimeException('cURL PHP eklentisi etkin değil.');
  $base=$conn['environment']==='stage'?'https://stageapigw.trendyol.com':'https://apigw.trendyol.com';$url=$base.$path;
  $auth=base64_encode($conn['seller_id'].':'.$conn['api_key'].':'.$conn['api_secret']);
  $headers=['Accept: application/json','Authorization: Basic '.$auth,'User-Agent: '.$conn['seller_id'].' - SelfIntegration','storeFrontCode: '.$conn['storefront'],'Content-Type: application/json'];
  $ch=curl_init($url);curl_setopt_array($ch,[CURLOPT_RETURNTRANSFER=>true,CURLOPT_CUSTOMREQUEST=>$method,CURLOPT_HTTPHEADER=>$headers,CURLOPT_CONNECTTIMEOUT=>10,CURLOPT_TIMEOUT=>35,CURLOPT_SSL_VERIFYPEER=>true,CURLOPT_SSL_VERIFYHOST=>2]);
  if($body!==null)curl_setopt($ch,CURLOPT_POSTFIELDS,json_encode($body,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES));
  $raw=curl_exec($ch);$status=(int)curl_getinfo($ch,CURLINFO_HTTP_CODE);$error=curl_error($ch);curl_close($ch);
  if($raw===false)throw new RuntimeException('Pazaryeri bağlantı hatası: '.$error);
  $decoded=json_decode((string)$raw,true);if($status<200||$status>=300)throw new RuntimeException('Pazaryeri HTTP '.$status.': '.mb_substr((string)$raw,0,500));
  return is_array($decoded)?$decoded:[];
 }
 private static function go(int $id=0):never{$url='index.php?page=marketplaces';if($id)$url.='&connection_id='.$id;header('Location: '.$url,true,303);exit;}
}
