<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('job_request_items', function (Blueprint $table) {
            if (!Schema::hasColumn('job_request_items', 'category_code')) {
                $table->string('category_code')->nullable()->after('service_id')->index();
            }
            if (!Schema::hasColumn('job_request_items', 'category_label')) {
                $table->string('category_label')->nullable()->after('category_code');
            }
            if (!Schema::hasColumn('job_request_items', 'bag_count')) {
                $table->unsignedInteger('bag_count')->nullable()->after('category_label');
            }
            if (!Schema::hasColumn('job_request_items', 'min_kg_applied')) {
                $table->decimal('min_kg_applied', 10, 2)->nullable()->after('weight_kg');
            }
            if (!Schema::hasColumn('job_request_items', 'price_snapshot')) {
                $table->json('price_snapshot')->nullable()->after('options');
            }
        });
    }

    public function down(): void
    {
        Schema::table('job_request_items', function (Blueprint $table) {
            foreach (['price_snapshot','min_kg_applied','bag_count','category_label','category_code'] as $col) {
                if (Schema::hasColumn('job_request_items', $col)) $table->dropColumn($col);
            }
        });
    }
};
