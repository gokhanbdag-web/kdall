<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
$pdo=kd_db();$admin=$_SESSION['user']??null;if(!$admin){http_response_code(401);header('Content-Type: application/json');echo json_encode(['success'=>false,'error'=>'Oturum gerekli.']);exit;}
\KodVadAll\Modules\POS\POSController::api($pdo,$admin);
