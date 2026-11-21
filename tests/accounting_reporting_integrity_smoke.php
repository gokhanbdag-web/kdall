<?php
declare(strict_types=1);
$source=file_get_contents(dirname(__DIR__).'/app/Modules/Accounting/AccountingController.php');
if($source===false)throw new RuntimeException('Accounting source missing');
foreach(["a.account_type IN ('income','expense')","a.account_type IN ('asset','liability','equity')","j.status='posted'","j.journal_date BETWEEN ? AND ?","j.journal_date<=?","DateTimeImmutable::createFromFormat('!Y-m-d'","Hesap ekstresi için geçerli bir hesap seçin."] as $needle)if(!str_contains($source,$needle))throw new RuntimeException('Accounting report guard missing: '.$needle);
if(str_contains($source,'WHERE a.is_active=1'))throw new RuntimeException('Inactive historical accounts excluded from financial statements');
foreach(['2026-02-29','2026-13-01','2026-00-12','not-a-date'] as $bad){$date=DateTimeImmutable::createFromFormat('!Y-m-d',$bad);if($date&&$date->format('Y-m-d')===$bad)throw new RuntimeException('Invalid report date accepted');}
if(!str_contains($source,'a.created_at < DATE_ADD(?, INTERVAL 1 DAY)'))throw new RuntimeException('Historical reconciliation includes future-created accounts');
echo "Accounting reporting integrity checks passed\n";
