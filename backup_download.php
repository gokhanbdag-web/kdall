<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
kd_require_permission('system.view');
$pdo=kd_db();$id=max(0,(int)($_GET['id']??0));
$q=$pdo->prepare("SELECT file_name,storage_path,size_bytes FROM system_backups WHERE id=? AND status='complete'");
$q->execute([$id]);$backup=$q->fetch();
if(!$backup){http_response_code(404);exit('Yedek bulunamadı.');}
$base=realpath(KD_STORAGE.'/backups');$path=realpath(KD_STORAGE.'/'.$backup['storage_path']);
if(!$base||!$path||!str_starts_with($path,$base.DIRECTORY_SEPARATOR)||!is_file($path)){http_response_code(404);exit('Yedek dosyası bulunamadı.');}
header('Content-Type: application/sql; charset=utf-8');
header('Content-Length: '.(int)$backup['size_bytes']);
header('Content-Disposition: attachment; filename="'.basename($backup['file_name']).'"');
header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');
readfile($path);exit;
