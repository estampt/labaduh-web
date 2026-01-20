<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendor_services', function (Blueprint $table) {
            // Vendor chooses whether to use system defaults (from services) or override.
            if (!Schema::hasColumn('vendor_services', 'use_default_pricing')) {
                // Existing vendor_services rows already have custom pricing (min_weight_kg/base_price...),
                // so default to FALSE to preserve current behavior.
                $table->boolean('use_default_pricing')->default(false)->after('is_enabled');
            }

            // New unified pricing fields (nullable). Your app logic can use these first,
            // and fall back to the legacy columns (min_weight_kg/base_price/price_per_extra_kg) if needed.
            if (!Schema::hasColumn('vendor_services', 'pricing_model')) {
                $table->enum('pricing_model', ['per_kg_min', 'per_piece'])->nullable()->after('use_default_pricing');
            }

            if (!Schema::hasColumn('vendor_services', 'min_kg')) {
                $table->decimal('min_kg', 10, 2)->nullable()->after('pricing_model');
            }

            if (!Schema::hasColumn('vendor_services', 'rate_per_kg')) {
                $table->decimal('rate_per_kg', 10, 2)->nullable()->after('min_kg');
            }

            if (!Schema::hasColumn('vendor_services', 'rate_per_piece')) {
                $table->decimal('rate_per_piece', 10, 2)->nullable()->after('rate_per_kg');
            }

            // Optional housekeeping fields
            if (!Schema::hasColumn('vendor_services', 'notes')) {
                $table->string('notes')->nullable()->after('rate_per_piece');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendor_services', function (Blueprint $table) {
            foreach (['notes', 'rate_per_piece', 'rate_per_kg', 'min_kg', 'pricing_model', 'use_default_pricing'] as $col) {
                if (Schema::hasColumn('vendor_services', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
