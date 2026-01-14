<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_shops', function (Blueprint $table) {
            $table->id();

            // 1 vendor -> many shops
            $table->foreignId('vendor_id')
                ->constrained('vendors')
                ->cascadeOnDelete();

            // Shop identity
            $table->string('name');
            $table->string('phone')->nullable();

            // Location (match your master tables)
            $table->foreignId('country_id')->nullable()
                ->constrained('countries')->nullOnDelete();

            // Note: your table is named `state_province` (singular) based on your migrations
            $table->foreignId('state_province_id')->nullable()
                ->constrained('state_province')->nullOnDelete();

            $table->foreignId('city_id')->nullable()
                ->constrained('cities')->nullOnDelete();

            // Geo
            $table->decimal('latitude', 10, 7);
            $table->decimal('longitude', 10, 7);

            // Status
            $table->boolean('is_active')->default(true);

            $table->timestamps();

            // Useful indexes for matching
            $table->index('vendor_id');
            $table->index('city_id');
            $table->index(['latitude', 'longitude']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_shops');
    }
};
