<?php
declare(strict_types=1);
$root=dirname(__DIR__);
$service=file_get_contents($root.'/app/Modules/Customers/CrmReminderService.php');
$cron=file_get_contents($root.'/cron/automation.php');
foreach (["crm_followup_overdue","crm_followup_upcoming","INSERT IGNORE INTO automation_logs","assigned_user_id","notifyUser","notifyRoles","f.status='open'","LIMIT 500"] as $required) {
 if (!str_contains($service,$required)) {fwrite(STDERR,"CRM reminder missing: $required\n");exit(1);}
}
if (!str_contains($cron,'CrmReminderService::run($pdo)')) {fwrite(STDERR,"CRM reminder cron missing\n");exit(1);}
echo "CRM reminders smoke tests passed\n";
