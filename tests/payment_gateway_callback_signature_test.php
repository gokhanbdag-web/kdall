<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Core/PaymentGateway.php';
use KodVadAll\Core\PaymentGateway;
$method=new ReflectionMethod(PaymentGateway::class,'callbackSignatureMatches');
$method->setAccessible(true);
$oid='KD000000000123';$salt='test-salt';$key='test-secret';$total=14999;
foreach(['success','failed'] as $status){
 $valid=base64_encode(hash_hmac('sha256',$oid.$salt.$status.$total,$key,true));
 $cases=[
  ['valid',[$oid,$salt,$status,$total,$key,$valid],true],
  ['changed amount',[$oid,$salt,$status,$total+1,$key,$valid],false],
  ['changed status',[$oid,$salt,$status==='success'?'failed':'success',$total,$key,$valid],false],
  ['changed order',[$oid.'X',$salt,$status,$total,$key,$valid],false],
  ['wrong key',[$oid,$salt,$status,$total,'wrong-key',$valid],false],
  ['empty hash',[$oid,$salt,$status,$total,$key,''],false],
 ];
 foreach($cases as [$label,$args,$expected]){
  if($method->invokeArgs(null,$args)!==$expected){fwrite(STDERR,"PayTR callback signature test failed: {$status} {$label}.\n");exit(1);}
 }
}
echo "KodVadAll PayTR callback signature tests passed.\n";
