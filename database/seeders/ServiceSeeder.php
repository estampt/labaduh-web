<?php
namespace Database\Seeders;
use Illuminate\Database\Seeder;
use App\Models\Service;
use App\Models\ServiceOption;
class ServiceSeeder extends Seeder
{
    public function run(): void
    {
        $washFold = Service::create(['name'=>'Wash & Fold','base_unit'=>'kg','is_active'=>true]);
        ServiceOption::create(['service_id'=>$washFold->id,'name'=>'Fabric Conditioner','price'=>30,'price_type'=>'fixed']);
        ServiceOption::create(['service_id'=>$washFold->id,'name'=>'Express (Same Day)','price'=>100,'price_type'=>'fixed']);
        Service::create(['name'=>'Wash & Iron','base_unit'=>'kg','is_active'=>true]);
        Service::create(['name'=>'Dry Clean','base_unit'=>'item','is_active'=>true]);
        Service::create(['name'=>'Blankets / Comforters','base_unit'=>'item','is_active'=>true]);
    }
}
