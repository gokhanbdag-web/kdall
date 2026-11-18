<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Files;
use PDO;
use RuntimeException;
use Throwable;
final class FilesController
{
 private const TYPES=[
  'application/pdf'=>'pdf',
  'image/jpeg'=>'jpg',
  'image/png'=>'png',
  'image/webp'=>'webp',
  'text/plain'=>'txt',
  'text/csv'=>'csv',
  'application/vnd.openxmlformats-officedocument.wordprocessingml.document'=>'docx',
  'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'=>'xlsx'
 ];
 public static function handle(PDO $db,array $admin):void
 {
  kd_require_permission('files.view');$errors=[];$success='';
  if($_SERVER['REQUEST_METHOD']==='POST'){kd_csrf_check();kd_require_permission('files.manage');$moved=null;try{$action=(string)($_POST['form_action']??'');
   if($action==='upload'||$action==='version'){$file=$_FILES['file']??null;if(!is_array($file)||($file['error']??UPLOAD_ERR_NO_FILE)!==UPLOAD_ERR_OK)throw new RuntimeException('Yüklenecek dosyayı seçin.');if(!is_uploaded_file($file['tmp_name'])||(int)$file['size']<1||(int)$file['size']>15*1024*1024)throw new RuntimeException('Dosya boş, geçersiz veya 15 MB sınırını aşıyor.');$finfo=new \finfo(FILEINFO_MIME_TYPE);$mime=(string)$finfo->file($file['tmp_name']);if(!isset(self::TYPES[$mime]))throw new RuntimeException('Bu dosya türüne izin verilmiyor.');$extension=self::TYPES[$mime];$original=mb_substr(basename((string)$file['name']),0,255);$hash=hash_file('sha256',$file['tmp_name']);$relative='uploads/docs/'.date('Y/m').'/'.bin2hex(random_bytes(20)).'.'.$extension;$absolute=KD_STORAGE.'/'.$relative;$directory=dirname($absolute);if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('Dosya klasörü oluşturulamadı.');if(!move_uploaded_file($file['tmp_name'],$absolute))throw new RuntimeException('Dosya sunucuya taşınamadı.');$moved=$absolute;
    $db->beginTransaction();$docId=(int)($_POST['document_id']??0);if($action==='upload'||$docId<1){$title=trim((string)($_POST['title']??pathinfo($original,PATHINFO_FILENAME)));if($title==='')throw new RuntimeException('Belge adı zorunludur.');$db->prepare('INSERT INTO documents (title,category,description,related_type,related_id,status,created_by,created_at) VALUES (?,?,?,?,?,\'active\',?,NOW())')->execute([$title,trim((string)($_POST['category']??'general'))?:'general',trim((string)($_POST['description']??''))?:null,in_array($_POST['related_type']??'', ['customer','order','product','supplier','employee','contract','invoice'],true)?$_POST['related_type']:null,(int)($_POST['related_id']??0)?:null,(int)$admin['id']]);$docId=(int)$db->lastInsertId();}else{$q=$db->prepare("SELECT id FROM documents WHERE id=? AND status='active'");$q->execute([$docId]);if(!$q->fetchColumn())throw new RuntimeException('Belge kaydı bulunamadı.');}
    $db->prepare('INSERT INTO document_versions (document_id,storage_path,original_name,mime_type,size_bytes,sha256,uploaded_by,created_at) VALUES (?,?,?,?,?,?,?,NOW())')->execute([$docId,$relative,$original,$mime,(int)$file['size'],$hash,(int)$admin['id']]);$versionId=(int)$db->lastInsertId();$db->prepare('UPDATE documents SET current_version_id=?,updated_at=NOW() WHERE id=?')->execute([$versionId,$docId]);$db->commit();$moved=null;kd_audit('file.uploaded','document',$docId);$success='Belge güvenli dosya alanına yüklendi.';
   }
   if($action==='archive'){$id=(int)($_POST['document_id']??0);$db->prepare("UPDATE documents SET status='archived',updated_at=NOW() WHERE id=?")->execute([$id]);kd_audit('file.archived','document',$id);$success='Belge arşivlendi.';}
  }catch(Throwable $e){if($db->inTransaction())$db->rollBack();if($moved&&is_file($moved))@unlink($moved);error_log('KodVadAll files: '.$e->getMessage());$errors[]=$e instanceof RuntimeException?$e->getMessage():'Dosya işlemi tamamlanamadı.';}}
  $q=trim((string)($_GET['q']??''));$where=["d.status='active'"];$params=[];if($q!==''){$where[]='(d.title LIKE ? OR v.original_name LIKE ? OR d.category LIKE ?)';$like='%'.$q.'%';array_push($params,$like,$like,$like);}$stmt=$db->prepare('SELECT d.*,v.id version_id,v.original_name,v.mime_type,v.size_bytes,v.created_at uploaded_at FROM documents d LEFT JOIN document_versions v ON v.id=d.current_version_id WHERE '.implode(' AND ',$where).' ORDER BY d.id DESC LIMIT 150');$stmt->execute($params);$documents=$stmt->fetchAll();$pageTitle='Dosya & Belge Yönetimi';ob_start();require KD_ROOT.'/app/Views/files/index.php';$content=ob_get_clean();require KD_ROOT.'/app/Views/layouts/admin.php';
 }
}
