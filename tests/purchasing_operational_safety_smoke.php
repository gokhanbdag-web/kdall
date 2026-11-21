<?php
declare(strict_types=1);
$source=file_get_contents(dirname(__DIR__).'/app/Modules/Purchasing/PurchasingController.php');
foreach(["validQuantity(","validNonnegativeQuantity(","validMoney(","seenProducts","Aynı ürün birden fazla satırda olamaz.","Açık satın alma siparişleri bulunan tedarikçi","Teslim alınan miktar geçersiz.","status IN ('draft','ordered','partial')"] as $needle){if(!str_contains($source,$needle))throw new RuntimeException("Missing purchasing safeguard: ".$needle);}
preg_match_all('/private static function (validQuantity|validNonnegativeQuantity|validMoney)\\(/',$source,$matches);
if(count(array_unique($matches[1]))!==3)throw new RuntimeException('Quantity validation helpers missing');
echo "Purchasing operational safety smoke tests passed\n";
