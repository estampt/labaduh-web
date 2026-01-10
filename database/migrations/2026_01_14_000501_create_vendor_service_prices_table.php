<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_service_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('vendor_id')->constrained('vendors')->cascadeOnDelete();
            $table->foreignId('shop_id')->nullable()->constrained('vendor_shops')->cascadeOnDelete();

            $table->unsignedBigInteger('service_id')->index();

            // Optional category override (whites/colored/etc.)
            $table->string('category_code')->nullable()->index();

            // Pricing model: per_kg with minKg, or per_block (e.g., per 6kg block), or flat
            $table->enum('pricing_model', ['per_kg_min','per_block','flat'])->default('per_kg_min')->index();

            $table->decimal('min_kg', 10, 2)->nullable();        // for per_kg_min
            $table->decimal('rate_per_kg', 10, 2)->nullable();   // for per_kg_min
            $table->decimal('block_kg', 10, 2)->nullable();      // for per_block
            $table->decimal('block_price', 10, 2)->nullable();   // for per_block
            $table->decimal('flat_price', 10, 2)->nullable();    // for flat

            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();

            $table->index(['vendor_id','shop_id','service_id','category_code','is_active'], 'vsp_lookup');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_service_prices');
    }
};
