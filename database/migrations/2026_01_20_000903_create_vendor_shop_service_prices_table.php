<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('vendor_shop_service_prices', function (Blueprint $table) {
            $table->id();

            $table->foreignId('shop_id')->constrained('vendor_shops')->cascadeOnDelete();
            $table->foreignId('service_id')->constrained('services')->cascadeOnDelete();

            // Whether this service is offered at this shop
            $table->boolean('is_enabled')->default(true)->index();

            // If true, use vendor_services settings (or service defaults). If false, use the overrides below.
            $table->boolean('use_vendor_default_pricing')->default(true)->index();

            // Effectivity range (inclusive). Nulls mean "always effective".
            $table->date('effective_from')->nullable()->index();
            $table->date('effective_to')->nullable()->index();

            // Shop-level overrides (nullable)
            $table->enum('pricing_model', ['per_kg_min', 'per_piece'])->nullable();
            $table->decimal('min_kg', 10, 2)->nullable();
            $table->decimal('rate_per_kg', 10, 2)->nullable();
            $table->decimal('rate_per_piece', 10, 2)->nullable();

            $table->timestamps();

            // Allow multiple rows over time per shop+service. Unique by start date if provided.
            $table->unique(['shop_id', 'service_id', 'effective_from'], 'vss_prices_unique');

            // Common lookup for "effective price for a given date"
            $table->index(['shop_id', 'service_id', 'is_enabled'], 'vss_prices_lookup');
            $table->index(['shop_id', 'service_id', 'effective_from', 'effective_to'], 'vss_prices_effective');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_shop_service_prices');
    }
};
