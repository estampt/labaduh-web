<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\DeliveryPricingRule;
class DeliveryPricingSeeder extends Seeder
{
    public function run(): void
    {
        DeliveryPricingRule::create([
            'vendor_id'=>null,'base_fee'=>50,'per_km_rate'=>10,'min_fee'=>50,'max_fee'=>250,'is_active'=>true,
        ]);
    }
}
