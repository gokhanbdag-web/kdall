<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){fwrite(STDERR,"CLI only.\n");exit(1);}
if(!function_exists('openssl_pkey_new')){fwrite(STDERR,"OpenSSL extension required.\n");exit(1);}
$directory=$argv[1]??'';if($directory===''||!is_dir($directory)){fwrite(STDERR,"Usage: php generate-license-keypair.php /secure/private/directory\n");exit(1);}
$key=openssl_pkey_new(['private_key_type'=>OPENSSL_KEYTYPE_RSA,'private_key_bits'=>3072]);if($key===false){fwrite(STDERR,"Key generation failed.\n");exit(1);}
if(!openssl_pkey_export($key,$private)){fwrite(STDERR,"Private key export failed.\n");exit(1);}
$details=openssl_pkey_get_details($key);$privatePath=rtrim($directory,'/').'/kodvadall-license-private.pem';$publicPath=rtrim($directory,'/').'/kodvadall-license-public.pem';
if(file_put_contents($privatePath,$private,LOCK_EX)===false||file_put_contents($publicPath,$details['key'],LOCK_EX)===false){fwrite(STDERR,"Key files could not be written.\n");exit(1);}
@chmod($privatePath,0600);@chmod($publicPath,0644);echo "Private key: ".$privatePath.PHP_EOL."Public key: ".$publicPath.PHP_EOL."Keep the private key outside the web root and never commit it.".PHP_EOL;
