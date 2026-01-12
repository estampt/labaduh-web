<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('shop_delivery_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_id')
                ->constrained('vendor_shops')
                ->cascadeOnDelete();

            // Only used when fulfillment_mode = IN_HOUSE (vendor delivery)
            $table->decimal('base_fee', 10, 2)->default(0);
            $table->decimal('per_km_fee', 10, 2)->default(0);

            // Optional min/max guardrails
            $table->decimal('min_fee', 10, 2)->nullable();
            $table->decimal('max_fee', 10, 2)->nullable();

            $table->timestamps();

            $table->unique(['shop_id'], 'uniq_shop_delivery_price');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shop_delivery_prices');
    }
};
