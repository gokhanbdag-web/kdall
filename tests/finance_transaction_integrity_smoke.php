<?php
declare(strict_types=1);
$finance=file_get_contents(dirname(__DIR__).'/app/Modules/Finance/FinanceController.php');
$advance=file_get_contents(dirname(__DIR__).'/app/Core/AdvanceAllocationService.php');
if($finance===false||$advance===false)throw new RuntimeException('Finance source not readable');
foreach([
"SELECT id,currency FROM financial_accounts WHERE id=? AND status='active' FOR UPDATE",
"SELECT id,currency FROM financial_accounts WHERE id IN (?,?) AND status='active' ORDER BY id FOR UPDATE",
"SELECT * FROM invoices WHERE id=? FOR UPDATE",
"postFinancialTransaction",
"UPDATE invoices SET status='paid'"
] as $guard)if(!str_contains($finance,$guard))throw new RuntimeException('Finance guard missing: '.$guard);
if(substr_count($finance,'$db->beginTransaction();')!==3)throw new RuntimeException('Unexpected finance transaction boundaries');
foreach(['financial_transactions WHERE id=?','invoices WHERE id=?','FOR UPDATE','financial_advance_allocations','remaining'] as $guard)if(!str_contains($advance,$guard))throw new RuntimeException('Advance allocation guard missing: '.$guard);
echo "Finance transaction and advance guards passed\n";
