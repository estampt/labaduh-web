<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_delivery_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained('vendor_shops')->cascadeOnDelete();

            $table->decimal('base_fee', 10, 2)->default(0);
            $table->decimal('fee_per_km', 10, 2)->default(0);

            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->unique(['vendor_id','shop_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_delivery_prices');
    }
};
