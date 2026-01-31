<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shop_services', function (Blueprint $table) {
            $table->id(); // shop_services.id

            // Relations
            $table->foreignId('shop_id')
                ->constrained('vendor_shops')
                ->cascadeOnDelete();

            $table->foreignId('service_id')
                ->constrained('services')
                ->restrictOnDelete();

            // Pricing configuration
            $table->string('pricing_model', 32)
                ->default('tiered_min_plus');
            // fixed | per_uom | tiered_min_plus | quote

            $table->string('uom', 16);
            // kg | piece | hour | sqm | bundle | etc.

            $table->decimal('minimum', 10, 2)->nullable();
            // minimum quantity

            $table->decimal('min_price', 12, 2)->nullable();
            // price for the minimum quantity

            $table->decimal('price_per_uom', 12, 2)->nullable();
            // price for each excess unit

            $table->boolean('is_active')->default(true);

            // Optional but recommended
            $table->char('currency', 3)->default('SGD');
            $table->unsignedInteger('sort_order')->default(0);

            $table->timestamps();

            // Prevent duplicate service pricing per shop
            $table->unique(['shop_id', 'service_id'], 'shop_services_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_services');
    }
};
