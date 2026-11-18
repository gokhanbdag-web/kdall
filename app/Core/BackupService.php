<?php
declare(strict_types=1);
namespace KodVadAll\Core;
use PDO;
use RuntimeException;
use Throwable;
final class BackupService
{
 public static function create(PDO $db,int $userId):array
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
   $db->prepare("INSERT INTO system_backups (file_name,storage_path,size_bytes,sha256,table_count,status,created_by,created_at) VALUES (?,?,?,?,?,'complete',?,NOW())")->execute([$name,$relative,$size,$hash,$tables,$userId]);
   return ['id'=>(int)$db->lastInsertId(),'file_name'=>$name,'size_bytes'=>$size,'sha256'=>$hash,'table_count'=>$tables];
  }catch(Throwable $e){
   if($db->inTransaction())$db->rollBack();
   if(is_resource($handle))fclose($handle);
   if(is_file($path))@unlink($path);
   throw $e;
  }
 }
}
