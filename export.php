<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
kd_require_permission('data.view');
$pdo=kd_db();$entity=(string)($_GET['entity']??'');$template=(string)($_GET['template']??'');
$columns=[
 'customers'=>['customer_code','type','name','email','phone','tax_number','notes','status'],
 'products'=>['sku','name','description','purchase_price','sale_price','stock_quantity','unit','min_stock','track_stock','tax_rate','status'],
 'orders'=>['order_number','customer_id','status','payment_status','subtotal','tax_total','discount_total','grand_total','currency','order_source','created_at']
];
$queries=[
 'customers'=>'SELECT customer_code,type,name,email,phone,tax_number,notes,status FROM customers ORDER BY id',
 'products'=>'SELECT sku,name,description,purchase_price,sale_price,stock_quantity,unit,min_stock,track_stock,tax_rate,status FROM products ORDER BY id',
 'orders'=>'SELECT order_number,customer_id,status,payment_status,subtotal,tax_total,discount_total,grand_total,currency,order_source,created_at FROM orders ORDER BY id'
];
if($template!==''){$headers=$columns[$template]??null;if(!$headers){http_response_code(404);exit('Şablon bulunamadı.');}$entity=$template;$rows=null;}
else{if(!isset($queries[$entity])){http_response_code(404);exit('Dışa aktarma türü geçersiz.');}$headers=$columns[$entity];$rows=$pdo->query($queries[$entity]);}
$filename='kodvadall-'.$entity.'-'.date('Ymd-His').'.csv';
try{if($template==='')$pdo->prepare("INSERT INTO data_transfer_logs (user_id,transfer_type,entity,file_name,rows_total,rows_success,rows_failed,created_at) VALUES (?, 'export', ?, ?,0,0,0,NOW())")->execute([(int)($_SESSION['user']['id']??0),$entity,$filename]);}catch(Throwable $e){error_log('CSV export log: '.$e->getMessage());}
header('Content-Type: text/csv; charset=utf-8');header('Content-Disposition: attachment; filename="'.$filename.'"');header('X-Content-Type-Options: nosniff');
$out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,$headers,',','"','');
if($rows){while($row=$rows->fetch(PDO::FETCH_NUM)){foreach($row as &$value){$value=(string)$value;if(preg_match('/^[=+@\\-]/',$value))$value="'".$value;}unset($value);fputcsv($out,$row,',','"','');}}
fclose($out);exit;
