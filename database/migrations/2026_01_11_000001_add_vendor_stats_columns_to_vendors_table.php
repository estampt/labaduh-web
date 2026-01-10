<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (!Schema::hasColumn('vendors', 'customers_serviced_count')) {
                $table->unsignedBigInteger('customers_serviced_count')->default(0)->after('is_active');
            }
            if (!Schema::hasColumn('vendors', 'kilograms_processed_total')) {
                $table->decimal('kilograms_processed_total', 14, 2)->default(0)->after('customers_serviced_count');
            }
            if (!Schema::hasColumn('vendors', 'rating_avg')) {
                $table->decimal('rating_avg', 4, 2)->default(0)->after('kilograms_processed_total');
            }
            if (!Schema::hasColumn('vendors', 'rating_count')) {
                $table->unsignedBigInteger('rating_count')->default(0)->after('rating_avg');
            }
        });
    }

    public function down(): void
    {
        Schema::table('vendors', function (Blueprint $table) {
            if (Schema::hasColumn('vendors', 'rating_count')) $table->dropColumn('rating_count');
            if (Schema::hasColumn('vendors', 'rating_avg')) $table->dropColumn('rating_avg');
            if (Schema::hasColumn('vendors', 'kilograms_processed_total')) $table->dropColumn('kilograms_processed_total');
            if (Schema::hasColumn('vendors', 'customers_serviced_count')) $table->dropColumn('customers_serviced_count');
        });
    }
};
