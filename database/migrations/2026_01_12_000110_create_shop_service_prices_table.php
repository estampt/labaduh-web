<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shop_service_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_id')
                ->constrained('vendor_shops')
                ->cascadeOnDelete();

            $table->foreignId('service_id')
                ->constrained('services')
                ->cascadeOnDelete();

            // Optional categorization (WHITES/COLORED/DELICATES/etc.)
            $table->string('category_code')->nullable();

            // Pricing model
            $table->enum('pricing_model', ['PER_KG', 'BLOCK_KG', 'FLAT'])->default('PER_KG');

            // For PER_KG: price_per_kg + min_kg
            $table->decimal('price_per_kg', 10, 2)->nullable();
            $table->decimal('min_kg', 10, 2)->nullable();

            // For BLOCK_KG: block_kg + block_price
            $table->decimal('block_kg', 10, 2)->nullable();
            $table->decimal('block_price', 10, 2)->nullable();

            // For FLAT: flat_price
            $table->decimal('flat_price', 10, 2)->nullable();

            $table->boolean('is_active')->default(true);

            $table->timestamps();

            $table->unique(['shop_id', 'service_id', 'category_code'], 'uniq_shop_service_category');
            $table->index(['shop_id', 'service_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_service_prices');
    }
};
