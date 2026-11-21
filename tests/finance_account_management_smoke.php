<?php
declare(strict_types=1);
$controller=file_get_contents(dirname(__DIR__).'/app/Modules/Finance/FinanceController.php');
$view=file_get_contents(dirname(__DIR__).'/app/Views/finance/index.php');
foreach(['if($form===\'update_account\')',"FOR UPDATE",'self::accountBalance($db,$account)','self::accountBalance($db,$from)',"Hesap bakiyesi ödeme için yetersiz","Kaynak hesap bakiyesi transfer için yetersiz","finance.account_updated"] as $needle)if(!str_contains($controller,$needle))throw new RuntimeException('Finance account guard missing: '.$needle);
foreach(['name="form_action" value="update_account"','name="status"','name="account_id"'] as $needle)if(!str_contains($view,$needle))throw new RuntimeException('Finance account form missing: '.$needle);
echo "Finance account management checks passed\n";
