<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/app/bootstrap.php';
$pdo=kd_db();
$count=\KodVadAll\Core\NotificationService::runDaily($pdo);
$delivered=\KodVadAll\Core\WebhookService::deliverPending($pdo);
$backup=\KodVadAll\Core\BackupService::runScheduled($pdo);
$reminders=\KodVadAll\Core\NotificationService::runReminders($pdo);
echo 'Automation completed. Notifications: '.$count.'; webhooks delivered: '.$delivered.'; scheduled backup: '.($backup['file_name']??'not due').'; reminders: '.$reminders.PHP_EOL;
