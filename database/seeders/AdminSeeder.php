<?php
namespace Database\Seeders;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
class AdminSeeder extends Seeder
{
    public function run(): void
    {
        $name = env('ADMIN_NAME', 'Labaduh Admin');
        $email = env('ADMIN_EMAIL', 'admin@labaduh.local');
        $password = env('ADMIN_PASSWORD', 'password123');
        User::updateOrCreate(['email'=>$email], ['name'=>$name,'password'=>Hash::make($password),'role'=>'admin','vendor_id'=>null]);
    }
}
