<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
$pdo=kd_db();
$count=\KodVadAll\Core\NotificationService::runDaily($pdo);
echo 'Automation completed. Notifications: '.$count.PHP_EOL;
