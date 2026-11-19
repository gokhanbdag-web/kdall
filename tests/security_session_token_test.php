<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
$source=file_get_contents(dirname(__DIR__).'/app/Core/SecurityService.php');
if($source===false){fwrite(STDERR,"Security service source missing.\n");exit(1);}
$start=strpos($source,'public static function ensureSession(');
$end=strpos($source,'public static function revokeCurrentSession(', $start===false?0:$start);
if($start===false||$end===false){fwrite(STDERR,"Session validation missing.\n");exit(1);}
$body=substr($source,$start,$end-$start);
if(strpos($body,"if(\$token===''){\$_SESSION=[];session_destroy();")===false||strpos($body,'self::createSession(')!==false){fwrite(STDERR,"Missing session token must fail closed.\n");exit(1);}
echo "KodVadAll session token guard passed.\n";
