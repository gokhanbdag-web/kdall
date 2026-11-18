<?php
declare(strict_types=1);
require __DIR__.'/app/bootstrap.php';
kd_require_permission('files.view');
$pdo=kd_db();$id=max(0,(int)($_GET['id']??0));$q=$pdo->prepare("SELECT v.storage_path,v.original_name,v.mime_type,v.size_bytes FROM documents d JOIN document_versions v ON v.id=d.current_version_id WHERE d.id=? AND d.status='active'");$q->execute([$id]);$file=$q->fetch();if(!$file){http_response_code(404);exit('Dosya bulunamadı.');}
$base=realpath(KD_STORAGE.'/uploads/docs');$path=realpath(KD_STORAGE.'/'.$file['storage_path']);if(!$base||!$path||!str_starts_with($path,$base.DIRECTORY_SEPARATOR)||!is_file($path)){http_response_code(404);exit('Dosya bulunamadı.');}
$name=str_replace(["\r","\n",'"'],'_',basename($file['original_name']));header('Content-Type: '.$file['mime_type']);header('Content-Length: '.(int)$file['size_bytes']);header('Content-Disposition: attachment; filename="'.$name.'"; filename*=UTF-8\'\''.rawurlencode($name));header('X-Content-Type-Options: nosniff');header('Cache-Control: private, no-store');readfile($path);exit;
