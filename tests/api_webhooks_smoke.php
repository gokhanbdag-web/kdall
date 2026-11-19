<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/bootstrap.php';
if(!class_exists(KodVadAll\Core\WebhookService::class)||!class_exists(KodVadAll\Modules\Integrations\IntegrationsController::class)){fwrite(STDERR,"API integration classes failed to autoload.\n");exit(1);}
$sql=file_get_contents(dirname(__DIR__).'/database/migrations/019_api_webhooks.sql');
foreach(['api_tokens','api_request_logs','webhook_endpoints','webhook_deliveries','api.manage'] as $needle){if($sql===false||strpos($sql,$needle)===false){fwrite(STDERR,"API migration missing {$needle}.\n");exit(1);}}
if(!is_file(dirname(__DIR__).'/api.php')){fwrite(STDERR,"API entrypoint missing.\n");exit(1);}
$service=file_get_contents(dirname(__DIR__).'/app/Core/WebhookService.php');
foreach(["status='processing'","\$claim->rowCount()!==1","INTERVAL 5 MINUTE","\$attempts>=5?'failed':'pending'"] as $needle){if($service===false||strpos($service,$needle)===false){fwrite(STDERR,"Webhook claim/retry guard missing.\\n");exit(1);}}
echo "KodVadAll API and webhook smoke tests passed.\n";
