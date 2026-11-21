<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Modules/Customers/CrmHistory.php';
require dirname(__DIR__).'/app/Modules/Customers/CrmForecast.php';
use KodVadAll\Modules\Customers\CrmHistory;
use KodVadAll\Modules\Customers\CrmForecast;
$pdo=new PDO('mysql:host='.(getenv('KD_TEST_DB_HOST')?:'127.0.0.1').';port='.(getenv('KD_TEST_DB_PORT')?:'3306').';dbname='.(getenv('KD_TEST_DB_NAME')?:'kdall_test').';charset=utf8mb4',getenv('KD_TEST_DB_USER')?:'root',getenv('KD_TEST_DB_PASS')?:'root',[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,PDO::ATTR_EMULATE_PREPARES=>false]);
function check(bool $condition,string $message):void {if (!$condition) throw new RuntimeException($message);}
$pdo->beginTransaction();
try {
 $pdo->exec("INSERT INTO customers(name,created_at) VALUES('CRM Integration A',NOW()),('CRM Integration B',NOW())");
 $ids=$pdo->query("SELECT id FROM customers WHERE name IN ('CRM Integration A','CRM Integration B') ORDER BY id DESC LIMIT 2")->fetchAll(PDO::FETCH_COLUMN);
 check(count($ids)===2,'Customer fixtures missing');
 $a=(int)$ids[1];$b=(int)$ids[0];
 $insert=$pdo->prepare("INSERT INTO crm_opportunities(customer_id,title,amount,stage,probability,expected_close_date) VALUES(?,'Integration opportunity',1000,'proposal',60,'2027-01-15')");
 $insert->execute([$a]);$opp=(int)$pdo->lastInsertId();
 $pdo->prepare("INSERT INTO crm_followups(customer_id,title,due_at,status,opportunity_id) VALUES(?,'Integration followup',DATE_ADD(NOW(),INTERVAL 1 DAY),'open',?)")->execute([$a,$opp]);
 $follow=(int)$pdo->lastInsertId();
 check(CrmForecast::weighted(1000,'proposal',60)===600.0,'Weighted forecast incorrect');
 check(CrmHistory::snapshot($pdo,'crm_opportunities',$b,$opp)===null,'Cross-customer read permitted');
 $before=CrmHistory::snapshot($pdo,'crm_opportunities',$a,$opp);
 check($before!==null,'Opportunity snapshot missing');
 $pdo->prepare("UPDATE crm_opportunities SET stage='negotiation' WHERE id=? AND customer_id=?")->execute([$opp,$a]);
 $after=CrmHistory::snapshot($pdo,'crm_opportunities',$a,$opp);
 CrmHistory::record($pdo,$a,0,'crm.stage','opportunity',$opp,$before,$after);
 $row=$pdo->query("SELECT before_json,after_json FROM crm_activity_history WHERE entity_id={$opp} AND entity_type='opportunity' ORDER BY id DESC LIMIT 1")->fetch();
 check($row!==false && json_decode($row['before_json'],true)['stage']==='proposal' && json_decode($row['after_json'],true)['stage']==='negotiation','History snapshots mismatch');
 $scope=$pdo->prepare('UPDATE crm_followups SET status=? WHERE id=? AND customer_id=?');
 $scope->execute(['done',$follow,$b]);
 check($scope->rowCount()===0,'Cross-customer update permitted');
 $count=$pdo->prepare('SELECT COUNT(*) FROM crm_opportunities WHERE customer_id=?');$count->execute([$a]);
 check((int)$count->fetchColumn()===1,'CRM count mismatch');
 $paged=$pdo->prepare('SELECT id FROM crm_opportunities WHERE customer_id=? ORDER BY id DESC LIMIT 1 OFFSET 0');$paged->execute([$a]);
 check((int)$paged->fetchColumn()===$opp,'CRM pagination mismatch');
 $pdo->prepare('DELETE FROM crm_opportunities WHERE id=? AND customer_id=?')->execute([$opp,$a]);
 $stmt=$pdo->prepare('SELECT opportunity_id FROM crm_followups WHERE id=?');$stmt->execute([$follow]);
 check($stmt->fetchColumn()===null,'Opportunity deletion did not unlink followup');
 echo "CRM database integration tests passed\n";
} finally {$pdo->rollBack();}
