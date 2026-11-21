<?php
declare(strict_types=1);
namespace KodVadAll\Modules\Customers;
final class CrmForecast
{
 public const DEFAULT_PROBABILITY=['lead'=>10,'qualified'=>25,'proposal'=>50,'negotiation'=>75,'won'=>100,'lost'=>0];
 public static function probability(string $stage,?int $override):int
 {
  if ($stage==='won') return 100;
  if ($stage==='lost') return 0;
  return $override===null? (self::DEFAULT_PROBABILITY[$stage]??0):max(0,min(100,$override));
 }
 public static function weighted(float $amount,string $stage,?int $override):float
 {
  return round(max(0,$amount)*self::probability($stage,$override)/100,2);
 }
}
