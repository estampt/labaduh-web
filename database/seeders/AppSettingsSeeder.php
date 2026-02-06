<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Services\AppSettings;

class AppSettingsSeeder extends Seeder
{
    public function run(): void
    {
        $s = app(AppSettings::class);

        // Broadcast defaults
        $s->set('broadcast.min_radius_km', 20, 'float', 'broadcast');
        $s->set('broadcast.top_n', 100, 'int', 'broadcast');
        $s->set('broadcast.ttl_seconds', 90, 'int', 'broadcast');
    }
}
