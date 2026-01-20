<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_service_option_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('vendor_id')->constrained()->cascadeOnDelete();
            // Nullable means vendor-wide override; if set, overrides for a particular shop.
            $table->foreignId('shop_id')->nullable()->constrained('vendor_shops')->cascadeOnDelete();
            $table->foreignId('service_option_id')->constrained('service_options')->cascadeOnDelete();

            // If null, fall back to service_options.price/price_type
            $table->decimal('price', 10, 2)->nullable();
            $table->enum('price_type', ['fixed', 'per_kg', 'per_item'])->nullable();

            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['vendor_id', 'shop_id', 'service_option_id'], 'vsop_unique');
            $table->index(['vendor_id', 'shop_id', 'is_active'], 'vsop_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_service_option_prices');
    }
};
