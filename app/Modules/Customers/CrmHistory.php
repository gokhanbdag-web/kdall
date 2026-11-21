<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Customers;
use PDO;
final class CrmHistory
{
 public static function snapshot(PDO $pdo,string $table,int $customerId,int $entityId):?array
 {
  if (!in_array($table,['crm_contacts','crm_opportunities','crm_followups'],true) || $entityId<1) return null;
  $stmt=$pdo->prepare("SELECT * FROM {$table} WHERE id=? AND customer_id=?");
  $stmt->execute([$entityId,$customerId]);
  return $stmt->fetch(PDO::FETCH_ASSOC)?:null;
 }
 public static function record(PDO $pdo,int $customerId,int $actorId,string $action,string $entityType,?int $entityId,?array $before,?array $after):void
 {
  $stmt=$pdo->prepare('INSERT INTO crm_activity_history(customer_id,actor_user_id,action_key,entity_type,entity_id,before_json,after_json) VALUES(?,?,?,?,?,?,?)');
  $stmt->execute([$customerId,$actorId>0?$actorId:null,$action,$entityType,$entityId,$before===null?null:json_encode($before,JSON_THROW_ON_ERROR),$after===null?null:json_encode($after,JSON_THROW_ON_ERROR)]);
 }
}
