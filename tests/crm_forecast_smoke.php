<?php
declare(strict_types=1);
require dirname(__DIR__).'/app/Modules/Customers/CrmForecast.php';
use KodVadAll\Modules\Customers\CrmForecast;
foreach (['lead'=>10,'qualified'=>25,'proposal'=>50,'negotiation'=>75,'won'=>100,'lost'=>0] as $stage=>$probability) {
 if (CrmForecast::probability($stage,null)!==$probability) {fwrite(STDERR,"Stage probability mismatch\n");exit(1);}
}
if (CrmForecast::weighted(1000,'proposal',null)!==500.0 || CrmForecast::weighted(1000,'won',20)!==1000.0 || CrmForecast::weighted(1000,'lost',80)!==0.0 || CrmForecast::weighted(1000,'lead',35)!==350.0) {fwrite(STDERR,"Weighted forecast mismatch\n");exit(1);}
$root=dirname(__DIR__);
foreach (['app/Modules/Customers/CrmWorkspace.php','app/Views/customers/crm.php','app/Modules/Customers/CrmDashboard.php'] as $file) {
 $contents=file_get_contents($root.'/'.$file);
 if (!str_contains($contents,'probability') || !str_contains($contents,'expected_close_date')) {fwrite(STDERR,"Forecast fields missing: $file\n");exit(1);}
}
echo "CRM forecast smoke tests passed\n";
