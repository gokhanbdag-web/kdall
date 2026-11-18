<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){fwrite(STDERR,"CLI only.\n");exit(1);}
$input=$argv[1]??'';$output=$argv[2]??'';$keyPath=getenv('KD_LICENSE_PRIVATE_KEY')?:($argv[3]??'');
if($input===''||$output===''||$keyPath===''||!is_file($input)||!is_file($keyPath)){fwrite(STDERR,"Usage: KD_LICENSE_PRIVATE_KEY=/secure/key.pem php issue-license.php payload.json signed-license.json\n");exit(1);}
$payload=json_decode((string)file_get_contents($input),true);if(!is_array($payload)||($payload['product']??'')!=='KodVadAll'||empty($payload['license_id'])||empty($payload['domain'])||empty($payload['machine_fingerprint'])){fwrite(STDERR,"Payload must include product, license_id, domain and machine_fingerprint.\n");exit(1);}
$private=openssl_pkey_get_private((string)file_get_contents($keyPath));if($private===false){fwrite(STDERR,"Private key could not be read.\n");exit(1);}
$json=json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);if(!openssl_sign($json,$signature,$private,OPENSSL_ALGO_SHA256)){fwrite(STDERR,"License signing failed.\n");exit(1);}
$document=['payload'=>$payload,'signature'=>base64_encode($signature)];if(file_put_contents($output,json_encode($document,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES),LOCK_EX)===false){fwrite(STDERR,"Output file could not be written.\n");exit(1);}
echo "Signed license created: ".$output.PHP_EOL;
