<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('services', function (Blueprint $table) {
            // Default pricing model for the system service catalog.
            // Vendors can later choose to use these defaults or override.
            if (!Schema::hasColumn('services', 'default_pricing_model')) {
                $table->enum('default_pricing_model', ['per_kg_min', 'per_piece'])
                    ->default('per_kg_min')
                    ->after('base_unit');
            }

            // per_kg_min defaults
            if (!Schema::hasColumn('services', 'default_min_kg')) {
                $table->decimal('default_min_kg', 10, 2)->nullable()->after('default_pricing_model');
            }
            if (!Schema::hasColumn('services', 'default_rate_per_kg')) {
                $table->decimal('default_rate_per_kg', 10, 2)->nullable()->after('default_min_kg');
            }

            // per_piece defaults
            if (!Schema::hasColumn('services', 'default_rate_per_piece')) {
                $table->decimal('default_rate_per_piece', 10, 2)->nullable()->after('default_rate_per_kg');
            }

            if (!Schema::hasColumn('services', 'allow_vendor_override_price')) {
                $table->boolean('allow_vendor_override_price')->default(true)->after('default_rate_per_piece');
            }
        });
    }

    public function down(): void
    {
        Schema::table('services', function (Blueprint $table) {
            foreach ([
                'allow_vendor_override_price',
                'default_rate_per_piece',
                'default_rate_per_kg',
                'default_min_kg',
                'default_pricing_model',
            ] as $col) {
                if (Schema::hasColumn('services', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
