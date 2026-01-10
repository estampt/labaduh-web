<?php
namespace App\Services\Pricing;
class DistanceCalculator { public function haversineKm(float $lat1,float $lon1,float $lat2,float $lon2): float { $R=6371.0; $dLat=deg2rad($lat2-$lat1); $dLon=deg2rad($lon2-$lon1); $a=sin($dLat/2)*sin($dLat/2)+cos(deg2rad($lat1))*cos(deg2rad($lat2))*sin($dLon/2)*sin($dLon/2); $c=2*atan2(sqrt($a),sqrt(1-$a)); return $R*$c; } }
