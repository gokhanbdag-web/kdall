<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
try {
 $pdo=kd_db();
 if(\KodVadAll\Core\PaymentGateway::handlePaytrCallback($pdo,$_POST)){
  header('Content-Type: text/plain; charset=utf-8');echo 'OK';exit;
 }
 http_response_code(400);header('Content-Type: text/plain; charset=utf-8');echo 'INVALID';exit;
} catch (Throwable $e) {
 error_log('Payment callback endpoint: '.$e->getMessage());
 http_response_code(500);header('Content-Type: text/plain; charset=utf-8');echo 'ERROR';exit;
}
