<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
kd_require_permission('license.view');
$pdo=kd_db();$request=\KodVadAll\Core\LicenseService::activationRequest($pdo);
header('Content-Type: application/json; charset=utf-8');header('Content-Disposition: attachment; filename="kodvadall-license-request.json"');header('X-Content-Type-Options: nosniff');
echo json_encode($request,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;
