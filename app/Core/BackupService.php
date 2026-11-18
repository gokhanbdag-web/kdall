<?php
declare(strict_types=1);
namespace KodVadAll\Core;
use PDO;
use RuntimeException;
use Throwable;
final class BackupService
{
 public static function create(PDO $db,?int $userId):array
 {
  $directory=KD_STORAGE.'/backups';
  if(!is_dir($directory)&&!mkdir($directory,0700,true)&&!is_dir($directory))throw new RuntimeException('Yedek klasörü oluşturulamadı.');
  $name='kodvadall-'.date('Ymd-His').'-'.bin2hex(random_bytes(4)).'.sql';
  $path=$directory.'/'.$name;
  $handle=fopen($path,'xb');
  if(!$handle)throw new RuntimeException('Yedek dosyası oluşturulamadı.');
  $tables=0;
  try{
   $db->exec('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
   $db->beginTransaction();
   fwrite($handle,"-- KodVadAll database backup\n-- Created: ".date(DATE_ATOM)."\nSET NAMES utf8mb4;\nSET FOREIGN_KEY_CHECKS=0;\n\n");
   $list=$db->query("SHOW FULL TABLES WHERE Table_type='BASE TABLE'")->fetchAll(PDO::FETCH_NUM);
   foreach($list as $row){
    $table=(string)$row[0];
    $tick=chr(96);$quoted=$tick.str_replace($tick,$tick.$tick,$table).$tick;
    $create=$db->query('SHOW CREATE TABLE '.$quoted)->fetch(PDO::FETCH_NUM);
    if(!$create||empty($create[1]))continue;
    fwrite($handle,"DROP TABLE IF EXISTS {$quoted};\n".$create[1].";\n");
    $stmt=$db->query('SELECT * FROM '.$quoted);
    $first=$stmt->fetch(PDO::FETCH_ASSOC);
    if($first===false){$tables++;fwrite($handle,"\n");continue;}
    $columns=array_keys($first);
    $columnSql=implode(',',array_map(static fn($c)=>$tick.str_replace($tick,$tick.$tick,$c).$tick,$columns));
    $writeRow=static function(array $record)use($handle,$db,$quoted,$columnSql):void{
     $values=[];foreach($record as $value)$values[]=$value===null?'NULL':$db->quote((string)$value);
     fwrite($handle,'INSERT INTO '.$quoted.' ('.$columnSql.') VALUES ('.implode(',',$values).");\n");
    };
    $writeRow($first);
    while($record=$stmt->fetch(PDO::FETCH_ASSOC))$writeRow($record);
    fwrite($handle,"\n");$tables++;
   }
   fwrite($handle,"SET FOREIGN_KEY_CHECKS=1;\n");
   $db->commit();fclose($handle);
   $size=(int)filesize($path);$hash=hash_file('sha256',$path);$relative='backups/'.$name;
   $db->prepare("INSERT INTO system_backups (file_name,storage_path,size_bytes,sha256,table_count,status,created_by,created_at) VALUES (?,?,?,?,?,'complete',?,NOW())")->execute([$name,$relative,$size,$hash,$tables,($userId!==null&&$userId>0)?$userId:null]);
   return ['id'=>(int)$db->lastInsertId(),'file_name'=>$name,'size_bytes'=>$size,'sha256'=>$hash,'table_count'=>$tables];
  }catch(Throwable $e){
   if($db->inTransaction())$db->rollBack();
   if(is_resource($handle))fclose($handle);
   if(is_file($path))@unlink($path);
   throw $e;
  }
 }
 public static function runScheduled(PDO $db):?array
 {
  $rows=$db->query("SELECT setting_key,setting_value FROM settings WHERE setting_key IN ('backup_enabled','backup_frequency','backup_retention')")->fetchAll(PDO::FETCH_KEY_PAIR);
  if(($rows['backup_enabled']??'0')!=='1')return null;
  $frequency=in_array($rows['backup_frequency']??'daily',['daily','weekly'],true)?$rows['backup_frequency']:'daily';
  $last=$db->query('SELECT MAX(created_at) FROM system_backups WHERE status=\'complete\'')->fetchColumn();
  if($last){$threshold=$frequency==='daily'?strtotime('today'):time()-7*86400;if(strtotime((string)$last)>=$threshold)return null;}
  $result=self::create($db,null);
  $retention=max(3,min(50,(int)($rows['backup_retention']??10)));
  $backups=$db->query('SELECT id,storage_path FROM system_backups ORDER BY id DESC')->fetchAll();
  foreach(array_slice($backups,$retention) as $old){$base=realpath(KD_STORAGE.'/backups');$path=realpath(KD_STORAGE.'/'.$old['storage_path']);if($base&&$path&&str_starts_with($path,$base.DIRECTORY_SEPARATOR)&&is_file($path))@unlink($path);$db->prepare('DELETE FROM system_backups WHERE id=?')->execute([(int)$old['id']]);}
  return $result;
 }

}
